<?php

require_once "../../auth/check_auth.php";
require_once __DIR__ . "/../../includes/database.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

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

$assetName = trim($_POST['asset_name'] ?? '');
$category = trim($_POST['category'] ?? '');
$brand = trim($_POST['brand'] ?? '');
$model = trim($_POST['model'] ?? '');
$serialNumber = trim($_POST['serial_number'] ?? '');
$supplier = trim($_POST['supplier'] ?? '');
$location = trim($_POST['location'] ?? '');
$remarks = trim($_POST['remarks'] ?? '');

if (
    $assetName === '' ||
    $category === ''
) {
    header("Location: index.php?error=save_failed");
    exit;
}

$pdo = null;

try {
    $pdo = getDbConnection();

    $pdo->beginTransaction();

    $lock = $pdo->prepare(
        "SELECT id
         FROM assets
         WHERE id = :id
         FOR UPDATE"
    );

    $lock->execute([
        'id' => $id
    ]);

    if (!$lock->fetch()) {
        $pdo->rollBack();

        header("Location: index.php");
        exit;
    }

    /*
     * Asset ID and Inventory relationship are intentionally immutable here.
     * This preserves the existing Edit workflow.
     */
    $update = $pdo->prepare(
        "UPDATE assets
         SET
            asset_name = :asset_name,
            category = :category,
            brand = :brand,
            model = :model,
            serial_number = :serial_number,
            supplier = :supplier,
            location = :location,
            remarks = :remarks,
            updated_at = now()
         WHERE id = :id"
    );

    $update->execute([
        'asset_name' => $assetName,
        'category' => $category,
        'brand' => $brand,
        'model' => $model,
        'serial_number' => $serialNumber,
        'supplier' => $supplier,
        'location' => $location,
        'remarks' => $remarks,
        'id' => $id
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