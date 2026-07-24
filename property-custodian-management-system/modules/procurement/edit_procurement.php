<?php

require_once "../../auth/check_auth.php";
include "../../includes/header.php";

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

if ($id === null || !isset($_SESSION['procurement'][$id])) {
    header("Location: index.php");
    exit;
}

$procurement = $_SESSION['procurement'][$id];

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