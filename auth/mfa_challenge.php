<?php

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/access_control.php';
require_once __DIR__ . '/../includes/mfa_challenge.php';

startPcmsSession();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (isset($_SESSION['user'])) {
    clearPendingMfaSession();
    header('Location: ../dashboard.php');
    exit();
}

$pendingState = getPendingMfaSession(
    PCMS_PENDING_MFA_PURPOSE_CHALLENGE
);

if ($pendingState === null) {
    header('Location: login.php?error=session');
    exit();
}

$error = '';
$pdo = null;

try {
    $pdo = getDbConnection();

    if (!isPendingMfaChallengeCurrent($pdo, $pendingState)) {
        clearPendingMfaSession();
        header('Location: login.php?error=session');
        exit();
    }
} catch (Throwable $exception) {
    clearPendingMfaSession();
    header('Location: login.php?error=session');
    exit();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    requireValidAccessCsrfPost();
    $submittedCode = trim((string) ($_POST['totp_code'] ?? ''));
    $clientIp = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

    try {
        pruneExpiredLoginAttempts($pdo);
        $result = processPendingMfaChallenge(
            $pdo,
            $pendingState,
            $submittedCode,
            $clientIp
        );

        if ($result['status'] === PCMS_MFA_CHALLENGE_SUCCESS) {
            establishMfaAuthenticatedSession($result['user']);
            header('Location: ../dashboard.php');
            exit();
        }

        if ($result['status'] === PCMS_MFA_CHALLENGE_STALE) {
            clearPendingMfaSession();
            header('Location: login.php?error=session');
            exit();
        }

        $error = $result['status'] === PCMS_MFA_CHALLENGE_BLOCKED
            ? 'Too many verification attempts. Please wait 15 minutes and sign in again.'
            : 'Enter the current six-digit code from your authenticator app.';
    } catch (Throwable $exception) {
        $error = 'MFA verification is temporarily unavailable. Please try again.';
    }
}

require_once __DIR__ . '/../config/config.php';

$loginCss = BASE_URL . 'auth/login.css?v=20260729';
$mfaCss = BASE_URL . 'auth/mfa_enroll.css?v=20260827';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify MFA | Property Custodian Management System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($loginCss) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($mfaCss) ?>">
</head>
<body class="pcms-login pcms-mfa-enrollment">

<div class="login-backdrop" aria-hidden="true">
    <span class="login-orb login-orb--1"></span>
    <span class="login-orb login-orb--2"></span>
    <span class="login-orb login-orb--3"></span>
</div>

<main class="login-shell">
    <section class="login-card mfa-enrollment-card" aria-labelledby="mfa-challenge-title">
        <div class="login-card-top">
            <div class="login-logo-badge mfa-shield-badge" aria-hidden="true">
                <i class="fas fa-shield-halved"></i>
            </div>
            <p class="login-eyebrow">Account Security</p>
            <h1 id="mfa-challenge-title" class="login-title">Verify your identity</h1>
            <p class="login-subtitle">Enter the current code from your authenticator app</p>
        </div>

        <div class="login-divider" aria-hidden="true"></div>

        <div class="login-card-body">
            <?php if ($error !== ''): ?>
                <div class="login-error" role="alert">
                    <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="mfa_challenge.php" class="login-form mfa-code-form" novalidate>
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
                    <span>Verify and Sign In</span>
                    <i class="fas fa-arrow-right" aria-hidden="true"></i>
                </button>
            </form>

            <form method="POST" action="logout.php" class="mfa-cancel-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getAccessCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="mfa-cancel-button">Cancel and return to sign in</button>
            </form>
        </div>

        <p class="login-footnote">
            <i class="fas fa-lock" aria-hidden="true"></i>
            Authenticator codes are never stored or logged
        </p>
    </section>
</main>

</body>
</html>
