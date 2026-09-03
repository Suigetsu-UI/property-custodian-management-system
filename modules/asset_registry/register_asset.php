<?php

require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../includes/property_core_gateway.php';

requireValidAccessCsrfPost();

$assetId = propertyCoreBusinessId($_POST['asset_id'] ?? null, 'AST');
$inventoryId = propertyCoreBusinessId($_POST['inventory_id'] ?? null, 'INV');
$issuedToSession = $assetId !== null
    && isset($_SESSION['pending_asset_ids'][$assetId]);

if (!$issuedToSession) {
    header('Location: index.php?error=invalid_id');
    exit;
}
if ($inventoryId === null) {
    header('Location: index.php?error=inventory');
    exit;
}

try {
    getPropertyCoreServiceClient()->registerAsset(
        assetRegistrationGatewayPayload($_POST),
        currentPropertyCoreActor()
    );
    unset($_SESSION['pending_asset_ids'][$assetId]);
    if (empty($_SESSION['pending_asset_ids'])) {
        unset($_SESSION['pending_asset_ids']);
    }
} catch (Throwable $error) {
    header('Location: index.php?error=' . rawurlencode(assetGatewayErrorKey($error)));
    exit;
}

header('Location: index.php');
exit;
