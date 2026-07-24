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

<button id="openMaintenanceModal" class="btn btn-primary">

Add Maintenance

</button>

</div>

<br>

<?php include "maintenance_table.php"; ?>

<div id="maintenanceModal" class="modal">

    <div class="modal-content">

        <span class="close-modal">&times;</span>

        <?php include "maintenance_form.php"; ?>

    </div>

</div>

</div>

</div>

<script src="<?= BASE_URL ?>assets/js/maintenance.js"></script>
<?php include "../../includes/footer.php"; ?>