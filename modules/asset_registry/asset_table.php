<?php

require_once __DIR__ . "/../../auth/check_auth.php";
require_once __DIR__ . "/../../includes/database.php";

$pdo = getDbConnection();

$stmt = $pdo->query(
    "SELECT
        id,
        asset_id,
        asset_name,
        category,
        brand,
        model,
        serial_number,
        acquisition_date,
        purchase_cost,
        supplier,
        location,
        remarks,
        employee_id,
        custodian,
        department,
        date_assigned,
        status
     FROM assets
     ORDER BY id ASC"
);

$assetRows = $stmt->fetchAll();

?>

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

<?php

$assetPayload = htmlspecialchars(
    json_encode(
        $asset,
        JSON_HEX_TAG |
        JSON_HEX_AMP |
        JSON_HEX_APOS |
        JSON_HEX_QUOT |
        JSON_INVALID_UTF8_SUBSTITUTE
    ),
    ENT_QUOTES,
    'UTF-8'
);

?>

<tr
    class="asset-row"
    data-asset="<?= $assetPayload ?>"
>

<td><?= htmlspecialchars($asset['asset_id']) ?></td>

<td><?= htmlspecialchars($asset['asset_name']) ?></td>

<td class="asset-category">
    <?= htmlspecialchars($asset['category']) ?>
</td>

<td>
    <?= !empty($asset['custodian'])
        ? htmlspecialchars($asset['custodian'])
        : 'Not Assigned' ?>
</td>

<td class="asset-status">
    <?= htmlspecialchars($status) ?>
</td>

<td>

<a
    href="view_asset.php?id=<?= (int) $asset['id'] ?>"
    class="btn btn-primary"
    data-asset-action="view"
>
    View
</a>

<a
    href="edit_asset.php?id=<?= (int) $asset['id'] ?>"
    class="btn btn-warning"
    data-asset-action="edit"
>
    Edit
</a>

<?php if ($status === 'Under Maintenance'): ?>

<span>Locked — Under Maintenance</span>

<?php elseif ($status === 'Lost'): ?>

<span>Locked — Marked Lost (Pending Audit Resolution)</span>

<?php elseif ($status === 'Assigned'): ?>

<form method="POST" action="return_asset.php" class="pcms-inline-action" onsubmit="return confirm('Return this asset? It will become Available again.');">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getAccessCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="id" value="<?= (int) $asset['id'] ?>">
    <button type="submit" class="btn btn-warning">Return Asset</button>
</form>

<form method="POST" action="delete_asset.php" class="pcms-inline-action" onsubmit="return confirm('Are you sure you want to delete this asset?');">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getAccessCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="id" value="<?= (int) $asset['id'] ?>">
    <button type="submit" class="btn btn-danger">Delete</button>
</form>

<?php else: ?>

<a
    href="assign_custodian.php?id=<?= (int) $asset['id'] ?>"
    class="btn btn-success"
    data-asset-action="assign"
>
    Assign
</a>

<form method="POST" action="delete_asset.php" class="pcms-inline-action" onsubmit="return confirm('Are you sure you want to delete this asset?');">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getAccessCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="id" value="<?= (int) $asset['id'] ?>">
    <button type="submit" class="btn btn-danger">Delete</button>
</form>

<?php endif; ?>

</td>

</tr>

<?php endforeach; ?>

<?php if (!empty($assetRows)): ?>

<tr id="assetFilterEmptyState" hidden>

<td colspan="6" style="text-align:center;padding:40px;">
    No assets match the current search and filters.
</td>

</tr>

<?php endif; ?>

<?php if (empty($assetRows)): ?>

<tr>

<td colspan="6" style="text-align:center;padding:40px;">
    No asset records found.
</td>

</tr>

<?php endif; ?>

</tbody>

</table>
