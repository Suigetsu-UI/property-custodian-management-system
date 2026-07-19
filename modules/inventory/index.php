<?php

require_once "../../auth/check_auth.php";

include "../../includes/header.php";

?>





<div class="layout">

    <?php include "../../includes/sidebar.php"; ?>

    <div class="main-content">

        <h1>Inventory Management</h1>

        <hr>

        <div class="toolbar">

            <form method="GET" action="search_inventory.php" class="toolbar">

    <input
    type="text"
    name="search"
    placeholder="Search Inventory..."
>

    <select name="category">
        <option value="">All Categories</option>
        <option>Computer</option>
        <option>Furniture</option>
        <option>Office Equipment</option>
        <option>Electronics</option>
    </select>

    <select name="condition">
        <option value="">All Conditions</option>
        <option>Good</option>
        <option>Fair</option>
        <option>Needs Repair</option>
        <option>Unserviceable</option>
    </select>

    <button
        type="submit"
        class="btn btn-primary">
        Search
    </button>

    <a href="add_inventory.php" class="btn btn-primary">
        Add Inventory
    </a>

</form>

        <?php include "inventory_table.php"; ?>

    </div>

</div>

<?php include "../../includes/footer.php"; ?>