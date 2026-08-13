<?php

require_once "../../auth/check_auth.php";
require_once __DIR__ . "/../../includes/asset_functions.php";

$id = $_GET['id'] ?? null;

if ($id !== null && isset($_SESSION['audits'][$id])) {

    $assetId = $_SESSION['audits'][$id]['asset_id'] ?? '';

    unset($_SESSION['audits'][$id]);
    $_SESSION['audits'] = array_values($_SESSION['audits']);

    if ($assetId !== '') {
        syncAssetStatusAfterAuditRemoval($assetId);
    }
}

header("Location: index.php");
exit;