<?php

require_once "../../auth/check_auth.php";

include "../../includes/header.php";

/*
 * Explicit Add-mode variables.
 *
 * inventory_table.php uses $inventoryRows, so these do not
 * collide with the DB-backed list include.
 */
$id = null;
$inventory = null;

?>

<div class="layout">

<?php include "../../includes/sidebar.php"; ?>

<div class="main-content">

<h1>Inventory Management</h1>

<hr>

<br>

<?php if (($_GET['error'] ?? '') === 'linked'): ?>

<div class="error-message">

Cannot delete this Inventory item because one or more registered assets are linked to it. Remove the linked asset records first before deleting this inventory item.

</div>

<br>

<?php endif; ?>

<?php if (($_GET['error'] ?? '') === 'duplicate_item'): ?>

<div class="error-message">

An Inventory item with this Asset Name and Category already exists. Please edit the existing item instead of creating a duplicate.

</div>

<br>

<?php endif; ?>

<?php if (($_GET['error'] ?? '') === 'invalid_id'): ?>

<div class="error-message">

The Inventory ID is invalid or was not issued for this session. Please reopen Add Inventory and try again.

</div>

<br>

<?php endif; ?>

<?php if (($_GET['error'] ?? '') === 'save_failed'): ?>

<div class="error-message">

The Inventory record could not be saved. Please check the entered values and try again.

</div>

<br>

<?php endif; ?>

<div class="search-toolbar">

<form
    method="GET"
    action="search_inventory.php"
    id="inventorySearchForm"
    style="display:flex; gap:15px; flex-wrap:wrap; align-items:center;"
>

<input
    type="text"
    id="searchInput"
    name="search"
    placeholder="Search Inventory..."
    value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
>

<select id="categoryFilter" name="category">

<option value="">All Categories</option>

<option
    value="Computer"
    <?= (($_GET['category'] ?? '') === 'Computer') ? 'selected' : '' ?>
>
    Computer
</option>

<option
    value="Furniture"
    <?= (($_GET['category'] ?? '') === 'Furniture') ? 'selected' : '' ?>
>
    Furniture
</option>

<option
    value="Office Equipment"
    <?= (($_GET['category'] ?? '') === 'Office Equipment') ? 'selected' : '' ?>
>
    Office Equipment
</option>

<option
    value="Electronics"
    <?= (($_GET['category'] ?? '') === 'Electronics') ? 'selected' : '' ?>
>
    Electronics
</option>

</select>

<select id="conditionFilter" name="condition">

<option value="">All Conditions</option>

<option
    value="Good"
    <?= (($_GET['condition'] ?? '') === 'Good') ? 'selected' : '' ?>
>
    Good
</option>

<option
    value="Fair"
    <?= (($_GET['condition'] ?? '') === 'Fair') ? 'selected' : '' ?>
>
    Fair
</option>

<option
    value="Needs Repair"
    <?= (($_GET['condition'] ?? '') === 'Needs Repair') ? 'selected' : '' ?>
>
    Needs Repair
</option>

<option
    value="Unserviceable"
    <?= (($_GET['condition'] ?? '') === 'Unserviceable') ? 'selected' : '' ?>
>
    Unserviceable
</option>

</select>

<button type="submit" class="btn btn-primary">
    Search
</button>

</form>

<button
    type="button"
    id="openInventoryModal"
    class="btn btn-primary"
>
    Add Inventory
</button>

</div>

<br>

<?php include "inventory_table.php"; ?>

<?php include "inventory_modals.php"; ?>

</div>

</div>

<script src="<?= BASE_URL ?>assets/js/inventory.js"></script>

<?php include "../../includes/footer.php"; ?>
