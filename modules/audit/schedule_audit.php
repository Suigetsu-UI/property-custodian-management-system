<?php

require_once "../../auth/check_auth.php";
require_once __DIR__ . "/../../includes/database.php";
require_once __DIR__ . "/../../includes/asset_functions.php";

$pdo = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $auditBusinessId =
        trim($_POST['audit_id'] ?? '');

    $validId =
        preg_match(
            '/^AUD-\d{6}$/',
            $auditBusinessId
        ) === 1;

    $issuedToSession =
        isset(
            $_SESSION['pending_audit_ids'][$auditBusinessId]
        );

    if (!$validId || !$issuedToSession) {
        header("Location: index.php?error=invalid_id");
        exit;
    }

    $assetId = filter_var(
        $_POST['asset_id'] ?? null,
        FILTER_VALIDATE_INT,
        [
            'options' => [
                'min_range' => 1
            ]
        ]
    );

    if ($assetId === false) {
        header("Location: index.php?error=asset");
        exit;
    }

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

    $createdAuditId = null;

    try {
        $pdo = getDbConnection();

        $pdo->beginTransaction();

        /*
         * Asset Registry is the current authoritative Asset record.
         */
        $assetStmt = $pdo->prepare(
            "SELECT
                id,
                asset_name,
                category,
                custodian,
                status
             FROM assets
             WHERE id = :id
             FOR UPDATE"
        );

        $assetStmt->execute([
            'id' => $assetId
        ]);

        $asset = $assetStmt->fetch();

        if (!$asset) {
            $pdo->rollBack();

            header("Location: index.php?error=asset");
            exit;
        }

        /*
         * Preserve current Asset values as the historical Audit snapshot.
         */
        $insert = $pdo->prepare(
            "INSERT INTO audits (
                audit_id,
                asset_id,
                asset_name_snap,
                category_snap,
                custodian_snap,
                auditor,
                audit_date,
                result,
                remarks,
                status
             )
             VALUES (
                :audit_id,
                :asset_id,
                :asset_name_snap,
                :category_snap,
                :custodian_snap,
                :auditor,
                :audit_date,
                :result,
                :remarks,
                :status
             )"
        );

        $insert->execute([
            'audit_id' => $auditBusinessId,
            'asset_id' => $asset['id'],
            'asset_name_snap' => $asset['asset_name'],
            'category_snap' => $asset['category'],
            'custodian_snap' => $asset['custodian'],
            'auditor' => $auditor,
            'audit_date' => $auditDate,
            'result' => $result,
            'remarks' => $remarks,
            'status' => $status
        ]);

        /*
         * Existing business behavior:
         *
         * Missing  -> Lost
         * Damaged  -> Under Maintenance
         * Verified -> Assigned/Available unless Maintenance owns status
         * For Investigation -> keep current status
         */
        $activeMaintenanceStmt = $pdo->prepare(
            "SELECT 1
             FROM maintenance
             WHERE asset_id = :asset_id
               AND status <> 'Completed'
             LIMIT 1"
        );

        $activeMaintenanceStmt->execute([
            'asset_id' => $asset['id']
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
            /*
             * Maintenance owns Under Maintenance.
             * Do not override it with a normal Audit result.
             */
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
                'id' => $asset['id']
            ]);
        }

        $createdAuditId =
            $auditBusinessId;

        $pdo->commit();

        unset(
            $_SESSION['pending_audit_ids'][$createdAuditId]
        );

        if (
            isset($_SESSION['pending_audit_ids']) &&
            empty($_SESSION['pending_audit_ids'])
        ) {
            unset($_SESSION['pending_audit_ids']);
        }

    } catch (Throwable $e) {

        if (
            $pdo instanceof PDO &&
            $pdo->inTransaction()
        ) {
            $pdo->rollBack();
        }

        header("Location: index.php?error=save_failed");
        exit;
    }

    header("Location: index.php");
    exit;
}

/*
 * Standalone GET Add route.
 *
 * The modal obtains its ID from next_audit_id.php instead.
 */
try {
    $pdo = getDbConnection();

    $auditIdForForm =
        nextBusinessId(
            $pdo,
            'audit',
            'AUD'
        );

    if (!isset($_SESSION['pending_audit_ids'])) {
        $_SESSION['pending_audit_ids'] = [];
    }

    $_SESSION['pending_audit_ids'][$auditIdForForm] = true;

} catch (Throwable $e) {
    header("Location: index.php?error=save_failed");
    exit;
}

include "../../includes/header.php";

?>

<div class="layout">

<?php include "../../includes/sidebar.php"; ?>

<div class="main-content">

<h1>Schedule Audit</h1>

<hr>

<?php include "audit_form.php"; ?>

</div>

</div>

<?php include "../../includes/footer.php"; ?>