<?php

require_once "../../auth/check_auth.php";
require_once __DIR__ . "/../../includes/database.php";

$id = filter_var(
    $_GET['id'] ?? null,
    FILTER_VALIDATE_INT,
    [
        'options' => [
            'min_range' => 1
        ]
    ]
);

if ($id === false) {
    header("Location: index.php");
    exit;
}

$pdo = getDbConnection();

$stmt = $pdo->prepare(
    "SELECT *
     FROM assets
     WHERE id = :id"
);

$stmt->execute([
    'id' => $id
]);

$asset = $stmt->fetch();

if (!$asset) {
    header("Location: index.php");
    exit;
}

include "../../includes/header.php";
include "../../includes/sidebar.php";

?>

<div class="main-content">

<h1>Edit Asset</h1>

<hr><br>

<form
    method="POST"
    action="update_asset.php"
    class="asset-form"
>

<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getAccessCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
<input type="hidden" name="id" value="<?= (int) $id ?>">

<div class="form-row">

<label>Asset ID</label>

<input
    type="text"
    value="<?= htmlspecialchars($asset['asset_id']) ?>"
    readonly
>

</div>

<div class="form-row">

<label>Asset Name</label>

<input
    type="text"
    name="asset_name"
    value="<?= htmlspecialchars($asset['asset_name']) ?>"
    required
>

</div>

<div class="form-row">

<label>Category</label>

<select name="category">

<?php

$categories = [
    'Computer',
    'Furniture',
    'Laboratory Equipment',
    'Office Equipment',
    'Electronics'
];

foreach ($categories as $category):

?>

<option
    value="<?= htmlspecialchars($category) ?>"
    <?= $asset['category'] === $category ? 'selected' : '' ?>
>
    <?= htmlspecialchars($category) ?>
</option>

<?php endforeach; ?>

</select>

</div>

<div class="form-row">

<label for="legacyEditAssetBrand">Brand</label>

<div class="pcms-typeahead" data-asset-typeahead data-suggestion-field="brand">
    <input
        type="text"
        id="legacyEditAssetBrand"
        name="brand"
        value="<?= htmlspecialchars($asset['brand'] ?? '') ?>"
        autocomplete="off"
        role="combobox"
        aria-autocomplete="list"
        aria-expanded="false"
        aria-controls="legacyEditAssetBrandSuggestions"
        data-asset-suggestion-input
    >
    <div id="legacyEditAssetBrandSuggestions" class="pcms-typeahead-list" role="listbox" aria-label="Brand suggestions" hidden></div>
</div>

</div>

<div class="form-row">

<label for="legacyEditAssetModel">Model</label>

<div class="pcms-typeahead" data-asset-typeahead data-suggestion-field="model">
    <input
        type="text"
        id="legacyEditAssetModel"
        name="model"
        value="<?= htmlspecialchars($asset['model'] ?? '') ?>"
        autocomplete="off"
        role="combobox"
        aria-autocomplete="list"
        aria-expanded="false"
        aria-controls="legacyEditAssetModelSuggestions"
        data-asset-suggestion-input
    >
    <div id="legacyEditAssetModelSuggestions" class="pcms-typeahead-list" role="listbox" aria-label="Model suggestions" hidden></div>
</div>

</div>

<div class="form-row">

<label>Serial Number</label>

<input
    type="text"
    name="serial_number"
    value="<?= htmlspecialchars($asset['serial_number'] ?? '') ?>"
>

</div>

<div class="form-row">

<label for="legacyEditAssetSupplier">Supplier</label>

<div class="pcms-typeahead" data-asset-typeahead data-suggestion-field="supplier">
    <input
        type="text"
        id="legacyEditAssetSupplier"
        name="supplier"
        value="<?= htmlspecialchars($asset['supplier'] ?? '') ?>"
        autocomplete="off"
        role="combobox"
        aria-autocomplete="list"
        aria-expanded="false"
        aria-controls="legacyEditAssetSupplierSuggestions"
        data-asset-suggestion-input
    >
    <div id="legacyEditAssetSupplierSuggestions" class="pcms-typeahead-list" role="listbox" aria-label="Supplier suggestions" hidden></div>
</div>

</div>

<div class="form-row">

<label>Location</label>

<input
    type="text"
    name="location"
    value="<?= htmlspecialchars($asset['location'] ?? '') ?>"
>

</div>

<div class="form-row">

<label>Remarks</label>

<textarea name="remarks"><?= htmlspecialchars($asset['remarks'] ?? '') ?></textarea>

</div>

<br>

<button
    class="btn"
    type="submit"
>
    Update Asset
</button>

<a
    href="index.php"
    class="btn"
>
    Cancel
</a>

</form>

</div>

<script src="<?= BASE_URL ?>assets/js/asset_typeahead.js?v=<?= filemtime(__DIR__ . '/../../assets/js/asset_typeahead.js') ?>"></script>

<?php include "../../includes/footer.php"; ?>
