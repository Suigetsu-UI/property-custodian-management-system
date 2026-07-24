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
                <option>Ordered</option>
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

        <div id="procurementModal" class="modal">
            <div class="modal-content">
                <span class="close-modal">&times;</span>
                <?php include "procurement_form.php"; ?>
            </div>
        </div>

    </div>

</div>

<script src="<?= BASE_URL ?>assets/js/procurement.js"></script>
<?php include "../../includes/footer.php"; ?>