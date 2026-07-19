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

<?php include 'asset_search.php'; ?>

<br>

<?php include 'asset_table.php'; ?>
<?php include 'asset_form.php'; ?>
</div>

</div>

<script src="<?= BASE_URL ?>assets/js/asset_registry.js"></script>
<?php include '../../includes/footer.php'; ?>