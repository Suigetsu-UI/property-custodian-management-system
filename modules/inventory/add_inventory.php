<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);



require_once "../../auth/check_auth.php";
require_once "../../includes/asset_functions.php";
include "../../includes/header.php";

$id = null;

?>

<div class="layout">

<?php include "../../includes/sidebar.php"; ?>

<div class="main-content">

<h1>Add Inventory</h1>

<hr>

<?php include "inventory_form.php"; ?>

</div>

</div>

<?php include "../../includes/footer.php"; ?>