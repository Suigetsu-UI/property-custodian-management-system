<?php

session_start();

require_once '../../includes/asset_functions.php';

if (!isset($_GET['id'])) {

    header("Location:index.php");
    exit();

}

$id = (int) $_GET['id'];

if (isset($_SESSION['assets'][$id])) {

    $status = $_SESSION['assets'][$id]['status'] ?? 'Available';
    $assetId = $_SESSION['assets'][$id]['asset_id'] ?? '';

    if ($status === 'Under Maintenance' || hasActiveMaintenance($assetId)) {

        header("Location:index.php?error=maintenance");
        exit();

    }

    if ($status === 'Lost') {

        header("Location:index.php?error=lost");
        exit();

    }

    if ($status === 'Assigned') {

        header("Location:index.php?error=assigned");
        exit();

    }

    $inventoryId = $_SESSION['assets'][$id]['inventory_id'] ?? '';

    if ($inventoryId !== '') {
        incrementInventoryQuantity($inventoryId);
    }

    unset($_SESSION['assets'][$id]);

    $_SESSION['assets'] = array_values($_SESSION['assets']);

}

header("Location:index.php");

exit();