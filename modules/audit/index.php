<?php

require_once "../../auth/check_auth.php";
require_once "../../includes/asset_functions.php";

include "../../includes/header.php";

?>

<div class="layout">

    <?php include "../../includes/sidebar.php"; ?>

    <div class="main-content">

        <h1>Audit Management</h1>

        <hr>
<br>
        <div class="search-toolbar">

            <form method="GET" action="search_audit.php" style="display:flex; gap:15px; flex-wrap:wrap; align-items:center;">

                <input
                    type="text"
                    id="searchInput"
                    name="search"
                    placeholder="Search Audit..."
                    value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
                >

                <select id="statusFilter" name="status">
                    <option value="">All Status</option>
                    <option value="Scheduled" <?= (($_GET['status'] ?? '') === 'Scheduled') ? 'selected' : '' ?>>Scheduled</option>
                    <option value="Ongoing" <?= (($_GET['status'] ?? '') === 'Ongoing') ? 'selected' : '' ?>>Ongoing</option>
                    <option value="Completed" <?= (($_GET['status'] ?? '') === 'Completed') ? 'selected' : '' ?>>Completed</option>
                </select>

<select id="resultFilter" name="result">
    <option value="">All Results</option>
    <option value="Verified" <?= (($_GET['result'] ?? '') === 'Verified') ? 'selected' : '' ?>>Verified</option>
    <option value="Missing" <?= (($_GET['result'] ?? '') === 'Missing') ? 'selected' : '' ?>>Missing</option>
    <option value="Damaged" <?= (($_GET['result'] ?? '') === 'Damaged') ? 'selected' : '' ?>>Damaged</option>
    <option value="For Investigation" <?= (($_GET['result'] ?? '') === 'For Investigation') ? 'selected' : '' ?>>For Investigation</option>
</select>

                <button type="submit" class="btn btn-primary">
                    Search
                </button>

            </form>

            <button type="button" id="openAuditModal" class="btn btn-primary">
                Add Audit
            </button>

        </div>
<br>
        <?php include "audit_table.php"; ?>

        <div id="auditModal" class="modal">
            <div class="modal-content">
                <span class="close-modal">&times;</span>
                <?php include "audit_form.php"; ?>
            </div>
        </div>

    </div>

</div>

<script src="<?= BASE_URL ?>assets/js/audit.js"></script>

<?php include "../../includes/footer.php"; ?>