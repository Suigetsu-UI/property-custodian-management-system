<?php
require_once __DIR__ . "/../../includes/asset_functions.php";
$form_action = isset($id) && $id !== null ? "edit_audit.php?id={$id}" : "schedule_audit.php";
$default_audit_id = generateAuditID();
$linkedAsset = isset($audit['asset_id']) && $audit['asset_id'] !== '' ? getAssetByID($audit['asset_id']) : null;
?>
<form class="asset-form" method="POST" action="<?= $form_action ?>">

    <input type="hidden" name="id" value="<?= htmlspecialchars((string) ($id ?? '')) ?>">

    <div class="form-row">

        <label>Audit ID</label>

        <input
            type="text"
            id="auditID"
            name="audit_id"
            value="<?= htmlspecialchars($audit['audit_id'] ?? $default_audit_id); ?>"
            readonly>

    </div>

    <div class="form-row">

        <label>Registered Asset</label>

        <?php if (isset($audit)): ?>

        <input
            type="text"
            value="<?= htmlspecialchars((($audit['asset_id'] ?? '') . ' - ' . ($linkedAsset['asset_name'] ?? $audit['asset_name'] ?? ''))) ?>"
            readonly>

        <input type="hidden" name="asset_id" value="<?= htmlspecialchars($audit['asset_id'] ?? '') ?>">

        <?php else: ?>

        <select name="asset_id" id="auditAssetSelect" required>

            <option value="">Select Registered Asset</option>

            <?php foreach (($_SESSION['assets'] ?? []) as $asset): ?>

            <option
                value="<?= htmlspecialchars($asset['asset_id']) ?>"
                data-name="<?= htmlspecialchars($asset['asset_name']) ?>"
                data-category="<?= htmlspecialchars($asset['category']) ?>"
                data-custodian="<?= htmlspecialchars($asset['custodian'] ?? '') ?>"
                data-status="<?= htmlspecialchars($asset['status'] ?? 'Available') ?>"
            >
            <?= htmlspecialchars($asset['asset_id'] . ' - ' . $asset['asset_name']) ?>
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
            value="<?= htmlspecialchars($linkedAsset['asset_name'] ?? ($audit['asset_name'] ?? '')) ?>"
            readonly
            placeholder="Auto-filled from Asset Registry">

    </div>

    <div class="form-row">

        <label>Category</label>

        <input
            type="text"
            id="auditCategory"
            name="category"
            value="<?= htmlspecialchars($linkedAsset['category'] ?? ($audit['category'] ?? '')) ?>"
            readonly
            placeholder="Auto-filled from Asset Registry">

    </div>

    <div class="form-row">

        <label>Custodian</label>

        <input
            type="text"
            id="auditCustodian"
            name="custodian"
            value="<?= htmlspecialchars($linkedAsset['custodian'] ?? ($audit['custodian'] ?? '')) ?>"
            readonly
            placeholder="Not Assigned">

    </div>

    <div class="form-row">

        <label>Current Asset Status</label>

        <input
            type="text"
            id="auditAssetStatus"
            value="<?= htmlspecialchars($linkedAsset['status'] ?? '') ?>"
            readonly
            placeholder="Auto-filled from Asset Registry">

    </div>

    <div class="form-row">

        <label>Auditor</label>

        <input
            type="text"
            name="auditor"
            value="<?= htmlspecialchars($audit['auditor'] ?? '') ?>"
            required>

    </div>

    <div class="form-row">

        <label>Audit Date</label>

        <input
            type="date"
            name="audit_date"
            value="<?= htmlspecialchars($audit['audit_date'] ?? '') ?>"
            required>

    </div>

    <div class="form-row">

        <label>Audit Result</label>

        <select name="result">
            <option <?= (($audit['result'] ?? '') == 'Verified') ? 'selected' : '' ?>>Verified</option>
            <option <?= (($audit['result'] ?? '') == 'Missing') ? 'selected' : '' ?>>Missing</option>
            <option <?= (($audit['result'] ?? '') == 'Damaged') ? 'selected' : '' ?>>Damaged</option>
            <option <?= (($audit['result'] ?? '') == 'For Investigation') ? 'selected' : '' ?>>For Investigation</option>
        </select>

    </div>

    <div class="form-row">

        <label>Remarks</label>

        <textarea name="remarks"><?= htmlspecialchars($audit['remarks'] ?? '') ?></textarea>

    </div>

    <div class="form-row">

        <label>Status</label>

        <select name="status">
            <option <?= (($audit['status'] ?? '') == 'Scheduled') ? 'selected' : '' ?>>Scheduled</option>
            <option <?= (($audit['status'] ?? '') == 'Ongoing') ? 'selected' : '' ?>>Ongoing</option>
            <option <?= (($audit['status'] ?? '') == 'Completed') ? 'selected' : '' ?>>Completed</option>
        </select>

    </div>

    <button type="submit" class="btn btn-primary">
        <?= isset($id) && $id !== null ? 'Update Audit' : 'Save Audit'; ?>
    </button>

</form>