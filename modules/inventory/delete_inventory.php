<?php

require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../includes/property_core_gateway.php';

requireValidAccessCsrfPost();

$inventoryId = propertyCoreBusinessId(
    $_POST['inventory_id'] ?? null,
    'INV'
);

if ($inventoryId === null) {
    header('Location: index.php?error=invalid_id');
    exit;
}

try {
    getPropertyCoreServiceClient()->deleteInventory(
        $inventoryId,
        currentPropertyCoreActor()
    );
} catch (Throwable $error) {
    header(
        'Location: index.php?error=' . rawurlencode(
            inventoryGatewayErrorKey($error)
        )
    );
    exit;
}

header('Location: index.php');
exit;
