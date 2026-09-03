<?php

require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../includes/property_core_gateway.php';

requireValidAccessCsrfPost();

$isEdit = trim((string) ($_POST['mode'] ?? '')) === 'edit';
$payload = inventoryGatewayPayload($_POST);
$inventoryId = propertyCoreBusinessId($payload['inventory_id'], 'INV');
$quantity = filter_var($payload['quantity'], FILTER_VALIDATE_INT);

if (
    $inventoryId === null ||
    $payload['asset_name'] === '' ||
    $payload['category'] === '' ||
    $payload['condition'] === '' ||
    $quantity === false ||
    (!$isEdit && (int) $quantity <= 0) ||
    ($isEdit && (int) $quantity < 0)
) {
    header('Location: index.php?error=save_failed');
    exit;
}

$payload['quantity'] = (int) $quantity;

if (
    !$isEdit &&
    !isset($_SESSION['pending_inventory_ids'][$inventoryId])
) {
    header('Location: index.php?error=invalid_id');
    exit;
}

try {
    $client = getPropertyCoreServiceClient();

    if ($isEdit) {
        $client->updateInventory(
            $inventoryId,
            $payload,
            currentPropertyCoreActor()
        );
    } else {
        $client->createInventory($payload, currentPropertyCoreActor());
        unset($_SESSION['pending_inventory_ids'][$inventoryId]);

        if (empty($_SESSION['pending_inventory_ids'])) {
            unset($_SESSION['pending_inventory_ids']);
        }
    }
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
