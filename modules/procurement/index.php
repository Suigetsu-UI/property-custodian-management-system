<?php

require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../includes/procurement_gateway.php';

$procurementFilters = [
    'search' => substr(trim((string) ($_GET['search'] ?? '')), 0, 100),
    'status' => trim((string) ($_GET['status'] ?? '')),
    'supplier' => substr(trim((string) ($_GET['supplier'] ?? '')), 0, 150),
    'page' => max(1, (int) ($_GET['page'] ?? 1)),
    'per_page' => 25,
];
$procurementPage = [
    'records' => [],
    'pagination' => [
        'page' => 1,
        'per_page' => 25,
        'total' => 0,
        'total_pages' => 1,
    ],
    'filters' => $procurementFilters + ['suppliers' => []],
];
$procurementServiceAvailable = true;

try {
    $procurementPage = getProcurementServiceClient()->list(
        $procurementFilters
    );
} catch (Throwable $error) {
    $procurementServiceAvailable = false;
}

include __DIR__ . '/../../includes/header.php';

$errorMessages = [
    'delivered' => 'This procurement record has already updated Inventory and cannot be deleted. Adjust the linked Inventory item manually if a reversal is required.',
    'invalid_status' => 'That status change is not allowed for this procurement record.',
    'delivery_date' => 'A Delivery Date is required when Procurement status is Delivered.',
    'inventory_negative' => 'This change would reduce Inventory below zero. No changes were made.',
    'invalid_id' => 'The Procurement ID is invalid or was not issued for this session. Please reopen Add Procurement and try again.',
    'duplicate_id' => 'This Procurement ID has already been used. Please reopen Add Procurement if you intended to create a new record.',
    'save_failed' => 'The procurement record could not be saved. Please try again.',
    'service_unavailable' => 'Procurement service is temporarily unavailable. Other PCMS modules remain available.',
];
$errorKey = trim((string) ($_GET['error'] ?? ''));
?>

<div class="layout">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <h1>Procurement Management</h1>
        <hr>
        <br>

        <?php if (!$procurementServiceAvailable): ?>
        <div class="error-message" role="alert">
            Procurement service is temporarily unavailable. Other PCMS modules remain available.
        </div>
        <br>
        <?php elseif (isset($errorMessages[$errorKey])): ?>
        <div class="error-message" role="alert">
            <?= htmlspecialchars($errorMessages[$errorKey]) ?>
        </div>
        <br>
        <?php endif; ?>

        <form method="GET" action="index.php" class="search-toolbar" id="procurementFilterForm">
            <input
                type="search"
                id="searchInput"
                name="search"
                value="<?= htmlspecialchars($procurementFilters['search']) ?>"
                placeholder="Search by Procurement ID, Item, Supplier..."
            >

            <select id="statusFilter" name="status">
                <option value="">All Status</option>
                <?php foreach (['Pending', 'Approved', 'Rejected', 'Delivered'] as $status): ?>
                <option value="<?= htmlspecialchars($status) ?>" <?= $procurementFilters['status'] === $status ? 'selected' : '' ?>>
                    <?= htmlspecialchars($status) ?>
                </option>
                <?php endforeach; ?>
            </select>

            <select id="supplierFilter" name="supplier">
                <option value="">All Suppliers</option>
                <?php foreach (($procurementPage['filters']['suppliers'] ?? []) as $supplier): ?>
                <option value="<?= htmlspecialchars($supplier) ?>" <?= $procurementFilters['supplier'] === $supplier ? 'selected' : '' ?>>
                    <?= htmlspecialchars($supplier) ?>
                </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn btn-outline">Search</button>
            <a href="index.php" class="btn btn-outline">Clear Filters</a>
            <button
                type="button"
                id="openProcurementModal"
                class="btn btn-primary"
                <?= !$procurementServiceAvailable ? 'disabled' : '' ?>
            >
                Add Procurement
            </button>
        </form>

        <br>

        <?php include __DIR__ . '/procurement_table.php'; ?>
        <?php include __DIR__ . '/procurement_modals.php'; ?>
    </div>
</div>

<script src="<?= BASE_URL ?>assets/js/procurement.js"></script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
