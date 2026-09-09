<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../includes/property_core_gateway.php';
requirePropertyCustodian();
requireValidAccessCsrfPost();
$assetId = propertyCoreBusinessId($_POST['asset_id'] ?? null, 'AST');
if ($assetId === null) { header('Location: index.php?error=not_found'); exit; }
try {
    getPropertyCoreServiceClient()->createDisposition($assetId, dispositionRequestGatewayPayload($_POST), currentPropertyCoreActor());
    header('Location: review.php?asset_id=' . rawurlencode($assetId) . '&message=requested');
} catch (Throwable $error) {
    header('Location: review.php?asset_id=' . rawurlencode($assetId) . '&message=' . rawurlencode(dispositionGatewayErrorKey($error)));
}
exit;
