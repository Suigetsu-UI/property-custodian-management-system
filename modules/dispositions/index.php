<?php

require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../includes/property_core_gateway.php';

$filters = [
    'search' => substr(trim((string) ($_GET['search'] ?? '')), 0, 200),
    'status' => trim((string) ($_GET['status'] ?? '')),
    'page' => max(1, (int) ($_GET['page'] ?? 1)),
    'per_page' => 25,
];
$result = [
    'records' => [],
    'pagination' => ['page' => 1, 'total_pages' => 1, 'total' => 0],
];
$serviceAvailable = true;

try {
    $result = getPropertyCoreServiceClient()->listDispositions($filters);
} catch (Throwable $error) {
    $serviceAvailable = false;
}

$records = is_array($result['records'] ?? null) ? $result['records'] : [];
$pagination = is_array($result['pagination'] ?? null) ? $result['pagination'] : [];
$currentPage = max(1, (int) ($pagination['page'] ?? 1));
$totalPages = max(1, (int) ($pagination['total_pages'] ?? 1));
$total = max(0, (int) ($pagination['total'] ?? 0));
$statuses = [
    'Pending Institutional Approval',
    'Approved for Sale/Bidding',
    'Sold',
    'Rejected',
    'Cancelled',
];
$pageUrl = static function (int $page) use ($filters): string {
    $query = array_filter(
        array_merge($filters, ['page' => $page]),
        static fn (mixed $value): bool => $value !== '' && $value !== null
    );
    unset($query['per_page']);
    return 'index.php?' . http_build_query($query);
};

include __DIR__ . '/../../includes/header.php';
?>

<div class="layout">
<?php include __DIR__ . '/../../includes/sidebar.php'; ?>
<main class="main-content disposition-page">
    <header class="disposition-heading">
        <div>
            <p class="section-heading">Asset Lifecycle Accountability</p>
            <h1>Disposition Reviews</h1>
            <p>Track externally authorized sale or bidding reviews without granting institutional approval inside Smart AssetTrack.</p>
        </div>
        <a class="btn btn-outline" href="<?= BASE_URL ?>modules/asset_registry/index.php?sort=highest_usage">Find Aging Assets</a>
    </header>

    <?php if (!isPropertyCustodian()): ?>
    <div class="pcms-form-notice" role="note">
        System Administrators have read-only access here. Disposition transactions are performed by the Property Custodian after external institutional approval.
    </div>
    <?php endif; ?>

    <?php if (!$serviceAvailable): ?>
    <div class="error-message" role="alert">Property Core service is temporarily unavailable. Disposition records cannot be loaded.</div>
    <?php endif; ?>

    <form method="GET" class="search-toolbar">
        <input type="search" name="search" maxlength="200" value="<?= htmlspecialchars($filters['search']) ?>" placeholder="Search disposition, Asset, or approval reference">
        <select name="status" aria-label="Filter disposition status">
            <option value="">All Statuses</option>
            <?php foreach ($statuses as $status): ?>
            <option value="<?= htmlspecialchars($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-outline" type="submit">Search</button>
        <a class="btn btn-outline" href="index.php">Clear Filters</a>
    </form>

    <p class="pcms-results-summary">Showing <?= $total ?> disposition record<?= $total === 1 ? '' : 's' ?></p>
    <div class="pcms-table-scroll" role="region" aria-label="Asset disposition records" tabindex="0">
    <table class="asset-table">
        <thead><tr><th>Disposition</th><th>Asset</th><th>Method</th><th>Status</th><th>Requested</th><th>Approval Reference</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($records as $record): ?>
        <tr>
            <td><?= htmlspecialchars((string) ($record['disposition_id'] ?? '')) ?></td>
            <td><strong><?= htmlspecialchars((string) ($record['asset_business_id'] ?? '')) ?></strong><br><small><?= htmlspecialchars((string) ($record['asset_name'] ?? '')) ?></small></td>
            <td><?= htmlspecialchars((string) ($record['proposed_method'] ?? '')) ?></td>
            <td><span class="pcms-status-badge" data-status="<?= htmlspecialchars(strtolower((string) ($record['status'] ?? ''))) ?>"><?= htmlspecialchars((string) ($record['status'] ?? '')) ?></span></td>
            <td><?= htmlspecialchars((string) ($record['requested_at'] ?? '')) ?><br><small><?= htmlspecialchars((string) ($record['requested_by'] ?? '')) ?></small></td>
            <td><?= htmlspecialchars((string) ($record['institutional_approval_reference'] ?? '—')) ?></td>
            <td><a class="btn btn-primary" href="review.php?asset_id=<?= rawurlencode((string) ($record['asset_business_id'] ?? '')) ?>">View Review</a></td>
        </tr>
        <?php endforeach; ?>
        <?php if ($records === []): ?>
        <tr><td colspan="7" class="pcms-empty-cell"><?= $serviceAvailable ? 'No disposition records found.' : 'Disposition records are temporarily unavailable.' ?></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <nav class="pcms-pagination" aria-label="Disposition pagination">
        <?php if ($currentPage > 1): ?><a class="btn btn-outline" href="<?= htmlspecialchars($pageUrl($currentPage - 1)) ?>">Previous</a><?php endif; ?>
        <span>Page <?= $currentPage ?> of <?= $totalPages ?></span>
        <?php if ($currentPage < $totalPages): ?><a class="btn btn-outline" href="<?= htmlspecialchars($pageUrl($currentPage + 1)) ?>">Next</a><?php endif; ?>
    </nav>
    <?php endif; ?>
</main>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
