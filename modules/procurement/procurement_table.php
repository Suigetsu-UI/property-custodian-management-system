<table class="asset-table">

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

<?php

$procurement = $_SESSION['procurement'] ?? [];

foreach ($procurement as $index => $item):

?>

<tr>

<td><?= htmlspecialchars($item['procurement_id']); ?></td>

<td><?= htmlspecialchars($item['item_name']); ?></td>

<td><?= htmlspecialchars($item['category']); ?></td>

<td><?= htmlspecialchars($item['quantity']); ?></td>

<td><?= htmlspecialchars($item['supplier']); ?></td>

<td><?= htmlspecialchars($item['status']); ?></td>

<td>

<a href="view_procurement.php?id=<?= $index ?>" class="btn btn-primary">
View
</a>

<a href="edit_procurement.php?id=<?= $index ?>" class="btn btn-warning">
Edit
</a>

<a href="delete_procurement.php?id=<?= $index ?>"
class="btn btn-danger"
onclick="return confirm('Delete this procurement record?');">
Delete
</a>

</td>

</tr>

<?php endforeach; ?>

<?php if (empty($procurement)): ?>

<tr>

<td colspan="7" style="text-align:center;padding:40px;">

No procurement records found.

</td>

</tr>

<?php endif; ?>

</tbody>

</table>