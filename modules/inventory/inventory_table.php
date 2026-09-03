<?php

require_once __DIR__ . '/../../auth/check_auth.php';

$inventoryRows = is_array($inventoryPage['records'] ?? null)
    ? $inventoryPage['records']
    : [];
$pagination = is_array($inventoryPage['pagination'] ?? null)
    ? $inventoryPage['pagination']
    : [];
$currentPage = max(1, (int) ($pagination['page'] ?? 1));
$totalPages = max(1, (int) ($pagination['total_pages'] ?? 1));
$perPage = max(1, (int) ($pagination['per_page'] ?? 25));
$totalRecords = max(0, (int) ($pagination['total'] ?? 0));
$firstRecord = $totalRecords === 0 ? 0 : (($currentPage - 1) * $perPage) + 1;
$lastRecord = min($totalRecords, $currentPage * $perPage);

$pageUrl = static function (int $page) use ($inventoryFilters): string {
    $query = $inventoryFilters;
    $query['page'] = $page;
    unset($query['per_page']);
    $query = array_filter(
        $query,
        static fn (mixed $value): bool => $value !== '' && $value !== null
    );
    return 'index.php?' . http_build_query($query);
};
$pageStart = max(1, $currentPage - 2);
$pageEnd = min($totalPages, $currentPage + 2);
?>

<div class="pcms-results-summary">
    Showing <?= $firstRecord ?>–<?= $lastRecord ?> of <?= $totalRecords ?> Inventory records
</div>

<div class="pcms-table-scroll" role="region" aria-label="Inventory records" tabindex="0">
<table class="asset-table" id="inventoryTable">
<thead>
<tr>
    <th>Inventory ID</th>
    <th>Asset Name</th>
    <th>Category</th>
    <th>Quantity</th>
    <th>Condition</th>
    <th>Actions</th>
</tr>
</thead>
<tbody>

<?php foreach ($inventoryRows as $item): ?>
<tr class="inventory-row" data-inventory-id="<?= htmlspecialchars($item['inventory_id']) ?>">
    <td><?= htmlspecialchars($item['inventory_id']) ?></td>
    <td><?= htmlspecialchars($item['asset_name']) ?></td>
    <td><?= htmlspecialchars($item['category']) ?></td>
    <td><?= (int) $item['quantity'] ?></td>
    <td><?= htmlspecialchars($item['condition']) ?></td>
    <td>
        <a href="view_inventory.php?inventory_id=<?= rawurlencode($item['inventory_id']) ?>" class="btn btn-primary" data-inventory-action="view">View</a>
        <a href="edit_inventory.php?inventory_id=<?= rawurlencode($item['inventory_id']) ?>" class="btn btn-warning" data-inventory-action="edit">Edit</a>
        <form method="POST" action="delete_inventory.php" class="pcms-inline-action" onsubmit="return confirm('Delete this inventory?');">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getAccessCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="inventory_id" value="<?= htmlspecialchars($item['inventory_id']) ?>">
            <button type="submit" class="btn btn-danger">Delete</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>

<?php if ($inventoryRows === []): ?>
<tr>
    <td colspan="6" style="text-align:center;padding:40px;">
        <?= $inventoryServiceAvailable
            ? 'No inventory records found.'
            : 'Inventory records are temporarily unavailable.' ?>
    </td>
</tr>
<?php endif; ?>

</tbody>
</table>
</div>

<?php if ($totalPages > 1): ?>
<nav class="pcms-pagination" aria-label="Inventory pagination">
    <?php if ($currentPage > 1): ?>
    <a class="btn btn-outline" href="<?= htmlspecialchars($pageUrl($currentPage - 1)) ?>">Previous</a>
    <?php endif; ?>
    <?php for ($page = $pageStart; $page <= $pageEnd; $page++): ?>
        <?php if ($page === $currentPage): ?>
        <span aria-current="page">Page <?= $page ?></span>
        <?php else: ?>
        <a class="btn btn-outline" href="<?= htmlspecialchars($pageUrl($page)) ?>"><?= $page ?></a>
        <?php endif; ?>
    <?php endfor; ?>
    <?php if ($currentPage < $totalPages): ?>
    <a class="btn btn-outline" href="<?= htmlspecialchars($pageUrl($currentPage + 1)) ?>">Next</a>
    <?php endif; ?>
</nav>
<?php endif; ?>
