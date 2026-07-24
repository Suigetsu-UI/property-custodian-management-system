<?php

session_start();

require_once '../../includes/asset_functions.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $inventoryId = trim($_POST["inventory_id"] ?? '');
    $inventoryItem = $inventoryId !== '' ? getInventoryByID($inventoryId) : null;

    if ($inventoryItem && (int) ($inventoryItem['quantity'] ?? 0) > 0) {

        $asset = [

            "asset_id" => generateAssetID(),

            "asset_name" => $inventoryItem['asset_name'],

            "category" => $inventoryItem['category'],

            "brand" => $_POST["brand"],

            "model" => $_POST["model"],

            "serial_number" => $_POST["serial_number"],

            "acquisition_date" => $_POST["acquisition_date"],

            "purchase_cost" => $_POST["purchase_cost"],

            "supplier" => $_POST["supplier"],

            "location" => $_POST["location"],

            "remarks" => $_POST["remarks"],

            "inventory_id" => $inventoryId,

            "status" => "Available"

        ];

        $_SESSION["assets"][] = $asset;

        decrementInventoryQuantity($inventoryId);

    }

}

header("Location: index.php");

exit();