<?php

require_once "../../auth/check_auth.php";
require_once __DIR__ . "/../../includes/database.php";
require_once __DIR__ . "/../../includes/asset_functions.php";

try {
    $pdo = getDbConnection();

    $maintenanceIdForForm =
        nextBusinessId(
            $pdo,
            'maintenance',
            'MNT'
        );

    if (!isset($_SESSION['pending_maintenance_ids'])) {
        $_SESSION['pending_maintenance_ids'] = [];
    }

    $_SESSION['pending_maintenance_ids'][$maintenanceIdForForm] = true;

} catch (Throwable $e) {
    header("Location: index.php?error=save_failed");
    exit;
}

include "../../includes/header.php";

?>

<div class="layout">

<?php include "../../includes/sidebar.php"; ?>

<div class="main-content">

<h1>Add Maintenance</h1>

<hr>

<?php include "maintenance_form.php"; ?>

</div>

</div>

<?php include "../../includes/footer.php"; ?>