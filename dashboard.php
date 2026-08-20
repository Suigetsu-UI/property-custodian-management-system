<?php

require_once "auth/check_auth.php";
require_once __DIR__ . "/includes/report_functions.php";

$assetSummary =
    generateAssetSummary();

$maintenanceSummary =
    generateMaintenanceSummary();

$inventorySummary =
    generateInventorySummary();

$auditSummary =
    generateAuditSummary();

$totalAssets =
    $assetSummary['total'];

$pendingMaintenance =
    $maintenanceSummary['scheduled'] +
    $maintenanceSummary['in_progress'];

$inventoryAlerts =
    $inventorySummary['low_stock'] +
    $inventorySummary['out_of_stock'];

$auditReports =
    $auditSummary['completed'];

include "includes/header.php";

?>

<div class="layout">

<?php include "includes/sidebar.php"; ?>

<div class="main-content">

<h1>Dashboard</h1>

<hr>

<div class="hero">

<div class="hero-content">

<h3>Welcome!</h3>

<p>
You are now logged in to the Property Custodian Management System.
</p>

</div>

<div class="hero-logo">

<img
    src="<?= BASE_URL ?>assets/images/logo.png"
    alt="Logo"
>

</div>

</div>

<br>

<div class="dashboard-cards">

<div class="card">

<div class="card-icon">
<i class="fas fa-boxes-stacked"></i>
</div>

<div class="card-body">

<h2><?= (int) $totalAssets ?></h2>
<p>Total Assets</p>

</div>

</div>


<div class="card">

<div class="card-icon">
<i class="fas fa-wrench"></i>
</div>

<div class="card-body">

<h2><?= (int) $pendingMaintenance ?></h2>
<p>Pending Maintenance</p>

</div>

</div>


<div class="card">

<div class="card-icon">
<i class="fas fa-triangle-exclamation"></i>
</div>

<div class="card-body">

<h2><?= (int) $inventoryAlerts ?></h2>
<p>Inventory Alerts</p>

</div>

</div>


<div class="card">

<div class="card-icon">
<i class="fas fa-clipboard-check"></i>
</div>

<div class="card-body">

<h2><?= (int) $auditReports ?></h2>
<p>Audit Reports</p>

</div>

</div>

</div>

</div>

</div>

<?php include "includes/footer.php"; ?>