<?php

require_once "../../auth/check_auth.php";
include "../../includes/header.php";

$id = $_GET['id'] ?? null;

if (!isset($_SESSION['procurement'][$id])) {
    header("Location: index.php");
    exit;
}

$procurement = $_SESSION['procurement'][$id];

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $_SESSION['procurement'][$id] = [

        "procurement_id" => $procurement["procurement_id"],

        "item_name" => $_POST["item_name"],

        "category" => $_POST["category"],

        "quantity" => $_POST["quantity"],

        "supplier" => $_POST["supplier"],

        "status" => $_POST["status"]

    ];

    header("Location: index.php");
    exit;
}

?>

<div class="layout">

<?php include "../../includes/sidebar.php"; ?>

<div class="main-content">

<h1>Edit Procurement</h1>

<hr>

<?php include "procurement_form.php"; ?>

</div>

</div>

<?php include "../../includes/footer.php"; ?>