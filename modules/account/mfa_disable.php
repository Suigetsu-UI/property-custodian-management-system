<?php

require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/mfa_account_security.php';

requireValidAccessCsrfPost();

$currentPassword = (string) ($_POST['current_password'] ?? '');
$submittedCode = trim((string) ($_POST['totp_code'] ?? ''));
$clientIp = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

try {
    $pdo = getDbConnection();
    pruneExpiredLoginAttempts($pdo);
    $result = processMfaSettingsDisable(
        $pdo,
        $_SESSION['user'],
        $currentPassword,
        $submittedCode,
        $clientIp
    );
} catch (Throwable $exception) {
    header('Location: security.php?error=failed');
    exit();
}

if ($result['status'] === PCMS_MFA_SETTINGS_SUCCESS) {
    destroyPcmsSession();
    header('Location: ../../auth/login.php');
    exit();
}

if ($result['status'] === PCMS_MFA_SETTINGS_STALE) {
    destroyPcmsSession();
    header('Location: ../../auth/login.php?error=session');
    exit();
}

$error = match ($result['status']) {
    PCMS_MFA_SETTINGS_BLOCKED => 'blocked',
    PCMS_MFA_SETTINGS_REQUIRED => 'required',
    PCMS_MFA_SETTINGS_UNAVAILABLE => 'unavailable',
    default => 'invalid',
};

header('Location: security.php?error=' . $error);
exit();
