<?php

require_once "../../auth/check_auth.php";

include "../../includes/header.php";

?>

<div class="layout">

<?php include "../../includes/sidebar.php"; ?>

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

<?php if (($_GET['error'] ?? '') === 'history'): ?>

<div class="error-message">

This asset cannot be deleted because Maintenance or Audit history is linked to it.

</div>

<br>

<?php endif; ?>

<?php if (($_GET['error'] ?? '') === 'invalid_id'): ?>

<div class="error-message">

The Asset ID is invalid or was not issued for this session. Please reopen Register Asset and try again.

</div>

<br>

<?php endif; ?>

<?php if (($_GET['error'] ?? '') === 'stock'): ?>

<div class="error-message">

The selected Inventory item is no longer available for registration.

</div>

<br>

<?php endif; ?>

<?php if (($_GET['error'] ?? '') === 'inventory'): ?>

<div class="error-message">

Please select a valid Inventory item.

</div>

<br>

<?php endif; ?>

<?php if (($_GET['error'] ?? '') === 'save_failed'): ?>

<div class="error-message">

The Asset record could not be saved. Please check the entered values and try again.

</div>

<br>

<?php endif; ?>

<?php include "asset_search.php"; ?>

<br>

<?php include "asset_table.php"; ?>

<?php include "asset_form.php"; ?>

<?php include "asset_action_modals.php"; ?>

</div>

</div>

<script src="<?= BASE_URL ?>assets/js/asset_registry.js"></script>

<?php include "../../includes/footer.php"; ?>
