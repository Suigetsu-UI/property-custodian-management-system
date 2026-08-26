<?php

require_once __DIR__ . "/../../auth/check_auth.php";
require_once __DIR__ . "/../../includes/database.php";

$pdo = getDbConnection();

$isEdit =
    isset($audit) &&
    is_array($audit);

$formAction = $isEdit
    ? "edit_audit.php"
    : "schedule_audit.php";

$auditIdValue = $isEdit
    ? ($audit['audit_id'] ?? '')
    : ($auditIdForForm ?? '');

$assetRows = [];

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
         ORDER BY id ASC"
    );

    $assetRows = $assetStmt->fetchAll();
}

?>

<form
    class="asset-form"
    method="POST"
    action="<?= htmlspecialchars($formAction) ?>"
>

<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getAccessCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
<?php if ($isEdit): ?>
<input type="hidden" name="id" value="<?= (int) $audit['id'] ?>">
<?php endif; ?>

<div class="form-row">

<label>Audit ID</label>

<input
    type="text"
    id="auditID"
    name="audit_id"
    value="<?= htmlspecialchars($auditIdValue) ?>"
    readonly
>

</div>

<div class="form-row">

<label>Registered Asset</label>

<?php if ($isEdit): ?>

<input
    type="text"
    value="<?= htmlspecialchars(
        ($audit['asset_business_id'] ?? '') .
        ' - ' .
        ($audit['current_asset_name'] ?? $audit['asset_name_snap'] ?? '')
    ) ?>"
    readonly
>

<input
    type="hidden"
    name="asset_id"
    value="<?= (int) $audit['asset_id'] ?>"
>

<?php else: ?>

<select
    name="asset_id"
    id="auditAssetSelect"
    required
>

<option value="">Select Registered Asset</option>

<?php foreach ($assetRows as $asset): ?>

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
    id="auditAssetName"
    name="asset_name"
    value="<?= htmlspecialchars(
        $audit['current_asset_name']
            ?? $audit['asset_name_snap']
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
    id="auditCategory"
    name="category"
    value="<?= htmlspecialchars(
        $audit['current_category']
            ?? $audit['category_snap']
            ?? ''
    ) ?>"
    readonly
    placeholder="Auto-filled from Asset Registry"
>

</div>

<div class="form-row">

<label>Custodian</label>

<input
    type="text"
    id="auditCustodian"
    name="custodian"
    value="<?= htmlspecialchars(
        $audit['current_custodian']
            ?? $audit['custodian_snap']
            ?? ''
    ) ?>"
    readonly
    placeholder="Not Assigned"
>

</div>

<div class="form-row">

<label>Current Asset Status</label>

<input
    type="text"
    id="auditAssetStatus"
    value="<?= htmlspecialchars(
        $audit['current_asset_status'] ?? ''
    ) ?>"
    readonly
    placeholder="Auto-filled from Asset Registry"
>

</div>

<div class="form-row">

<label>Auditor</label>

<input
    type="text"
    name="auditor"
    value="<?= htmlspecialchars($audit['auditor'] ?? '') ?>"
    required
>

</div>

<div class="form-row">

<label>Audit Date</label>

<input
    type="date"
    name="audit_date"
    value="<?= htmlspecialchars($audit['audit_date'] ?? '') ?>"
    required
>

</div>

<div class="form-row">

<label>Audit Result</label>

<select name="result" required>

<option
    value="Verified"
    <?= (($audit['result'] ?? '') === 'Verified') ? 'selected' : '' ?>
>
Verified
</option>

<option
    value="Missing"
    <?= (($audit['result'] ?? '') === 'Missing') ? 'selected' : '' ?>
>
Missing
</option>

<option
    value="Damaged"
    <?= (($audit['result'] ?? '') === 'Damaged') ? 'selected' : '' ?>
>
Damaged
</option>

<option
    value="For Investigation"
    <?= (($audit['result'] ?? '') === 'For Investigation') ? 'selected' : '' ?>
>
For Investigation
</option>

</select>

</div>

<div class="form-row">

<label>Remarks</label>

<textarea name="remarks"><?= htmlspecialchars($audit['remarks'] ?? '') ?></textarea>

</div>

<div class="form-row">

<label>Status</label>

<select name="status" required>

<option
    value="Scheduled"
    <?= (($audit['status'] ?? '') === 'Scheduled') ? 'selected' : '' ?>
>
Scheduled
</option>

<option
    value="Ongoing"
    <?= (($audit['status'] ?? '') === 'Ongoing') ? 'selected' : '' ?>
>
Ongoing
</option>

<option
    value="Completed"
    <?= (($audit['status'] ?? '') === 'Completed') ? 'selected' : '' ?>
>
Completed
</option>

</select>

</div>

<button
    type="submit"
    class="btn btn-primary"
>
    <?= $isEdit ? 'Update Audit' : 'Save Audit' ?>
</button>

</form>
