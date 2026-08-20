<?php

require_once __DIR__ . "/../../includes/database.php";

$pdo = getDbConnection();

$inventoryStmt = $pdo->query(
    "SELECT
        id,
        inventory_id,
        asset_name,
        category,
        quantity
     FROM inventory
     WHERE quantity > 0
     ORDER BY id ASC"
);

$availableInventoryRows = $inventoryStmt->fetchAll();

?>

<div
    id="assetModal"
    class="modal pcms-modal pcms-modal--lg"
    role="dialog"
    aria-modal="true"
    aria-hidden="true"
    aria-labelledby="registerAssetTitle"
>

<div class="modal-content pcms-modal-dialog">

<form
    class="pcms-modal-form"
    method="POST"
    action="register_asset.php"
>

<header class="pcms-modal-header">

<div>
    <span class="pcms-modal-eyebrow">Asset Registry</span>
    <h2 id="registerAssetTitle">Register New Asset</h2>
    <p>Create a tagged property record from available inventory.</p>
</div>

<button
    type="button"
    class="close-modal"
    data-modal-close
    aria-label="Close Register Asset"
>
    <span aria-hidden="true">&times;</span>
</button>

</header>

<div class="pcms-modal-body">

<div class="pcms-form-grid">

<div class="form-row">
    <label for="assetID">Asset ID</label>
    <input type="text" id="assetID" name="asset_id" value="" readonly>
</div>

<div class="form-row">
    <label for="assetQrPreview">QR Code</label>
    <input
        type="text"
        id="assetQrPreview"
        value="Automatically Generated After Saving"
        readonly
    >
</div>

<div class="form-row pcms-form-span-2">

<label for="inventoryItemSelect">Select Inventory Item</label>

<select name="inventory_id" id="inventoryItemSelect" required>

<option value="">Select Inventory Item</option>

<?php foreach ($availableInventoryRows as $item): ?>

<option
    value="<?= (int) $item['id'] ?>"
    data-name="<?= htmlspecialchars($item['asset_name']) ?>"
    data-category="<?= htmlspecialchars($item['category']) ?>"
>
    <?= htmlspecialchars(
        $item['inventory_id'] .
        ' - ' .
        $item['asset_name'] .
        ' (Qty: ' .
        $item['quantity'] .
        ')'
    ) ?>
</option>

<?php endforeach; ?>

</select>

</div>

<div class="form-row">
    <label for="assetNameField">Asset Name</label>
    <input
        type="text"
        id="assetNameField"
        name="asset_name"
        readonly
        placeholder="Auto-filled from Inventory"
    >
</div>

<div class="form-row">
    <label for="assetCategoryField">Category</label>
    <input
        type="text"
        id="assetCategoryField"
        name="category"
        readonly
        placeholder="Auto-filled from Inventory"
    >
</div>

<div class="form-row">
    <label for="registerAssetBrand">Brand</label>
    <input type="text" id="registerAssetBrand" name="brand">
</div>

<div class="form-row">
    <label for="registerAssetModel">Model</label>
    <input type="text" id="registerAssetModel" name="model">
</div>

<div class="form-row">
    <label for="registerAssetSerial">Serial Number</label>
    <input type="text" id="registerAssetSerial" name="serial_number">
</div>

<div class="form-row">
    <label for="acquisitionDate">Acquisition Date</label>
    <input
        type="date"
        id="acquisitionDate"
        name="acquisition_date"
        required
    >
</div>

<div class="form-row">
    <label for="registerAssetCost">Purchase Cost</label>
    <input
        type="number"
        id="registerAssetCost"
        step="0.01"
        min="0"
        name="purchase_cost"
        required
    >
</div>

<div class="form-row">
    <label for="registerAssetSupplier">Supplier</label>
    <input type="text" id="registerAssetSupplier" name="supplier">
</div>

<div class="form-row pcms-form-span-2">
    <label for="registerAssetLocation">Location</label>
    <input type="text" id="registerAssetLocation" name="location">
</div>

<div class="form-row pcms-form-span-2">
    <label for="registerAssetRemarks">Remarks</label>
    <textarea id="registerAssetRemarks" name="remarks" rows="3"></textarea>
</div>

</div>

</div>

<footer class="pcms-modal-footer">

<button type="button" class="btn btn-outline" data-modal-close>
    Cancel
</button>

<button type="submit" class="btn btn-primary">
    Save Asset
</button>

</footer>

</form>

</div>

</div>
