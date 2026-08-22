<?php

require_once "../../auth/check_auth.php";
require_once __DIR__ . "/../../includes/database.php";
require_once __DIR__ . "/../../includes/event_functions.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

$maintenanceBusinessId =
    trim($_POST['maintenance_id'] ?? '');

$validId =
    preg_match(
        '/^MNT-\d{6}$/',
        $maintenanceBusinessId
    ) === 1;

$issuedToSession =
    isset(
        $_SESSION['pending_maintenance_ids'][$maintenanceBusinessId]
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

$pdo = null;
$createdMaintenanceId = null;

try {
    $pdo = getDbConnection();

    $pdo->beginTransaction();

    /*
     * Lock the linked Asset before creating Maintenance.
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
        'id' => $assetId
    ]);

    $asset = $assetStmt->fetch();

    if (!$asset) {
        $pdo->rollBack();

        header("Location: index.php?error=asset");
        exit;
    }

    /*
     * New Maintenance may only be started from the same states
     * the old UI exposed: Available or Assigned.
     */
    if (
        !in_array(
            $asset['status'],
            ['Available', 'Assigned'],
            true
        )
    ) {
        $pdo->rollBack();

        header("Location: index.php?error=asset_unavailable");
        exit;
    }

    /*
     * Store the current Asset values as the historical snapshot.
     */
    $insert = $pdo->prepare(
        "INSERT INTO maintenance (
            maintenance_id,
            asset_id,
            asset_name_snap,
            category_snap,
            custodian_snap,
            maintenance_type,
            scheduled_date,
            status
         )
         VALUES (
            :maintenance_id,
            :asset_id,
            :asset_name_snap,
            :category_snap,
            :custodian_snap,
            :maintenance_type,
            :scheduled_date,
            :status
         )"
    );

    $insert->execute([
        'maintenance_id' => $maintenanceBusinessId,
        'asset_id' => $asset['id'],
        'asset_name_snap' => $asset['asset_name'],
        'category_snap' => $asset['category'],
        'custodian_snap' => $asset['custodian'],
        'maintenance_type' => $maintenanceType,
        'scheduled_date' => $scheduledDate,
        'status' => $status
    ]);

    /*
     * Maintenance controls the active Under Maintenance state.
     *
     * A newly-created active record sets Under Maintenance.
     * A newly-created Completed record leaves/restores normal
     * custody-derived state.
     */
    $newAssetStatus =
        $status === 'Completed'
            ? (
                !empty($asset['custodian'])
                    ? 'Assigned'
                    : 'Available'
            )
            : 'Under Maintenance';

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

    $maintenanceEventType = match ($status) {
        'In Progress' => 'Started',
        'Completed' => 'Completed',
        default => 'Scheduled',
    };

    $maintenanceEventDate =
        $status === 'Scheduled'
            ? $scheduledDate
            : currentPropertyEventDate();

    recordPropertyEvent($pdo, [
        'module' => 'Maintenance',
        'event_type' => $maintenanceEventType,
        'business_id' => $maintenanceBusinessId,
        'related_business_id' => $asset['asset_id'],
        'record_name_snap' => $asset['asset_name'],
        'category_snap' => $asset['category'],
        'event_date' => $maintenanceEventDate,
        'to_status' => $status,
        'outcome' => $maintenanceType,
        'performed_by' => currentPropertyEventActor(),
    ]);

    recordAssetStatusChangeEvent(
        $pdo,
        $asset,
        $newAssetStatus,
        currentPropertyEventDate(),
        $maintenanceBusinessId,
        'Asset status synchronized from Maintenance.'
    );

    $createdMaintenanceId =
        $maintenanceBusinessId;

    $pdo->commit();

    /*
     * Retire the issued ID only after successful commit.
     */
    unset(
        $_SESSION['pending_maintenance_ids'][$createdMaintenanceId]
    );

    if (
        isset($_SESSION['pending_maintenance_ids']) &&
        empty($_SESSION['pending_maintenance_ids'])
    ) {
        unset($_SESSION['pending_maintenance_ids']);
    }

} catch (Throwable $e) {

    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    header("Location: index.php?error=save_failed");
    exit;
}

header("Location: index.php");
exit;
