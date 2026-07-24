<table class="asset-table" id="maintenanceTable">

<thead>

<tr>

<th>Maintenance ID</th>

<th>Asset Name</th>

<th>Maintenance Type</th>

<th>Scheduled Date</th>

<th>Status</th>

<th>Actions</th>

</tr>

</thead>

<tbody>

<?php

$maintenance = $_SESSION['filtered_maintenance']
    ?? $_SESSION['maintenance']
    ?? [];

unset($_SESSION['filtered_maintenance']);

foreach ($maintenance as $index => $item):

?>

<tr class="maintenance-row">

<td><?= htmlspecialchars($item['maintenance_id']); ?></td>

<td><?= htmlspecialchars($item['asset_name']); ?></td>

<td><?= htmlspecialchars($item['maintenance_type']); ?></td>

<td><?= htmlspecialchars($item['scheduled_date']); ?></td>

<td><?= htmlspecialchars($item['status']); ?></td>

<td>

<a href="view_maintenance.php?id=<?= $index ?>" class="btn btn-primary">
View
</a>

<a href="edit_maintenance.php?id=<?= $index ?>" class="btn btn-warning">
Edit
</a>

<a href="delete_maintenance.php?id=<?= $index ?>"
class="btn btn-danger"
onclick="return confirm('Delete this maintenance record?');">
Delete
</a>

</td>

</tr>

<?php endforeach; ?>

<?php if (empty($maintenance)): ?>

<tr>

<td colspan="6" style="text-align:center; padding:40px;">

No maintenance records found.

</td>

</tr>

<?php endif; ?>

</tbody>

</table>