<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../includes/property_core_gateway.php';
requirePropertyCustodian();
requireValidAccessCsrfPost();
$assetId = propertyCoreBusinessId($_POST['asset_id'] ?? null, 'AST');
$dispositionId = propertyCoreBusinessId($_POST['disposition_id'] ?? null, 'DSP');
if ($assetId === null || $dispositionId === null) { header('Location: index.php?error=not_found'); exit; }
try {
    getPropertyCoreServiceClient()->transitionDisposition($dispositionId, 'cancel', dispositionDecisionGatewayPayload($_POST), currentPropertyCoreActor());
    header('Location: review.php?asset_id=' . rawurlencode($assetId) . '&message=cancelled');
} catch (Throwable $error) {
    header('Location: review.php?asset_id=' . rawurlencode($assetId) . '&message=' . rawurlencode(dispositionGatewayErrorKey($error)));
}
exit;
