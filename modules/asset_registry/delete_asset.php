<?php

require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../includes/property_core_gateway.php';

requireValidAccessCsrfPost();
$assetId = propertyCoreBusinessId($_POST['asset_id'] ?? null, 'AST');

if ($assetId === null) {
    header('Location: index.php?error=not_found');
    exit;
}

try {
    getPropertyCoreServiceClient()->deleteAsset(
        $assetId,
        currentPropertyCoreActor()
    );
} catch (Throwable $error) {
    header('Location: index.php?error=' . rawurlencode(assetGatewayErrorKey($error)));
    exit;
}

header('Location: index.php');
exit;
