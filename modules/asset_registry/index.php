<?php

require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../includes/property_core_gateway.php';

$assetFilters = [
    'search' => substr(trim((string) ($_GET['search'] ?? '')), 0, 200),
    'category' => trim((string) ($_GET['category'] ?? '')),
    'status' => trim((string) ($_GET['status'] ?? '')),
    'location' => trim((string) ($_GET['location'] ?? '')),
    'page' => max(1, (int) ($_GET['page'] ?? 1)),
    'per_page' => 25,
];
$assetPage = [
    'records' => [],
    'pagination' => [
        'page' => 1,
        'per_page' => 25,
        'total' => 0,
        'total_pages' => 1,
    ],
    'filters' => $assetFilters,
];
$assetFilterOptions = [
    'categories' => [],
    'locations' => [],
    'statuses' => ['Available', 'Assigned', 'Under Maintenance', 'Lost'],
];
$assetServiceAvailable = true;

try {
    $client = getPropertyCoreServiceClient();
    $assetPage = $client->listAssets($assetFilters);
    $assetFilterOptions = $client->assetFilters();
} catch (Throwable $error) {
    $assetServiceAvailable = false;
}

include __DIR__ . '/../../includes/header.php';

$errorMessages = [
    'assigned' => 'This Asset is currently assigned to a custodian and cannot be deleted while assigned.',
    'lost' => 'This Asset is marked Lost and cannot be changed until its Audit status changes.',
    'maintenance' => 'This Asset is currently under Maintenance. Complete the Maintenance record before changing it.',
    'history' => 'This Asset cannot be deleted because Maintenance or Audit history is linked to it.',
    'invalid_id' => 'The Asset ID is invalid or was not issued for this session. Please reopen Register Asset and try again.',
    'stock' => 'The selected Inventory item is no longer available for registration.',
    'inventory' => 'Please select a valid available Inventory item.',
    'not_found' => 'The requested Asset record was not found.',
    'state_changed' => 'The Asset state changed before the request completed. Refresh and try again.',
    'save_failed' => 'The Asset record could not be saved. Please check the entered values and try again.',
    'service_unavailable' => 'Property Core service is temporarily unavailable. Other PCMS modules remain available.',
];
$errorKey = trim((string) ($_GET['error'] ?? ''));
?>

<div class="layout">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <h1>Asset Registry</h1>
        <hr>
        <br>

        <?php if (!$assetServiceAvailable): ?>
        <div class="error-message" role="alert">
            Property Core service is temporarily unavailable. Other PCMS modules remain available.
        </div>
        <br>
        <?php elseif (isset($errorMessages[$errorKey])): ?>
        <div class="error-message" role="alert">
            <?= htmlspecialchars($errorMessages[$errorKey]) ?>
        </div>
        <br>
        <?php endif; ?>

        <?php include __DIR__ . '/asset_search.php'; ?>
        <br>
        <?php include __DIR__ . '/asset_table.php'; ?>
        <?php include __DIR__ . '/asset_form.php'; ?>
        <?php include __DIR__ . '/asset_action_modals.php'; ?>
    </div>
</div>

<script src="<?= BASE_URL ?>assets/js/asset_typeahead.js?v=<?= filemtime(__DIR__ . '/../../assets/js/asset_typeahead.js') ?>"></script>
<script src="<?= BASE_URL ?>assets/js/asset_registry.js?v=<?= filemtime(__DIR__ . '/../../assets/js/asset_registry.js') ?>"></script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
