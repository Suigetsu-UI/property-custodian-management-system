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

$pdo = null;

try {
    $pdo = getDbConnection();

    $pdo->beginTransaction();

    /*
     * Lock the Inventory row before checking/deleting it.
     */
    $inventoryStmt = $pdo->prepare(
        "SELECT
            id,
            inventory_id,
            asset_name,
            category,
            quantity
         FROM inventory
         WHERE id = :id
         FOR UPDATE"
    );

    $inventoryStmt->execute([
        'id' => $id
    ]);

    $inventory = $inventoryStmt->fetch();

    if (!$inventory) {
        $pdo->rollBack();

        header("Location: index.php");
        exit;
    }

    /*
     * Asset Registry is now persistent.
     * Do not delete an Inventory row used by any registered Asset.
     */
    $assetStmt = $pdo->prepare(
        "SELECT 1
         FROM assets
         WHERE inventory_id = :inventory_id
         LIMIT 1"
    );

    $assetStmt->execute([
        'inventory_id' => $inventory['id']
    ]);

    if ($assetStmt->fetch()) {
        $pdo->rollBack();

        header("Location: index.php?error=linked");
        exit;
    }

    recordPropertyEvent($pdo, [
        'module' => 'Inventory',
        'event_type' => 'Deleted',
        'business_id' => $inventory['inventory_id'],
        'record_name_snap' => $inventory['asset_name'],
        'category_snap' => $inventory['category'],
        'event_date' => currentPropertyEventDate(),
        'quantity_delta' => -(int) $inventory['quantity'],
        'performed_by' => currentPropertyEventActor(),
    ]);

    $deleteStmt = $pdo->prepare(
        "DELETE FROM inventory
         WHERE id = :id"
    );

    $deleteStmt->execute([
        'id' => $inventory['id']
    ]);

    $pdo->commit();

} catch (PDOException $e) {

    if (
        $pdo instanceof PDO &&
        $pdo->inTransaction()
    ) {
        $pdo->rollBack();
    }

    /*
     * PostgreSQL FK backstop.
     */
    if ($e->getCode() === '23503') {
        header("Location: index.php?error=linked");
        exit;
    }

    header("Location: index.php?error=save_failed");
    exit;

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
