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

<tr class="asset-row">

<td><?= $asset['asset_id']; ?></td>

<td><?= htmlspecialchars($asset['asset_name']); ?></td>

<td class="asset-category"><?= htmlspecialchars($asset['category']); ?></td>

<td><?= !empty($asset['custodian'])
    ? htmlspecialchars($asset['custodian'])
    : 'Not Assigned'; ?></td>

<td class="asset-status">Available</td>

<td>

<a href="view_asset.php?id=<?= $index ?>" class="btn btn-primary">
    View
</a>

<a href="edit_asset.php?id=<?= $index ?>" class="btn btn-warning">
    Edit
</a>

<a href="assign_custodian.php?id=<?= $index ?>" class="btn btn-success">
    Assign
</a>

<a href="delete_asset.php?id=<?= $index ?>"
   class="btn btn-danger"
   onclick="return confirm('Are you sure you want to delete this asset?');">
    Delete
</a>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>