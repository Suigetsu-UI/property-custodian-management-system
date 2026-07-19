<?php

session_start();

require_once "../../includes/asset_functions.php";

if (!isset($_SESSION['inventory'])) {
    $_SESSION['inventory'] = [];
}

$inventory = [

    'inventory_id' => generateInventoryID(),

    'asset_name' => $_POST['asset_name'] ?? '',

    'category' => $_POST['category'] ?? '',

    'quantity' => $_POST['quantity'] ?? 1,

    'condition' => $_POST['condition'] ?? 'Good'

];

$_SESSION['inventory'][] = $inventory;

header("Location: index.php");

exit();

?>