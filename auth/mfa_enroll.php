<?php

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/access_control.php';
require_once __DIR__ . '/../includes/pending_mfa.php';

startPcmsSession();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (isset($_SESSION['user'])) {
    clearPendingMfaSession();
    header('Location: ../dashboard.php');
    exit();
}

$pendingState = getPendingMfaSession([
    PCMS_PENDING_MFA_PURPOSE_ENROLL,
    PCMS_PENDING_MFA_PURPOSE_SETTINGS_ENROLL,
]);

if ($pendingState === null) {
    header('Location: login.php?error=session');
    exit();
}

$error = '';
$pdo = null;
$enrollment = null;

try {
    $pdo = getDbConnection();
    $enrollment = loadPendingMfaEnrollment(
        $pdo,
        (int) $pendingState['user_id'],
        (int) $pendingState['session_version'],
        (string) $pendingState['purpose']
    );
} catch (Throwable $exception) {
    clearPendingMfaSession();
    header('Location: login.php?error=session');
    exit();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    requireValidAccessCsrfPost();

    $submittedCode = trim((string) ($_POST['totp_code'] ?? ''));
    $matchedStep = verifyTotpCode(
        $enrollment['plain_secret'],
        $submittedCode
    );

    if ($matchedStep === null) {
        $error = 'Enter the current six-digit code from your authenticator app.';
    } else {
        try {
            $newSessionVersion = completePendingMfaEnrollment(
                $pdo,
                (int) $enrollment['user_id'],
                (string) $enrollment['role'],
                (string) $enrollment['encrypted_secret'],
                (int) $pendingState['session_version'],
                $matchedStep,
                (string) $pendingState['purpose']
            );

            if ($newSessionVersion === null) {
                throw new RuntimeException(
                    'The MFA enrollment state changed before completion.'
                );
            }

            sodium_memzero($enrollment['plain_secret']);
            destroyPcmsSession();

            header('Location: login.php');
            exit();
        } catch (Throwable $exception) {
            $error = 'MFA enrollment could not be completed. Please try again.';
        }
    }
}

require_once __DIR__ . '/../config/config.php';

$loginCss = BASE_URL . 'auth/login.css?v=20260729';
$enrollmentCss = BASE_URL . 'auth/mfa_enroll.css?v=20260827';
$issuer = getMfaIssuer();
$settingsEnrollment = $pendingState['purpose'] ===
    PCMS_PENDING_MFA_PURPOSE_SETTINGS_ENROLL;
$displaySecret = trim(chunk_split(
    $enrollment['plain_secret'],
    4,
    ' '
));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Up MFA | Property Custodian Management System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($loginCss) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($enrollmentCss) ?>">
</head>
<body class="pcms-login pcms-mfa-enrollment">

<div class="login-backdrop" aria-hidden="true">
    <span class="login-orb login-orb--1"></span>
    <span class="login-orb login-orb--2"></span>
    <span class="login-orb login-orb--3"></span>
</div>

<main class="login-shell">
    <section class="login-card mfa-enrollment-card" aria-labelledby="mfa-enrollment-title">
        <div class="login-card-top">
            <div class="login-logo-badge mfa-shield-badge" aria-hidden="true">
                <i class="fas fa-shield-halved"></i>
            </div>
            <p class="login-eyebrow">Account Security</p>
            <h1 id="mfa-enrollment-title" class="login-title">Set up multi-factor authentication</h1>
            <p class="login-subtitle">
                <?= $settingsEnrollment
                    ? 'Add authenticator verification to your account'
                    : 'Required before this account can access PCMS' ?>
            </p>
        </div>

        <div class="login-divider" aria-hidden="true"></div>

        <div class="login-card-body">
            <?php if ($error !== ''): ?>
                <div class="login-error" role="alert">
                    <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <ol class="mfa-setup-steps">
                <li>Open your trusted authenticator app and choose to add a setup key.</li>
                <li>
                    Enter the account details below. Select <strong>Time based</strong> if the app asks for a key type.
                </li>
            </ol>

            <dl class="mfa-setup-details">
                <div>
                    <dt>Issuer</dt>
                    <dd><?= htmlspecialchars($issuer) ?></dd>
                </div>
                <div>
                    <dt>Account</dt>
                    <dd><?= htmlspecialchars($enrollment['employee_id']) ?></dd>
                </div>
                <div class="mfa-secret-row">
                    <dt>Setup key</dt>
                    <dd aria-label="Authenticator setup key"><?= htmlspecialchars($displaySecret) ?></dd>
                </div>
            </dl>

            <form method="POST" action="mfa_enroll.php" class="login-form mfa-code-form" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getAccessCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">

                <div class="login-field">
                    <label for="totp_code">Six-digit authenticator code</label>
                    <div class="login-input">
                        <i class="fas fa-key" aria-hidden="true"></i>
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
                            autofocus>
                    </div>
                </div>

                <button type="submit" class="login-submit">
                    <span>Verify and Enable MFA</span>
                    <i class="fas fa-arrow-right" aria-hidden="true"></i>
                </button>
            </form>

            <p class="mfa-fresh-login-note">
                After verification, this security change will revoke existing sessions and require a fresh sign-in.
            </p>

            <form method="POST" action="logout.php" class="mfa-cancel-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getAccessCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="mfa-cancel-button">Cancel and return to sign in</button>
            </form>
        </div>

        <p class="login-footnote">
            <i class="fas fa-lock" aria-hidden="true"></i>
            Never share your setup key or authenticator code
        </p>
    </section>
</main>

</body>
</html>
