<?php

require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../includes/property_core_gateway.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
requireValidAccessCsrfPost();

try {
    $assetId = getPropertyCoreServiceClient()->nextAssetBusinessId(
        currentPropertyCoreActor()
    );
    if (propertyCoreBusinessId($assetId, 'AST') === null) {
        throw new RuntimeException('Invalid service response.');
    }
    $_SESSION['pending_asset_ids'] ??= [];
    $_SESSION['pending_asset_ids'][$assetId] = true;
    echo json_encode(['asset_id' => $assetId]);
} catch (Throwable $error) {
    http_response_code(503);
    echo json_encode(['error' => 'Could not generate an Asset ID.']);
}
exit;
