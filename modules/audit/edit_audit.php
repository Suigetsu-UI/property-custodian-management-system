<?php

require_once "../../auth/check_auth.php";
require_once __DIR__ . "/../../includes/asset_functions.php";
include "../../includes/header.php";

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

if ($id === null || !isset($_SESSION['audits'][$id])) {
    header("Location: index.php");
    exit;
}

$audit = $_SESSION['audits'][$id];

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $assetId = $audit['asset_id'] ?? '';
    $linkedAsset = $assetId !== '' ? getAssetByID($assetId) : null;
    $result = $_POST["result"];

    $_SESSION['audits'][$id] = [
        "audit_id" => $audit["audit_id"],
        "asset_id" => $assetId,
        "asset_name" => $linkedAsset['asset_name'] ?? $audit['asset_name'],
        "category" => $linkedAsset['category'] ?? $audit['category'],
        "custodian" => $linkedAsset['custodian'] ?? '',
        "auditor" => $_POST["auditor"],
        "audit_date" => $_POST["audit_date"],
        "result" => $result,
        "remarks" => $_POST["remarks"] ?? '',
        "status" => $_POST["status"]
    ];

    if ($assetId !== '') {
        updateAssetStatusFromAudit($assetId, $result);
    }

    header("Location: index.php");
    exit;
}

?>

<div class="layout">

    <?php include "../../includes/sidebar.php"; ?>

    <div class="main-content">

        <h1>Edit Audit</h1>

        <hr>

        <?php include "audit_form.php"; ?>

    </div>

</div>

<?php include "../../includes/footer.php"; ?>