<?php

require_once "../../auth/check_auth.php";
require_once __DIR__ . "/../../includes/asset_functions.php";

if (!isset($_SESSION['maintenance'])) {
    $_SESSION['maintenance'] = [];
}

$id = isset($_POST['id']) && $_POST['id'] !== ''
    ? (int) $_POST['id']
    : null;

$assetId = trim($_POST['asset_id'] ?? '');
$linkedAsset = $assetId !== '' ? getAssetByID($assetId) : null;
$status = $_POST["status"];

if ($id !== null && isset($_SESSION['maintenance'][$id])) {

    $existing = $_SESSION['maintenance'][$id];

    $_SESSION['maintenance'][$id] = [

        "maintenance_id" => $existing['maintenance_id'],
        "asset_id" => $existing['asset_id'] ?? $assetId,
        "asset_name" => $linkedAsset['asset_name'] ?? $existing['asset_name'],
        "category" => $linkedAsset['category'] ?? $existing['category'],
        "custodian" => $linkedAsset['custodian'] ?? '',
        "maintenance_type" => $_POST["maintenance_type"],
        "scheduled_date" => $_POST["scheduled_date"],
        "status" => $status

    ];

} else {

    $_SESSION['maintenance'][] = [

        "maintenance_id" => $_POST["maintenance_id"] ?? generateMaintenanceID(),
        "asset_id" => $assetId,
        "asset_name" => $linkedAsset['asset_name'] ?? '',
        "category" => $linkedAsset['category'] ?? '',
        "custodian" => $linkedAsset['custodian'] ?? '',
        "maintenance_type" => $_POST["maintenance_type"],
        "scheduled_date" => $_POST["scheduled_date"],
        "status" => $status

    ];
}

if ($assetId !== '') {
    updateAssetStatusFromMaintenance($assetId, $status);
}

header("Location: index.php");
exit;