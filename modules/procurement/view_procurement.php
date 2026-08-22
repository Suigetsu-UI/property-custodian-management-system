<?php

require_once "../../auth/check_auth.php";
require_once __DIR__ . "/../../includes/database.php";

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

$pdo = getDbConnection();
$procurement = null;

if ($id !== null) {
    $stmt = $pdo->prepare(
        "SELECT *
         FROM procurement
         WHERE id = :id"
    );

    $stmt->execute([
        'id' => $id,
    ]);

    $procurement = $stmt->fetch() ?: null;
}

include "../../includes/header.php";

?>

<div class="layout">

<?php include "../../includes/sidebar.php"; ?>

<div class="main-content">

<h1>View Procurement</h1>

<hr>

<?php if ($procurement): ?>

<table class="asset-table">

<tr>
    <th style="width:220px;">Procurement ID</th>
    <td><?= htmlspecialchars($procurement['procurement_id']) ?></td>
</tr>

<tr>
    <th>Item Name</th>
    <td><?= htmlspecialchars($procurement['item_name']) ?></td>
</tr>

<tr>
    <th>Category</th>
    <td><?= htmlspecialchars($procurement['category']) ?></td>
</tr>

<tr>
    <th>Quantity</th>
    <td><?= htmlspecialchars((string) $procurement['quantity']) ?></td>
</tr>

<tr>
    <th>Supplier</th>
    <td><?= htmlspecialchars($procurement['supplier']) ?></td>
</tr>

<tr>
    <th>Requested By</th>
    <td><?= htmlspecialchars($procurement['requested_by'] ?? '') ?></td>
</tr>

<tr>
    <th>Request Date</th>
    <td><?= htmlspecialchars($procurement['request_date'] ?? '') ?></td>
</tr>

<tr>
    <th>Status</th>
    <td><?= htmlspecialchars($procurement['status']) ?></td>
</tr>

<tr>
    <th>Approved By</th>
    <td><?= !empty($procurement['approved_by']) ? htmlspecialchars($procurement['approved_by']) : 'Not Yet Approved' ?></td>
</tr>

<tr>
    <th>Approval Date</th>
    <td><?= !empty($procurement['approval_date']) ? htmlspecialchars($procurement['approval_date']) : 'Not Yet Approved' ?></td>
</tr>

<tr>
    <th>Delivery Date</th>
    <td><?= !empty($procurement['delivery_date']) ? htmlspecialchars($procurement['delivery_date']) : 'Not Yet Delivered' ?></td>
</tr>

<tr>
    <th>Delivered Quantity</th>
    <td><?= htmlspecialchars((string) ($procurement['delivered_quantity'] ?? 0)) ?></td>
</tr>

<tr>
    <th>Remarks</th>
    <td><?= htmlspecialchars($procurement['remarks'] ?? '') ?></td>
</tr>

</table>

<br>

<a href="index.php" class="btn btn-primary">
Back to Procurement
</a>

<?php else: ?>

<p>Procurement record not found.</p>

<a href="index.php" class="btn btn-primary">
Back
</a>

<?php endif; ?>

</div>

</div>

<?php include "../../includes/footer.php"; ?>
