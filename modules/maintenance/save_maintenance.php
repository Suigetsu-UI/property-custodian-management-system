<?php

require_once "../../auth/check_auth.php";

if (!isset($_SESSION['maintenance'])) {
    $_SESSION['maintenance'] = [];
}

$_SESSION['maintenance'][] = [

    "maintenance_id" => $_POST["maintenance_id"],
    "asset_name" => $_POST["asset_name"],
    "maintenance_type" => $_POST["maintenance_type"],
    "scheduled_date" => $_POST["scheduled_date"],
    "status" => $_POST["status"]

];

header("Location: index.php");
exit;