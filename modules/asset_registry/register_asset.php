<?php

session_start();

require_once '../../includes/asset_functions.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $asset = [

        "asset_id" => generateAssetID(),

        "asset_name" => $_POST["asset_name"],

        "category" => $_POST["category"],

        "brand" => $_POST["brand"],

        "model" => $_POST["model"],

        "serial_number" => $_POST["serial_number"],

        "acquisition_date" => $_POST["acquisition_date"],

        "purchase_cost" => $_POST["purchase_cost"],

        "supplier" => $_POST["supplier"],

        "location" => $_POST["location"],

        "remarks" => $_POST["remarks"]

    ];

    $_SESSION["assets"][] = $asset;

}

header("Location: index.php");

exit();