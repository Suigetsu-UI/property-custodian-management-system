<?php

require_once "../../auth/check_auth.php";

include "../../includes/header.php";

?>

<div class="layout">

    <?php include "../../includes/sidebar.php"; ?>

    <div class="main-content">

        <h1>Audit Management</h1>

        <hr>

        <div class="toolbar">

            <input
                type="text"
                id="auditSearch"
                placeholder="Search Audit..."
            >

            <select id="statusFilter">
                <option value="">All Status</option>
                <option>Scheduled</option>
                <option>Ongoing</option>
                <option>Completed</option>
            </select>

            <select id="resultFilter">
                <option value="">All Results</option>
                <option>Passed</option>
                <option>With Findings</option>
                <option>Failed</option>
            </select>

            <button class="btn btn-primary">

                Schedule Audit

            </button>

        </div>

        <?php include "audit_table.php"; ?>

    </div>

</div>

<?php include "../../includes/footer.php"; ?>