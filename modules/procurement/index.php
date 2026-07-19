<?php

require_once "../../auth/check_auth.php";

include "../../includes/header.php";

?>

<div class="layout">

    <?php include "../../includes/sidebar.php"; ?>

    <div class="main-content">

        <h1>Procurement Management</h1>

        <hr>

        <div class="toolbar">

            <input
                type="text"
                id="procurementSearch"
                placeholder="Search Procurement..."
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

            <a href="add_procurement.php" class="btn btn-primary">
    Add Procurement
</a>

        </div>

        <?php include "procurement_table.php"; ?>

    </div>

</div>

<?php include "../../includes/footer.php"; ?>