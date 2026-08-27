<?php

require_once __DIR__ . '/access_control.php';
require_once __DIR__ . '/login_throttle.php';
require_once __DIR__ . '/mfa_functions.php';
require_once __DIR__ . '/pending_mfa.php';

const PCMS_MFA_CHALLENGE_SUCCESS = 'success';
const PCMS_MFA_CHALLENGE_INVALID = 'invalid';
const PCMS_MFA_CHALLENGE_BLOCKED = 'blocked';
const PCMS_MFA_CHALLENGE_STALE = 'stale';

function isPendingMfaChallengeCurrent(
    PDO $pdo,
    array $pendingState
): bool {
    $userId = filter_var(
        $pendingState['user_id'] ?? null,
        FILTER_VALIDATE_INT
    );
    $sessionVersion = filter_var(
        $pendingState['session_version'] ?? null,
        FILTER_VALIDATE_INT
    );
    $expiresAt = filter_var(
        $pendingState['expires_at'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (
        ($pendingState['purpose'] ?? '') !==
            PCMS_PENDING_MFA_PURPOSE_CHALLENGE ||
        $userId === false ||
        $userId <= 0 ||
        $sessionVersion === false ||
        $sessionVersion <= 0 ||
        $expiresAt === false ||
        $expiresAt <= time()
    ) {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT is_active, session_version, mfa_enabled,
                mfa_secret_enc, mfa_enrolled_at, mfa_last_used_step
         FROM public.users
         WHERE id = :user_id'
    );
    $stmt->execute(['user_id' => $userId]);
    $user = $stmt->fetch();

    return isCurrentMfaChallengeUser($user, (int) $sessionVersion);
}

function processPendingMfaChallenge(
    PDO $pdo,
    array $pendingState,
    string $submittedCode,
    string $clientIp,
    ?int $timestamp = null
): array {
    $userId = filter_var(
        $pendingState['user_id'] ?? null,
        FILTER_VALIDATE_INT
    );
    $pendingSessionVersion = filter_var(
        $pendingState['session_version'] ?? null,
        FILTER_VALIDATE_INT
    );
    $expiresAt = filter_var(
        $pendingState['expires_at'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (
        ($pendingState['purpose'] ?? '') !==
            PCMS_PENDING_MFA_PURPOSE_CHALLENGE ||
        $userId === false ||
        $userId <= 0 ||
        $pendingSessionVersion === false ||
        $pendingSessionVersion <= 0 ||
        $expiresAt === false ||
        $expiresAt <= time()
    ) {
        return ['status' => PCMS_MFA_CHALLENGE_STALE];
    }

    $ownsTransaction = !$pdo->inTransaction();

    try {
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        $identityStmt = $pdo->prepare(
            'SELECT employee_id
             FROM public.users
             WHERE id = :user_id'
        );
        $identityStmt->execute(['user_id' => $userId]);
        $employeeId = $identityStmt->fetchColumn();

        if ($employeeId === false || trim((string) $employeeId) === '') {
            commitOwnedMfaChallengeTransaction($pdo, $ownsTransaction);
            return ['status' => PCMS_MFA_CHALLENGE_STALE];
        }

        acquireLoginThrottleLocks(
            $pdo,
            (string) $employeeId,
            $clientIp
        );

        $userStmt = $pdo->prepare(
            'SELECT id, employee_id, full_name, role, is_active,
                    session_version, mfa_enabled, mfa_secret_enc,
                    mfa_enrolled_at, mfa_last_used_step
             FROM public.users
             WHERE id = :user_id
             FOR UPDATE'
        );
        $userStmt->execute(['user_id' => $userId]);
        $user = $userStmt->fetch();

        if (
            !isCurrentMfaChallengeUser(
                $user,
                (int) $pendingSessionVersion
            ) ||
            !hash_equals(
                (string) $employeeId,
                (string) ($user['employee_id'] ?? '')
            )
        ) {
            commitOwnedMfaChallengeTransaction($pdo, $ownsTransaction);
            return ['status' => PCMS_MFA_CHALLENGE_STALE];
        }

        if (
            isLoginAttemptBlocked(
                $pdo,
                (string) $user['employee_id'],
                $clientIp
            )
        ) {
            commitOwnedMfaChallengeTransaction($pdo, $ownsTransaction);
            return [
                'status' => PCMS_MFA_CHALLENGE_BLOCKED,
                'newly_blocked' => false,
            ];
        }

        $matchedStep = null;

        if (preg_match('/\A\d{6}\z/', $submittedCode) === 1) {
            $plainSecret = decryptMfaSecret(
                (string) $user['mfa_secret_enc']
            );

            try {
                $matchedStep = verifyTotpCode(
                    $plainSecret,
                    $submittedCode,
                    $timestamp
                );
            } finally {
                sodium_memzero($plainSecret);
            }
        }

        if (
            $matchedStep === null ||
            !claimMfaTimestep($pdo, (int) $user['id'], $matchedStep)
        ) {
            $newlyBlocked = recordLoginFailure(
                $pdo,
                (string) $user['employee_id'],
                $clientIp
            );

            if ($newlyBlocked) {
                recordSecurityEvent(
                    $pdo,
                    'MFA_CHALLENGE_BLOCKED',
                    null,
                    (int) $user['id'],
                    'MFA challenge entered the authentication cooldown.'
                );
            }

            commitOwnedMfaChallengeTransaction($pdo, $ownsTransaction);

            return [
                'status' => $newlyBlocked
                    ? PCMS_MFA_CHALLENGE_BLOCKED
                    : PCMS_MFA_CHALLENGE_INVALID,
                'newly_blocked' => $newlyBlocked,
            ];
        }

        clearLoginAccountFailures(
            $pdo,
            (string) $user['employee_id']
        );
        commitOwnedMfaChallengeTransaction($pdo, $ownsTransaction);

        return [
            'status' => PCMS_MFA_CHALLENGE_SUCCESS,
            'matched_step' => $matchedStep,
            'user' => [
                'id' => (int) $user['id'],
                'employee_id' => (string) $user['employee_id'],
                'name' => (string) $user['full_name'],
                'role' => (string) $user['role'],
                'session_version' => (int) $user['session_version'],
            ],
        ];
    } catch (Throwable $exception) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            try {
                $pdo->rollBack();
            } catch (Throwable $rollbackError) {
                // Preserve the original challenge failure.
            }
        }

        throw $exception;
    }
}

function establishMfaAuthenticatedSession(
    array $authenticatedUser,
    ?int $now = null
): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        throw new LogicException(
            'A PHP session must be active before MFA authentication.'
        );
    }

    $userId = filter_var(
        $authenticatedUser['id'] ?? null,
        FILTER_VALIDATE_INT
    );
    $sessionVersion = filter_var(
        $authenticatedUser['session_version'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (
        $userId === false ||
        $userId <= 0 ||
        $sessionVersion === false ||
        $sessionVersion <= 0 ||
        trim((string) ($authenticatedUser['employee_id'] ?? '')) === '' ||
        trim((string) ($authenticatedUser['name'] ?? '')) === '' ||
        trim((string) ($authenticatedUser['role'] ?? '')) === ''
    ) {
        throw new InvalidArgumentException(
            'Invalid authenticated MFA user.'
        );
    }

    session_regenerate_id(true);
    clearPendingMfaSession();
    $authenticatedAt = $now ?? time();

    $_SESSION['user'] = [
        'id' => (int) $userId,
        'employee_id' => (string) $authenticatedUser['employee_id'],
        'name' => (string) $authenticatedUser['name'],
        'role' => (string) $authenticatedUser['role'],
        'session_version' => (int) $sessionVersion,
    ];
    $_SESSION['session_created_at'] = $authenticatedAt;
    $_SESSION['session_last_activity_at'] = $authenticatedAt;
}

function isCurrentMfaChallengeUser(
    mixed $user,
    int $pendingSessionVersion
): bool {
    return is_array($user) &&
        isUserAccountActive($user['is_active'] ?? false) &&
        (int) ($user['session_version'] ?? 0) ===
            $pendingSessionVersion &&
        isUserAccountActive($user['mfa_enabled'] ?? false) &&
        trim((string) ($user['mfa_secret_enc'] ?? '')) !== '' &&
        ($user['mfa_enrolled_at'] ?? null) !== null &&
        ($user['mfa_last_used_step'] ?? null) !== null;
}

function commitOwnedMfaChallengeTransaction(
    PDO $pdo,
    bool $ownsTransaction
): void {
    if ($ownsTransaction) {
        $pdo->commit();
    }
}
