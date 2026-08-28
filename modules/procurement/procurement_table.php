<?php

require_once __DIR__ . '/../../auth/check_auth.php';

$procurementRows = is_array($procurementPage['records'] ?? null)
    ? $procurementPage['records']
    : [];
$pagination = is_array($procurementPage['pagination'] ?? null)
    ? $procurementPage['pagination']
    : [];
$currentPage = max(1, (int) ($pagination['page'] ?? 1));
$totalPages = max(1, (int) ($pagination['total_pages'] ?? 1));
$perPage = max(1, (int) ($pagination['per_page'] ?? 25));
$totalRecords = max(0, (int) ($pagination['total'] ?? 0));
$firstRecord = $totalRecords === 0 ? 0 : (($currentPage - 1) * $perPage) + 1;
$lastRecord = min($totalRecords, $currentPage * $perPage);

$pageUrl = static function (int $page) use ($procurementFilters): string {
    $query = $procurementFilters;
    $query['page'] = $page;
    unset($query['per_page']);
    $query = array_filter(
        $query,
        static fn (mixed $value): bool => $value !== '' && $value !== null
    );
    return 'index.php?' . http_build_query($query);
};
?>

<div class="pcms-results-summary">
    Showing <?= $firstRecord ?>–<?= $lastRecord ?> of <?= $totalRecords ?> Procurement records
</div>

<table class="asset-table" id="procurementTable">
<thead>
<tr>
    <th>Procurement ID</th>
    <th>Item Name</th>
    <th>Category</th>
    <th>Quantity</th>
    <th>Supplier</th>
    <th>Status</th>
    <th>Actions</th>
</tr>
</thead>
<tbody>

<?php foreach ($procurementRows as $item): ?>
<?php
$procurementPayload = htmlspecialchars(
    json_encode(
        $item,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS |
        JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE
    ),
    ENT_QUOTES,
    'UTF-8'
);
?>
<tr class="procurement-row" data-record="<?= $procurementPayload ?>">
    <td><?= htmlspecialchars($item['procurement_id']) ?></td>
    <td><?= htmlspecialchars($item['item_name']) ?></td>
    <td><?= htmlspecialchars($item['category']) ?></td>
    <td><?= (int) $item['quantity'] ?></td>
    <td><?= htmlspecialchars($item['supplier']) ?></td>
    <td><?= htmlspecialchars($item['status']) ?></td>
    <td>
        <a href="view_procurement.php?id=<?= rawurlencode($item['procurement_id']) ?>" class="btn btn-primary" data-procurement-action="view">View</a>
        <a href="edit_procurement.php?id=<?= rawurlencode($item['procurement_id']) ?>" class="btn btn-warning" data-procurement-action="edit">Edit</a>
        <form method="POST" action="delete_procurement.php" class="pcms-inline-action" onsubmit="return confirm('Delete this procurement record?');">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getAccessCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="procurement_id" value="<?= htmlspecialchars($item['procurement_id']) ?>">
            <button type="submit" class="btn btn-danger">Delete</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>

<?php if ($procurementRows === []): ?>
<tr>
    <td colspan="7" style="text-align:center;padding:40px;">
        <?= $procurementServiceAvailable
            ? 'No procurement records found.'
            : 'Procurement records are temporarily unavailable.' ?>
    </td>
</tr>
<?php endif; ?>

</tbody>
</table>

<?php if ($totalPages > 1): ?>
<nav class="pcms-pagination" aria-label="Procurement pagination">
    <?php if ($currentPage > 1): ?>
    <a class="btn btn-outline" href="<?= htmlspecialchars($pageUrl($currentPage - 1)) ?>">Previous</a>
    <?php endif; ?>
    <span>Page <?= $currentPage ?> of <?= $totalPages ?></span>
    <?php if ($currentPage < $totalPages): ?>
    <a class="btn btn-outline" href="<?= htmlspecialchars($pageUrl($currentPage + 1)) ?>">Next</a>
    <?php endif; ?>
</nav>
<?php endif; ?>
