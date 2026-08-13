<?php

require_once "../../auth/check_auth.php";
include "../../includes/header.php";

$id = $_GET['id'] ?? null;
$audit = $_SESSION['audits'][$id] ?? null;

?>

<div class="layout">

    <?php include "../../includes/sidebar.php"; ?>

    <div class="main-content">

        <h1>View Audit</h1>

        <hr>

        <?php if ($audit): ?>

        <table class="asset-table">

            <tr><th style="width:220px;">Audit ID</th><td><?= htmlspecialchars($audit['audit_id']) ?></td></tr>
            <tr><th>Asset ID</th><td><?= htmlspecialchars($audit['asset_id'] ?? 'Not Linked') ?></td></tr>
            <tr><th>Asset</th><td><?= htmlspecialchars($audit['asset_name']) ?></td></tr>
            <tr><th>Category</th><td><?= htmlspecialchars($audit['category'] ?? '') ?></td></tr>
            <tr><th>Custodian</th><td><?= !empty($audit['custodian']) ? htmlspecialchars($audit['custodian']) : 'Not Assigned' ?></td></tr>
            <tr><th>Auditor</th><td><?= htmlspecialchars($audit['auditor']) ?></td></tr>
            <tr><th>Date</th><td><?= htmlspecialchars($audit['audit_date']) ?></td></tr>
            <tr><th>Status</th><td><?= htmlspecialchars($audit['status']) ?></td></tr>
            <tr><th>Result</th><td><?= htmlspecialchars($audit['result']) ?></td></tr>
            <tr><th>Remarks</th><td><?= htmlspecialchars($audit['remarks'] ?? '') ?></td></tr>

        </table>

        <br>

        <a href="index.php" class="btn btn-primary">Back to Audit</a>

        <?php else: ?>

        <p>Audit record not found.</p>
        <a href="index.php" class="btn btn-primary">Back</a>

        <?php endif; ?>

    </div>

</div>

<?php include "../../includes/footer.php"; ?>