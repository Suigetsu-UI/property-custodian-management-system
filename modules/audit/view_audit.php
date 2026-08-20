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

$audit = null;

if ($id !== false) {
    $pdo = getDbConnection();

    $stmt = $pdo->prepare(
        "SELECT
            au.*,
            a.asset_id AS asset_business_id
         FROM audits au
         JOIN assets a
           ON a.id = au.asset_id
         WHERE au.id = :id"
    );

    $stmt->execute([
        'id' => $id
    ]);

    $audit =
        $stmt->fetch() ?: null;
}

include "../../includes/header.php";

?>

<div class="layout">

<?php include "../../includes/sidebar.php"; ?>

<div class="main-content">

<h1>View Audit</h1>

<hr>

<?php if ($audit): ?>

<table class="asset-table">

<tr>
<th style="width:220px;">Audit ID</th>
<td><?= htmlspecialchars($audit['audit_id']) ?></td>
</tr>

<tr>
<th>Asset ID</th>
<td><?= htmlspecialchars($audit['asset_business_id']) ?></td>
</tr>

<tr>
<th>Asset</th>
<td><?= htmlspecialchars($audit['asset_name_snap']) ?></td>
</tr>

<tr>
<th>Category</th>
<td><?= htmlspecialchars($audit['category_snap'] ?? '') ?></td>
</tr>

<tr>
<th>Custodian</th>

<td>
<?= !empty($audit['custodian_snap'])
    ? htmlspecialchars($audit['custodian_snap'])
    : 'Not Assigned' ?>
</td>

</tr>

<tr>
<th>Auditor</th>
<td><?= htmlspecialchars($audit['auditor']) ?></td>
</tr>

<tr>
<th>Date</th>
<td><?= htmlspecialchars($audit['audit_date'] ?? '') ?></td>
</tr>

<tr>
<th>Status</th>
<td><?= htmlspecialchars($audit['status']) ?></td>
</tr>

<tr>
<th>Result</th>
<td><?= htmlspecialchars($audit['result']) ?></td>
</tr>

<tr>
<th>Remarks</th>
<td><?= htmlspecialchars($audit['remarks'] ?? '') ?></td>
</tr>

</table>

<br>

<a
    href="index.php"
    class="btn btn-primary"
>
Back to Audit
</a>

<?php else: ?>

<p>Audit record not found.</p>

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