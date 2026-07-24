<?php

require_once "auth/check_auth.php";

?>

<?php include 'includes/header.php'; ?>

<div class="layout">

<?php include 'includes/sidebar.php'; ?>

<div class="main-content">

<h1>Dashboard</h1>

<hr>

<br>

<h3>Welcome!</h3>

<p>

You are now logged in to the Property Custodian Management System.

</p>

<br>

<div class="dashboard-cards">

    <div class="card">
        <h2>0</h2>
        <p>Total Assets</p>
    </div>

    <div class="card">
        <h2>0</h2>
        <p>Pending Maintenance</p>
    </div>

    <div class="card">
        <h2>0</h2>
        <p>Inventory Alerts</p>
    </div>

    <div class="card">
        <h2>0</h2>
        <p>Audit Reports</p>
    </div>

</div>

</div>

</div>

<?php include 'includes/footer.php'; ?>