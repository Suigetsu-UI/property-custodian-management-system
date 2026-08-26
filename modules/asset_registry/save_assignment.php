<?php

require_once "../../auth/check_auth.php";
require_once __DIR__ . "/../../includes/database.php";
require_once __DIR__ . "/../../includes/event_functions.php";

requireValidAccessCsrfPost();

$id = filter_var(
    $_POST['id'] ?? null,
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

$employeeId = trim($_POST['employee_id'] ?? '');
$custodian = trim($_POST['custodian'] ?? '');
$department = trim($_POST['department'] ?? '');
$dateAssigned = trim($_POST['date_assigned'] ?? '');

if (
    $employeeId === '' ||
    $custodian === '' ||
    $department === '' ||
    $dateAssigned === ''
) {
    header("Location: index.php?error=save_failed");
    exit;
}

$pdo = null;

try {
    $pdo = getDbConnection();

    $pdo->beginTransaction();

    $assetStmt = $pdo->prepare(
        "SELECT *
         FROM assets
         WHERE id = :id
         FOR UPDATE"
    );

    $assetStmt->execute([
        'id' => $id
    ]);

    $asset = $assetStmt->fetch();

    if (!$asset) {
        $pdo->rollBack();

        header("Location: index.php");
        exit;
    }

    $maintenanceStmt = $pdo->prepare(
        "SELECT 1
         FROM maintenance
         WHERE asset_id = :asset_id
           AND status <> 'Completed'
         LIMIT 1"
    );

    $maintenanceStmt->execute([
        'asset_id' => $id
    ]);

    if (
        $asset['status'] === 'Under Maintenance' ||
        $maintenanceStmt->fetch()
    ) {
        $pdo->rollBack();

        header("Location: index.php?error=maintenance");
        exit;
    }

    if ($asset['status'] === 'Lost') {
        $pdo->rollBack();

        header("Location: index.php?error=lost");
        exit;
    }

    if ($asset['status'] !== 'Available') {
        $pdo->rollBack();

        header("Location: index.php");
        exit;
    }

    $update = $pdo->prepare(
        "UPDATE assets
         SET
            employee_id = :employee_id,
            custodian = :custodian,
            department = :department,
            date_assigned = :date_assigned,
            status = 'Assigned',
            updated_at = now()
         WHERE id = :id"
    );

    $update->execute([
        'employee_id' => $employeeId,
        'custodian' => $custodian,
        'department' => $department,
        'date_assigned' => $dateAssigned,
        'id' => $id
    ]);

    recordPropertyEvent($pdo, [
        'module' => 'Asset Registry',
        'event_type' => 'Assigned',
        'business_id' => $asset['asset_id'],
        'related_business_id' => $employeeId,
        'record_name_snap' => $asset['asset_name'],
        'category_snap' => $asset['category'],
        'event_date' => $dateAssigned,
        'from_status' => $asset['status'],
        'to_status' => 'Assigned',
        'performed_by' => currentPropertyEventActor(),
        'description' => 'Assigned to ' . $custodian . ' (' . $department . ').',
    ]);

    $pdo->commit();

} catch (Throwable $e) {

    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    header("Location: index.php?error=save_failed");
    exit;
}

header("Location: index.php");
exit;
