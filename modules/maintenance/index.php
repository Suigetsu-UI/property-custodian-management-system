<?php

require_once "../../auth/check_auth.php";

include "../../includes/header.php";

?>

<div class="layout">

<?php include "../../includes/sidebar.php"; ?>

<div class="main-content">

<h1>Maintenance Management</h1>

<hr>

<br>

<?php if (($_GET['error'] ?? '') === 'invalid_id'): ?>

<div class="error-message">

The Maintenance ID is invalid or was not issued for this session. Please reopen Add Maintenance and try again.

</div>

<br>

<?php endif; ?>

<?php if (($_GET['error'] ?? '') === 'asset'): ?>

<div class="error-message">

Please select a valid registered Asset.

</div>

<br>

<?php endif; ?>

<?php if (($_GET['error'] ?? '') === 'asset_unavailable'): ?>

<div class="error-message">

The selected Asset is not currently available for a new Maintenance record.

</div>

<br>

<?php endif; ?>

<?php if (($_GET['error'] ?? '') === 'save_failed'): ?>

<div class="error-message">

The Maintenance record could not be saved. Please check the entered values and try again.

</div>

<br>

<?php endif; ?>

<div class="search-toolbar">

<input
    type="text"
    id="searchInput"
    placeholder="Search by Maintenance ID, Asset Name, Type..."
>

<select id="statusFilter">

<option value="">All Status</option>

<option>Scheduled</option>
<option>In Progress</option>
<option>Completed</option>

</select>

<button
    type="button"
    id="openMaintenanceModal"
    class="btn btn-primary"
>
Add Maintenance
</button>

</div>

<br>

<?php include "maintenance_table.php"; ?>

<div
    id="maintenanceModal"
    class="modal"
>

<div class="modal-content">

<span class="close-modal">&times;</span>

<?php include "maintenance_form.php"; ?>

</div>

</div>

</div>

</div>

<script src="<?= BASE_URL ?>assets/js/maintenance.js"></script>

<?php include "../../includes/footer.php"; ?>