<?php

require_once __DIR__ . '/../../auth/check_auth.php';

$assetRows = is_array($assetPage['records'] ?? null)
    ? $assetPage['records']
    : [];
$pagination = is_array($assetPage['pagination'] ?? null)
    ? $assetPage['pagination']
    : [];
$currentPage = max(1, (int) ($pagination['page'] ?? 1));
$totalPages = max(1, (int) ($pagination['total_pages'] ?? 1));
$perPage = max(1, (int) ($pagination['per_page'] ?? 25));
$totalRecords = max(0, (int) ($pagination['total'] ?? 0));
$firstRecord = $totalRecords === 0 ? 0 : (($currentPage - 1) * $perPage) + 1;
$lastRecord = min($totalRecords, $currentPage * $perPage);
$pageStart = max(1, $currentPage - 2);
$pageEnd = min($totalPages, $currentPage + 2);

$pageUrl = static function (int $page) use ($assetFilters): string {
    $query = $assetFilters;
    $query['page'] = $page;
    unset($query['per_page']);
    $query = array_filter(
        $query,
        static fn (mixed $value): bool => $value !== '' && $value !== null
    );
    return 'index.php?' . http_build_query($query);
};
?>

<div class="pcms-results-summary" id="assetResultCount" aria-live="polite">
    Showing <?= $firstRecord ?>–<?= $lastRecord ?> of <?= $totalRecords ?> Assets
</div>

<div class="pcms-table-scroll" role="region" aria-label="Asset Registry records" tabindex="0">
<table class="asset-table" id="assetTable">
<thead>
<tr>
    <th>Asset ID</th>
    <th>Asset Name</th>
    <th>Category</th>
    <th>Custodian</th>
    <th>Status</th>
    <th>Actions</th>
</tr>
</thead>
<tbody>

<?php foreach ($assetRows as $asset): ?>
<?php $status = $asset['status'] ?? 'Available'; ?>
<tr class="asset-row" data-asset-id="<?= htmlspecialchars($asset['asset_id']) ?>">
    <td><?= htmlspecialchars($asset['asset_id']) ?></td>
    <td><?= htmlspecialchars($asset['asset_name']) ?></td>
    <td class="asset-category"><?= htmlspecialchars($asset['category']) ?></td>
    <td><?= !empty($asset['custodian'])
        ? htmlspecialchars($asset['custodian'])
        : 'Not Assigned' ?></td>
    <td class="asset-status"><?= htmlspecialchars($status) ?></td>
    <td>
        <a href="view_asset.php?asset_id=<?= rawurlencode($asset['asset_id']) ?>" class="btn btn-primary" data-asset-action="view">View</a>
        <a href="edit_asset.php?asset_id=<?= rawurlencode($asset['asset_id']) ?>" class="btn btn-warning" data-asset-action="edit">Edit</a>

        <?php if ($status === 'Under Maintenance'): ?>
        <span>Locked — Under Maintenance</span>
        <?php elseif ($status === 'Lost'): ?>
        <span>Locked — Marked Lost (Pending Audit Resolution)</span>
        <?php elseif ($status === 'Assigned'): ?>
        <form method="POST" action="return_asset.php" class="pcms-inline-action" onsubmit="return confirm('Return this Asset? It will become Available again.');">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getAccessCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="asset_id" value="<?= htmlspecialchars($asset['asset_id']) ?>">
            <button type="submit" class="btn btn-warning">Return Asset</button>
        </form>
        <form method="POST" action="delete_asset.php" class="pcms-inline-action" onsubmit="return confirm('Are you sure you want to delete this Asset?');">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getAccessCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="asset_id" value="<?= htmlspecialchars($asset['asset_id']) ?>">
            <button type="submit" class="btn btn-danger">Delete</button>
        </form>
        <?php else: ?>
        <a href="assign_custodian.php?asset_id=<?= rawurlencode($asset['asset_id']) ?>" class="btn btn-success" data-asset-action="assign">Assign</a>
        <form method="POST" action="delete_asset.php" class="pcms-inline-action" onsubmit="return confirm('Are you sure you want to delete this Asset?');">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getAccessCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="asset_id" value="<?= htmlspecialchars($asset['asset_id']) ?>">
            <button type="submit" class="btn btn-danger">Delete</button>
        </form>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>

<?php if ($assetRows === []): ?>
<tr>
    <td colspan="6" style="text-align:center;padding:40px;">
        <?= $assetServiceAvailable
            ? 'No Asset records found.'
            : 'Asset records are temporarily unavailable.' ?>
    </td>
</tr>
<?php endif; ?>

</tbody>
</table>
</div>

<?php if ($totalPages > 1): ?>
<nav class="pcms-pagination" aria-label="Asset Registry pagination">
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
