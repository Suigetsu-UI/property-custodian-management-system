<?php

require_once "../../auth/check_auth.php";
require_once __DIR__ . "/../../includes/database.php";
require_once __DIR__ . "/../../includes/event_functions.php";

$isPost = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';

if ($isPost) {
    requireValidAccessCsrfPost();
}

$id = filter_var(
    $isPost ? ($_POST['id'] ?? null) : ($_GET['id'] ?? null),
    FILTER_VALIDATE_INT,
    [
        'options' => [
            'min_range' => 1
        ]
    ]
);

if ($id === false) {
    header("Location: index.php");
    exit;
}

$pdo = getDbConnection();

if ($isPost) {

    $auditor =
        trim($_POST['auditor'] ?? '');

    $auditDate =
        trim($_POST['audit_date'] ?? '');

    $result =
        trim($_POST['result'] ?? '');

    $remarks =
        trim($_POST['remarks'] ?? '');

    $status =
        trim($_POST['status'] ?? '');

    $allowedResults = [
        'Verified',
        'Missing',
        'Damaged',
        'For Investigation'
    ];

    $allowedStatuses = [
        'Scheduled',
        'Ongoing',
        'Completed'
    ];

    if (
        $auditor === '' ||
        $auditDate === '' ||
        !in_array($result, $allowedResults, true) ||
        !in_array($status, $allowedStatuses, true)
    ) {
        header("Location: index.php?error=save_failed");
        exit;
    }

    try {
        $pdo->beginTransaction();

        /*
         * Lock the Audit row. Linked Asset remains immutable.
         */
        $auditStmt = $pdo->prepare(
            "SELECT
                id,
                asset_id,
                audit_id,
                auditor,
                audit_date,
                result,
                remarks,
                status
             FROM audits
             WHERE id = :id
             FOR UPDATE"
        );

        $auditStmt->execute([
            'id' => $id
        ]);

        $existing = $auditStmt->fetch();

        if (!$existing) {
            $pdo->rollBack();

            header("Location: index.php");
            exit;
        }

        $assetStmt = $pdo->prepare(
            "SELECT
                id,
                asset_id,
                asset_name,
                category,
                custodian,
                status
             FROM assets
             WHERE id = :id
             FOR UPDATE"
        );

        $assetStmt->execute([
            'id' => $existing['asset_id']
        ]);

        $asset = $assetStmt->fetch();

        if (!$asset) {
            throw new RuntimeException(
                'AUDIT_ASSET_MISSING'
            );
        }

        if (($asset['status'] ?? '') === 'Sold') {
            $pdo->rollBack();
            header("Location: index.php?error=asset_sold");
            exit;
        }

        /*
         * Existing session implementation refreshed the stored
         * Asset snapshot when an Audit was edited.
         */
        $updateAudit = $pdo->prepare(
            "UPDATE audits
             SET
                asset_name_snap = :asset_name_snap,
                category_snap = :category_snap,
                custodian_snap = :custodian_snap,
                auditor = :auditor,
                audit_date = :audit_date,
                result = :result,
                remarks = :remarks,
                status = :status,
                updated_at = now()
             WHERE id = :id"
        );

        $updateAudit->execute([
            'asset_name_snap' => $asset['asset_name'],
            'category_snap' => $asset['category'],
            'custodian_snap' => $asset['custodian'],
            'auditor' => $auditor,
            'audit_date' => $auditDate,
            'result' => $result,
            'remarks' => $remarks,
            'status' => $status,
            'id' => $id
        ]);

        $activeMaintenanceStmt = $pdo->prepare(
            "SELECT 1
             FROM maintenance
             WHERE asset_id = :asset_id
               AND status <> 'Completed'
             LIMIT 1"
        );

        $activeMaintenanceStmt->execute([
            'asset_id' => $existing['asset_id']
        ]);

        $hasActiveMaintenance =
            (bool) $activeMaintenanceStmt->fetch();

        $newAssetStatus = null;

        if (
            $hasActiveMaintenance &&
            in_array(
                $result,
                ['Verified', 'For Investigation'],
                true
            )
        ) {
            $newAssetStatus = null;

        } else {
            switch ($result) {

                case 'Missing':
                    $newAssetStatus = 'Lost';
                    break;

                case 'Damaged':
                    $newAssetStatus = 'Under Maintenance';
                    break;

                case 'Verified':
                    $newAssetStatus =
                        !empty($asset['custodian'])
                            ? 'Assigned'
                            : 'Available';
                    break;

                case 'For Investigation':
                default:
                    $newAssetStatus = null;
                    break;
            }
        }

        if ($newAssetStatus !== null) {
            $updateAsset = $pdo->prepare(
                "UPDATE assets
                 SET
                    status = :status,
                    updated_at = now()
                 WHERE id = :id"
            );

            $updateAsset->execute([
                'status' => $newAssetStatus,
                'id' => $existing['asset_id']
            ]);
        }

        $auditChanged =
            $existing['auditor'] !== $auditor ||
            $existing['audit_date'] !== $auditDate ||
            $existing['result'] !== $result ||
            (string) ($existing['remarks'] ?? '') !== $remarks ||
            $existing['status'] !== $status;

        if ($auditChanged) {
$statusChanged =
    $existing['status'] !== $status;

$resultChanged =
    $existing['result'] !== $result;

if ($statusChanged) {

    $auditEventType = match ($status) {
        'Ongoing' => 'Started',
        'Completed' => 'Completed',
        default => 'Scheduled',
    };

} elseif ($resultChanged) {

    $auditEventType =
        'Result Changed';

} else {

    $auditEventType =
        'Updated';
}

            recordPropertyEvent($pdo, [
                'module' => 'Audit',
                'event_type' => $auditEventType,
                'business_id' => $existing['audit_id'],
                'related_business_id' => $asset['asset_id'],
                'record_name_snap' => $asset['asset_name'],
                'category_snap' => $asset['category'],
                'event_date' => $auditDate,
                'from_status' => $existing['status'],
                'to_status' => $status,
                'outcome' => $result,
                'performed_by' => currentPropertyEventActor(),
                'description' => $remarks,
            ]);
        }

        if ($newAssetStatus !== null) {
            recordAssetStatusChangeEvent(
                $pdo,
                $asset,
                $newAssetStatus,
                $auditDate,
                $existing['audit_id'],
                'Asset status synchronized from Audit update: ' . $result . '.'
            );
        }

        $pdo->commit();

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        header("Location: index.php?error=save_failed");
        exit;
    }

    header("Location: index.php");
    exit;
}

/*
 * GET before HTML output.
 */
$stmt = $pdo->prepare(
    "SELECT
        au.*,
        a.asset_id AS asset_business_id,
        a.asset_name AS current_asset_name,
        a.category AS current_category,
        a.custodian AS current_custodian,
        a.status AS current_asset_status
     FROM audits au
     JOIN assets a
       ON a.id = au.asset_id
     WHERE au.id = :id"
);

$stmt->execute([
    'id' => $id
]);

$audit = $stmt->fetch();

if (!$audit) {
    header("Location: index.php");
    exit;
}

include "../../includes/header.php";

?>

<div class="layout">

<?php include "../../includes/sidebar.php"; ?>

<div class="main-content">

<h1>Edit Audit</h1>

<hr>

<?php include "audit_form.php"; ?>

</div>

</div>

<?php include "../../includes/footer.php"; ?>
