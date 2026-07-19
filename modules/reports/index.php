<?php

require_once "../../auth/check_auth.php";

include "../../includes/header.php";

?>

<div class="layout">

    <?php include "../../includes/sidebar.php"; ?>

    <div class="main-content">

        <h1>Reports Management</h1>

        <hr>

        <div class="toolbar">

            <input
                type="text"
                id="reportSearch"
                placeholder="Search Reports..."
            >

            <select id="reportType">

                <option value="">All Report Types</option>

                <option>Asset Report</option>

                <option>Inventory Report</option>

                <option>Maintenance Report</option>

                <option>Procurement Report</option>

                <option>Audit Report</option>

            </select>

            <select id="reportStatus">

                <option value="">All Status</option>

                <option>Generated</option>

                <option>Pending</option>

            </select>

            <button class="btn btn-primary">

                Generate Report

            </button>

        </div>

        <?php include "reports_table.php"; ?>

    </div>

</div>

<?php include "../../includes/footer.php"; ?>