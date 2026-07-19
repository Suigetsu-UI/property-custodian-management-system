<?php $form_action = isset($id) ? "edit_maintenance.php?id={$id}" : "save_maintenance.php"; ?>
<form class="asset-form" method="POST" action="<?= $form_action ?>">

<div class="form-row">

<label>Maintenance ID</label>

<input
    type="text"
    name="maintenance_id"
    value="<?= $maintenance['maintenance_id'] ?? 'MNT-000001'; ?>"
    readonly
>

</div>

<div class="form-row">

<label>Asset Name</label>

<input
    type="text"
    name="asset_name"
    value="<?= htmlspecialchars($maintenance['asset_name'] ?? '') ?>"
    required
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

<button
type="submit"
class="btn btn-primary">

<?= isset($maintenance) ? "Update Maintenance" : "Save Maintenance"; ?>

</button>

</form>