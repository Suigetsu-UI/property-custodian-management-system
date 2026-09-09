<?php

require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../includes/property_core_gateway.php';

$assetId = propertyCoreBusinessId($_GET['asset_id'] ?? null, 'AST');
if ($assetId === null) {
    header('Location: index.php?error=not_found');
    exit;
}

try {
    $review = getPropertyCoreServiceClient()->dispositionReview($assetId);
} catch (Throwable $error) {
    header('Location: index.php?error=' . rawurlencode(dispositionGatewayErrorKey($error)));
    exit;
}

$asset = is_array($review['asset'] ?? null) ? $review['asset'] : [];
$audit = is_array($review['latest_completed_audit'] ?? null)
    ? $review['latest_completed_audit']
    : null;
$disposition = is_array($review['latest_disposition'] ?? null)
    ? $review['latest_disposition']
    : null;
$requestBlockers = is_array($review['request_blockers'] ?? null)
    ? $review['request_blockers']
    : [];
$approvalBlockers = is_array($review['approval_blockers'] ?? null)
    ? $review['approval_blockers']
    : [];
$messageMap = [
    'requested' => ['success-message', 'The disposition review was created and is pending external institutional approval.'],
    'approved' => ['success-message', 'External institutional approval was recorded and the disposition is approved for sale/bidding.'],
    'sold' => ['success-message', 'The sale/bidding completion was recorded. The Asset remains preserved as Sold and Inventory was unchanged.'],
    'rejected' => ['success-message', 'The disposition review was rejected.'],
    'cancelled' => ['success-message', 'The disposition review was cancelled.'],
    'invalid_input' => ['error-message', 'Complete all required fields with valid information.'],
    'invalid_transition' => ['error-message', 'That action is no longer allowed for the current disposition status.'],
    'not_eligible' => ['error-message', 'The Asset is not currently eligible for that disposition step. Review the readiness checks below.'],
    'state_changed' => ['error-message', 'The Asset or disposition state changed. Review the latest information and try again.'],
    'save_failed' => ['error-message', 'The disposition action could not be saved. Please try again.'],
];
$message = $messageMap[(string) ($_GET['message'] ?? '')] ?? null;
$status = (string) ($disposition['status'] ?? '');
$isOpen = in_array($status, ['Pending Institutional Approval', 'Approved for Sale/Bidding'], true);

include __DIR__ . '/../../includes/header.php';
?>

<div class="layout">
<?php include __DIR__ . '/../../includes/sidebar.php'; ?>
<main class="main-content disposition-page">
    <header class="disposition-heading">
        <div>
            <p class="section-heading">Asset Disposition / Sale Review</p>
            <h1><?= htmlspecialchars($assetId) ?> — <?= htmlspecialchars((string) ($asset['asset_name'] ?? 'Asset')) ?></h1>
            <p>Age and Audit evidence support review only. External institutional authority remains outside Smart AssetTrack.</p>
        </div>
        <a class="btn btn-outline" href="index.php">Disposition History</a>
    </header>

    <?php if ($message !== null): ?><div class="<?= htmlspecialchars($message[0]) ?>" role="status"><?= htmlspecialchars($message[1]) ?></div><?php endif; ?>
    <?php if (!isPropertyCustodian()): ?><div class="pcms-form-notice" role="note">Read-only System Administrator view. Only the Property Custodian may record disposition transactions.</div><?php endif; ?>

    <section class="disposition-summary-grid" aria-label="Asset disposition evidence">
        <article><span>Asset Status</span><strong><?= htmlspecialchars((string) ($asset['status'] ?? '')) ?></strong></article>
        <article><span>Asset Age</span><strong><?= htmlspecialchars((string) ($asset['asset_age'] ?? 'Not recorded')) ?></strong></article>
        <article><span>Useful Life</span><strong><?= htmlspecialchars((string) ($asset['useful_life'] ?? 'Not configured')) ?></strong></article>
        <article><span>Lifecycle</span><strong><?= htmlspecialchars((string) ($asset['lifecycle'] ?? 'Not configured')) ?></strong></article>
        <article><span>Lifecycle Usage</span><strong><?= isset($asset['lifecycle_usage_percent']) && $asset['lifecycle_usage_percent'] !== null ? htmlspecialchars(number_format((float) $asset['lifecycle_usage_percent'], 1) . '%') : 'Not available' ?></strong></article>
        <article><span>Latest Completed Audit</span><strong><?= $audit === null ? 'None' : htmlspecialchars((string) (($audit['audit_id'] ?? '') . ' — ' . ($audit['result'] ?? ''))) ?></strong></article>
    </section>

    <section class="lifecycle-settings-card">
        <div class="lifecycle-settings-card-header"><div><p class="section-heading">Readiness</p><h2>Approval and Sale Checks</h2></div></div>
        <?php if ($approvalBlockers === []): ?>
        <div class="success-message">Current eligibility checks pass. “Verified” means only that the latest completed Audit has no listed disqualifying result; it does not grant sale authority.</div>
        <?php else: ?>
        <div class="pcms-form-notice pcms-form-notice--warning"><strong>Not ready for approval or final sale:</strong><ul><?php foreach ($approvalBlockers as $blocker): ?><li><?= htmlspecialchars((string) $blocker) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
    </section>

    <?php if ($disposition !== null): ?>
    <section class="lifecycle-settings-card">
        <div class="lifecycle-settings-card-header"><div><p class="section-heading">Disposition Record</p><h2><?= htmlspecialchars((string) ($disposition['disposition_id'] ?? '')) ?></h2></div><span class="pcms-status-badge"><?= htmlspecialchars($status) ?></span></div>
        <dl class="disposition-details">
            <div><dt>Proposed method</dt><dd><?= htmlspecialchars((string) ($disposition['proposed_method'] ?? '')) ?></dd></div>
            <div><dt>Reason</dt><dd><?= nl2br(htmlspecialchars((string) ($disposition['reason'] ?? ''))) ?></dd></div>
            <div><dt>Requested by / at</dt><dd><?= htmlspecialchars((string) ($disposition['requested_by'] ?? '')) ?> · <?= htmlspecialchars((string) ($disposition['requested_at'] ?? '')) ?></dd></div>
            <div><dt>External approval</dt><dd><?= htmlspecialchars((string) ($disposition['institutional_approval_reference'] ?? 'Not yet recorded')) ?></dd></div>
            <div><dt>External approver</dt><dd><?= htmlspecialchars(trim((string) (($disposition['institutional_approver_name'] ?? '') . ' — ' . ($disposition['institutional_approver_position'] ?? '')), " —")) ?: 'Not yet recorded' ?></dd></div>
            <div><dt>Approval date</dt><dd><?= htmlspecialchars((string) ($disposition['institutional_approval_date'] ?? 'Not yet recorded')) ?></dd></div>
            <div><dt>Completion reference</dt><dd><?= htmlspecialchars((string) ($disposition['completion_reference'] ?? 'Not yet recorded')) ?></dd></div>
            <div><dt>Status note</dt><dd><?= nl2br(htmlspecialchars((string) ($disposition['status_note'] ?? '—'))) ?></dd></div>
        </dl>
    </section>
    <?php endif; ?>

    <?php if (isPropertyCustodian() && !$isOpen && ($asset['status'] ?? '') !== 'Sold' && $requestBlockers === []): ?>
    <section class="lifecycle-settings-card">
        <div class="lifecycle-settings-card-header"><div><p class="section-heading">Step 1</p><h2>Start Disposition Review</h2></div></div>
        <form method="POST" action="request.php" class="pcms-form-grid">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getAccessCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="asset_id" value="<?= htmlspecialchars($assetId) ?>">
            <div class="form-row"><label for="method">Proposed method</label><select id="method" name="proposed_method" required><option value="Sale">Sale</option><option value="Bidding">Bidding</option></select></div>
            <div class="form-row pcms-form-span-2"><label for="reason">Reason for review</label><textarea id="reason" name="reason" minlength="3" maxlength="2000" required></textarea></div>
            <div class="form-row pcms-form-span-2"><label for="requestRemarks">Request remarks (optional)</label><textarea id="requestRemarks" name="request_remarks" maxlength="2000"></textarea></div>
            <div class="pcms-form-span-2"><button class="btn btn-primary" type="submit">Create Disposition Review</button></div>
        </form>
    </section>
    <?php endif; ?>

    <?php if (isPropertyCustodian() && $status === 'Pending Institutional Approval'): ?>
    <section class="lifecycle-settings-card">
        <div class="lifecycle-settings-card-header"><div><p class="section-heading">Step 2</p><h2>Record External Institutional Approval</h2></div></div>
        <form method="POST" action="approve.php" class="pcms-form-grid">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getAccessCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="asset_id" value="<?= htmlspecialchars($assetId) ?>">
            <input type="hidden" name="disposition_id" value="<?= htmlspecialchars((string) ($disposition['disposition_id'] ?? '')) ?>">
            <div class="form-row"><label>Approval reference</label><input name="institutional_approval_reference" maxlength="150" required></div>
            <div class="form-row"><label>Approval date</label><input type="date" name="institutional_approval_date" max="<?= date('Y-m-d') ?>" required></div>
            <div class="form-row"><label>External approver name</label><input name="institutional_approver_name" maxlength="150" required></div>
            <div class="form-row"><label>External approver position</label><input name="institutional_approver_position" maxlength="150" required></div>
            <div class="form-row pcms-form-span-2"><label>Approval remarks (optional)</label><textarea name="approval_remarks" maxlength="2000"></textarea></div>
            <div class="pcms-form-span-2"><button class="btn btn-success" type="submit" <?= $approvalBlockers !== [] ? 'disabled' : '' ?>>Record Approval and Approve for Sale/Bidding</button></div>
        </form>
    </section>
    <?php endif; ?>

    <?php if (isPropertyCustodian() && $status === 'Approved for Sale/Bidding'): ?>
    <section class="lifecycle-settings-card">
        <div class="lifecycle-settings-card-header"><div><p class="section-heading">Step 3</p><h2>Record Sale/Bidding Completion</h2></div></div>
        <form method="POST" action="complete.php" class="pcms-form-grid">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getAccessCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="asset_id" value="<?= htmlspecialchars($assetId) ?>">
            <input type="hidden" name="disposition_id" value="<?= htmlspecialchars((string) ($disposition['disposition_id'] ?? '')) ?>">
            <div class="form-row"><label>Completion reference</label><input name="completion_reference" maxlength="150" required></div>
            <div class="form-row pcms-form-span-2"><label>Completion remarks (optional)</label><textarea name="completion_remarks" maxlength="2000"></textarea></div>
            <div class="pcms-form-span-2"><button class="btn btn-danger" type="submit" <?= $approvalBlockers !== [] ? 'disabled' : '' ?> onclick="return confirm('Record this Asset as Sold? This is a terminal state and Inventory will not be restored.');">Mark Asset Sold</button></div>
        </form>
    </section>
    <?php endif; ?>

    <?php if (isPropertyCustodian() && $isOpen): ?>
    <section class="disposition-decision-grid">
        <?php if ($status === 'Pending Institutional Approval'): ?>
        <form method="POST" action="reject.php" class="lifecycle-settings-card">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getAccessCsrfToken(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="asset_id" value="<?= htmlspecialchars($assetId) ?>"><input type="hidden" name="disposition_id" value="<?= htmlspecialchars((string) ($disposition['disposition_id'] ?? '')) ?>">
            <h2>Reject Review</h2><label>Reason</label><textarea name="status_note" minlength="3" maxlength="2000" required></textarea><button class="btn btn-danger" type="submit">Reject</button>
        </form>
        <?php endif; ?>
        <form method="POST" action="cancel.php" class="lifecycle-settings-card">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getAccessCsrfToken(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="asset_id" value="<?= htmlspecialchars($assetId) ?>"><input type="hidden" name="disposition_id" value="<?= htmlspecialchars((string) ($disposition['disposition_id'] ?? '')) ?>">
            <h2>Cancel Review</h2><label>Reason</label><textarea name="status_note" minlength="3" maxlength="2000" required></textarea><button class="btn btn-outline" type="submit">Cancel Review</button>
        </form>
    </section>
    <?php endif; ?>
</main>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
