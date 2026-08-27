<?php

require_once __DIR__ . '/access_control.php';
require_once __DIR__ . '/login_throttle.php';
require_once __DIR__ . '/mfa_functions.php';
require_once __DIR__ . '/pending_mfa.php';

const PCMS_MFA_SETTINGS_SUCCESS = 'success';
const PCMS_MFA_SETTINGS_INVALID = 'invalid';
const PCMS_MFA_SETTINGS_BLOCKED = 'blocked';
const PCMS_MFA_SETTINGS_STALE = 'stale';
const PCMS_MFA_SETTINGS_REQUIRED = 'required';
const PCMS_MFA_SETTINGS_UNAVAILABLE = 'unavailable';

function loadMfaAccountSecurityState(
    PDO $pdo,
    array $sessionUser
): array {
    [$userId, $sessionVersion, $employeeId] =
        validateMfaSettingsSessionUser($sessionUser);

    $stmt = $pdo->prepare(
        'SELECT id, employee_id, full_name, role, is_active,
                session_version, mfa_enabled, mfa_secret_enc,
                mfa_enrolled_at, mfa_last_used_step
         FROM public.users
         WHERE id = :user_id'
    );
    $stmt->execute(['user_id' => $userId]);
    $user = $stmt->fetch();

    if (
        !is_array($user) ||
        !isUserAccountActive($user['is_active'] ?? false) ||
        (int) ($user['session_version'] ?? 0) !== $sessionVersion ||
        !hash_equals(
            $employeeId,
            (string) ($user['employee_id'] ?? '')
        )
    ) {
        throw new RuntimeException(
            'The authenticated account state is no longer current.'
        );
    }

    $mfaEnabled = isUserAccountActive($user['mfa_enabled'] ?? false);
    $mfaRequired = isMfaRequiredForRole((string) $user['role']);

    return [
        'id' => (int) $user['id'],
        'employee_id' => (string) $user['employee_id'],
        'full_name' => (string) $user['full_name'],
        'role' => (string) $user['role'],
        'session_version' => (int) $user['session_version'],
        'mfa_enabled' => $mfaEnabled,
        'mfa_required' => $mfaRequired,
        'mfa_enrolled_at' => $user['mfa_enrolled_at'],
        'can_enable' => !$mfaEnabled &&
            isMfaVoluntarySettingsRole((string) $user['role']),
        'can_disable' => $mfaEnabled &&
            isMfaVoluntarySettingsRole((string) $user['role']),
    ];
}

function prepareMfaSettingsEnrollment(
    PDO $pdo,
    array $sessionUser
): ?array {
    [$userId, $sessionVersion, $employeeId] =
        validateMfaSettingsSessionUser($sessionUser);
    $ownsTransaction = !$pdo->inTransaction();

    try {
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        $stmt = $pdo->prepare(
            'SELECT id, employee_id, role, is_active,
                    session_version, mfa_enabled,
                    mfa_enrolled_at, mfa_last_used_step
             FROM public.users
             WHERE id = :user_id
             FOR UPDATE'
        );
        $stmt->execute(['user_id' => $userId]);
        $user = $stmt->fetch();

        if (
            !is_array($user) ||
            !isUserAccountActive($user['is_active'] ?? false) ||
            (int) ($user['session_version'] ?? 0) !== $sessionVersion ||
            !hash_equals(
                $employeeId,
                (string) ($user['employee_id'] ?? '')
            ) ||
            !isMfaVoluntarySettingsRole((string) ($user['role'] ?? '')) ||
            isUserAccountActive($user['mfa_enabled'] ?? false) ||
            ($user['mfa_enrolled_at'] ?? null) !== null ||
            ($user['mfa_last_used_step'] ?? null) !== null
        ) {
            commitOwnedMfaSettingsTransaction($pdo, $ownsTransaction);
            return null;
        }

        commitOwnedMfaSettingsTransaction($pdo, $ownsTransaction);

        return [
            'id' => (int) $user['id'],
            'session_version' => (int) $user['session_version'],
        ];
    } catch (Throwable $exception) {
        rollbackOwnedMfaSettingsTransaction(
            $pdo,
            $ownsTransaction,
            $exception
        );
    }
}

function processMfaSettingsDisable(
    PDO $pdo,
    array $sessionUser,
    string $currentPassword,
    string $submittedCode,
    string $clientIp,
    ?int $timestamp = null
): array {
    try {
        [$userId, $sessionVersion, $employeeId] =
            validateMfaSettingsSessionUser($sessionUser);
    } catch (Throwable $exception) {
        return ['status' => PCMS_MFA_SETTINGS_STALE];
    }

    $ownsTransaction = !$pdo->inTransaction();

    try {
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        acquireLoginThrottleLocks($pdo, $employeeId, $clientIp);

        $stmt = $pdo->prepare(
            'SELECT id, employee_id, role, password_hash, is_active,
                    session_version, mfa_enabled, mfa_secret_enc,
                    mfa_enrolled_at, mfa_last_used_step
             FROM public.users
             WHERE id = :user_id
             FOR UPDATE'
        );
        $stmt->execute(['user_id' => $userId]);
        $user = $stmt->fetch();

        if (
            !is_array($user) ||
            !isUserAccountActive($user['is_active'] ?? false) ||
            (int) ($user['session_version'] ?? 0) !== $sessionVersion ||
            !hash_equals(
                $employeeId,
                (string) ($user['employee_id'] ?? '')
            )
        ) {
            commitOwnedMfaSettingsTransaction($pdo, $ownsTransaction);
            return ['status' => PCMS_MFA_SETTINGS_STALE];
        }

        if (isMfaRequiredForRole((string) $user['role'])) {
            commitOwnedMfaSettingsTransaction($pdo, $ownsTransaction);
            return ['status' => PCMS_MFA_SETTINGS_REQUIRED];
        }

        if (
            !isMfaVoluntarySettingsRole((string) $user['role']) ||
            !isUserAccountActive($user['mfa_enabled'] ?? false) ||
            trim((string) ($user['mfa_secret_enc'] ?? '')) === '' ||
            ($user['mfa_enrolled_at'] ?? null) === null ||
            ($user['mfa_last_used_step'] ?? null) === null
        ) {
            commitOwnedMfaSettingsTransaction($pdo, $ownsTransaction);
            return ['status' => PCMS_MFA_SETTINGS_UNAVAILABLE];
        }

        if (isLoginAttemptBlocked($pdo, $employeeId, $clientIp)) {
            commitOwnedMfaSettingsTransaction($pdo, $ownsTransaction);
            return ['status' => PCMS_MFA_SETTINGS_BLOCKED];
        }

        $passwordValid = password_verify(
            $currentPassword,
            (string) $user['password_hash']
        );
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
            !$passwordValid ||
            $matchedStep === null ||
            !claimMfaTimestep($pdo, (int) $user['id'], $matchedStep)
        ) {
            $newlyBlocked = recordLoginFailure(
                $pdo,
                $employeeId,
                $clientIp
            );
            commitOwnedMfaSettingsTransaction($pdo, $ownsTransaction);

            return [
                'status' => $newlyBlocked
                    ? PCMS_MFA_SETTINGS_BLOCKED
                    : PCMS_MFA_SETTINGS_INVALID,
            ];
        }

        $update = $pdo->prepare(
            'UPDATE public.users
             SET mfa_enabled = FALSE,
                 mfa_secret_enc = NULL,
                 mfa_enrolled_at = NULL,
                 mfa_last_used_step = NULL,
                 session_version = session_version + 1
             WHERE id = :user_id
               AND employee_id = :employee_id
               AND role = :expected_role
               AND is_active = TRUE
               AND session_version = :session_version
               AND mfa_enabled = TRUE
             RETURNING session_version'
        );
        $update->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $update->bindValue(':employee_id', $employeeId);
        $update->bindValue(':expected_role', (string) $user['role']);
        $update->bindValue(
            ':session_version',
            $sessionVersion,
            PDO::PARAM_INT
        );
        $update->execute();
        $newSessionVersion = $update->fetchColumn();

        if ($newSessionVersion === false) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return ['status' => PCMS_MFA_SETTINGS_STALE];
        }

        recordSecurityEvent(
            $pdo,
            'MFA_DISABLED',
            $userId,
            $userId,
            'User disabled optional TOTP MFA after reauthentication.'
        );
        clearLoginAccountFailures($pdo, $employeeId);
        commitOwnedMfaSettingsTransaction($pdo, $ownsTransaction);

        return [
            'status' => PCMS_MFA_SETTINGS_SUCCESS,
            'session_version' => (int) $newSessionVersion,
        ];
    } catch (Throwable $exception) {
        rollbackOwnedMfaSettingsTransaction(
            $pdo,
            $ownsTransaction,
            $exception
        );
    }
}

function validateMfaSettingsSessionUser(array $sessionUser): array
{
    $userId = filter_var(
        $sessionUser['id'] ?? null,
        FILTER_VALIDATE_INT
    );
    $sessionVersion = filter_var(
        $sessionUser['session_version'] ?? null,
        FILTER_VALIDATE_INT
    );
    $employeeId = trim((string) (
        $sessionUser['employee_id'] ?? ''
    ));

    if (
        $userId === false ||
        $userId <= 0 ||
        $sessionVersion === false ||
        $sessionVersion <= 0 ||
        $employeeId === ''
    ) {
        throw new InvalidArgumentException(
            'Invalid authenticated account state.'
        );
    }

    return [(int) $userId, (int) $sessionVersion, $employeeId];
}

function isMfaVoluntarySettingsRole(string $role): bool
{
    return strtolower(trim($role)) === 'property custodian' &&
        !isMfaRequiredForRole($role);
}

function commitOwnedMfaSettingsTransaction(
    PDO $pdo,
    bool $ownsTransaction
): void {
    if ($ownsTransaction) {
        $pdo->commit();
    }
}

function rollbackOwnedMfaSettingsTransaction(
    PDO $pdo,
    bool $ownsTransaction,
    Throwable $exception
): never {
    if ($ownsTransaction && $pdo->inTransaction()) {
        try {
            $pdo->rollBack();
        } catch (Throwable $rollbackError) {
            // Preserve the original settings failure.
        }
    }

    throw $exception;
}
