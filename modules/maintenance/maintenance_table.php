<?php

require_once __DIR__ . "/../../includes/database.php";

$pdo = getDbConnection();

$keyword =
    trim($_GET['search'] ?? '');

$statusParam =
    trim($_GET['status'] ?? '');

$sql =
    "SELECT
        m.id,
        m.maintenance_id,
        m.maintenance_type,
        m.scheduled_date,
        m.status,
        a.asset_name AS current_asset_name
     FROM maintenance m
     JOIN assets a
       ON a.id = m.asset_id
     WHERE 1=1";

$params = [];

if ($keyword !== '') {
    $searchValue =
        '%' . strtolower($keyword) . '%';

    $sql .=
        " AND (
            lower(m.maintenance_id)
                LIKE :kw_maintenance_id
            OR lower(a.asset_name)
                LIKE :kw_asset_name
            OR lower(m.maintenance_type)
                LIKE :kw_maintenance_type
          )";

    $params['kw_maintenance_id'] =
        $searchValue;

    $params['kw_asset_name'] =
        $searchValue;

    $params['kw_maintenance_type'] =
        $searchValue;
}

if ($statusParam !== '') {
    $sql .= " AND m.status = :status";

    $params['status'] =
        $statusParam;
}

$sql .= " ORDER BY m.id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$maintenanceRows =
    $stmt->fetchAll();

?>

<table
    class="asset-table"
    id="maintenanceTable"
>

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

<?php foreach ($maintenanceRows as $item): ?>

<tr class="maintenance-row">

<td>
<?= htmlspecialchars($item['maintenance_id']) ?>
</td>

<td>
<?= htmlspecialchars($item['current_asset_name']) ?>
</td>

<td>
<?= htmlspecialchars($item['maintenance_type']) ?>
</td>

<td>
<?= htmlspecialchars($item['scheduled_date'] ?? '') ?>
</td>

<td>
<?= htmlspecialchars($item['status']) ?>
</td>

<td>

<a
    href="view_maintenance.php?id=<?= (int) $item['id'] ?>"
    class="btn btn-primary"
>
View
</a>

<a
    href="edit_maintenance.php?id=<?= (int) $item['id'] ?>"
    class="btn btn-warning"
>
Edit
</a>

<a
    href="delete_maintenance.php?id=<?= (int) $item['id'] ?>"
    class="btn btn-danger"
    onclick="return confirm('Delete this maintenance record?');"
>
Delete
</a>

</td>

</tr>

<?php endforeach; ?>

<?php if (empty($maintenanceRows)): ?>

<tr>

<td
    colspan="6"
    style="text-align:center; padding:40px;"
>
No maintenance records found.
</td>

</tr>

<?php endif; ?>

</tbody>

</table>