<?php

require_once "../../auth/check_auth.php";
require_once __DIR__ . "/../../includes/asset_functions.php";

$id = $_GET['id'] ?? null;

if (
    $id !== null &&
    isset($_SESSION['maintenance'][$id])
) {

    $assetId = $_SESSION['maintenance'][$id]['asset_id'] ?? '';

    unset($_SESSION['maintenance'][$id]);

    // Re-index the array
    $_SESSION['maintenance'] = array_values($_SESSION['maintenance']);

    // Restore Asset Registry status now that no maintenance record
    // references this asset. Reuses the same restoration rule as
    // Completed Maintenance: Assigned if a custodian exists, otherwise
    // Available.
    if ($assetId !== '') {
        updateAssetStatusFromMaintenance($assetId, 'Completed');
    }
}

header("Location: index.php");
exit;