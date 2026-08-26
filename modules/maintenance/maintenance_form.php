<?php

require_once __DIR__ . "/../../auth/check_auth.php";
require_once __DIR__ . "/../../includes/database.php";

$pdo = getDbConnection();

$isEdit =
    isset($maintenance) &&
    is_array($maintenance);

$formAction = $isEdit
    ? "edit_maintenance.php"
    : "save_maintenance.php";

$maintenanceIdValue = $isEdit
    ? ($maintenance['maintenance_id'] ?? '')
    : ($maintenanceIdForForm ?? '');

$availableAssets = [];

if (!$isEdit) {
    $assetStmt = $pdo->query(
        "SELECT
            id,
            asset_id,
            asset_name,
            category,
            custodian,
            status
         FROM assets
         WHERE status IN ('Available', 'Assigned')
         ORDER BY id ASC"
    );

    $availableAssets = $assetStmt->fetchAll();
}

?>

<form
    class="asset-form"
    method="POST"
    action="<?= htmlspecialchars($formAction) ?>"
>

<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getAccessCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
<?php if ($isEdit): ?>
<input type="hidden" name="id" value="<?= (int) $maintenance['id'] ?>">
<?php endif; ?>

<h2><?= $isEdit ? 'Edit Maintenance' : 'Add Maintenance' ?></h2>

<div class="form-row">

<label>Maintenance ID</label>

<input
    type="text"
    id="maintenanceID"
    name="maintenance_id"
    value="<?= htmlspecialchars($maintenanceIdValue) ?>"
    readonly
>

</div>

<div class="form-row">

<label>Registered Asset</label>

<?php if ($isEdit): ?>

<input
    type="text"
    value="<?= htmlspecialchars(
        ($maintenance['asset_business_id'] ?? '') .
        ' - ' .
        ($maintenance['current_asset_name'] ?? $maintenance['asset_name_snap'] ?? '')
    ) ?>"
    readonly
>

<input
    type="hidden"
    name="asset_id"
    value="<?= (int) $maintenance['asset_id'] ?>"
>

<?php else: ?>

<select
    name="asset_id"
    id="maintenanceAssetSelect"
    required
>

<option value="">Select Registered Asset</option>

<?php foreach ($availableAssets as $asset): ?>

<option
    value="<?= (int) $asset['id'] ?>"
    data-name="<?= htmlspecialchars($asset['asset_name']) ?>"
    data-category="<?= htmlspecialchars($asset['category']) ?>"
    data-custodian="<?= htmlspecialchars($asset['custodian'] ?? '') ?>"
    data-status="<?= htmlspecialchars($asset['status']) ?>"
>
    <?= htmlspecialchars(
        $asset['asset_id'] .
        ' - ' .
        $asset['asset_name']
    ) ?>
</option>

<?php endforeach; ?>

</select>

<?php endif; ?>

</div>

<div class="form-row">

<label>Asset Name</label>

<input
    type="text"
    id="maintenanceAssetName"
    name="asset_name"
    value="<?= htmlspecialchars(
        $maintenance['current_asset_name']
            ?? $maintenance['asset_name_snap']
            ?? ''
    ) ?>"
    readonly
    placeholder="Auto-filled from Asset Registry"
>

</div>

<div class="form-row">

<label>Category</label>

<input
    type="text"
    id="maintenanceCategory"
    name="category"
    value="<?= htmlspecialchars(
        $maintenance['current_category']
            ?? $maintenance['category_snap']
            ?? ''
    ) ?>"
    readonly
    placeholder="Auto-filled from Asset Registry"
>

</div>

<div class="form-row">

<label>Current Custodian</label>

<input
    type="text"
    id="maintenanceCustodian"
    value="<?= htmlspecialchars(
        $maintenance['current_custodian'] ?? ''
    ) ?>"
    readonly
    placeholder="Not Assigned"
>

</div>

<div class="form-row">

<label>Current Asset Status</label>

<input
    type="text"
    id="maintenanceAssetStatus"
    value="<?= htmlspecialchars(
        $maintenance['current_asset_status'] ?? ''
    ) ?>"
    readonly
    placeholder="Auto-filled from Asset Registry"
>

</div>

<div class="form-row">

<label>Maintenance Type</label>

<select name="maintenance_type" required>

<option
    value="Preventive"
    <?= (($maintenance['maintenance_type'] ?? '') === 'Preventive') ? 'selected' : '' ?>
>
Preventive
</option>

<option
    value="Corrective"
    <?= (($maintenance['maintenance_type'] ?? '') === 'Corrective') ? 'selected' : '' ?>
>
Corrective
</option>

<option
    value="Inspection"
    <?= (($maintenance['maintenance_type'] ?? '') === 'Inspection') ? 'selected' : '' ?>
>
Inspection
</option>

</select>

</div>

<div class="form-row">

<label>Scheduled Date</label>

<input
    type="date"
    name="scheduled_date"
    value="<?= htmlspecialchars($maintenance['scheduled_date'] ?? '') ?>"
    required
>

</div>

<div class="form-row">

<label>Status</label>

<select name="status" required>

<option
    value="Scheduled"
    <?= (($maintenance['status'] ?? '') === 'Scheduled') ? 'selected' : '' ?>
>
Scheduled
</option>

<option
    value="In Progress"
    <?= (($maintenance['status'] ?? '') === 'In Progress') ? 'selected' : '' ?>
>
In Progress
</option>

<option
    value="Completed"
    <?= (($maintenance['status'] ?? '') === 'Completed') ? 'selected' : '' ?>
>
Completed
</option>

</select>

</div>

<button
    type="submit"
    class="btn btn-primary"
>
    <?= $isEdit ? 'Update Maintenance' : 'Save Maintenance' ?>
</button>

</form>
