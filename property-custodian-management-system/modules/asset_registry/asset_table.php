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

<?php

$assets = $_SESSION['assets'] ?? [];

?>

<?php foreach($assets as $index => $asset): ?>

<?php $status = $asset['status'] ?? 'Available'; ?>

<tr class="asset-row">

<td><?= $asset['asset_id']; ?></td>

<td><?= htmlspecialchars($asset['asset_name']); ?></td>

<td class="asset-category"><?= htmlspecialchars($asset['category']); ?></td>

<td><?= !empty($asset['custodian'])
    ? htmlspecialchars($asset['custodian'])
    : 'Not Assigned'; ?></td>

<td class="asset-status"><?= htmlspecialchars($status); ?></td>

<td>

<a href="view_asset.php?id=<?= $index ?>" class="btn btn-primary">
    View
</a>

<a href="edit_asset.php?id=<?= $index ?>" class="btn btn-warning">
    Edit
</a>

<?php if ($status === 'Under Maintenance'): ?>

<span>Locked — Under Maintenance</span>

<?php elseif ($status === 'Assigned'): ?>

<a href="return_asset.php?id=<?= $index ?>"
   class="btn btn-warning"
   onclick="return confirm('Return this asset? It will become Available again.');">
    Return Asset
</a>

<a href="delete_asset.php?id=<?= $index ?>"
   class="btn btn-danger"
   onclick="return confirm('Are you sure you want to delete this asset?');">
    Delete
</a>

<?php else: ?>

<a href="assign_custodian.php?id=<?= $index ?>" class="btn btn-success">
    Assign
</a>

<a href="delete_asset.php?id=<?= $index ?>"
   class="btn btn-danger"
   onclick="return confirm('Are you sure you want to delete this asset?');">
    Delete
</a>

<?php endif; ?>

</td>

</tr>




<?php endforeach; ?>





<?php if (empty($assets)): ?>

<tr>

<td colspan="6" style="text-align:center;padding:40px;">

No asset records found.

</td>

</tr>

<?php endif; ?>

</tbody>

</table>