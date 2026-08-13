<?php

session_start();


?>
<?php

require_once "../../auth/check_auth.php";

?>

<?php include '../../includes/header.php'; ?>

<div class="layout">

<?php include '../../includes/sidebar.php'; ?>

<div class="main-content">

<h1>Asset Registry</h1>

<hr>

<br>

<?php if (($_GET['error'] ?? '') === 'assigned'): ?>

<div class="error-message">

    This asset is currently assigned to a custodian and cannot be deleted while assigned.

</div>

<br>

<?php endif; ?>

<?php if (($_GET['error'] ?? '') === 'lost'): ?>

<div class="error-message">

    This asset is marked Lost and cannot be assigned or deleted until its audit status changes.

</div>

<br>

<?php endif; ?>

<?php if (($_GET['error'] ?? '') === 'maintenance'): ?>

<div class="error-message">

    This asset is currently under maintenance. Please complete the maintenance record before assigning, returning, or deleting this asset.

</div>

<br>

<?php endif; ?>

<?php include 'asset_search.php'; ?>

<br>

<?php include 'asset_table.php'; ?>

<?php include 'asset_form.php'; ?>
</div>

</div>

<script src="<?= BASE_URL ?>assets/js/asset_registry.js"></script>
<?php include '../../includes/footer.php'; ?>