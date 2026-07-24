<?php

require_once "../../auth/check_auth.php";


include "../../includes/header.php";

$id = $_GET['id'] ?? null;

$inventory = $_SESSION['inventory'][$id] ?? null;

?>

<div class="layout">

<?php include "../../includes/sidebar.php"; ?>

<div class="main-content">

<h1>View Inventory</h1>

<hr>
<br>
<?php if ($inventory): ?>

<table class="asset-table">


<tr>
    <th style="width:220px;">Inventory ID</th>
    <td><?= htmlspecialchars($inventory['inventory_id']) ?></td>
</tr>

<tr>
    <th>Asset Name</th>
    <td><?= htmlspecialchars($inventory['asset_name']) ?></td>
</tr>

<tr>
    <th>Category</th>
    <td><?= htmlspecialchars($inventory['category']) ?></td>
</tr>

<tr>
    <th>Quantity</th>
    <td><?= htmlspecialchars($inventory['quantity']) ?></td>
</tr>

<tr>
    <th>Condition</th>
    <td><?= htmlspecialchars($inventory['condition']) ?></td>
</tr>

</table>

<br>

<a href="index.php" class="btn btn-primary">

Back to Inventory

</a>

<?php else: ?>

<p>Inventory record not found.</p>

<a href="index.php" class="btn btn-primary">

Back

</a>

<?php endif; ?>

</div>

</div>

<?php include "../../includes/footer.php"; ?>