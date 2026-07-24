<?php
require_once __DIR__ . "/../../includes/asset_functions.php";
$form_action = isset($id) ? "edit_maintenance.php?id={$id}" : "save_maintenance.php";
$default_maintenance_id = generateMaintenanceID();
?>
<form class="asset-form" method="POST" action="<?= $form_action ?>">

<h2><?= isset($maintenance) ? "Edit Maintenance" : "Add Maintenance" ?></h2>

<div class="form-row">

<label>Maintenance ID</label>

<input
    type="text"
    id="maintenanceID"
    name="maintenance_id"
    value="<?= htmlspecialchars($maintenance['maintenance_id'] ?? $default_maintenance_id) ?>"
    readonly
>

</div>

<div class="form-row">

<label>Registered Asset</label>

<?php if (isset($maintenance)): ?>

<input
    type="text"
    value="<?= htmlspecialchars((($maintenance['asset_id'] ?? '') . ' - ' . ($maintenance['asset_name'] ?? ''))) ?>"
    readonly
>

<input type="hidden" name="asset_id" value="<?= htmlspecialchars($maintenance['asset_id'] ?? '') ?>">

<?php else: ?>

<select name="asset_id" id="maintenanceAssetSelect" required>

<option value="">Select Registered Asset</option>

<?php foreach (($_SESSION['assets'] ?? []) as $asset): ?>

<?php if (in_array($asset['status'] ?? 'Available', ['Available', 'Assigned'], true)): ?>

<option
    value="<?= htmlspecialchars($asset['asset_id']) ?>"
    data-name="<?= htmlspecialchars($asset['asset_name']) ?>"
    data-category="<?= htmlspecialchars($asset['category']) ?>"
    data-custodian="<?= htmlspecialchars($asset['custodian'] ?? '') ?>"
>
<?= htmlspecialchars($asset['asset_id'] . ' - ' . $asset['asset_name']) ?>
</option>

<?php endif; ?>

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
    value="<?= htmlspecialchars($maintenance['asset_name'] ?? '') ?>"
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
    value="<?= htmlspecialchars($maintenance['category'] ?? '') ?>"
    readonly
    placeholder="Auto-filled from Asset Registry"
>

</div>

<div class="form-row">

<label>Current Custodian</label>

<input
    type="text"
    id="maintenanceCustodian"
    value="<?= htmlspecialchars($maintenance['custodian'] ?? '') ?>"
    readonly
    placeholder="Not Assigned"
>

</div>

<div class="form-row">

<label>Maintenance Type</label>

<select name="maintenance_type">

<option <?= (($maintenance['maintenance_type'] ?? '') == 'Preventive') ? 'selected' : '' ?>>
Preventive
</option>

<option <?= (($maintenance['maintenance_type'] ?? '') == 'Corrective') ? 'selected' : '' ?>>
Corrective
</option>

<option <?= (($maintenance['maintenance_type'] ?? '') == 'Inspection') ? 'selected' : '' ?>>
Inspection
</option>

</select>

</div>

<div class="form-row">

<label>Scheduled Date</label>

<input
    type="date"
    name="scheduled_date"
    value="<?= $maintenance['scheduled_date'] ?? '' ?>"
    required
>

</div>

<div class="form-row">

<label>Status</label>

<select name="status">

<option <?= (($maintenance['status'] ?? '') == 'Scheduled') ? 'selected' : '' ?>>
Scheduled
</option>

<option <?= (($maintenance['status'] ?? '') == 'In Progress') ? 'selected' : '' ?>>
In Progress
</option>

<option <?= (($maintenance['status'] ?? '') == 'Completed') ? 'selected' : '' ?>>
Completed
</option>

</select>

</div>

<input
    type="hidden"
    name="id"
    value="<?= htmlspecialchars((string) ($id ?? '')) ?>">

<button
type="submit"
class="btn btn-primary">

<?= isset($maintenance) ? "Update Maintenance" : "Save Maintenance"; ?>

</button>

</form>