<?php
require_once __DIR__ . "/../../includes/asset_functions.php";
$form_action = isset($id) && $id !== null ? "edit_audit.php?id={$id}" : "schedule_audit.php";
$default_audit_id = generateAuditID();
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

        <label>Asset</label>

        <input
            type="text"
            name="asset_name"
            value="<?= htmlspecialchars($audit['asset_name'] ?? '') ?>"
            required>

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

        <label>Date</label>

        <input
            type="date"
            name="audit_date"
            value="<?= htmlspecialchars($audit['audit_date'] ?? '') ?>"
            required>

    </div>

    <div class="form-row">

        <label>Status</label>

        <select name="status">
            <option <?= (($audit['status'] ?? '') == 'Scheduled') ? 'selected' : '' ?>>Scheduled</option>
            <option <?= (($audit['status'] ?? '') == 'Ongoing') ? 'selected' : '' ?>>Ongoing</option>
            <option <?= (($audit['status'] ?? '') == 'Completed') ? 'selected' : '' ?>>Completed</option>
        </select>

    </div>

    <div class="form-row">

        <label>Result</label>

        <select name="result">
            <option <?= (($audit['result'] ?? '') == 'Passed') ? 'selected' : '' ?>>Passed</option>
            <option <?= (($audit['result'] ?? '') == 'With Findings') ? 'selected' : '' ?>>With Findings</option>
            <option <?= (($audit['result'] ?? '') == 'Failed') ? 'selected' : '' ?>>Failed</option>
        </select>

    </div>

    <button type="submit" class="btn btn-primary">
        <?= isset($id) && $id !== null ? 'Update Audit' : 'Save Audit'; ?>
    </button>

</form>
