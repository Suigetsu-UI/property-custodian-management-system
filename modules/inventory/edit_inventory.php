<?php

require_once "../../auth/check_auth.php";
include "../../includes/header.php";

$id = $_GET['id'] ?? null;

if (!isset($_SESSION['inventory'][$id])) {
    header("Location: index.php");
    exit;
}

$inventory = $_SESSION['inventory'][$id];

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $_SESSION['inventory'][$id] = [

        "inventory_id" => $inventory["inventory_id"],

        "asset_name" => $_POST["asset_name"],

        "category" => $_POST["category"],

        "quantity" => $_POST["quantity"],

        "condition" => $_POST["condition"]

    ];

    header("Location: index.php");
    exit;
}

?>

<div class="layout">

    <?php include "../../includes/sidebar.php"; ?>

    <div class="main-content">

        <h1>Edit Inventory</h1>

        <hr>

        <?php include "inventory_form.php"; ?>

    </div>

</div>

<?php include "../../includes/footer.php"; ?>