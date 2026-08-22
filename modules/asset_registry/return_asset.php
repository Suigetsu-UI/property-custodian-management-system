<?php

require_once "../../auth/check_auth.php";
require_once __DIR__ . "/../../includes/database.php";
require_once __DIR__ . "/../../includes/event_functions.php";

$id = filter_var(
    $_GET['id'] ?? null,
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

    /*
     * Only Assigned assets have a Return transition.
     */
    if ($asset['status'] !== 'Assigned') {
        $pdo->rollBack();

        header("Location: index.php");
        exit;
    }

    $update = $pdo->prepare(
        "UPDATE assets
         SET
            employee_id = NULL,
            custodian = NULL,
            department = NULL,
            date_assigned = NULL,
            status = 'Available',
            updated_at = now()
         WHERE id = :id"
    );

    $update->execute([
        'id' => $id
    ]);

    recordPropertyEvent($pdo, [
        'module' => 'Asset Registry',
        'event_type' => 'Returned',
        'business_id' => $asset['asset_id'],
        'related_business_id' => $asset['employee_id'] ?? null,
        'record_name_snap' => $asset['asset_name'],
        'category_snap' => $asset['category'],
        'event_date' => currentPropertyEventDate(),
        'from_status' => $asset['status'],
        'to_status' => 'Available',
        'performed_by' => currentPropertyEventActor(),
        'description' => !empty($asset['custodian'])
            ? 'Returned by ' . $asset['custodian'] . '.'
            : 'Asset returned to available custody.',
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
