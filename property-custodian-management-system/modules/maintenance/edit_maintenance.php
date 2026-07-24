<?php

require_once "../../auth/check_auth.php";
require_once "../../includes/asset_functions.php";
include "../../includes/header.php";

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

if (!isset($_SESSION['maintenance'][$id])) {
    header("Location: index.php");
    exit;
}

$maintenance = $_SESSION['maintenance'][$id];

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $assetId = $maintenance['asset_id'] ?? '';
    $linkedAsset = $assetId !== '' ? getAssetByID($assetId) : null;
    $status = $_POST["status"];

    $_SESSION['maintenance'][$id] = [

        "maintenance_id" => $maintenance["maintenance_id"],
        "asset_id" => $assetId,
        "asset_name" => $linkedAsset['asset_name'] ?? $maintenance['asset_name'],
        "category" => $linkedAsset['category'] ?? $maintenance['category'],
        "custodian" => $linkedAsset['custodian'] ?? '',
        "maintenance_type" => $_POST["maintenance_type"],
        "scheduled_date" => $_POST["scheduled_date"],
        "status" => $status

    ];

    if ($assetId !== '') {
        updateAssetStatusFromMaintenance($assetId, $status);
    }

    header("Location: index.php");
    exit;
}

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