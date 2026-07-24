<?php

require_once "../../auth/check_auth.php";
include "../../includes/header.php";

$id = $_GET['id'] ?? null;

$maintenance = $_SESSION['maintenance'][$id] ?? null;

?>

<div class="layout">

<?php include "../../includes/sidebar.php"; ?>

<div class="main-content">

<h1>View Maintenance</h1>

<hr>

<?php if ($maintenance): ?>

<table class="asset-table">

<tr>
    <th style="width:220px;">Maintenance ID</th>
    <td><?= htmlspecialchars($maintenance['maintenance_id']) ?></td>
</tr>

<tr>
    <th>Asset ID</th>
    <td><?= htmlspecialchars($maintenance['asset_id'] ?? 'Not Linked') ?></td>
</tr>

<tr>
    <th>Asset Name</th>
    <td><?= htmlspecialchars($maintenance['asset_name']) ?></td>
</tr>

<tr>
    <th>Maintenance Type</th>
    <td><?= htmlspecialchars($maintenance['maintenance_type']) ?></td>
</tr>

<tr>
    <th>Scheduled Date</th>
    <td><?= htmlspecialchars($maintenance['scheduled_date']) ?></td>
</tr>

<tr>
    <th>Status</th>
    <td><?= htmlspecialchars($maintenance['status']) ?></td>
</tr>

<tr>
    <th>Custodian</th>
    <td><?= !empty($maintenance['custodian']) ? htmlspecialchars($maintenance['custodian']) : 'Not Assigned' ?></td>
</tr>

</table>

<br>

<a href="index.php" class="btn btn-primary">

Back to Maintenance

</a>

<?php else: ?>

<p>Maintenance record not found.</p>

<a href="index.php" class="btn btn-primary">

Back

</a>

<?php endif; ?>

</div>

</div>

<?php include "../../includes/footer.php"; ?>