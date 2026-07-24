<div id="assetModal" class="modal">

    <div class="modal-content">

        <span class="close-modal">&times;</span>


<form class="asset-form" method="POST" action="register_asset.php">

<h2>Register New Asset</h2>

<div class="form-row">

<div class="form-row">

    <label>Asset ID</label>

    <input
        type="text"
        id="assetID"
        readonly
    >

</div>


<div class="form-row">

<label>QR Code</label>

<input
type="text"
value="Automatically Generated After Saving"
readonly>

</div>

</div>

<div class="form-row">

<label>Select Inventory Item</label>

<select name="inventory_id" id="inventoryItemSelect" required>

<option value="">Select Inventory Item</option>

<?php foreach (($_SESSION['inventory'] ?? []) as $item): ?>

<?php if ((int) ($item['quantity'] ?? 0) > 0): ?>

<option
    value="<?= htmlspecialchars($item['inventory_id']) ?>"
    data-name="<?= htmlspecialchars($item['asset_name']) ?>"
    data-category="<?= htmlspecialchars($item['category']) ?>"
>
<?= htmlspecialchars($item['inventory_id'] . ' - ' . $item['asset_name'] . ' (Qty: ' . $item['quantity'] . ')') ?>
</option>

<?php endif; ?>

<?php endforeach; ?>

</select>

</div>

<div class="form-row">

<label>Asset Name</label>

<input type="text" id="assetNameField" name="asset_name" readonly placeholder="Auto-filled from Inventory">

</div>

<div class="form-row">

<label>Category</label>

<input type="text" id="assetCategoryField" name="category" readonly placeholder="Auto-filled from Inventory">

</div>

<div class="form-row">

<label>Brand</label>

<input type="text" name="brand">

</div>

<div class="form-row">

<label>Model</label>

<input type="text" name="model">

</div>

<div class="form-row">

<label>Serial Number</label>

<input type="text" name="serial_number">

</div>

<div class="form-row">

<label>Acquisition Date</label>

<input type="date" id="acquisitionDate" name="acquisition_date" required>

</div>

<div class="form-row">

<label>Purchase Cost</label>

<input type="number" step="0.01" min="0" name="purchase_cost" required>

</div>

<div class="form-row">

<label>Supplier</label>

<input type="text" name="supplier">

</div>

<div class="form-row">

<label>Location</label>

<input type="text" name="location">

</div>

<div class="form-row">

<label>Remarks</label>

<textarea name="remarks"></textarea>

</div>

<button type="submit">

Save Asset

</button>

</form>

</div>
</div>