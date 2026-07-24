<?php

require_once "../../auth/check_auth.php";
require_once "../../includes/asset_functions.php";

$id = $_GET['id'] ?? null;

if (
    $id !== null &&
    isset($_SESSION['inventory'][$id])
) {

    $inventoryId = $_SESSION['inventory'][$id]['inventory_id'] ?? '';

    if (isInventoryLinkedToAsset($inventoryId)) {

        header("Location: index.php?error=linked");
        exit;

    }

    unset($_SESSION['inventory'][$id]);

    // Re-index the array
    $_SESSION['inventory'] = array_values($_SESSION['inventory']);
}

header("Location: index.php");
exit;