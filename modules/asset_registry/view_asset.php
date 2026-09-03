<?php

require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../includes/property_core_gateway.php';

$assetId = propertyCoreBusinessId($_GET['asset_id'] ?? null, 'AST');
$jsonMode = ($_GET['format'] ?? '') === 'json';

if ($assetId === null) {
    if ($jsonMode) {
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code(400);
        echo json_encode(['error' => 'Invalid Asset ID.']);
        exit;
    }
    header('Location: index.php?error=not_found');
    exit;
}

try {
    $asset = getPropertyCoreServiceClient()->findAsset($assetId);
} catch (Throwable $error) {
    if ($jsonMode) {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        http_response_code($error instanceof PropertyCoreServiceException ? 404 : 503);
        echo json_encode(['error' => 'The Asset record could not be loaded.']);
        exit;
    }
    header('Location: index.php?error=' . rawurlencode(assetGatewayErrorKey($error)));
    exit;
}

if ($jsonMode) {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    echo json_encode(
        ['success' => true, 'asset' => $asset],
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

include __DIR__ . '/../../includes/header.php';
?>
<div class="layout">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main-content">
        <h1>View Asset</h1><hr><br>
        <table class="asset-table">
            <?php
            $labels = [
                'asset_id' => 'Asset ID', 'asset_name' => 'Asset Name',
                'category' => 'Category', 'status' => 'Status',
                'brand' => 'Brand', 'model' => 'Model',
                'serial_number' => 'Serial Number',
                'acquisition_date' => 'Acquisition Date',
                'purchase_cost' => 'Purchase Cost', 'supplier' => 'Supplier',
                'location' => 'Location', 'remarks' => 'Remarks',
                'employee_id' => 'Employee ID', 'custodian' => 'Custodian',
                'department' => 'Department', 'date_assigned' => 'Date Assigned',
            ];
            foreach ($labels as $field => $label):
                $value = $asset[$field] ?? '';
            ?>
            <tr><th><?= htmlspecialchars($label) ?></th><td><?= htmlspecialchars(
                $value === null || $value === '' ? 'Not Assigned' : (string) $value
            ) ?></td></tr>
            <?php endforeach; ?>
        </table>
        <br><a href="index.php" class="btn btn-outline">← Back to Asset Registry</a>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
