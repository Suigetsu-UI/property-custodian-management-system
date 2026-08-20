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

    /*
     * Lock the individual Asset first.
     */
    $assetStmt = $pdo->prepare(
        "SELECT
            id,
            asset_id,
            inventory_id,
            status
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

    /*
     * Maintenance owns Under Maintenance.
     */
    $activeMaintenance = $pdo->prepare(
        "SELECT 1
         FROM maintenance
         WHERE asset_id = :asset_id
           AND status <> 'Completed'
         LIMIT 1"
    );

    $activeMaintenance->execute([
        'asset_id' => $id
    ]);

    if (
        $asset['status'] === 'Under Maintenance' ||
        $activeMaintenance->fetch()
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

    if ($asset['status'] === 'Assigned') {
        $pdo->rollBack();

        header("Location: index.php?error=assigned");
        exit;
    }

    /*
     * Preserve Maintenance/Audit history.
     * The schema also enforces these references through ON DELETE RESTRICT.
     */
    $historyStmt = $pdo->prepare(
        "SELECT
            EXISTS(
                SELECT 1
                FROM maintenance
                WHERE asset_id = :maintenance_asset_id
            )
            OR
            EXISTS(
                SELECT 1
                FROM audits
                WHERE asset_id = :audit_asset_id
            )
            AS has_history"
    );

    $historyStmt->execute([
        'maintenance_asset_id' => $id,
        'audit_asset_id' => $id
    ]);

    $history = $historyStmt->fetch();

    if (
        $history &&
        filter_var(
            $history['has_history'],
            FILTER_VALIDATE_BOOLEAN
        )
    ) {
        $pdo->rollBack();

        header("Location: index.php?error=history");
        exit;
    }

    /*
     * Lock Inventory before restoring stock.
     */
    $inventoryStmt = $pdo->prepare(
        "SELECT id
         FROM inventory
         WHERE id = :id
         FOR UPDATE"
    );

    $inventoryStmt->execute([
        'id' => $asset['inventory_id']
    ]);

    $inventory = $inventoryStmt->fetch();

    if (!$inventory) {
        throw new RuntimeException(
            'ASSET_INVENTORY_LINK_MISSING'
        );
    }

    /*
     * Delete the individual Asset.
     */
    $delete = $pdo->prepare(
        "DELETE FROM assets
         WHERE id = :id"
    );

    $delete->execute([
        'id' => $id
    ]);

    /*
     * Eligible Asset deletion restores exactly one Inventory unit.
     */
    $restoreInventory = $pdo->prepare(
        "UPDATE inventory
         SET
            quantity = quantity + 1,
            updated_at = now()
         WHERE id = :id"
    );

    $restoreInventory->execute([
        'id' => $asset['inventory_id']
    ]);

    $pdo->commit();

} catch (PDOException $e) {

    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($e->getCode() === '23503') {
        header("Location: index.php?error=history");
        exit;
    }

    header("Location: index.php?error=save_failed");
    exit;

} catch (Throwable $e) {

    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    header("Location: index.php?error=save_failed");
    exit;
}

header("Location: index.php");
exit;