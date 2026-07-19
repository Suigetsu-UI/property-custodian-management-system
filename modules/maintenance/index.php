<?php

require_once "../../auth/check_auth.php";
include "../../includes/header.php";

?>

<div class="layout">

<?php include "../../includes/sidebar.php"; ?>

<div class="main-content">

<h1>Maintenance Management</h1>

<hr>

<div class="toolbar">

<form method="GET" action="search_maintenance.php" class="toolbar">

<input
    type="text"
    name="search"
    placeholder="Search Maintenance..."
>

<select name="status">

    <option value="">All Status</option>

    <option>Scheduled</option>

    <option>In Progress</option>

    <option>Completed</option>

</select>

<button
    type="submit"
    class="btn btn-primary">

Search

</button>

<a href="add_maintenance.php"
class="btn btn-primary">

Add Maintenance

</a>

</form>

</div>

<?php include "maintenance_table.php"; ?>

</div>

</div>

<?php include "../../includes/footer.php"; ?>