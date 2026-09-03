<?php

require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../includes/property_core_gateway.php';

$inventoryId = propertyCoreBusinessId(
    $_GET['inventory_id'] ?? $_GET['id'] ?? null,
    'INV'
);
$inventory = null;
$errorKey = null;

if ($inventoryId !== null) {
    try {
        $inventory = getPropertyCoreServiceClient()->findInventory(
            $inventoryId
        );
    } catch (Throwable $error) {
        $errorKey = inventoryGatewayErrorKey($error);
    }
}

$wantsJson = ($_GET['format'] ?? '') === 'json' || str_contains(
    strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')),
    'application/json'
);

if ($wantsJson) {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');

    if (is_array($inventory)) {
        echo json_encode(['success' => true, 'inventory' => $inventory]);
    } else {
        http_response_code($errorKey === 'service_unavailable' ? 503 : 404);
        echo json_encode([
            'success' => false,
            'error' => $errorKey ?? 'not_found',
        ]);
    }
    exit;
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="layout">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main-content">
        <h1>View Inventory</h1>
        <hr><br>

        <?php if ($inventory): ?>
        <table class="asset-table">
            <tr><th style="width:220px;">Inventory ID</th><td><?= htmlspecialchars($inventory['inventory_id']) ?></td></tr>
            <tr><th>Asset Name</th><td><?= htmlspecialchars($inventory['asset_name']) ?></td></tr>
            <tr><th>Category</th><td><?= htmlspecialchars($inventory['category']) ?></td></tr>
            <tr><th>Quantity</th><td><?= (int) $inventory['quantity'] ?></td></tr>
            <tr><th>Condition</th><td><?= htmlspecialchars($inventory['condition']) ?></td></tr>
        </table>
        <?php else: ?>
        <p><?= $errorKey === 'service_unavailable'
            ? 'Inventory details are temporarily unavailable.'
            : 'Inventory record not found.' ?></p>
        <?php endif; ?>

        <br>
        <a href="index.php" class="btn btn-primary">Back to Inventory</a>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
