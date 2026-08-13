<?php

require_once "auth/check_auth.php";

?>

<?php include 'includes/header.php'; ?>

<div class="layout">

<?php include 'includes/sidebar.php'; ?>

<div class="main-content">

<h1>Dashboard</h1>

<hr>

<div class="hero">
    <div class="hero-content">
        <h3>Welcome!</h3>
        <p>You are now logged in to the Property Custodian Management System.</p>
    </div>
    <div class="hero-logo">
        <img src="<?= BASE_URL ?>assets/images/logo.png" alt="Logo">
    </div>
</div>

<br>

<div class="dashboard-cards">

    <div class="card">
        <div class="card-icon"><i class="fas fa-boxes-stacked"></i></div>
        <div class="card-body">
            <h2>0</h2>
            <p>Total Assets</p>
        </div>
    </div>

    <div class="card">
        <div class="card-icon"><i class="fas fa-wrench"></i></div>
        <div class="card-body">
            <h2>0</h2>
            <p>Pending Maintenance</p>
        </div>
    </div>

    <div class="card">
        <div class="card-icon"><i class="fas fa-triangle-exclamation"></i></div>
        <div class="card-body">
            <h2>0</h2>
            <p>Inventory Alerts</p>
        </div>
    </div>

    <div class="card">
        <div class="card-icon"><i class="fas fa-clipboard-check"></i></div>
        <div class="card-body">
            <h2>0</h2>
            <p>Audit Reports</p>
        </div>
    </div>

</div>

</div>

</div>

<?php include 'includes/footer.php'; ?>