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

$status = (string) ($asset['status'] ?? '');
if ($status === 'Under Maintenance') {
    header('Location: index.php?error=maintenance');
    exit;
}
if ($status === 'Lost') {
    header('Location: index.php?error=lost');
    exit;
}
if ($status !== 'Available') {
    header('Location: index.php?error=state_changed');
    exit;
}

include __DIR__ . '/../../includes/header.php';
?>
<div class="layout">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main-content">
        <h1>Assign Custodian</h1><hr><br>
        <form method="POST" action="save_assignment.php" class="asset-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getAccessCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="asset_id" value="<?= htmlspecialchars($assetId) ?>">
            <div class="form-row"><label>Asset ID</label><input type="text" value="<?= htmlspecialchars($assetId) ?>" readonly></div>
            <div class="form-row"><label>Asset Name</label><input type="text" value="<?= htmlspecialchars($asset['asset_name'] ?? '') ?>" readonly></div>
            <div class="form-row"><label>Employee ID</label><input type="text" name="employee_id" required></div>
            <div class="form-row"><label>Custodian Name</label><input type="text" name="custodian" required></div>
            <div class="form-row"><label>Department</label><select name="department" required><option>ICT Office</option><option>Registrar</option><option>Accounting</option><option>Library</option><option>Guidance Office</option></select></div>
            <div class="form-row"><label>Date Assigned</label><input type="date" name="date_assigned" required></div>
            <br><button class="btn btn-success" type="submit">Assign Asset</button> <a href="index.php" class="btn btn-outline">Cancel</a>
        </form>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
