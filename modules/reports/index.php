<?php

require_once "../../auth/check_auth.php";
require_once "../../includes/asset_functions.php";

include "../../includes/header.php";

?>

<div class="layout">

    <?php include "../../includes/sidebar.php"; ?>

    <div class="main-content">

        <h1>Reports Management</h1>

        <hr>
<br>
        <div class="search-toolbar">

            <form method="GET" action="search_reports.php" style="display:flex; gap:15px; flex-wrap:wrap; align-items:center;">

                <input
                    type="text"
                    id="searchInput"
                    name="search"
                    placeholder="Search Reports..."
                    value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
                >

                <select id="reportType" name="report_type">
                    <option value="">All Report Types</option>
                    <option value="Asset Report" <?= (($_GET['report_type'] ?? '') === 'Asset Report') ? 'selected' : '' ?>>Asset Report</option>
                    <option value="Inventory Report" <?= (($_GET['report_type'] ?? '') === 'Inventory Report') ? 'selected' : '' ?>>Inventory Report</option>
                    <option value="Maintenance Report" <?= (($_GET['report_type'] ?? '') === 'Maintenance Report') ? 'selected' : '' ?>>Maintenance Report</option>
                    <option value="Procurement Report" <?= (($_GET['report_type'] ?? '') === 'Procurement Report') ? 'selected' : '' ?>>Procurement Report</option>
                    <option value="Audit Report" <?= (($_GET['report_type'] ?? '') === 'Audit Report') ? 'selected' : '' ?>>Audit Report</option>
                </select>

                <select id="reportStatus" name="status">
                    <option value="">All Status</option>
                    <option value="Generated" <?= (($_GET['status'] ?? '') === 'Generated') ? 'selected' : '' ?>>Generated</option>
                    <option value="Pending" <?= (($_GET['status'] ?? '') === 'Pending') ? 'selected' : '' ?>>Pending</option>
                </select>

                <button type="submit" class="btn btn-primary">
                    Search
                </button>

            </form>

            <button type="button" id="openReportModal" class="btn btn-primary">
                Generate Report
            </button>

        </div>
<br>
        <?php include "reports_table.php"; ?>

        <div id="reportModal" class="modal">
            <div class="modal-content">
                <span class="close-modal">&times;</span>
                <?php include "generate_report.php"; ?>
            </div>
        </div>

    </div>

</div>

<script src="<?= BASE_URL ?>assets/js/reports.js"></script>

<?php include "../../includes/footer.php"; ?>