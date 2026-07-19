<table class="asset-table">

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

<?php


$inventory = $_SESSION['filtered_inventory']
    ?? $_SESSION['inventory']
    ?? [];

unset($_SESSION['filtered_inventory']);


foreach ($inventory as $index => $item):

?>

<tr>

<td><?= $item['inventory_id']; ?></td>

<td><?= htmlspecialchars($item['asset_name']); ?></td>

<td><?= htmlspecialchars($item['category']); ?></td>

<td><?= $item['quantity']; ?></td>

<td><?= htmlspecialchars($item['condition']); ?></td>

<td>

<a href="view_inventory.php?id=<?= $index ?>" class="btn btn-primary">
    View
</a>

<a href="edit_inventory.php?id=<?= $index ?>" class="btn btn-warning">
    Edit
</a>

<a href="delete_inventory.php?id=<?= $index ?>"
   class="btn btn-danger"
   onclick="return confirm('Delete this inventory?');">
    Delete
</a>

</td>

</tr>

<?php endforeach; ?>

<?php if (empty($inventory)): ?>

<tr>

<td colspan="6" style="text-align:center;padding:40px;">

No inventory records found.

</td>

</tr>

<?php endif; ?>

</tbody>

</table>