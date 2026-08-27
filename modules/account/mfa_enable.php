<?php

require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/mfa_account_security.php';

requireValidAccessCsrfPost();

try {
    $pdo = getDbConnection();
    $pendingUser = prepareMfaSettingsEnrollment(
        $pdo,
        $_SESSION['user']
    );

    if ($pendingUser === null) {
        header('Location: security.php?error=unavailable');
        exit();
    }

    session_regenerate_id(true);
    beginPendingMfaSession(
        (int) $pendingUser['id'],
        (int) $pendingUser['session_version'],
        PCMS_PENDING_MFA_PURPOSE_SETTINGS_ENROLL
    );

    header('Location: ../../auth/mfa_enroll.php');
    exit();
} catch (Throwable $exception) {
    header('Location: security.php?error=failed');
    exit();
}
