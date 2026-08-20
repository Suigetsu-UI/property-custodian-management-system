<?php

require_once __DIR__ . "/../../includes/database.php";

$pdo = getDbConnection();

$keyword = trim($_GET['search'] ?? '');
$categoryParam = trim($_GET['category'] ?? '');
$conditionParam = trim($_GET['condition'] ?? '');

$sql = "SELECT * FROM inventory WHERE 1=1";
$params = [];

if ($keyword !== '') {

    $searchValue = '%' . strtolower($keyword) . '%';

    $sql .= " AND (
                lower(inventory_id) LIKE :kw_inventory_id
                OR lower(asset_name) LIKE :kw_asset_name
                OR lower(category) LIKE :kw_category
                OR lower(condition) LIKE :kw_condition
              )";

    $params['kw_inventory_id'] = $searchValue;
    $params['kw_asset_name'] = $searchValue;
    $params['kw_category'] = $searchValue;
    $params['kw_condition'] = $searchValue;
}

if ($categoryParam !== '') {
    $sql .= " AND category = :category";
    $params['category'] = $categoryParam;
}

if ($conditionParam !== '') {
    $sql .= " AND condition = :condition";
    $params['condition'] = $conditionParam;
}

$sql .= " ORDER BY id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$inventoryRows = $stmt->fetchAll();

?>

<table class="asset-table" id="inventoryTable">

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

<?php foreach ($inventoryRows as $item): ?>

<tr class="inventory-row">

<td><?= htmlspecialchars($item['inventory_id']) ?></td>
<td><?= htmlspecialchars($item['asset_name']) ?></td>
<td><?= htmlspecialchars($item['category']) ?></td>
<td><?= htmlspecialchars($item['quantity']) ?></td>
<td><?= htmlspecialchars($item['condition']) ?></td>

<td>

<a
    href="view_inventory.php?id=<?= (int) $item['id'] ?>"
    class="btn btn-primary"
>
    View
</a>

<a
    href="edit_inventory.php?id=<?= (int) $item['id'] ?>"
    class="btn btn-warning"
>
    Edit
</a>

<a
    href="delete_inventory.php?id=<?= (int) $item['id'] ?>"
    class="btn btn-danger"
    onclick="return confirm('Delete this inventory?');"
>
    Delete
</a>

</td>

</tr>

<?php endforeach; ?>

<?php if (empty($inventoryRows)): ?>

<tr>

<td colspan="6" style="text-align:center;padding:40px;">
    No inventory records found.
</td>

</tr>

<?php endif; ?>

</tbody>

</table>