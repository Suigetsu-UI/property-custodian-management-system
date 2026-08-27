<?php

require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../includes/access_control.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/mfa_admin_reset.php';

requireAdministrator();
requireValidAccessCsrfPost();

$targetUserId = filter_var(
    $_POST['target_user_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
$targetSessionVersion = filter_var(
    $_POST['target_session_version'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
$currentPassword = (string) ($_POST['current_password'] ?? '');
$submittedCode = trim((string) ($_POST['totp_code'] ?? ''));
$clientIp = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

if ($targetUserId === false || $targetSessionVersion === false) {
    header('Location: index.php?error=mfa_unavailable');
    exit();
}

try {
    $pdo = getDbConnection();
    pruneExpiredLoginAttempts($pdo);
    $result = processMfaAdminReset(
        $pdo,
        $_SESSION['user'],
        (int) $targetUserId,
        (int) $targetSessionVersion,
        $currentPassword,
        $submittedCode,
        $clientIp
    );
} catch (Throwable $exception) {
    header('Location: index.php?error=mfa_reset_failed');
    exit();
}

if ($result['status'] === PCMS_MFA_ADMIN_RESET_SUCCESS) {
    header('Location: index.php?message=mfa_reset');
    exit();
}

if (
    $result['status'] === PCMS_MFA_ADMIN_RESET_STALE_ACTOR ||
    $result['status'] ===
        PCMS_MFA_ADMIN_RESET_ACTOR_MFA_REQUIRED
) {
    destroyPcmsSession();
    header('Location: ../../auth/login.php?error=session');
    exit();
}

$error = match ($result['status']) {
    PCMS_MFA_ADMIN_RESET_BLOCKED => 'mfa_blocked',
    PCMS_MFA_ADMIN_RESET_SELF => 'mfa_self',
    PCMS_MFA_ADMIN_RESET_STALE_TARGET,
    PCMS_MFA_ADMIN_RESET_UNAVAILABLE => 'mfa_unavailable',
    default => 'mfa_verification',
};

header('Location: index.php?error=' . $error);
exit();
