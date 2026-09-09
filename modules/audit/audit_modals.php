<?php
require_once __DIR__ . "/../../auth/check_auth.php";

if (!isset($auditAssets)) {
    require_once __DIR__ . "/../../includes/database.php";
    $auditPdo = getDbConnection();
    $auditAssets = $auditPdo->query("SELECT id, asset_id, asset_name, category, custodian, status FROM assets WHERE status <> 'Sold' ORDER BY id ASC")->fetchAll();
}
$auditResults = ['Verified', 'Missing', 'Damaged', 'For Investigation'];
$auditStatuses = ['Scheduled', 'Ongoing', 'Completed'];
?>

<div id="auditModal" class="modal pcms-modal pcms-modal--lg" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="addAuditTitle">
<div class="modal-content pcms-modal-dialog"><form id="addAuditForm" class="pcms-modal-form" method="POST" action="schedule_audit.php">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getAccessCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
<header class="pcms-modal-header"><div><span class="pcms-modal-eyebrow">Audit</span><h2 id="addAuditTitle">Add Audit</h2><p>Record a physical verification against a registered Asset.</p></div><button type="button" class="close-modal" data-modal-close aria-label="Close Add Audit"><span aria-hidden="true">&times;</span></button></header>
<div class="pcms-modal-body"><div class="pcms-form-grid">
    <div class="form-row"><label for="auditID">Audit ID</label><input type="text" id="auditID" name="audit_id" readonly></div>
    <div class="form-row"><label for="auditAssetSelect">Registered Asset</label><select id="auditAssetSelect" name="asset_id" required><option value="">Select Registered Asset</option><?php foreach ($auditAssets as $asset): ?><option value="<?= (int) $asset['id'] ?>" data-name="<?= htmlspecialchars($asset['asset_name']) ?>" data-category="<?= htmlspecialchars($asset['category']) ?>" data-custodian="<?= htmlspecialchars($asset['custodian'] ?? '') ?>" data-status="<?= htmlspecialchars($asset['status']) ?>"><?= htmlspecialchars($asset['asset_id'] . ' - ' . $asset['asset_name']) ?></option><?php endforeach; ?></select></div>
    <div class="form-row"><label for="auditAssetName">Asset Name</label><input type="text" id="auditAssetName" name="asset_name" readonly placeholder="Auto-filled from Asset Registry"></div>
    <div class="form-row"><label for="auditCategory">Category</label><input type="text" id="auditCategory" name="category" readonly placeholder="Auto-filled from Asset Registry"></div>
    <div class="form-row"><label for="auditCustodian">Custodian</label><input type="text" id="auditCustodian" name="custodian" readonly placeholder="Not Assigned"></div>
    <div class="form-row"><label for="auditAssetStatus">Current Asset Status</label><input type="text" id="auditAssetStatus" readonly placeholder="Auto-filled from Asset Registry"></div>
    <div class="form-row"><label for="auditAuditor">Auditor</label><input type="text" id="auditAuditor" name="auditor" required></div>
    <div class="form-row"><label for="auditDate">Audit Date</label><input type="date" id="auditDate" name="audit_date" required></div>
    <div class="form-row"><label for="auditResult">Audit Result</label><select id="auditResult" name="result" required><?php foreach ($auditResults as $value): ?><option value="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars($value) ?></option><?php endforeach; ?></select></div>
    <div class="form-row"><label for="auditStatus">Status</label><select id="auditStatus" name="status" required><?php foreach ($auditStatuses as $value): ?><option value="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars($value) ?></option><?php endforeach; ?></select></div>
    <div class="form-row pcms-form-span-2"><label for="auditRemarks">Remarks</label><textarea id="auditRemarks" name="remarks" rows="3"></textarea></div>
</div></div>
<footer class="pcms-modal-footer"><button type="button" class="btn btn-outline" data-modal-close>Cancel</button><button type="submit" class="btn btn-primary">Save Audit</button></footer>
</form></div></div>

<div id="viewAuditModal" class="modal pcms-modal pcms-modal--lg" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="viewAuditTitle">
<div class="modal-content pcms-modal-dialog">
<header class="pcms-modal-header"><div><span class="pcms-modal-eyebrow">Audit</span><div class="pcms-modal-title-row"><h2 id="viewAuditTitle">Audit Details</h2><span id="viewAuditStatus" class="pcms-status-badge">Scheduled</span></div><p id="viewAuditSubtitle">Audit record information</p></div><button type="button" class="close-modal" data-modal-close aria-label="Close Audit Details"><span aria-hidden="true">&times;</span></button></header>
<div class="pcms-modal-body"><section class="pcms-detail-section"><h3>Asset Snapshot</h3><dl class="pcms-detail-grid">
    <div><dt>Audit ID</dt><dd data-audit-view="audit_id">—</dd></div><div><dt>Asset ID</dt><dd data-audit-view="asset_business_id">—</dd></div>
    <div><dt>Asset</dt><dd data-audit-view="asset_name_snap">—</dd></div><div><dt>Category</dt><dd data-audit-view="category_snap">—</dd></div>
    <div class="pcms-detail-span-2"><dt>Custodian</dt><dd data-audit-view="custodian_snap">Not Assigned</dd></div>
</dl></section>
<section class="pcms-detail-section"><h3>Audit Information</h3><dl class="pcms-detail-grid">
    <div><dt>Auditor</dt><dd data-audit-view="auditor">—</dd></div><div><dt>Audit Date</dt><dd data-audit-view="audit_date">—</dd></div>
    <div><dt>Status</dt><dd data-audit-view="status">—</dd></div><div><dt>Result</dt><dd data-audit-view="result">—</dd></div>
    <div class="pcms-detail-span-2"><dt>Remarks</dt><dd data-audit-view="remarks">—</dd></div>
</dl></section></div>
<footer class="pcms-modal-footer"><button type="button" class="btn btn-outline" data-modal-close>Close</button><button type="button" class="btn btn-warning" id="editAuditFromView">Edit Audit</button></footer>
</div></div>

<div id="editAuditModal" class="modal pcms-modal pcms-modal--lg" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="editAuditTitle">
<div class="modal-content pcms-modal-dialog"><form id="editAuditForm" class="pcms-modal-form" method="POST" action="edit_audit.php">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getAccessCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
<input type="hidden" id="editAuditRowID" name="id">
<input type="hidden" id="editAuditAssetID" name="asset_id">
<header class="pcms-modal-header"><div><span class="pcms-modal-eyebrow">Audit</span><h2 id="editAuditTitle">Edit Audit</h2><p>The linked Asset remains fixed while the existing audit update rules are preserved.</p></div><button type="button" class="close-modal" data-modal-close aria-label="Close Edit Audit"><span aria-hidden="true">&times;</span></button></header>
<div class="pcms-modal-body"><div class="pcms-form-grid">
    <div class="form-row"><label for="editAuditID">Audit ID</label><input type="text" id="editAuditID" readonly></div>
    <div class="form-row"><label for="editAuditAsset">Registered Asset</label><input type="text" id="editAuditAsset" readonly></div>
    <div class="form-row"><label for="editAuditAuditor">Auditor</label><input type="text" id="editAuditAuditor" name="auditor" required></div>
    <div class="form-row"><label for="editAuditDate">Audit Date</label><input type="date" id="editAuditDate" name="audit_date" required></div>
    <div class="form-row"><label for="editAuditResult">Audit Result</label><select id="editAuditResult" name="result" required><?php foreach ($auditResults as $value): ?><option value="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars($value) ?></option><?php endforeach; ?></select></div>
    <div class="form-row"><label for="editAuditStatus">Status</label><select id="editAuditStatus" name="status" required><?php foreach ($auditStatuses as $value): ?><option value="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars($value) ?></option><?php endforeach; ?></select></div>
    <div class="form-row pcms-form-span-2"><label for="editAuditRemarks">Remarks</label><textarea id="editAuditRemarks" name="remarks" rows="4"></textarea></div>
</div></div>
<footer class="pcms-modal-footer"><button type="button" class="btn btn-outline" data-modal-close>Cancel</button><button type="submit" class="btn btn-warning">Update Audit</button></footer>
</form></div></div>
