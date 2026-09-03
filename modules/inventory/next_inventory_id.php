<?php

require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../includes/property_core_gateway.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

requireValidAccessCsrfPost();

try {
    $inventoryId = getPropertyCoreServiceClient()
        ->nextInventoryBusinessId(currentPropertyCoreActor());

    if (propertyCoreBusinessId($inventoryId, 'INV') === null) {
        throw new RuntimeException('Invalid Inventory ID response.');
    }

    $_SESSION['pending_inventory_ids'][$inventoryId] = true;
    echo json_encode(['inventory_id' => $inventoryId]);
} catch (Throwable $error) {
    http_response_code(503);
    echo json_encode(['error' => 'Could not generate an Inventory ID.']);
}

exit;
