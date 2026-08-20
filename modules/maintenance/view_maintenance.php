<?php

require_once "../../auth/check_auth.php";
require_once __DIR__ . "/../../includes/database.php";

$id = filter_var(
    $_GET['id'] ?? null,
    FILTER_VALIDATE_INT,
    [
        'options' => [
            'min_range' => 1
        ]
    ]
);

$maintenance = null;

if ($id !== false) {
    $pdo = getDbConnection();

    $stmt = $pdo->prepare(
        "SELECT
            m.*,
            a.asset_id AS asset_business_id,
            a.asset_name AS current_asset_name,
            a.custodian AS current_custodian
         FROM maintenance m
         JOIN assets a
           ON a.id = m.asset_id
         WHERE m.id = :id"
    );

    $stmt->execute([
        'id' => $id
    ]);

    $maintenance =
        $stmt->fetch() ?: null;
}

include "../../includes/header.php";

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
<td>
<?= htmlspecialchars($maintenance['maintenance_id']) ?>
</td>
</tr>

<tr>
<th>Asset ID</th>
<td>
<?= htmlspecialchars($maintenance['asset_business_id']) ?>
</td>
</tr>

<tr>
<th>Asset Name</th>
<td>
<?= htmlspecialchars($maintenance['current_asset_name']) ?>
</td>
</tr>

<tr>
<th>Maintenance Type</th>
<td>
<?= htmlspecialchars($maintenance['maintenance_type']) ?>
</td>
</tr>

<tr>
<th>Scheduled Date</th>
<td>
<?= htmlspecialchars($maintenance['scheduled_date'] ?? '') ?>
</td>
</tr>

<tr>
<th>Status</th>
<td>
<?= htmlspecialchars($maintenance['status']) ?>
</td>
</tr>

<tr>
<th>Custodian</th>
<td>
<?= !empty($maintenance['current_custodian'])
    ? htmlspecialchars($maintenance['current_custodian'])
    : 'Not Assigned' ?>
</td>
</tr>

</table>

<br>

<a
    href="index.php"
    class="btn btn-primary"
>
Back to Maintenance
</a>

<?php else: ?>

<p>Maintenance record not found.</p>

<a
    href="index.php"
    class="btn btn-primary"
>
Back
</a>

<?php endif; ?>

</div>

</div>

<?php include "../../includes/footer.php"; ?>