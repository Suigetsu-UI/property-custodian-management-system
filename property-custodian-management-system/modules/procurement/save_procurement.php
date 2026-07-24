<?php

require_once "../../auth/check_auth.php";
require_once __DIR__ . "/../../includes/asset_functions.php";

if (!isset($_SESSION['procurement'])) {

    $_SESSION['procurement'] = [];

}

$id = isset($_POST['id']) && $_POST['id'] !== ''
    ? (int) $_POST['id']
    : null;

if ($id !== null && isset($_SESSION['procurement'][$id])) {

    $_SESSION['procurement'][$id] = [

        "procurement_id" => $_POST["procurement_id"] ?? $_SESSION['procurement'][$id]['procurement_id'],

        "item_name" => $_POST["item_name"],

        "category" => $_POST["category"],

        "quantity" => $_POST["quantity"],

        "supplier" => $_POST["supplier"],

        "status" => $_POST["status"]

    ];

} else {

    $_SESSION['procurement'][] = [

        "procurement_id" => $_POST["procurement_id"] ?? generateProcurementID(),

        "item_name" => $_POST["item_name"],

        "category" => $_POST["category"],

        "quantity" => $_POST["quantity"],

        "supplier" => $_POST["supplier"],

        "status" => $_POST["status"]

    ];
}

header("Location: index.php");
exit;