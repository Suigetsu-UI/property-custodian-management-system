<?php

require_once "../../auth/check_auth.php";

if (!isset($_SESSION['procurement'])) {

    $_SESSION['procurement'] = [];

}

$_SESSION['procurement'][] = [

    "procurement_id" => $_POST["procurement_id"],

    "item_name" => $_POST["item_name"],

    "category" => $_POST["category"],

    "quantity" => $_POST["quantity"],

    "supplier" => $_POST["supplier"],

    "status" => $_POST["status"]

];

header("Location: index.php");
exit;