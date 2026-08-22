<?php

require_once "../../auth/check_auth.php";

include "../../includes/header.php";

?>

<div class="layout">

    <?php include "../../includes/sidebar.php"; ?>

    <div class="main-content">

        <h1>Procurement Management</h1>

        <hr>

        <br>

        <?php if (($_GET['error'] ?? '') === 'delivered'): ?>

        <div class="error-message">

            This procurement record has already updated Inventory and cannot be deleted. Adjust the linked Inventory item manually if a reversal is required.

        </div>

        <br>

        <?php endif; ?>

        <?php if (($_GET['error'] ?? '') === 'invalid_status'): ?>

<div class="error-message">

    That status change is not allowed for this procurement record.

</div>

<br>

<?php endif; ?>

<?php if (($_GET['error'] ?? '') === 'delivery_date'): ?>

<div class="error-message">

    A Delivery Date is required when Procurement status is Delivered.

</div>

<br>

<?php endif; ?>

<?php if (($_GET['error'] ?? '') === 'inventory_negative'): ?>

<div class="error-message">

    This change would reduce Inventory below zero. No changes were made.

</div>

<br>

<?php endif; ?>

<?php if (($_GET['error'] ?? '') === 'invalid_id'): ?>

<div class="error-message">

    The Procurement ID is invalid or was not issued for this session. Please reopen "Add Procurement" and try again.

</div>

<br>

<?php endif; ?>

<?php if (($_GET['error'] ?? '') === 'duplicate_id'): ?>

<div class="error-message">

    This Procurement ID has already been used. Please reopen "Add Procurement" if you intended to create a new record.

</div>

<br>

<?php endif; ?>

<?php if (($_GET['error'] ?? '') === 'save_failed'): ?>

<div class="error-message">

    The procurement record could not be saved. Please try again.

</div>

<br>

<?php endif; ?>

        <div class="search-toolbar">

            <input
                type="text"
                id="searchInput"
                placeholder="Search by Procurement ID, Item, Supplier..."
            >

            <select id="statusFilter">
                <option value="">All Status</option>
                <option>Pending</option>
                <option>Approved</option>
                <option>Rejected</option>
                <option>Delivered</option>
            </select>

            <select id="supplierFilter">
                <option value="">All Suppliers</option>
                <option>Supplier A</option>
                <option>Supplier B</option>
                <option>Supplier C</option>
            </select>

            <button id="openProcurementModal" class="btn btn-primary">
                Add Procurement
            </button>

        </div>

        <br>

        <?php include "procurement_table.php"; ?>

        <?php include "procurement_modals.php"; ?>

    </div>

</div>

<script src="<?= BASE_URL ?>assets/js/procurement.js"></script>
<?php include "../../includes/footer.php"; ?>
