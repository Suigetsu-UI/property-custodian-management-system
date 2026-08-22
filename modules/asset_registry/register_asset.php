<?php

require_once "../../auth/check_auth.php";
require_once __DIR__ . "/../../includes/database.php";
require_once __DIR__ . "/../../includes/event_functions.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

$assetBusinessId = trim($_POST['asset_id'] ?? '');

$validAssetId =
    preg_match('/^AST-\d{6}$/', $assetBusinessId) === 1;

$issuedToSession =
    isset($_SESSION['pending_asset_ids'][$assetBusinessId]);

if (!$validAssetId || !$issuedToSession) {
    header("Location: index.php?error=invalid_id");
    exit;
}

$inventoryId = filter_var(
    $_POST['inventory_id'] ?? null,
    FILTER_VALIDATE_INT,
    [
        'options' => [
            'min_range' => 1
        ]
    ]
);

if ($inventoryId === false) {
    header("Location: index.php?error=inventory");
    exit;
}

$brand = trim($_POST['brand'] ?? '');
$model = trim($_POST['model'] ?? '');
$serialNumber = trim($_POST['serial_number'] ?? '');
$acquisitionDate = trim($_POST['acquisition_date'] ?? '');
$supplier = trim($_POST['supplier'] ?? '');
$location = trim($_POST['location'] ?? '');
$remarks = trim($_POST['remarks'] ?? '');

$purchaseCostRaw = trim((string) ($_POST['purchase_cost'] ?? ''));

if (
    $acquisitionDate === '' ||
    $purchaseCostRaw === '' ||
    !is_numeric($purchaseCostRaw) ||
    (float) $purchaseCostRaw < 0
) {
    header("Location: index.php?error=save_failed");
    exit;
}

$purchaseCost = (float) $purchaseCostRaw;

$pdo = null;
$createdAssetId = null;

try {
    $pdo = getDbConnection();

    $pdo->beginTransaction();

    /*
     * Inventory owns available stock.
     * Lock before checking quantity so two simultaneous registrations
     * cannot consume the same final unit.
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
        'id' => $inventoryId
    ]);

    $inventory = $inventoryStmt->fetch();

    if (
        !$inventory ||
        (int) $inventory['quantity'] <= 0
    ) {
        $pdo->rollBack();

        header("Location: index.php?error=stock");
        exit;
    }

    /*
     * Asset Name and Category come from the authoritative Inventory row.
     * Posted readonly values are intentionally not trusted.
     */
    $insert = $pdo->prepare(
        "INSERT INTO assets (
            asset_id,
            asset_name,
            category,
            brand,
            model,
            serial_number,
            acquisition_date,
            purchase_cost,
            supplier,
            location,
            remarks,
            inventory_id,
            status
         )
         VALUES (
            :asset_id,
            :asset_name,
            :category,
            :brand,
            :model,
            :serial_number,
            :acquisition_date,
            :purchase_cost,
            :supplier,
            :location,
            :remarks,
            :inventory_id,
            'Available'
         )"
    );

    $insert->execute([
        'asset_id' => $assetBusinessId,
        'asset_name' => $inventory['asset_name'],
        'category' => $inventory['category'],
        'brand' => $brand,
        'model' => $model,
        'serial_number' => $serialNumber,
        'acquisition_date' => $acquisitionDate,
        'purchase_cost' => $purchaseCost,
        'supplier' => $supplier,
        'location' => $location,
        'remarks' => $remarks,
        'inventory_id' => $inventory['id']
    ]);

    /*
     * Registration consumes exactly one unit of available Inventory.
     */
    $updateInventory = $pdo->prepare(
        "UPDATE inventory
         SET
            quantity = quantity - 1,
            updated_at = now()
         WHERE id = :id"
    );

    $updateInventory->execute([
        'id' => $inventory['id']
    ]);

    $eventDate = currentPropertyEventDate();

    recordPropertyEvent($pdo, [
        'module' => 'Asset Registry',
        'event_type' => 'Registered',
        'business_id' => $assetBusinessId,
        'related_business_id' => $inventory['inventory_id'],
        'record_name_snap' => $inventory['asset_name'],
        'category_snap' => $inventory['category'],
        'event_date' => $eventDate,
        'to_status' => 'Available',
        'performed_by' => currentPropertyEventActor(),
    ]);

    recordPropertyEvent($pdo, [
        'module' => 'Inventory',
        'event_type' => 'Stock Decreased',
        'business_id' => $inventory['inventory_id'],
        'related_business_id' => $assetBusinessId,
        'record_name_snap' => $inventory['asset_name'],
        'category_snap' => $inventory['category'],
        'event_date' => $eventDate,
        'quantity_delta' => -1,
        'performed_by' => currentPropertyEventActor(),
        'description' => 'Inventory unit registered as an individual Asset.',
    ]);

    $createdAssetId = $assetBusinessId;

    $pdo->commit();

    /*
     * Retire the temporary issued ID only after successful commit.
     */
    unset(
        $_SESSION['pending_asset_ids'][$createdAssetId]
    );

    if (
        isset($_SESSION['pending_asset_ids']) &&
        empty($_SESSION['pending_asset_ids'])
    ) {
        unset($_SESSION['pending_asset_ids']);
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
