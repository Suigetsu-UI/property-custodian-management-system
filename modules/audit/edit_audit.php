<?php

require_once "../../auth/check_auth.php";
include "../../includes/header.php";

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

if ($id === null || !isset($_SESSION['audits'][$id])) {
    header("Location: index.php");
    exit;
}

$audit = $_SESSION['audits'][$id];

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $_SESSION['audits'][$id] = [
        "audit_id" => $_POST["audit_id"] ?? $audit["audit_id"],
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

        <h1>Edit Audit</h1>

        <hr>

        <?php include "audit_form.php"; ?>

    </div>

</div>

<?php include "../../includes/footer.php"; ?>
