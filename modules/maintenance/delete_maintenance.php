<?php

require_once "../../auth/check_auth.php";
require_once __DIR__ . "/../../includes/database.php";

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

    $maintenanceStmt = $pdo->prepare(
        "SELECT
            id,
            asset_id
         FROM maintenance
         WHERE id = :id
         FOR UPDATE"
    );

    $maintenanceStmt->execute([
        'id' => $id
    ]);

    $maintenance = $maintenanceStmt->fetch();

    if (!$maintenance) {
        $pdo->rollBack();

        header("Location: index.php");
        exit;
    }

    /*
     * Lock the linked Asset while status is recalculated.
     */
    $assetStmt = $pdo->prepare(
        "SELECT
            id,
            custodian
         FROM assets
         WHERE id = :id
         FOR UPDATE"
    );

    $assetStmt->execute([
        'id' => $maintenance['asset_id']
    ]);

    $asset = $assetStmt->fetch();

    if (!$asset) {
        throw new RuntimeException(
            'MAINTENANCE_ASSET_MISSING'
        );
    }

    $deleteStmt = $pdo->prepare(
        "DELETE FROM maintenance
         WHERE id = :id"
    );

    $deleteStmt->execute([
        'id' => $id
    ]);

    /*
     * Determine whether another active record still owns the
     * Under Maintenance state.
     */
    $activeStmt = $pdo->prepare(
        "SELECT 1
         FROM maintenance
         WHERE asset_id = :asset_id
           AND status <> 'Completed'
         LIMIT 1"
    );

    $activeStmt->execute([
        'asset_id' => $maintenance['asset_id']
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
        'id' => $maintenance['asset_id']
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