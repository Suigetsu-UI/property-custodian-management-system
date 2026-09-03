<?php

require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../includes/property_core_gateway.php';

$inventoryFilters = [
    'search' => substr(trim((string) ($_GET['search'] ?? '')), 0, 200),
    'category' => trim((string) ($_GET['category'] ?? '')),
    'condition' => trim((string) ($_GET['condition'] ?? '')),
    'page' => max(1, (int) ($_GET['page'] ?? 1)),
    'per_page' => 25,
];
$inventoryPage = [
    'records' => [],
    'pagination' => [
        'page' => 1,
        'per_page' => 25,
        'total' => 0,
        'total_pages' => 1,
    ],
    'filters' => $inventoryFilters,
];
$inventoryServiceAvailable = true;

try {
    $inventoryPage = getPropertyCoreServiceClient()->listInventory(
        $inventoryFilters
    );
} catch (Throwable $error) {
    $inventoryServiceAvailable = false;
}

include __DIR__ . '/../../includes/header.php';

$errorMessages = [
    'linked' => 'Cannot delete this Inventory item because one or more registered Assets are linked to it.',
    'duplicate_item' => 'An Inventory item with this Asset Name and Category already exists. Please edit the existing item instead.',
    'invalid_id' => 'The Inventory ID is invalid or was not issued for this session. Please reopen Add Inventory and try again.',
    'not_found' => 'The requested Inventory record was not found.',
    'save_failed' => 'The Inventory record could not be saved. Please check the entered values and try again.',
    'service_unavailable' => 'Property Core service is temporarily unavailable. Other PCMS modules remain available.',
];
$errorKey = trim((string) ($_GET['error'] ?? ''));
?>

<div class="layout">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <h1>Inventory Management</h1>
        <hr>
        <br>

        <?php if (!$inventoryServiceAvailable): ?>
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

        <form method="GET" action="index.php" class="search-toolbar" id="inventorySearchForm">
            <input
                type="search"
                id="searchInput"
                name="search"
                placeholder="Search Inventory..."
                value="<?= htmlspecialchars($inventoryFilters['search']) ?>"
            >

            <select id="categoryFilter" name="category">
                <option value="">All Categories</option>
                <?php foreach (['Computer', 'Furniture', 'Office Equipment', 'Electronics'] as $category): ?>
                <option value="<?= htmlspecialchars($category) ?>" <?= $inventoryFilters['category'] === $category ? 'selected' : '' ?>>
                    <?= htmlspecialchars($category) ?>
                </option>
                <?php endforeach; ?>
            </select>

            <select id="conditionFilter" name="condition">
                <option value="">All Conditions</option>
                <?php foreach (['Good', 'Fair', 'Needs Repair', 'Unserviceable'] as $condition): ?>
                <option value="<?= htmlspecialchars($condition) ?>" <?= $inventoryFilters['condition'] === $condition ? 'selected' : '' ?>>
                    <?= htmlspecialchars($condition) ?>
                </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn btn-outline">Search</button>
            <a href="index.php" class="btn btn-outline">Clear Filters</a>
            <button
                type="button"
                id="openInventoryModal"
                class="btn btn-primary"
                <?= !$inventoryServiceAvailable ? 'disabled' : '' ?>
            >
                Add Inventory
            </button>
        </form>

        <br>

        <?php include __DIR__ . '/inventory_table.php'; ?>
        <?php include __DIR__ . '/inventory_modals.php'; ?>
    </div>
</div>

<script src="<?= BASE_URL ?>assets/js/inventory.js?v=<?= filemtime(__DIR__ . '/../../assets/js/inventory.js') ?>"></script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
