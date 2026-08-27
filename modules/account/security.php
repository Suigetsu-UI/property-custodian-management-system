<?php

require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/mfa_account_security.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

try {
    $pdo = getDbConnection();
    $securityState = loadMfaAccountSecurityState(
        $pdo,
        $_SESSION['user']
    );
} catch (Throwable $exception) {
    destroyPcmsSession();
    header('Location: ../../auth/login.php?error=session');
    exit();
}

$messages = [
    'invalid' => [
        'error',
        'MFA could not be disabled. Check your current password and authenticator code, then try again.',
    ],
    'blocked' => [
        'error',
        'Too many verification attempts. Please wait 15 minutes and sign in again.',
    ],
    'required' => [
        'error',
        'MFA cannot be disabled because it is required for your role.',
    ],
    'unavailable' => [
        'error',
        'That MFA settings action is not available for this account.',
    ],
    'failed' => [
        'error',
        'The Account Security change could not be completed. Please try again.',
    ],
];
$messageKey = (string) ($_GET['error'] ?? '');
$message = $messages[$messageKey] ?? null;
$enrolledLabel = null;

if ($securityState['mfa_enrolled_at'] !== null) {
    try {
        $enrolledLabel = (new DateTimeImmutable(
            (string) $securityState['mfa_enrolled_at']
        ))
            ->setTimezone(new DateTimeZone('Asia/Manila'))
            ->format('F j, Y');
    } catch (Throwable $exception) {
        $enrolledLabel = 'Recorded';
    }
}

include __DIR__ . '/../../includes/header.php';

?>

<div class="layout">

<?php include __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="main-content account-security-page">

<div class="account-security-heading">
    <div>
        <p class="section-heading">Account Protection</p>
        <h1>Account Security</h1>
        <p>Review and manage authenticator verification for your PCMS account.</p>
    </div>
    <div class="account-security-shield" aria-hidden="true">
        <i class="fas fa-shield-halved"></i>
    </div>
</div>

<?php if ($message): ?>
<div class="error-message" role="alert">
    <?= htmlspecialchars($message[1]) ?>
</div>
<?php endif; ?>

<section class="account-security-card" aria-labelledby="mfa-settings-title">
    <div class="account-security-card-header">
        <div>
            <p class="section-heading">Sign-in Verification</p>
            <h2 id="mfa-settings-title">Multi-Factor Authentication</h2>
        </div>
        <span
            class="pcms-status-badge"
            data-status="<?= $securityState['mfa_enabled'] ? 'active' : 'inactive' ?>"
        >
            <?= $securityState['mfa_enabled'] ? 'Enabled' : 'Disabled' ?>
        </span>
    </div>

    <dl class="account-security-details">
        <div>
            <dt>Status</dt>
            <dd><?= $securityState['mfa_enabled'] ? 'Enabled' : 'Disabled' ?></dd>
        </div>
        <div>
            <dt>Policy</dt>
            <dd>
                <?= $securityState['mfa_required']
                    ? 'Required for ' . htmlspecialchars(userRoleLabel($securityState['role']))
                    : 'Optional for ' . htmlspecialchars(userRoleLabel($securityState['role'])) ?>
            </dd>
        </div>
        <div>
            <dt>Enrolled</dt>
            <dd><?= htmlspecialchars($enrolledLabel ?? 'Not enrolled') ?></dd>
        </div>
    </dl>

    <?php if ($securityState['mfa_required']): ?>
        <div class="account-security-notice" role="note">
            <i class="fas fa-lock" aria-hidden="true"></i>
            <p>
                MFA cannot be disabled because it is required for your role.
                If setup is incomplete, the next sign-in will require enrollment
                before normal PCMS access.
            </p>
        </div>
    <?php elseif ($securityState['can_enable']): ?>
        <div class="account-security-action">
            <div>
                <h3>Add authenticator verification</h3>
                <p>Use a time-based authenticator code after your password on future sign-ins.</p>
            </div>
            <form method="POST" action="mfa_enable.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getAccessCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="btn btn-primary">Enable MFA</button>
            </form>
        </div>
    <?php elseif ($securityState['can_disable']): ?>
        <div class="account-security-disable">
            <div>
                <h3>Disable optional MFA</h3>
                <p>
                    Confirm both your current password and current authenticator
                    code. Lost-device recovery is not available from this page.
                </p>
            </div>

            <form method="POST" action="mfa_disable.php" class="account-security-form" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getAccessCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-row">
                    <label for="current_password">Current password</label>
                    <input
                        id="current_password"
                        type="password"
                        name="current_password"
                        autocomplete="current-password"
                        required
                    >
                </div>

                <div class="form-row">
                    <label for="totp_code">Current authenticator code</label>
                    <input
                        id="totp_code"
                        type="text"
                        name="totp_code"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        pattern="[0-9]{6}"
                        minlength="6"
                        maxlength="6"
                        placeholder="000000"
                        required
                    >
                </div>

                <button type="submit" class="btn btn-danger">Disable MFA</button>
            </form>
        </div>
    <?php endif; ?>
</section>

</main>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
