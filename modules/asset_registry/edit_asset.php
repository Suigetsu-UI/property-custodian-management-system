<?php

require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../includes/property_core_gateway.php';

$assetId = propertyCoreBusinessId($_GET['asset_id'] ?? null, 'AST');
if ($assetId === null) {
    header('Location: index.php?error=not_found');
    exit;
}

try {
    $asset = getPropertyCoreServiceClient()->findAsset($assetId);
} catch (Throwable $error) {
    header('Location: index.php?error=' . rawurlencode(assetGatewayErrorKey($error)));
    exit;
}

if (($asset['status'] ?? '') === 'Sold') {
    header('Location: index.php?error=sold');
    exit;
}

$categories = ['Computer', 'Furniture', 'Laboratory Equipment', 'Office Equipment', 'Electronics'];
if (!in_array($asset['category'] ?? '', $categories, true)) {
    $categories[] = (string) ($asset['category'] ?? '');
}

include __DIR__ . '/../../includes/header.php';
?>
<div class="layout">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main-content">
        <h1>Edit Asset</h1><hr><br>
        <form method="POST" action="update_asset.php" class="asset-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getAccessCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="asset_id" value="<?= htmlspecialchars($assetId) ?>">
            <div class="form-row"><label>Asset ID</label><input type="text" value="<?= htmlspecialchars($assetId) ?>" readonly></div>
            <div class="form-row"><label>Asset Name</label><input type="text" name="asset_name" value="<?= htmlspecialchars($asset['asset_name'] ?? '') ?>" required></div>
            <div class="form-row"><label>Category</label><select name="category" required><?php foreach ($categories as $category): ?><option value="<?= htmlspecialchars($category) ?>" <?= ($asset['category'] ?? '') === $category ? 'selected' : '' ?>><?= htmlspecialchars($category) ?></option><?php endforeach; ?></select></div>
            <?php foreach (['brand' => 'Brand', 'model' => 'Model', 'serial_number' => 'Serial Number', 'supplier' => 'Supplier', 'location' => 'Location'] as $field => $label): ?>
            <div class="form-row"><label><?= htmlspecialchars($label) ?></label><input type="text" name="<?= htmlspecialchars($field) ?>" value="<?= htmlspecialchars($asset[$field] ?? '') ?>"></div>
            <?php endforeach; ?>
            <div class="form-row"><label>Remarks</label><textarea name="remarks"><?= htmlspecialchars($asset['remarks'] ?? '') ?></textarea></div>
            <br><button class="btn btn-primary" type="submit">Update Asset</button> <a href="index.php" class="btn btn-outline">Cancel</a>
        </form>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
