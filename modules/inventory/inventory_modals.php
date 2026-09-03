<?php require_once __DIR__ . "/../../auth/check_auth.php"; ?>

<div id="inventoryModal" class="modal pcms-modal pcms-modal--md" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="addInventoryTitle">
<div class="modal-content pcms-modal-dialog">
<form id="addInventoryForm" class="pcms-modal-form" method="POST" action="save_inventory.php">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getAccessCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
<input type="hidden" name="mode" value="create">
<header class="pcms-modal-header">
    <div><span class="pcms-modal-eyebrow">Inventory</span><h2 id="addInventoryTitle">Add Inventory</h2><p>Create a stock record that can later be registered as an Asset.</p></div>
    <button type="button" class="close-modal" data-modal-close aria-label="Close Add Inventory"><span aria-hidden="true">&times;</span></button>
</header>
<div class="pcms-modal-body"><div class="pcms-form-grid">
    <div class="form-row"><label for="inventoryID">Inventory ID</label><input type="text" id="inventoryID" name="inventory_id" readonly></div>
    <div class="form-row"><label for="inventoryAssetName">Asset Name</label><input type="text" id="inventoryAssetName" name="asset_name" required></div>
    <div class="form-row"><label for="inventoryCategory">Category</label><select id="inventoryCategory" name="category"><option>Computer</option><option>Furniture</option><option>Office Equipment</option><option>Electronics</option></select></div>
    <div class="form-row"><label for="inventoryQuantity">Quantity</label><input type="number" id="inventoryQuantity" name="quantity" min="1" value="1" required></div>
    <div class="form-row pcms-form-span-2"><label for="inventoryCondition">Condition</label><select id="inventoryCondition" name="condition"><option>Good</option><option>Fair</option><option>Needs Repair</option><option>Unserviceable</option></select></div>
</div></div>
<footer class="pcms-modal-footer"><button type="button" class="btn btn-outline" data-modal-close>Cancel</button><button type="submit" class="btn btn-primary">Save Inventory</button></footer>
</form>
</div>
</div>

<div id="viewInventoryModal" class="modal pcms-modal pcms-modal--md" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="viewInventoryTitle">
<div class="modal-content pcms-modal-dialog">
<header class="pcms-modal-header">
    <div><span class="pcms-modal-eyebrow">Inventory</span><h2 id="viewInventoryTitle">Inventory Details</h2><p id="viewInventorySubtitle">Stock record information</p></div>
    <button type="button" class="close-modal" data-modal-close aria-label="Close Inventory Details"><span aria-hidden="true">&times;</span></button>
</header>
<div class="pcms-modal-body"><section class="pcms-detail-section"><dl class="pcms-detail-grid">
    <div><dt>Inventory ID</dt><dd data-inventory-view="inventory_id">—</dd></div>
    <div><dt>Asset Name</dt><dd data-inventory-view="asset_name">—</dd></div>
    <div><dt>Category</dt><dd data-inventory-view="category">—</dd></div>
    <div><dt>Quantity</dt><dd data-inventory-view="quantity">—</dd></div>
    <div class="pcms-detail-span-2"><dt>Condition</dt><dd data-inventory-view="condition">—</dd></div>
</dl></section></div>
<footer class="pcms-modal-footer"><button type="button" class="btn btn-outline" data-modal-close>Close</button><button type="button" class="btn btn-warning" id="editInventoryFromView">Edit Inventory</button></footer>
</div>
</div>

<div id="editInventoryModal" class="modal pcms-modal pcms-modal--md" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="editInventoryTitle">
<div class="modal-content pcms-modal-dialog">
<form id="editInventoryForm" class="pcms-modal-form" method="POST" action="save_inventory.php">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getAccessCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
<input type="hidden" name="mode" value="edit">
<input type="hidden" id="editInventoryBusinessID" name="inventory_id">
<header class="pcms-modal-header">
    <div><span class="pcms-modal-eyebrow">Inventory</span><h2 id="editInventoryTitle">Edit Inventory</h2><p>Update stock details while keeping the Inventory ID unchanged.</p></div>
    <button type="button" class="close-modal" data-modal-close aria-label="Close Edit Inventory"><span aria-hidden="true">&times;</span></button>
</header>
<div class="pcms-modal-body"><div class="pcms-form-grid">
    <div class="form-row"><label for="editInventoryID">Inventory ID</label><input type="text" id="editInventoryID" readonly></div>
    <div class="form-row"><label for="editInventoryName">Asset Name</label><input type="text" id="editInventoryName" name="asset_name" required></div>
    <div class="form-row"><label for="editInventoryCategory">Category</label><select id="editInventoryCategory" name="category"><option>Computer</option><option>Furniture</option><option>Office Equipment</option><option>Electronics</option></select></div>
    <div class="form-row"><label for="editInventoryQuantity">Quantity</label><input type="number" id="editInventoryQuantity" name="quantity" min="0" required></div>
    <div class="form-row pcms-form-span-2"><label for="editInventoryCondition">Condition</label><select id="editInventoryCondition" name="condition"><option>Good</option><option>Fair</option><option>Needs Repair</option><option>Unserviceable</option></select></div>
</div></div>
<footer class="pcms-modal-footer"><button type="button" class="btn btn-outline" data-modal-close>Cancel</button><button type="submit" class="btn btn-warning">Update Inventory</button></footer>
</form>
</div>
</div>
