<?php

require_once "../../auth/check_auth.php";
require_once __DIR__ . "/../../includes/asset_functions.php";

include "../../includes/header.php";

if (!isset($_SESSION['audits'])) {
    $_SESSION['audits'] = [];
}

$id = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $assetId = trim($_POST["asset_id"] ?? '');
    $linkedAsset = $assetId !== '' ? getAssetByID($assetId) : null;
    $result = $_POST["result"];

    $_SESSION['audits'][] = [
        "audit_id" => $_POST["audit_id"] ?? generateAuditID(),
        "asset_id" => $assetId,
        "asset_name" => $linkedAsset['asset_name'] ?? '',
        "category" => $linkedAsset['category'] ?? '',
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

        <h1>Schedule Audit</h1>

        <hr>

        <?php include "audit_form.php"; ?>

    </div>

</div>

<?php include "../../includes/footer.php"; ?>