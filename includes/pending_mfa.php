<?php

require_once __DIR__ . '/access_control.php';
require_once __DIR__ . '/mfa_functions.php';

const PCMS_PENDING_MFA_LIFETIME = 300;
const PCMS_PENDING_MFA_PURPOSE_ENROLL = 'enroll';
const PCMS_PENDING_MFA_PURPOSE_SETTINGS_ENROLL = 'settings_enroll';
const PCMS_PENDING_MFA_PURPOSE_CHALLENGE = 'challenge';
const PCMS_MFA_AUTH_STAGE_COMPLETE = 'complete';
const PCMS_MFA_AUTH_STAGE_ENROLL = 'enroll';
const PCMS_MFA_AUTH_STAGE_CHALLENGE = 'challenge';

function determineMfaAuthenticationStage(
    string $role,
    mixed $mfaEnabled
): string {
    if (isUserAccountActive($mfaEnabled)) {
        return PCMS_MFA_AUTH_STAGE_CHALLENGE;
    }

    return isMfaRequiredForRole($role)
        ? PCMS_MFA_AUTH_STAGE_ENROLL
        : PCMS_MFA_AUTH_STAGE_COMPLETE;
}

function beginPendingMfaSession(
    int $userId,
    int $sessionVersion,
    string $purpose = PCMS_PENDING_MFA_PURPOSE_ENROLL,
    ?int $now = null
): void {
    if (
        $userId <= 0 ||
        $sessionVersion <= 0 ||
        !in_array(
            $purpose,
            [
                PCMS_PENDING_MFA_PURPOSE_ENROLL,
                PCMS_PENDING_MFA_PURPOSE_SETTINGS_ENROLL,
                PCMS_PENDING_MFA_PURPOSE_CHALLENGE,
            ],
            true
        )
    ) {
        throw new InvalidArgumentException('Invalid pending MFA session.');
    }

    $issuedAt = $now ?? time();

    unset(
        $_SESSION['user'],
        $_SESSION['session_created_at'],
        $_SESSION['session_last_activity_at']
    );

    $_SESSION['pending_mfa'] = [
        'user_id' => $userId,
        'purpose' => $purpose,
        'expires_at' => $issuedAt + PCMS_PENDING_MFA_LIFETIME,
        'session_version' => $sessionVersion,
    ];
}

function getPendingMfaSession(
    string|array $requiredPurpose = PCMS_PENDING_MFA_PURPOSE_ENROLL,
    ?int $now = null
): ?array {
    $requiredPurposes = is_array($requiredPurpose)
        ? array_values($requiredPurpose)
        : [$requiredPurpose];
    $allowedPurposes = [
        PCMS_PENDING_MFA_PURPOSE_ENROLL,
        PCMS_PENDING_MFA_PURPOSE_SETTINGS_ENROLL,
        PCMS_PENDING_MFA_PURPOSE_CHALLENGE,
    ];

    if (
        $requiredPurposes === [] ||
        array_diff($requiredPurposes, $allowedPurposes) !== []
    ) {
        throw new InvalidArgumentException(
            'Invalid pending MFA purpose requirement.'
        );
    }

    $state = $_SESSION['pending_mfa'] ?? null;

    if (!is_array($state)) {
        return null;
    }

    $userId = filter_var(
        $state['user_id'] ?? null,
        FILTER_VALIDATE_INT
    );
    $purpose = (string) ($state['purpose'] ?? '');
    $expiresAt = filter_var(
        $state['expires_at'] ?? null,
        FILTER_VALIDATE_INT
    );
    $sessionVersion = filter_var(
        $state['session_version'] ?? null,
        FILTER_VALIDATE_INT
    );
    $effectiveNow = $now ?? time();

    if (
        $userId === false ||
        $userId <= 0 ||
        !in_array($purpose, $requiredPurposes, true) ||
        $expiresAt === false ||
        $expiresAt <= $effectiveNow ||
        $sessionVersion === false ||
        $sessionVersion <= 0
    ) {
        clearPendingMfaSession();
        return null;
    }

    return [
        'user_id' => (int) $userId,
        'purpose' => $purpose,
        'expires_at' => (int) $expiresAt,
        'session_version' => (int) $sessionVersion,
    ];
}

function clearPendingMfaSession(): void
{
    unset($_SESSION['pending_mfa']);
}

function loadPendingMfaEnrollment(
    PDO $pdo,
    int $userId,
    int $pendingSessionVersion,
    string $enrollmentPurpose = PCMS_PENDING_MFA_PURPOSE_ENROLL
): array {
    if ($userId <= 0 || $pendingSessionVersion <= 0) {
        throw new InvalidArgumentException('Invalid MFA enrollment user.');
    }

    $user = fetchPendingMfaEnrollmentUser(
        $pdo,
        $userId,
        $pendingSessionVersion,
        $enrollmentPurpose
    );

    if ($user === null) {
        throw new RuntimeException('MFA enrollment is unavailable.');
    }

    $encryptedSecret = trim((string) (
        $user['mfa_secret_enc'] ?? ''
    ));
    $plainSecret = null;

    if ($encryptedSecret === '') {
        $plainSecret = generateMfaSecret();
        $candidateEncryptedSecret = encryptMfaSecret($plainSecret);
        $update = $pdo->prepare(
            'UPDATE public.users
             SET mfa_secret_enc = :mfa_secret_enc
             WHERE id = :user_id
               AND mfa_enabled = FALSE
               AND mfa_secret_enc IS NULL
               AND mfa_enrolled_at IS NULL
               AND mfa_last_used_step IS NULL
               AND session_version = :pending_session_version'
        );
        $update->execute([
            'mfa_secret_enc' => $candidateEncryptedSecret,
            'user_id' => $userId,
            'pending_session_version' => $pendingSessionVersion,
        ]);

        if ($update->rowCount() === 1) {
            $encryptedSecret = $candidateEncryptedSecret;
        } else {
            sodium_memzero($plainSecret);
            $plainSecret = null;
            $user = fetchPendingMfaEnrollmentUser(
                $pdo,
                $userId,
                $pendingSessionVersion,
                $enrollmentPurpose
            );

            if ($user === null) {
                throw new RuntimeException('MFA enrollment is unavailable.');
            }

            $encryptedSecret = trim((string) (
                $user['mfa_secret_enc'] ?? ''
            ));
        }
    }

    if ($encryptedSecret === '') {
        throw new RuntimeException('MFA enrollment secret is unavailable.');
    }

    if ($plainSecret === null) {
        $plainSecret = decryptMfaSecret($encryptedSecret);
    }

    return [
        'user_id' => (int) $user['id'],
        'employee_id' => (string) $user['employee_id'],
        'full_name' => (string) $user['full_name'],
        'role' => (string) $user['role'],
        'plain_secret' => $plainSecret,
        'encrypted_secret' => $encryptedSecret,
    ];
}

function fetchPendingMfaEnrollmentUser(
    PDO $pdo,
    int $userId,
    int $pendingSessionVersion,
    string $enrollmentPurpose = PCMS_PENDING_MFA_PURPOSE_ENROLL
): ?array {
    $stmt = $pdo->prepare(
        'SELECT id, employee_id, full_name, role, is_active,
                mfa_enabled, mfa_secret_enc, mfa_enrolled_at,
                mfa_last_used_step, session_version
         FROM public.users
         WHERE id = :user_id'
    );
    $stmt->execute(['user_id' => $userId]);
    $user = $stmt->fetch();

    if (
        !$user ||
        !isUserAccountActive($user['is_active']) ||
        !isMfaEnrollmentPurposeAllowedForRole(
            $enrollmentPurpose,
            (string) $user['role']
        ) ||
        isUserAccountActive($user['mfa_enabled']) ||
        (int) $user['session_version'] !== $pendingSessionVersion ||
        $user['mfa_enrolled_at'] !== null ||
        $user['mfa_last_used_step'] !== null
    ) {
        return null;
    }

    return $user;
}

function completePendingMfaEnrollment(
    PDO $pdo,
    int $userId,
    string $expectedRole,
    string $expectedEncryptedSecret,
    int $pendingSessionVersion,
    int $matchedStep,
    string $enrollmentPurpose = PCMS_PENDING_MFA_PURPOSE_ENROLL
): ?int {
    if (
        $userId <= 0 ||
        trim($expectedRole) === '' ||
        $expectedEncryptedSecret === '' ||
        $pendingSessionVersion <= 0 ||
        $matchedStep < 0
    ) {
        throw new InvalidArgumentException(
            'Invalid MFA enrollment completion data.'
        );
    }

    if (
        !isMfaEnrollmentPurposeAllowedForRole(
            $enrollmentPurpose,
            $expectedRole
        )
    ) {
        return null;
    }

    $ownsTransaction = !$pdo->inTransaction();

    try {
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        $update = $pdo->prepare(
            'UPDATE public.users
             SET mfa_enabled = TRUE,
                 mfa_enrolled_at = CURRENT_TIMESTAMP,
                 mfa_last_used_step = :matched_step,
                 session_version = session_version + 1
             WHERE id = :user_id
               AND role = :expected_role
               AND is_active = TRUE
               AND mfa_enabled = FALSE
               AND mfa_secret_enc = :expected_encrypted_secret
               AND mfa_enrolled_at IS NULL
               AND mfa_last_used_step IS NULL
               AND session_version = :pending_session_version
             RETURNING session_version'
        );
        $update->bindValue(':matched_step', $matchedStep, PDO::PARAM_INT);
        $update->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $update->bindValue(':expected_role', $expectedRole);
        $update->bindValue(
            ':expected_encrypted_secret',
            $expectedEncryptedSecret
        );
        $update->bindValue(
            ':pending_session_version',
            $pendingSessionVersion,
            PDO::PARAM_INT
        );
        $update->execute();
        $newSessionVersion = $update->fetchColumn();

        if ($newSessionVersion === false) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return null;
        }

        recordSecurityEvent(
            $pdo,
            'MFA_ENROLLED',
            $userId,
            $userId,
            'User completed TOTP MFA enrollment.'
        );

        if ($ownsTransaction) {
            $pdo->commit();
        }

        return (int) $newSessionVersion;
    } catch (Throwable $exception) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            try {
                $pdo->rollBack();
            } catch (Throwable $rollbackError) {
                // Preserve the original enrollment failure.
            }
        }

        throw $exception;
    }
}

function isMfaEnrollmentPurposeAllowedForRole(
    string $enrollmentPurpose,
    string $role
): bool {
    if ($enrollmentPurpose === PCMS_PENDING_MFA_PURPOSE_ENROLL) {
        return isMfaRequiredForRole($role);
    }

    return $enrollmentPurpose ===
            PCMS_PENDING_MFA_PURPOSE_SETTINGS_ENROLL &&
        strtolower(trim($role)) === 'property custodian' &&
        !isMfaRequiredForRole($role);
}
