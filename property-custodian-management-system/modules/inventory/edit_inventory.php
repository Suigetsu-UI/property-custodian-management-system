<?php

require_once "../../auth/check_auth.php";
require_once "../../includes/asset_functions.php";
include "../../includes/header.php";

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

if ($id === null || !isset($_SESSION['inventory'][$id])) {
    header("Location: index.php");
    exit;
}

$inventory = $_SESSION['inventory'][$id];

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