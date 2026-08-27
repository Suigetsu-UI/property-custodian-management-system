<?php

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/access_control.php';
require_once __DIR__ . '/../includes/login_throttle.php';
require_once __DIR__ . '/../includes/pending_mfa.php';

startPcmsSession();
requireValidAccessCsrfPost();

$employeeID = trim((string) ($_POST["employee_id"] ?? ''));
$password = (string) ($_POST["password"] ?? '');
$clientIp = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

$authenticatedUser = null;
$pendingMfaUser = null;
$pendingMfaPurpose = null;
$pdo = null;

try {
    $pdo = getDbConnection();
    pruneExpiredLoginAttempts($pdo);

    $pdo->beginTransaction();
    acquireLoginThrottleLocks($pdo, $employeeID, $clientIp);

    if (!isLoginAttemptBlocked($pdo, $employeeID, $clientIp)) {
        $stmt = $pdo->prepare(
            "SELECT id, employee_id, full_name, role, password_hash, is_active,
                    session_version, mfa_enabled
             FROM users
             WHERE employee_id = :employee_id"
        );

        $stmt->execute([
            'employee_id' => $employeeID
        ]);

        $row = $stmt->fetch();

        if (
            $row &&
            password_verify($password, $row['password_hash']) &&
            isUserAccountActive($row['is_active'])
        ) {
            $mfaStage = determineMfaAuthenticationStage(
                (string) $row['role'],
                $row['mfa_enabled']
            );

            if ($mfaStage === PCMS_MFA_AUTH_STAGE_ENROLL) {
                $pendingMfaUser = $row;
                $pendingMfaPurpose = PCMS_PENDING_MFA_PURPOSE_ENROLL;
            } elseif ($mfaStage === PCMS_MFA_AUTH_STAGE_CHALLENGE) {
                $pendingMfaUser = $row;
                $pendingMfaPurpose = PCMS_PENDING_MFA_PURPOSE_CHALLENGE;
            } elseif ($mfaStage === PCMS_MFA_AUTH_STAGE_COMPLETE) {
                $authenticatedUser = $row;
                clearLoginAccountFailures($pdo, $employeeID);
            }
        } else {
            recordLoginFailure($pdo, $employeeID, $clientIp);
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        try {
            $pdo->rollBack();
        } catch (Throwable $rollbackError) {
            // The login still fails closed if rollback itself is unavailable.
        }
    }

    // Fail closed. Do not expose database or credential details.
    $authenticatedUser = null;
    $pendingMfaUser = null;
    $pendingMfaPurpose = null;
}

if ($pendingMfaUser !== null && $pendingMfaPurpose !== null) {
    session_regenerate_id(true);
    beginPendingMfaSession(
        (int) $pendingMfaUser['id'],
        (int) $pendingMfaUser['session_version'],
        $pendingMfaPurpose
    );

    header(
        'Location: ' . (
            $pendingMfaPurpose === PCMS_PENDING_MFA_PURPOSE_ENROLL
                ? 'mfa_enroll.php'
                : 'mfa_challenge.php'
        )
    );
    exit();
}

if ($authenticatedUser !== null) {
    session_regenerate_id(true);
    clearPendingMfaSession();

    $now = time();

    $_SESSION["user"] = [
        "id" => (int) $authenticatedUser["id"],
        "employee_id" => $authenticatedUser["employee_id"],
        "name" => $authenticatedUser["full_name"],
        "role" => $authenticatedUser["role"],
        "session_version" => (int) $authenticatedUser["session_version"],
    ];
    $_SESSION['session_created_at'] = $now;
    $_SESSION['session_last_activity_at'] = $now;

    header("Location: ../dashboard.php");
    exit();
}

header("Location: login.php?error=invalid");
exit();
