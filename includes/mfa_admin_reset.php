<?php

require_once __DIR__ . '/access_control.php';
require_once __DIR__ . '/login_throttle.php';
require_once __DIR__ . '/mfa_functions.php';

const PCMS_MFA_ADMIN_RESET_SUCCESS = 'success';
const PCMS_MFA_ADMIN_RESET_INVALID = 'invalid';
const PCMS_MFA_ADMIN_RESET_BLOCKED = 'blocked';
const PCMS_MFA_ADMIN_RESET_STALE_ACTOR = 'stale_actor';
const PCMS_MFA_ADMIN_RESET_ACTOR_MFA_REQUIRED = 'actor_mfa_required';
const PCMS_MFA_ADMIN_RESET_SELF = 'self_reset';
const PCMS_MFA_ADMIN_RESET_STALE_TARGET = 'stale_target';
const PCMS_MFA_ADMIN_RESET_UNAVAILABLE = 'unavailable';
const PCMS_MFA_ADMIN_RESET_SAVEPOINT = 'pcms_mfa_admin_reset';

function processMfaAdminReset(
    PDO $pdo,
    array $sessionUser,
    int $targetUserId,
    int $expectedTargetSessionVersion,
    string $currentPassword,
    string $submittedCode,
    string $clientIp,
    ?int $timestamp = null,
    ?callable $eventWriter = null
): array {
    try {
        [$actorUserId, $actorSessionVersion, $actorEmployeeId] =
            validateMfaAdminResetSessionUser($sessionUser);
    } catch (Throwable $exception) {
        return ['status' => PCMS_MFA_ADMIN_RESET_STALE_ACTOR];
    }

    if ($targetUserId <= 0 || $expectedTargetSessionVersion <= 0) {
        return ['status' => PCMS_MFA_ADMIN_RESET_STALE_TARGET];
    }

    if ($actorUserId === $targetUserId) {
        return ['status' => PCMS_MFA_ADMIN_RESET_SELF];
    }

    $ownsTransaction = !$pdo->inTransaction();
    $usesSavepoint = !$ownsTransaction;

    try {
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        } else {
            $pdo->exec(
                'SAVEPOINT ' . PCMS_MFA_ADMIN_RESET_SAVEPOINT
            );
        }

        acquireLoginThrottleLocks(
            $pdo,
            $actorEmployeeId,
            $clientIp
        );

        $usersStmt = $pdo->prepare(
            'SELECT id, employee_id, role, password_hash, is_active,
                    session_version, mfa_enabled, mfa_secret_enc,
                    mfa_enrolled_at, mfa_last_used_step
             FROM public.users
             WHERE id IN (:actor_user_id, :target_user_id)
             ORDER BY id
             FOR UPDATE'
        );
        $usersStmt->execute([
            'actor_user_id' => $actorUserId,
            'target_user_id' => $targetUserId,
        ]);
        $usersById = [];

        foreach ($usersStmt->fetchAll() as $user) {
            $usersById[(int) $user['id']] = $user;
        }

        $actor = $usersById[$actorUserId] ?? null;
        $target = $usersById[$targetUserId] ?? null;

        if (
            !is_array($actor) ||
            !isUserAccountActive($actor['is_active'] ?? false) ||
            (string) ($actor['role'] ?? '') !== 'Administrator' ||
            (int) ($actor['session_version'] ?? 0) !==
                $actorSessionVersion ||
            !hash_equals(
                $actorEmployeeId,
                (string) ($actor['employee_id'] ?? '')
            )
        ) {
            finishMfaAdminResetTransaction(
                $pdo,
                $ownsTransaction,
                $usesSavepoint
            );
            return ['status' => PCMS_MFA_ADMIN_RESET_STALE_ACTOR];
        }

        if (
            !isUserAccountActive($actor['mfa_enabled'] ?? false) ||
            trim((string) ($actor['mfa_secret_enc'] ?? '')) === '' ||
            ($actor['mfa_enrolled_at'] ?? null) === null ||
            ($actor['mfa_last_used_step'] ?? null) === null
        ) {
            finishMfaAdminResetTransaction(
                $pdo,
                $ownsTransaction,
                $usesSavepoint
            );
            return [
                'status' => PCMS_MFA_ADMIN_RESET_ACTOR_MFA_REQUIRED,
            ];
        }

        if (
            !is_array($target) ||
            (int) ($target['session_version'] ?? 0) !==
                $expectedTargetSessionVersion
        ) {
            finishMfaAdminResetTransaction(
                $pdo,
                $ownsTransaction,
                $usesSavepoint
            );
            return ['status' => PCMS_MFA_ADMIN_RESET_STALE_TARGET];
        }

        if (
            !in_array(
                (string) ($target['role'] ?? ''),
                getAllowedUserRoles(),
                true
            ) ||
            (
                !isUserAccountActive($target['mfa_enabled'] ?? false) &&
                trim((string) ($target['mfa_secret_enc'] ?? '')) === ''
            )
        ) {
            finishMfaAdminResetTransaction(
                $pdo,
                $ownsTransaction,
                $usesSavepoint
            );
            return ['status' => PCMS_MFA_ADMIN_RESET_UNAVAILABLE];
        }

        if (
            isLoginAttemptBlocked(
                $pdo,
                $actorEmployeeId,
                $clientIp
            )
        ) {
            finishMfaAdminResetTransaction(
                $pdo,
                $ownsTransaction,
                $usesSavepoint
            );
            return ['status' => PCMS_MFA_ADMIN_RESET_BLOCKED];
        }

        $passwordValid = password_verify(
            $currentPassword,
            (string) $actor['password_hash']
        );
        $matchedStep = null;

        if (preg_match('/\A\d{6}\z/', $submittedCode) === 1) {
            $plainSecret = decryptMfaSecret(
                (string) $actor['mfa_secret_enc']
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
            !claimMfaTimestep($pdo, $actorUserId, $matchedStep)
        ) {
            $newlyBlocked = recordLoginFailure(
                $pdo,
                $actorEmployeeId,
                $clientIp
            );
            finishMfaAdminResetTransaction(
                $pdo,
                $ownsTransaction,
                $usesSavepoint
            );

            return [
                'status' => $newlyBlocked
                    ? PCMS_MFA_ADMIN_RESET_BLOCKED
                    : PCMS_MFA_ADMIN_RESET_INVALID,
            ];
        }

        $resetStmt = $pdo->prepare(
            'UPDATE public.users
             SET mfa_enabled = FALSE,
                 mfa_secret_enc = NULL,
                 mfa_enrolled_at = NULL,
                 mfa_last_used_step = NULL,
                 session_version = session_version + 1
             WHERE id = :target_user_id
               AND session_version = :expected_session_version
               AND (
                   mfa_enabled = TRUE
                   OR mfa_secret_enc IS NOT NULL
               )
             RETURNING session_version, role'
        );
        $resetStmt->bindValue(
            ':target_user_id',
            $targetUserId,
            PDO::PARAM_INT
        );
        $resetStmt->bindValue(
            ':expected_session_version',
            $expectedTargetSessionVersion,
            PDO::PARAM_INT
        );
        $resetStmt->execute();
        $resetTarget = $resetStmt->fetch();

        if (!is_array($resetTarget)) {
            rollbackMfaAdminResetTransaction(
                $pdo,
                $ownsTransaction,
                $usesSavepoint
            );
            return ['status' => PCMS_MFA_ADMIN_RESET_STALE_TARGET];
        }

        $writeEvent = $eventWriter ?? static function (
            PDO $eventPdo,
            int $eventActorUserId,
            int $eventTargetUserId
        ): void {
            recordSecurityEvent(
                $eventPdo,
                'MFA_RESET_BY_ADMIN',
                $eventActorUserId,
                $eventTargetUserId,
                'Multi-factor authentication was reset by an Administrator.'
            );
        };
        $writeEvent($pdo, $actorUserId, $targetUserId);
        clearLoginAccountFailures($pdo, $actorEmployeeId);
        finishMfaAdminResetTransaction(
            $pdo,
            $ownsTransaction,
            $usesSavepoint
        );

        return [
            'status' => PCMS_MFA_ADMIN_RESET_SUCCESS,
            'target_session_version' =>
                (int) $resetTarget['session_version'],
            'target_role' => (string) $resetTarget['role'],
            'actor_matched_step' => $matchedStep,
        ];
    } catch (Throwable $exception) {
        rollbackMfaAdminResetTransaction(
            $pdo,
            $ownsTransaction,
            $usesSavepoint
        );
        throw $exception;
    }
}

function validateMfaAdminResetSessionUser(array $sessionUser): array
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
            'Invalid Administrator session state.'
        );
    }

    return [(int) $userId, (int) $sessionVersion, $employeeId];
}

function finishMfaAdminResetTransaction(
    PDO $pdo,
    bool $ownsTransaction,
    bool $usesSavepoint
): void {
    if ($ownsTransaction) {
        $pdo->commit();
    } elseif ($usesSavepoint) {
        $pdo->exec(
            'RELEASE SAVEPOINT ' . PCMS_MFA_ADMIN_RESET_SAVEPOINT
        );
    }
}

function rollbackMfaAdminResetTransaction(
    PDO $pdo,
    bool $ownsTransaction,
    bool $usesSavepoint
): void {
    if ($ownsTransaction && $pdo->inTransaction()) {
        $pdo->rollBack();
        return;
    }

    if ($usesSavepoint && $pdo->inTransaction()) {
        $pdo->exec(
            'ROLLBACK TO SAVEPOINT ' . PCMS_MFA_ADMIN_RESET_SAVEPOINT
        );
        $pdo->exec(
            'RELEASE SAVEPOINT ' . PCMS_MFA_ADMIN_RESET_SAVEPOINT
        );
    }
}
