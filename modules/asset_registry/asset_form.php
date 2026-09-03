<?php require_once __DIR__ . '/../../auth/check_auth.php'; ?>

<div id="assetModal" class="modal pcms-modal pcms-modal--lg" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="registerAssetTitle">
<div class="modal-content pcms-modal-dialog">
<form class="pcms-modal-form" method="POST" action="register_asset.php" id="registerAssetForm">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getAccessCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">

<header class="pcms-modal-header">
    <div><span class="pcms-modal-eyebrow">Asset Registry</span><h2 id="registerAssetTitle">Register New Asset</h2><p>Create a tagged property record from available Inventory.</p></div>
    <button type="button" class="close-modal" data-modal-close aria-label="Close Register Asset"><span aria-hidden="true">&times;</span></button>
</header>

<div class="pcms-modal-body"><div class="pcms-form-grid">
    <div class="form-row"><label for="assetID">Asset ID</label><input type="text" id="assetID" name="asset_id" readonly></div>

    <div class="form-row pcms-form-span-2">
        <label for="inventoryItemSearch">Select Available Inventory Item</label>
        <div class="pcms-typeahead" id="inventoryOptionTypeahead">
            <input type="hidden" name="inventory_id" id="inventoryItemID">
            <input
                type="search"
                id="inventoryItemSearch"
                autocomplete="off"
                role="combobox"
                aria-autocomplete="list"
                aria-expanded="false"
                aria-controls="inventoryItemSuggestions"
                placeholder="Type at least 2 characters..."
                required
            >
            <div id="inventoryItemSuggestions" class="pcms-typeahead-list" role="listbox" aria-label="Available Inventory items" hidden></div>
        </div>
        <small>Search by Inventory ID, Asset Name, Category, or Condition. Up to 10 available results are shown.</small>
    </div>

    <div class="form-row"><label for="assetNameField">Asset Name</label><input type="text" id="assetNameField" readonly placeholder="Auto-filled from Inventory"></div>
    <div class="form-row"><label for="assetCategoryField">Category</label><input type="text" id="assetCategoryField" readonly placeholder="Auto-filled from Inventory"></div>

    <div class="form-row"><label for="registerAssetBrand">Brand</label><div class="pcms-typeahead" data-asset-typeahead data-suggestion-field="brand"><input type="text" id="registerAssetBrand" name="brand" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="registerAssetBrandSuggestions" data-asset-suggestion-input><div id="registerAssetBrandSuggestions" class="pcms-typeahead-list" role="listbox" aria-label="Brand suggestions" hidden></div></div></div>
    <div class="form-row"><label for="registerAssetModel">Model</label><div class="pcms-typeahead" data-asset-typeahead data-suggestion-field="model"><input type="text" id="registerAssetModel" name="model" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="registerAssetModelSuggestions" data-asset-suggestion-input><div id="registerAssetModelSuggestions" class="pcms-typeahead-list" role="listbox" aria-label="Model suggestions" hidden></div></div></div>
    <div class="form-row"><label for="registerAssetSerial">Serial Number</label><input type="text" id="registerAssetSerial" name="serial_number"></div>
    <div class="form-row"><label for="acquisitionDate">Acquisition Date</label><input type="date" id="acquisitionDate" name="acquisition_date" required></div>
    <div class="form-row"><label for="registerAssetCost">Purchase Cost</label><input type="number" id="registerAssetCost" step="0.01" min="0" name="purchase_cost" required></div>
    <div class="form-row"><label for="registerAssetSupplier">Supplier</label><div class="pcms-typeahead" data-asset-typeahead data-suggestion-field="supplier"><input type="text" id="registerAssetSupplier" name="supplier" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="registerAssetSupplierSuggestions" data-asset-suggestion-input><div id="registerAssetSupplierSuggestions" class="pcms-typeahead-list" role="listbox" aria-label="Supplier suggestions" hidden></div></div></div>
    <div class="form-row pcms-form-span-2"><label for="registerAssetLocation">Location</label><input type="text" id="registerAssetLocation" name="location"></div>
    <div class="form-row pcms-form-span-2"><label for="registerAssetRemarks">Remarks</label><textarea id="registerAssetRemarks" name="remarks" rows="3"></textarea></div>
</div></div>

<footer class="pcms-modal-footer"><button type="button" class="btn btn-outline" data-modal-close>Cancel</button><button type="submit" class="btn btn-primary">Save Asset</button></footer>
</form>
</div>
</div>
