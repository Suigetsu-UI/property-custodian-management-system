<?php

require_once __DIR__ . "/../../auth/check_auth.php";
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
        m.asset_id,
        m.asset_name_snap,
        m.category_snap,
        m.custodian_snap,
        m.maintenance_type,
        m.scheduled_date,
        m.status,
        a.asset_id AS asset_business_id,
        a.asset_name AS current_asset_name,
        a.category AS current_category,
        a.custodian AS current_custodian,
        a.status AS current_asset_status
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

<div class="pcms-table-scroll pcms-card-table-shell" role="region" aria-label="Maintenance records" tabindex="0">
<table
    class="asset-table pcms-mobile-card-table"
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

<?php
$maintenancePayload = htmlspecialchars(
    json_encode($item, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE),
    ENT_QUOTES,
    'UTF-8'
);
?>

<tr class="maintenance-row" data-record="<?= $maintenancePayload ?>">

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
    data-maintenance-action="view"
>
View
</a>

<a
    href="edit_maintenance.php?id=<?= (int) $item['id'] ?>"
    class="btn btn-warning"
    data-maintenance-action="edit"
>
Edit
</a>

<form method="POST" action="delete_maintenance.php" class="pcms-inline-action" onsubmit="return confirm('Delete this maintenance record?');">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getAccessCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
    <button type="submit" class="btn btn-danger">Delete</button>
</form>

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
</div>
