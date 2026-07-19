<?php

require_once "../../auth/check_auth.php";
include "../../includes/header.php";

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

if (!isset($_SESSION['maintenance'][$id])) {
    header("Location: index.php");
    exit;
}

$maintenance = $_SESSION['maintenance'][$id];

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $_SESSION['maintenance'][$id] = [

        "maintenance_id" => $maintenance["maintenance_id"],

        "asset_name" => $_POST["asset_name"],

        "maintenance_type" => $_POST["maintenance_type"],

        "scheduled_date" => $_POST["scheduled_date"],

        "status" => $_POST["status"]

    ];

    header("Location: index.php");
    exit;
}

?>

<div class="layout">

    <?php include "../../includes/sidebar.php"; ?>

    <div class="main-content">

        <h1>Edit Maintenance</h1>

        <hr>

        <?php include "maintenance_form.php"; ?>

    </div>

</div>

<?php include "../../includes/footer.php"; ?>