<?php

require_once __DIR__ . "/../../includes/database.php";

/*
|--------------------------------------------------------------------------
| Procurement list
|--------------------------------------------------------------------------
| Procurement business data now comes directly from PostgreSQL.
|
| Use a dedicated list variable so it cannot collide with $procurement,
| which is reserved for the single-record Edit/View context.
*/
$pdo = getDbConnection();

$procurementRows = $pdo
    ->query("SELECT * FROM procurement ORDER BY id ASC")
    ->fetchAll();

?>

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
    json_encode($item, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE),
    ENT_QUOTES,
    'UTF-8'
);
?>

<tr class="procurement-row" data-record="<?= $procurementPayload ?>">

<td><?= htmlspecialchars($item['procurement_id']); ?></td>

<td><?= htmlspecialchars($item['item_name']); ?></td>

<td><?= htmlspecialchars($item['category']); ?></td>

<td><?= htmlspecialchars((string) $item['quantity']); ?></td>

<td><?= htmlspecialchars($item['supplier']); ?></td>

<td><?= htmlspecialchars($item['status']); ?></td>

<td>

<a href="view_procurement.php?id=<?= (int) $item['id'] ?>" class="btn btn-primary" data-procurement-action="view">
View
</a>

<a href="edit_procurement.php?id=<?= (int) $item['id'] ?>" class="btn btn-warning" data-procurement-action="edit">
Edit
</a>

<a href="delete_procurement.php?id=<?= (int) $item['id'] ?>"
class="btn btn-danger"
onclick="return confirm('Delete this procurement record?');">
Delete
</a>

</td>

</tr>

<?php endforeach; ?>

<?php if (empty($procurementRows)): ?>

<tr>

<td colspan="7" style="text-align:center;padding:40px;">

No procurement records found.

</td>

</tr>

<?php endif; ?>

</tbody>

</table>
