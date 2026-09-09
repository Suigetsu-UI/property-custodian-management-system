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

    $maintenanceType =
        trim($_POST['maintenance_type'] ?? '');

    $scheduledDate =
        trim($_POST['scheduled_date'] ?? '');

    $status =
        trim($_POST['status'] ?? '');

    $allowedTypes = [
        'Preventive',
        'Corrective',
        'Inspection'
    ];

    $allowedStatuses = [
        'Scheduled',
        'In Progress',
        'Completed'
    ];

    if (
        !in_array($maintenanceType, $allowedTypes, true) ||
        !in_array($status, $allowedStatuses, true) ||
        $scheduledDate === ''
    ) {
        header("Location: index.php?error=save_failed");
        exit;
    }

    try {
        $pdo->beginTransaction();

        /*
         * Lock the Maintenance record.
         * Its linked Asset is immutable during this edit.
         */
        $maintenanceStmt = $pdo->prepare(
            "SELECT
                id,
                asset_id,
                maintenance_id,
                maintenance_type,
                scheduled_date,
                status
             FROM maintenance
             WHERE id = :id
             FOR UPDATE"
        );

        $maintenanceStmt->execute([
            'id' => $id
        ]);

        $existing = $maintenanceStmt->fetch();

        if (!$existing) {
            $pdo->rollBack();

            header("Location: index.php");
            exit;
        }

        /*
         * Lock and read the current Asset so snapshot fields and final
         * status are calculated from persistent current state.
         */
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
                'MAINTENANCE_ASSET_MISSING'
            );
        }

        if (($asset['status'] ?? '') === 'Sold') {
            $pdo->rollBack();
            header("Location: index.php?error=asset_sold");
            exit;
        }

        $updateMaintenance = $pdo->prepare(
            "UPDATE maintenance
             SET
                asset_name_snap = :asset_name_snap,
                category_snap = :category_snap,
                custodian_snap = :custodian_snap,
                maintenance_type = :maintenance_type,
                scheduled_date = :scheduled_date,
                status = :status,
                updated_at = now()
             WHERE id = :id"
        );

        $updateMaintenance->execute([
            'asset_name_snap' => $asset['asset_name'],
            'category_snap' => $asset['category'],
            'custodian_snap' => $asset['custodian'],
            'maintenance_type' => $maintenanceType,
            'scheduled_date' => $scheduledDate,
            'status' => $status,
            'id' => $id
        ]);

        /*
         * Recalculate from ALL Maintenance records for this Asset.
         * One completed record must not restore the Asset if another
         * active Maintenance record still exists.
         */
        $activeStmt = $pdo->prepare(
            "SELECT 1
             FROM maintenance
             WHERE asset_id = :asset_id
               AND status <> 'Completed'
             LIMIT 1"
        );

        $activeStmt->execute([
            'asset_id' => $existing['asset_id']
        ]);

        $hasActiveMaintenance =
            (bool) $activeStmt->fetch();

        $newAssetStatus =
            $hasActiveMaintenance
                ? 'Under Maintenance'
                : (
                    !empty($asset['custodian'])
                        ? 'Assigned'
                        : 'Available'
                );

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

        $maintenanceChanged =
            $existing['maintenance_type'] !== $maintenanceType ||
            $existing['scheduled_date'] !== $scheduledDate ||
            $existing['status'] !== $status;

        if ($maintenanceChanged) {
            $statusChanged =
                $existing['status'] !== $status;

            $maintenanceEventType =
                $statusChanged
                    ? match ($status) {
                        'In Progress' => 'Started',
                        'Completed' => 'Completed',
                        default => 'Scheduled',
                    }
                    : 'Updated';

            $maintenanceEventDate =
                $statusChanged && $status === 'Scheduled'
                    ? $scheduledDate
                    : currentPropertyEventDate();

            recordPropertyEvent($pdo, [
                'module' => 'Maintenance',
                'event_type' => $maintenanceEventType,
                'business_id' => $existing['maintenance_id'],
                'related_business_id' => $asset['asset_id'],
                'record_name_snap' => $asset['asset_name'],
                'category_snap' => $asset['category'],
                'event_date' => $maintenanceEventDate,
                'from_status' => $existing['status'],
                'to_status' => $status,
                'outcome' => $maintenanceType,
                'performed_by' => currentPropertyEventActor(),
            ]);
        }

        recordAssetStatusChangeEvent(
            $pdo,
            $asset,
            $newAssetStatus,
            currentPropertyEventDate(),
            $existing['maintenance_id'],
            'Asset status synchronized from Maintenance update.'
        );

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
 * GET — load the record before any HTML output.
 */
$stmt = $pdo->prepare(
    "SELECT
        m.*,
        a.asset_id AS asset_business_id,
        a.asset_name AS current_asset_name,
        a.category AS current_category,
        a.custodian AS current_custodian,
        a.status AS current_asset_status
     FROM maintenance m
     JOIN assets a
       ON a.id = m.asset_id
     WHERE m.id = :id"
);

$stmt->execute([
    'id' => $id
]);

$maintenance = $stmt->fetch();

if (!$maintenance) {
    header("Location: index.php");
    exit;
}

include "../../includes/header.php";

?>

<div class="layout">

<?php include "../../includes/sidebar.php"; ?>

<div class="main-content">

<h1>Edit Maintenance</h1>

<hr>

<?php include "maintenance_form.php"; ?>

</div>

</div>

<?php include "../../includes/footer.php"; ?>
