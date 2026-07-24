<?php

require_once "../../auth/check_auth.php";
require_once __DIR__ . "/../../includes/asset_functions.php";

if (!function_exists('generateAuditID')) {
    function generateAuditID()
    {
        return "AUD-" . str_pad(count($_SESSION['audits']) + 1, 6, "0", STR_PAD_LEFT);
    }
}

include "../../includes/header.php";

if (!isset($_SESSION['audits'])) {
    $_SESSION['audits'] = [];
}

$id = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $_SESSION['audits'][] = [
        "audit_id" => $_POST["audit_id"] ?? generateAuditID(),
        "asset_name" => $_POST["asset_name"],
        "auditor" => $_POST["auditor"],
        "audit_date" => $_POST["audit_date"],
        "status" => $_POST["status"],
        "result" => $_POST["result"]
    ];

    header("Location: index.php");
    exit;
}

?>

<div class="layout">

    <?php include "../../includes/sidebar.php"; ?>

    <div class="main-content">

        <h1>Schedule Audit</h1>

        <hr>

        <?php include "audit_form.php"; ?>

    </div>

</div>

<?php include "../../includes/footer.php"; ?>
