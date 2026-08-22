<div
    id="viewAssetModal"
    class="modal pcms-modal pcms-modal--lg"
    role="dialog"
    aria-modal="true"
    aria-hidden="true"
    aria-labelledby="viewAssetTitle"
>

<div class="modal-content pcms-modal-dialog">

<header class="pcms-modal-header">

<div>
    <span class="pcms-modal-eyebrow">Asset Registry</span>
    <div class="pcms-modal-title-row">
        <h2 id="viewAssetTitle">Asset Details</h2>
        <span id="viewAssetStatus" class="pcms-status-badge">Available</span>
    </div>
    <p id="viewAssetSubtitle">Asset record information</p>
</div>

<button
    type="button"
    class="close-modal"
    data-modal-close
    aria-label="Close Asset Details"
>
    <span aria-hidden="true">&times;</span>
</button>

</header>

<div class="pcms-modal-body">

<section class="pcms-detail-section">

<h3>General Information</h3>

<dl class="pcms-detail-grid">
    <div><dt>Asset ID</dt><dd data-view-field="asset_id">—</dd></div>
    <div><dt>Asset Name</dt><dd data-view-field="asset_name">—</dd></div>
    <div><dt>Category</dt><dd data-view-field="category">—</dd></div>
    <div><dt>Location</dt><dd data-view-field="location">—</dd></div>
    <div><dt>Brand</dt><dd data-view-field="brand">—</dd></div>
    <div><dt>Model</dt><dd data-view-field="model">—</dd></div>
    <div><dt>Serial Number</dt><dd data-view-field="serial_number">—</dd></div>
    <div><dt>Supplier</dt><dd data-view-field="supplier">—</dd></div>
</dl>

</section>

<section class="pcms-detail-section">

<h3>Acquisition Information</h3>

<dl class="pcms-detail-grid">
    <div><dt>Acquisition Date</dt><dd data-view-field="acquisition_date">—</dd></div>
    <div><dt>Purchase Cost</dt><dd data-view-field="purchase_cost">—</dd></div>
    <div class="pcms-detail-span-2"><dt>Remarks</dt><dd data-view-field="remarks">—</dd></div>
</dl>

</section>

<section class="pcms-detail-section">

<h3>Custody Information</h3>

<dl class="pcms-detail-grid">
    <div><dt>Employee ID</dt><dd data-view-field="employee_id">Not Assigned</dd></div>
    <div><dt>Custodian</dt><dd data-view-field="custodian">Not Assigned</dd></div>
    <div><dt>Department</dt><dd data-view-field="department">Not Assigned</dd></div>
    <div><dt>Date Assigned</dt><dd data-view-field="date_assigned">Not Assigned</dd></div>
</dl>

</section>

</div>

<footer class="pcms-modal-footer">

<button type="button" class="btn btn-outline" data-modal-close>
    Close
</button>

<button type="button" class="btn btn-warning" id="editAssetFromView">
    Edit Asset
</button>

</footer>

</div>

</div>

<div
    id="editAssetModal"
    class="modal pcms-modal pcms-modal--lg"
    role="dialog"
    aria-modal="true"
    aria-hidden="true"
    aria-labelledby="editAssetTitle"
>

<div class="modal-content pcms-modal-dialog">

<form
    id="editAssetForm"
    class="pcms-modal-form"
    method="POST"
    action="update_asset.php"
>

<header class="pcms-modal-header">

<div>
    <span class="pcms-modal-eyebrow">Asset Registry</span>
    <h2 id="editAssetTitle">Edit Asset</h2>
    <p>Update the property details while keeping its Asset ID and inventory link unchanged.</p>
</div>

<button
    type="button"
    class="close-modal"
    data-modal-close
    aria-label="Close Edit Asset"
>
    <span aria-hidden="true">&times;</span>
</button>

</header>

<div class="pcms-modal-body">

<div class="pcms-form-grid">

<div class="form-row">
    <label for="editAssetID">Asset ID</label>
    <input type="text" id="editAssetID" readonly>
</div>

<div class="form-row">
    <label for="editAssetName">Asset Name</label>
    <input type="text" id="editAssetName" name="asset_name" required>
</div>

<div class="form-row">
    <label for="editAssetCategory">Category</label>
    <select id="editAssetCategory" name="category" required>
        <option value="Computer">Computer</option>
        <option value="Furniture">Furniture</option>
        <option value="Laboratory Equipment">Laboratory Equipment</option>
        <option value="Office Equipment">Office Equipment</option>
        <option value="Electronics">Electronics</option>
    </select>
</div>

<div class="form-row">
    <label for="editAssetBrand">Brand</label>
    <div class="pcms-typeahead" data-asset-typeahead data-suggestion-field="brand">
        <input type="text" id="editAssetBrand" name="brand" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="editAssetBrandSuggestions" data-asset-suggestion-input>
        <div id="editAssetBrandSuggestions" class="pcms-typeahead-list" role="listbox" aria-label="Brand suggestions" hidden></div>
    </div>
</div>

<div class="form-row">
    <label for="editAssetModel">Model</label>
    <div class="pcms-typeahead" data-asset-typeahead data-suggestion-field="model">
        <input type="text" id="editAssetModel" name="model" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="editAssetModelSuggestions" data-asset-suggestion-input>
        <div id="editAssetModelSuggestions" class="pcms-typeahead-list" role="listbox" aria-label="Model suggestions" hidden></div>
    </div>
</div>

<div class="form-row">
    <label for="editAssetSerial">Serial Number</label>
    <input type="text" id="editAssetSerial" name="serial_number">
</div>

<div class="form-row">
    <label for="editAssetSupplier">Supplier</label>
    <div class="pcms-typeahead" data-asset-typeahead data-suggestion-field="supplier">
        <input type="text" id="editAssetSupplier" name="supplier" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="editAssetSupplierSuggestions" data-asset-suggestion-input>
        <div id="editAssetSupplierSuggestions" class="pcms-typeahead-list" role="listbox" aria-label="Supplier suggestions" hidden></div>
    </div>
</div>

<div class="form-row">
    <label for="editAssetLocation">Location</label>
    <input type="text" id="editAssetLocation" name="location">
</div>

<div class="form-row pcms-form-span-2">
    <label for="editAssetRemarks">Remarks</label>
    <textarea id="editAssetRemarks" name="remarks" rows="4"></textarea>
</div>

</div>

</div>

<footer class="pcms-modal-footer">

<button type="button" class="btn btn-outline" data-modal-close>
    Cancel
</button>

<button type="submit" class="btn btn-warning">
    Update Asset
</button>

</footer>

</form>

</div>

</div>

<div
    id="assignCustodianModal"
    class="modal pcms-modal pcms-modal--md"
    role="dialog"
    aria-modal="true"
    aria-hidden="true"
    aria-labelledby="assignCustodianTitle"
>

<div class="modal-content pcms-modal-dialog">

<form
    id="assignCustodianForm"
    class="pcms-modal-form"
    method="POST"
    action="save_assignment.php"
>

<header class="pcms-modal-header">

<div>
    <span class="pcms-modal-eyebrow">Custody Assignment</span>
    <h2 id="assignCustodianTitle">Assign Custodian</h2>
    <p>Record who is responsible for this available asset.</p>
</div>

<button
    type="button"
    class="close-modal"
    data-modal-close
    aria-label="Close Assign Custodian"
>
    <span aria-hidden="true">&times;</span>
</button>

</header>

<div class="pcms-modal-body">

<div class="pcms-selected-asset" aria-label="Selected asset">
    <span>Selected Asset</span>
    <strong id="assignAssetLabel">—</strong>
</div>

<div class="pcms-form-grid">

<div class="form-row">
    <label for="assignEmployeeID">Employee ID</label>
    <input
        type="text"
        id="assignEmployeeID"
        name="employee_id"
        placeholder="Enter Employee ID"
        required
    >
</div>

<div class="form-row">
    <label for="assignCustodianName">Custodian Name</label>
    <input
        type="text"
        id="assignCustodianName"
        name="custodian"
        placeholder="Enter Custodian Name"
        required
    >
</div>

<div class="form-row">
    <label for="assignDepartment">Department</label>
    <select id="assignDepartment" name="department" required>
        <option value="ICT Office">ICT Office</option>
        <option value="Registrar">Registrar</option>
        <option value="Accounting">Accounting</option>
        <option value="Library">Library</option>
        <option value="Guidance Office">Guidance Office</option>
    </select>
</div>

<div class="form-row">
    <label for="assignDate">Date Assigned</label>
    <input type="date" id="assignDate" name="date_assigned" required>
</div>

</div>

</div>

<footer class="pcms-modal-footer">

<button type="button" class="btn btn-outline" data-modal-close>
    Cancel
</button>

<button type="submit" class="btn btn-success">
    Assign Asset
</button>

</footer>

</form>

</div>

</div>
