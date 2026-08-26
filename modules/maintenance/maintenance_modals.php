<?php
require_once __DIR__ . "/../../auth/check_auth.php";

if (!isset($maintenanceAssets)) {
    require_once __DIR__ . "/../../includes/database.php";
    $maintenancePdo = getDbConnection();
    $maintenanceAssets = $maintenancePdo->query(
        "SELECT id, asset_id, asset_name, category, custodian, status FROM assets WHERE status IN ('Available', 'Assigned') ORDER BY id ASC"
    )->fetchAll();
}
$maintenanceTypes = ['Preventive', 'Corrective', 'Inspection'];
$maintenanceStatuses = ['Scheduled', 'In Progress', 'Completed'];
?>

<div id="maintenanceModal" class="modal pcms-modal pcms-modal--lg" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="addMaintenanceTitle">
<div class="modal-content pcms-modal-dialog"><form id="addMaintenanceForm" class="pcms-modal-form" method="POST" action="save_maintenance.php">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getAccessCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
<header class="pcms-modal-header"><div><span class="pcms-modal-eyebrow">Maintenance</span><h2 id="addMaintenanceTitle">Add Maintenance</h2><p>Schedule work against an eligible registered Asset.</p></div><button type="button" class="close-modal" data-modal-close aria-label="Close Add Maintenance"><span aria-hidden="true">&times;</span></button></header>
<div class="pcms-modal-body"><div class="pcms-form-grid">
    <div class="form-row"><label for="maintenanceID">Maintenance ID</label><input type="text" id="maintenanceID" name="maintenance_id" readonly></div>
    <div class="form-row"><label for="maintenanceAssetSelect">Registered Asset</label><select id="maintenanceAssetSelect" name="asset_id" required><option value="">Select Registered Asset</option><?php foreach ($maintenanceAssets as $asset): ?><option value="<?= (int) $asset['id'] ?>" data-name="<?= htmlspecialchars($asset['asset_name']) ?>" data-category="<?= htmlspecialchars($asset['category']) ?>" data-custodian="<?= htmlspecialchars($asset['custodian'] ?? '') ?>" data-status="<?= htmlspecialchars($asset['status']) ?>"><?= htmlspecialchars($asset['asset_id'] . ' - ' . $asset['asset_name']) ?></option><?php endforeach; ?></select></div>
    <div class="form-row"><label for="maintenanceAssetName">Asset Name</label><input type="text" id="maintenanceAssetName" name="asset_name" readonly placeholder="Auto-filled from Asset Registry"></div>
    <div class="form-row"><label for="maintenanceCategory">Category</label><input type="text" id="maintenanceCategory" name="category" readonly placeholder="Auto-filled from Asset Registry"></div>
    <div class="form-row"><label for="maintenanceCustodian">Current Custodian</label><input type="text" id="maintenanceCustodian" readonly placeholder="Not Assigned"></div>
    <div class="form-row"><label for="maintenanceAssetStatus">Current Asset Status</label><input type="text" id="maintenanceAssetStatus" readonly placeholder="Auto-filled from Asset Registry"></div>
    <div class="form-row"><label for="maintenanceType">Maintenance Type</label><select id="maintenanceType" name="maintenance_type" required><?php foreach ($maintenanceTypes as $value): ?><option value="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars($value) ?></option><?php endforeach; ?></select></div>
    <div class="form-row"><label for="maintenanceDate">Scheduled Date</label><input type="date" id="maintenanceDate" name="scheduled_date" required></div>
    <div class="form-row pcms-form-span-2"><label for="maintenanceStatus">Status</label><select id="maintenanceStatus" name="status" required><?php foreach ($maintenanceStatuses as $value): ?><option value="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars($value) ?></option><?php endforeach; ?></select></div>
</div></div>
<footer class="pcms-modal-footer"><button type="button" class="btn btn-outline" data-modal-close>Cancel</button><button type="submit" class="btn btn-primary">Save Maintenance</button></footer>
</form></div></div>

<div id="viewMaintenanceModal" class="modal pcms-modal pcms-modal--lg" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="viewMaintenanceTitle">
<div class="modal-content pcms-modal-dialog">
<header class="pcms-modal-header"><div><span class="pcms-modal-eyebrow">Maintenance</span><div class="pcms-modal-title-row"><h2 id="viewMaintenanceTitle">Maintenance Details</h2><span id="viewMaintenanceStatus" class="pcms-status-badge">Scheduled</span></div><p id="viewMaintenanceSubtitle">Maintenance record information</p></div><button type="button" class="close-modal" data-modal-close aria-label="Close Maintenance Details"><span aria-hidden="true">&times;</span></button></header>
<div class="pcms-modal-body"><section class="pcms-detail-section"><dl class="pcms-detail-grid">
    <div><dt>Maintenance ID</dt><dd data-maintenance-view="maintenance_id">—</dd></div><div><dt>Asset ID</dt><dd data-maintenance-view="asset_business_id">—</dd></div>
    <div><dt>Asset Name</dt><dd data-maintenance-view="current_asset_name">—</dd></div><div><dt>Current Custodian</dt><dd data-maintenance-view="current_custodian">Not Assigned</dd></div>
    <div><dt>Maintenance Type</dt><dd data-maintenance-view="maintenance_type">—</dd></div><div><dt>Scheduled Date</dt><dd data-maintenance-view="scheduled_date">—</dd></div>
    <div class="pcms-detail-span-2"><dt>Status</dt><dd data-maintenance-view="status">—</dd></div>
</dl></section></div>
<footer class="pcms-modal-footer"><button type="button" class="btn btn-outline" data-modal-close>Close</button><button type="button" class="btn btn-warning" id="editMaintenanceFromView">Edit Maintenance</button></footer>
</div></div>

<div id="editMaintenanceModal" class="modal pcms-modal pcms-modal--md" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="editMaintenanceTitle">
<div class="modal-content pcms-modal-dialog"><form id="editMaintenanceForm" class="pcms-modal-form" method="POST" action="edit_maintenance.php">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getAccessCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
<input type="hidden" id="editMaintenanceRowID" name="id">
<input type="hidden" id="editMaintenanceAssetID" name="asset_id">
<header class="pcms-modal-header"><div><span class="pcms-modal-eyebrow">Maintenance</span><h2 id="editMaintenanceTitle">Edit Maintenance</h2><p>The linked Asset remains fixed while schedule and status are updated.</p></div><button type="button" class="close-modal" data-modal-close aria-label="Close Edit Maintenance"><span aria-hidden="true">&times;</span></button></header>
<div class="pcms-modal-body"><div class="pcms-form-grid">
    <div class="form-row"><label for="editMaintenanceID">Maintenance ID</label><input type="text" id="editMaintenanceID" readonly></div>
    <div class="form-row"><label for="editMaintenanceAsset">Registered Asset</label><input type="text" id="editMaintenanceAsset" readonly></div>
    <div class="form-row"><label for="editMaintenanceType">Maintenance Type</label><select id="editMaintenanceType" name="maintenance_type" required><?php foreach ($maintenanceTypes as $value): ?><option value="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars($value) ?></option><?php endforeach; ?></select></div>
    <div class="form-row"><label for="editMaintenanceDate">Scheduled Date</label><input type="date" id="editMaintenanceDate" name="scheduled_date" required></div>
    <div class="form-row pcms-form-span-2"><label for="editMaintenanceStatus">Status</label><select id="editMaintenanceStatus" name="status" required><?php foreach ($maintenanceStatuses as $value): ?><option value="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars($value) ?></option><?php endforeach; ?></select></div>
</div></div>
<footer class="pcms-modal-footer"><button type="button" class="btn btn-outline" data-modal-close>Cancel</button><button type="submit" class="btn btn-warning">Update Maintenance</button></footer>
</form></div></div>
