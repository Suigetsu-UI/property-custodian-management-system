<?php

require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../includes/property_core_gateway.php';

$inventoryId = propertyCoreBusinessId(
    $_GET['inventory_id'] ?? $_GET['id'] ?? null,
    'INV'
);

if ($inventoryId === null) {
    header('Location: index.php?error=invalid_id');
    exit;
}

try {
    $inventory = getPropertyCoreServiceClient()->findInventory($inventoryId);
} catch (Throwable $error) {
    header(
        'Location: index.php?error=' . rawurlencode(
            inventoryGatewayErrorKey($error)
        )
    );
    exit;
}

$id = $inventoryId;
include __DIR__ . '/../../includes/header.php';
?>

<div class="layout">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main-content">
        <h1>Edit Inventory</h1>
        <hr>
        <?php include __DIR__ . '/inventory_form.php'; ?>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
