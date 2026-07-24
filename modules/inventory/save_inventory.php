<?php

require_once "../../auth/check_auth.php";
require_once "../../includes/asset_functions.php";

if (!isset($_SESSION['inventory'])) {
    $_SESSION['inventory'] = [];
}

$id = isset($_POST['id']) && $_POST['id'] !== ''
    ? (int) $_POST['id']
    : null;

if ($id !== null && isset($_SESSION['inventory'][$id])) {

    $_SESSION['inventory'][$id] = [

        'inventory_id' => $_POST['inventory_id'] ?? $_SESSION['inventory'][$id]['inventory_id'],

        'asset_name' => $_POST['asset_name'],

        'category' => $_POST['category'],

        'quantity' => $_POST['quantity'] ?? 1,

        'condition' => $_POST['condition'] ?? 'Good'

    ];

} else {

    $_SESSION['inventory'][] = [

        'inventory_id' => $_POST['inventory_id'] ?? generateInventoryID(),

        'asset_name' => $_POST['asset_name'],

        'category' => $_POST['category'],

        'quantity' => $_POST['quantity'] ?? 1,

        'condition' => $_POST['condition'] ?? 'Good'

    ];
}

header("Location: index.php");

exit();

?>