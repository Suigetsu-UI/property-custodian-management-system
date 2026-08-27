<?php

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/mfa_admin_reset.php';
require_once __DIR__ . '/../includes/pending_mfa.php';

$testsRun = 0;

function assertMfaAdminReset(bool $condition, string $message): void
{
    global $testsRun;
    $testsRun++;

    if (!$condition) {
        throw new RuntimeException('FAILED: ' . $message);
    }
}

function clearMfaAdminResetThrottle(
    PDO $pdo,
    string $employeeId,
    string $ipAddress
): void {
    $keys = loginThrottleKeys($employeeId, $ipAddress);
    $placeholders = implode(', ', array_fill(0, count($keys), '?'));
    $stmt = $pdo->prepare(
        'DELETE FROM public.login_attempts
         WHERE attempt_key IN (' . $placeholders . ')'
    );
    $stmt->execute($keys);
}

function readMfaAdminResetUser(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT role, is_active, session_version, mfa_enabled,
                mfa_secret_enc, mfa_enrolled_at, mfa_last_used_step
         FROM public.users
         WHERE id = :user_id'
    );
    $stmt->execute(['user_id' => $userId]);
    $user = $stmt->fetch();

    if (!is_array($user)) {
        throw new RuntimeException('Unable to read the reset fixture.');
    }

    return $user;
}

function differentMfaAdminResetCode(string $validCode): string
{
    return $validCode === '000000' ? '000001' : '000000';
}

$handlerSource = file_get_contents(
    __DIR__ . '/../modules/users/reset_mfa.php'
);
$indexSource = file_get_contents(__DIR__ . '/../modules/users/index.php');
$modalSource = file_get_contents(
    __DIR__ . '/../modules/users/user_modals.php'
);
$usersJsSource = file_get_contents(__DIR__ . '/../assets/js/users.js');
$helperSource = file_get_contents(
    __DIR__ . '/../includes/mfa_admin_reset.php'
);
$checkAuthSource = file_get_contents(__DIR__ . '/../auth/check_auth.php');

foreach (
    [
        $handlerSource,
        $indexSource,
        $modalSource,
        $usersJsSource,
        $helperSource,
        $checkAuthSource,
    ] as $source
) {
    if ($source === false) {
        throw new RuntimeException(
            'Unable to inspect the Administrator MFA reset flow.'
        );
    }
}

$checkAuthPosition = strpos($handlerSource, 'auth/check_auth.php');
$administratorPosition = strpos($handlerSource, 'requireAdministrator()');
$csrfPosition = strpos($handlerSource, 'requireValidAccessCsrfPost()');
assertMfaAdminReset(
    $checkAuthPosition !== false &&
    $administratorPosition !== false &&
    $csrfPosition !== false &&
    $checkAuthPosition < $administratorPosition &&
    $administratorPosition < $csrfPosition,
    'Logged-out access must be redirected, non-Administrators rejected, and Administrator GET or bad-CSRF requests rejected.'
);
assertMfaAdminReset(
    str_contains($helperSource, '$actorUserId === $targetUserId') &&
    str_contains($helperSource, 'PCMS_MFA_ADMIN_RESET_SELF'),
    'Self-reset must be rejected inside the server-side reset helper.'
);
assertMfaAdminReset(
    str_contains($modalSource, 'name="current_password"') &&
    str_contains($modalSource, 'name="totp_code"') &&
    str_contains($modalSource, 'name="target_session_version"') &&
    str_contains($handlerSource, "\$_POST['target_session_version']"),
    'Reset must require actor password/TOTP and the target version observed by User Management.'
);
assertMfaAdminReset(
    str_contains($indexSource, 'mfa_has_state') &&
    str_contains($indexSource, '$mfaHasState && !$isSelf') &&
    str_contains($indexSource, 'Self-reset is not permitted.') &&
    str_contains($usersJsSource, 'user.is_self') &&
    str_contains($usersJsSource, 'user.mfa_has_state'),
    'The UI must show Reset MFA only for another user with completed or pending MFA state.'
);
assertMfaAdminReset(
    !str_contains($modalSource, 'mfa_secret_enc') &&
    !str_contains($modalSource, 'plain_secret') &&
    !str_contains($usersJsSource, 'mfa_secret_enc') &&
    !str_contains($usersJsSource, 'plain_secret'),
    'The reset UI and JavaScript must never receive MFA secret material.'
);

$lockPosition = strpos($helperSource, 'acquireLoginThrottleLocks(');
$rowLockPosition = strpos($helperSource, 'ORDER BY id', $lockPosition);
$blockedPosition = strpos($helperSource, 'isLoginAttemptBlocked(', $rowLockPosition);
$passwordPosition = strpos($helperSource, 'password_verify(', $blockedPosition);
$decryptPosition = strpos($helperSource, 'decryptMfaSecret(', $passwordPosition);
$claimPosition = strpos($helperSource, 'claimMfaTimestep(', $decryptPosition);
$resetPosition = strpos($helperSource, 'UPDATE public.users', $claimPosition);
$eventPosition = strpos($helperSource, "'MFA_RESET_BY_ADMIN'", $resetPosition);
assertMfaAdminReset(
    $lockPosition !== false &&
    $rowLockPosition !== false &&
    $blockedPosition !== false &&
    $passwordPosition !== false &&
    $decryptPosition !== false &&
    $claimPosition !== false &&
    $resetPosition !== false &&
    $eventPosition !== false &&
    $lockPosition < $rowLockPosition &&
    $rowLockPosition < $blockedPosition &&
    $blockedPosition < $passwordPosition &&
    $passwordPosition < $decryptPosition &&
    $decryptPosition < $claimPosition &&
    $claimPosition < $resetPosition &&
    $resetPosition < $eventPosition,
    'Throttle locks, deterministic row locks, block check, actor factors, replay claim, target reset, and audit must remain ordered.'
);
assertMfaAdminReset(
    str_contains($helperSource, 'ROLLBACK TO SAVEPOINT') &&
    str_contains($helperSource, 'MFA_RESET_BY_ADMIN') &&
    str_contains(
        $helperSource,
        'Multi-factor authentication was reset by an Administrator.'
    ),
    'The reset and non-sensitive audit event must share a rollback-capable transaction boundary.'
);
assertMfaAdminReset(
    str_contains($checkAuthSource, 'session_version') &&
    str_contains($checkAuthSource, 'destroyPcmsSession()'),
    'Incrementing the target session_version must revoke existing target sessions on their next request.'
);

$pdo = getDbConnection();
$baselineEventCount = (int) $pdo->query(
    'SELECT COUNT(*) FROM public.security_events'
)->fetchColumn();
$fixtureSuffix = bin2hex(random_bytes(5));
$actorEmployeeId = 'MFA-RESET-A-' . $fixtureSuffix;
$noMfaActorEmployeeId = 'MFA-RESET-N-' . $fixtureSuffix;
$pendingAdminEmployeeId = 'MFA-RESET-P-' . $fixtureSuffix;
$custodianEmployeeId = 'MFA-RESET-C-' . $fixtureSuffix;
$administratorEmployeeId = 'MFA-RESET-T-' . $fixtureSuffix;
$auditFailureEmployeeId = 'MFA-RESET-F-' . $fixtureSuffix;
$plainEmployeeId = 'MFA-RESET-X-' . $fixtureSuffix;
$fixtureIp = '203.0.113.91';
$currentPassword = 'Reset-' . bin2hex(random_bytes(12));
$passwordHash = password_hash($currentPassword, PASSWORD_DEFAULT);
$actorSecret = generateMfaSecret();
$actorEncryptedSecret = encryptMfaSecret($actorSecret);
$targetSecret = generateMfaSecret();
$targetEncryptedSecret = encryptMfaSecret($targetSecret);
$baseTimestamp = 1234567890;
$baseStep = intdiv($baseTimestamp, PCMS_MFA_TOTP_PERIOD);

try {
    $pdo->beginTransaction();

    $insertEnabled = $pdo->prepare(
        'INSERT INTO public.users (
            employee_id, full_name, password_hash, role, is_active,
            mfa_enabled, mfa_secret_enc, mfa_enrolled_at,
            mfa_last_used_step
         ) VALUES (
            :employee_id, :full_name, :password_hash, :role,
            :is_active, TRUE, :mfa_secret_enc,
            CURRENT_TIMESTAMP, :mfa_last_used_step
         )
         RETURNING id, session_version'
    );
    $insertEnabled->execute([
        'employee_id' => $actorEmployeeId,
        'full_name' => 'MFA Reset Acting Administrator',
        'password_hash' => $passwordHash,
        'role' => 'Administrator',
        'is_active' => 'true',
        'mfa_secret_enc' => $actorEncryptedSecret,
        'mfa_last_used_step' => $baseStep - 2,
    ]);
    $actor = $insertEnabled->fetch();
    $actorUserId = (int) $actor['id'];
    $actorSessionVersion = (int) $actor['session_version'];
    $actorSession = [
        'id' => $actorUserId,
        'employee_id' => $actorEmployeeId,
        'role' => 'Administrator',
        'session_version' => $actorSessionVersion,
    ];

    $insertDisabled = $pdo->prepare(
        'INSERT INTO public.users (
            employee_id, full_name, password_hash, role, is_active
         ) VALUES (
            :employee_id, :full_name, :password_hash,
            :role, TRUE
         )
         RETURNING id, session_version'
    );
    $insertDisabled->execute([
        'employee_id' => $noMfaActorEmployeeId,
        'full_name' => 'MFA Reset Unenrolled Administrator',
        'password_hash' => $passwordHash,
        'role' => 'Administrator',
    ]);
    $noMfaActor = $insertDisabled->fetch();
    $noMfaActorSession = [
        'id' => (int) $noMfaActor['id'],
        'employee_id' => $noMfaActorEmployeeId,
        'role' => 'Administrator',
        'session_version' => (int) $noMfaActor['session_version'],
    ];

    $insertPending = $pdo->prepare(
        'INSERT INTO public.users (
            employee_id, full_name, password_hash, role, is_active,
            mfa_secret_enc
         ) VALUES (
            :employee_id, :full_name, :password_hash,
            :role, TRUE, :mfa_secret_enc
         )
         RETURNING id, session_version'
    );
    $insertPending->execute([
        'employee_id' => $pendingAdminEmployeeId,
        'full_name' => 'MFA Reset Pending Administrator',
        'password_hash' => $passwordHash,
        'role' => 'Administrator',
        'mfa_secret_enc' => $targetEncryptedSecret,
    ]);
    $pendingAdmin = $insertPending->fetch();

    $targets = [];

    foreach (
        [
            [$custodianEmployeeId, 'Property Custodian'],
            [$administratorEmployeeId, 'Administrator'],
            [$auditFailureEmployeeId, 'Property Custodian'],
        ] as [$employeeId, $role]
    ) {
        $insertEnabled->execute([
            'employee_id' => $employeeId,
            'full_name' => 'MFA Reset Enabled Target',
            'password_hash' => $passwordHash,
            'role' => $role,
            'is_active' => 'true',
            'mfa_secret_enc' => $targetEncryptedSecret,
            'mfa_last_used_step' => $baseStep - 2,
        ]);
        $targets[$employeeId] = $insertEnabled->fetch();
    }

    $insertDisabled->execute([
        'employee_id' => $plainEmployeeId,
        'full_name' => 'MFA Reset Plain Target',
        'password_hash' => $passwordHash,
        'role' => 'Property Custodian',
    ]);
    $plainTarget = $insertDisabled->fetch();

    $custodianTargetId = (int) $targets[$custodianEmployeeId]['id'];
    $custodianTargetVersion =
        (int) $targets[$custodianEmployeeId]['session_version'];

    assertMfaAdminReset(
        processMfaAdminReset(
            $pdo,
            $actorSession,
            $actorUserId,
            $actorSessionVersion,
            $currentPassword,
            generateTotpCode($actorSecret, $baseTimestamp),
            $fixtureIp,
            $baseTimestamp
        )['status'] === PCMS_MFA_ADMIN_RESET_SELF,
        'An Administrator must never reset their own MFA.'
    );
    assertMfaAdminReset(
        processMfaAdminReset(
            $pdo,
            $noMfaActorSession,
            $custodianTargetId,
            $custodianTargetVersion,
            $currentPassword,
            '000000',
            $fixtureIp,
            $baseTimestamp
        )['status'] ===
            PCMS_MFA_ADMIN_RESET_ACTOR_MFA_REQUIRED,
        'An acting Administrator without enabled MFA must be rejected.'
    );
    assertMfaAdminReset(
        processMfaAdminReset(
            $pdo,
            $actorSession,
            (int) $plainTarget['id'],
            (int) $plainTarget['session_version'],
            $currentPassword,
            generateTotpCode($actorSecret, $baseTimestamp),
            $fixtureIp,
            $baseTimestamp
        )['status'] === PCMS_MFA_ADMIN_RESET_UNAVAILABLE,
        'A target without current or pending MFA state must not create a reset event.'
    );
    assertMfaAdminReset(
        processMfaAdminReset(
            $pdo,
            $actorSession,
            $custodianTargetId,
            $custodianTargetVersion + 1,
            $currentPassword,
            generateTotpCode($actorSecret, $baseTimestamp),
            $fixtureIp,
            $baseTimestamp
        )['status'] === PCMS_MFA_ADMIN_RESET_STALE_TARGET,
        'A target session_version changed after page load must fail safely.'
    );

    $actorUpdate = $pdo->prepare(
        'UPDATE public.users
         SET session_version = :session_version,
             is_active = :is_active,
             role = :role
         WHERE id = :user_id'
    );
    $actorUpdate->execute([
        'session_version' => $actorSessionVersion + 1,
        'is_active' => 'true',
        'role' => 'Administrator',
        'user_id' => $actorUserId,
    ]);
    assertMfaAdminReset(
        processMfaAdminReset(
            $pdo,
            $actorSession,
            $custodianTargetId,
            $custodianTargetVersion,
            $currentPassword,
            '000000',
            $fixtureIp,
            $baseTimestamp
        )['status'] === PCMS_MFA_ADMIN_RESET_STALE_ACTOR,
        'An acting Administrator session_version change must reject reset.'
    );
    $actorUpdate->execute([
        'session_version' => $actorSessionVersion,
        'is_active' => 'false',
        'role' => 'Administrator',
        'user_id' => $actorUserId,
    ]);
    assertMfaAdminReset(
        processMfaAdminReset(
            $pdo,
            $actorSession,
            $custodianTargetId,
            $custodianTargetVersion,
            $currentPassword,
            '000000',
            $fixtureIp,
            $baseTimestamp
        )['status'] === PCMS_MFA_ADMIN_RESET_STALE_ACTOR,
        'A deactivated acting Administrator must be rejected.'
    );
    $actorUpdate->execute([
        'session_version' => $actorSessionVersion,
        'is_active' => 'true',
        'role' => 'Property Custodian',
        'user_id' => $actorUserId,
    ]);
    assertMfaAdminReset(
        processMfaAdminReset(
            $pdo,
            $actorSession,
            $custodianTargetId,
            $custodianTargetVersion,
            $currentPassword,
            '000000',
            $fixtureIp,
            $baseTimestamp
        )['status'] === PCMS_MFA_ADMIN_RESET_STALE_ACTOR,
        'An acting account whose Administrator role changed must be rejected.'
    );
    $actorUpdate->execute([
        'session_version' => $actorSessionVersion,
        'is_active' => 'true',
        'role' => 'Administrator',
        'user_id' => $actorUserId,
    ]);

    clearMfaAdminResetThrottle($pdo, $actorEmployeeId, $fixtureIp);
    $validBaseCode = generateTotpCode($actorSecret, $baseTimestamp);
    $wrongBaseCode = differentMfaAdminResetCode($validBaseCode);
    $wrongPassword = processMfaAdminReset(
        $pdo,
        $actorSession,
        $custodianTargetId,
        $custodianTargetVersion,
        'wrong-password',
        $validBaseCode,
        $fixtureIp,
        $baseTimestamp
    );
    assertMfaAdminReset(
        $wrongPassword['status'] === PCMS_MFA_ADMIN_RESET_INVALID &&
        (int) readMfaAdminResetUser(
            $pdo,
            $actorUserId
        )['mfa_last_used_step'] === $baseStep - 2,
        'Wrong actor password must fail generically without consuming a valid TOTP.'
    );
    $wrongTotp = processMfaAdminReset(
        $pdo,
        $actorSession,
        $custodianTargetId,
        $custodianTargetVersion,
        $currentPassword,
        $wrongBaseCode,
        $fixtureIp,
        $baseTimestamp
    );
    assertMfaAdminReset(
        $wrongTotp['status'] === PCMS_MFA_ADMIN_RESET_INVALID &&
        isUserAccountActive(
            readMfaAdminResetUser(
                $pdo,
                $custodianTargetId
            )['mfa_enabled']
        ),
        'Wrong actor TOTP must fail generically without resetting the target.'
    );

    clearMfaAdminResetThrottle($pdo, $actorEmployeeId, $fixtureIp);
    $pdo->prepare(
        'UPDATE public.users
         SET mfa_last_used_step = :step
         WHERE id = :user_id'
    )->execute([
        'step' => $baseStep,
        'user_id' => $actorUserId,
    ]);
    assertMfaAdminReset(
        processMfaAdminReset(
            $pdo,
            $actorSession,
            $custodianTargetId,
            $custodianTargetVersion,
            $currentPassword,
            $validBaseCode,
            $fixtureIp,
            $baseTimestamp
        )['status'] === PCMS_MFA_ADMIN_RESET_INVALID,
        'A replayed actor TOTP must not reset MFA.'
    );

    clearMfaAdminResetThrottle($pdo, $actorEmployeeId, $fixtureIp);
    $blockResults = [];

    for ($attempt = 1; $attempt <= PCMS_LOGIN_MAX_FAILURES; $attempt++) {
        $blockResults[] = processMfaAdminReset(
            $pdo,
            $actorSession,
            $custodianTargetId,
            $custodianTargetVersion,
            'wrong-password',
            'invalid',
            $fixtureIp,
            $baseTimestamp
        );
    }

    assertMfaAdminReset(
        array_column(array_slice($blockResults, 0, 4), 'status') === [
            PCMS_MFA_ADMIN_RESET_INVALID,
            PCMS_MFA_ADMIN_RESET_INVALID,
            PCMS_MFA_ADMIN_RESET_INVALID,
            PCMS_MFA_ADMIN_RESET_INVALID,
        ] &&
        $blockResults[4]['status'] === PCMS_MFA_ADMIN_RESET_BLOCKED,
        'The fifth failed Administrator reauthentication must begin cooldown.'
    );
    assertMfaAdminReset(
        processMfaAdminReset(
            $pdo,
            $actorSession,
            $custodianTargetId,
            $custodianTargetVersion,
            $currentPassword,
            $validBaseCode,
            $fixtureIp,
            $baseTimestamp
        )['status'] === PCMS_MFA_ADMIN_RESET_BLOCKED,
        'Requests during cooldown must be rejected before factor verification.'
    );

    $eventsForTargets = $pdo->prepare(
        "SELECT COUNT(*)
         FROM public.security_events
         WHERE event_type = 'MFA_RESET_BY_ADMIN'
           AND target_user_id = ANY(:target_ids::int[])"
    );
    $allTargetIds = [
        (int) $pendingAdmin['id'],
        $custodianTargetId,
        (int) $targets[$administratorEmployeeId]['id'],
        (int) $targets[$auditFailureEmployeeId]['id'],
    ];
    $eventListLiteral = '{' . implode(',', $allTargetIds) . '}';
    $eventsForTargets->execute(['target_ids' => $eventListLiteral]);
    assertMfaAdminReset(
        (int) $eventsForTargets->fetchColumn() === 0,
        'Failed reset attempts must append no reset audit events.'
    );

    clearMfaAdminResetThrottle($pdo, $actorEmployeeId, $fixtureIp);
    $pdo->prepare(
        'UPDATE public.users
         SET mfa_last_used_step = :step
         WHERE id = :user_id'
    )->execute([
        'step' => $baseStep - 2,
        'user_id' => $actorUserId,
    ]);
    recordLoginFailure($pdo, $actorEmployeeId, $fixtureIp);
    recordLoginFailure($pdo, $actorEmployeeId, $fixtureIp);
    $pendingResetTimestamp = $baseTimestamp +
        (PCMS_MFA_TOTP_PERIOD * 2);
    $pendingReset = processMfaAdminReset(
        $pdo,
        $actorSession,
        (int) $pendingAdmin['id'],
        (int) $pendingAdmin['session_version'],
        $currentPassword,
        generateTotpCode($actorSecret, $pendingResetTimestamp),
        $fixtureIp,
        $pendingResetTimestamp
    );
    $pendingState = readMfaAdminResetUser(
        $pdo,
        (int) $pendingAdmin['id']
    );
    assertMfaAdminReset(
        $pendingReset['status'] === PCMS_MFA_ADMIN_RESET_SUCCESS &&
        !isUserAccountActive($pendingState['mfa_enabled']) &&
        $pendingState['mfa_secret_enc'] === null &&
        $pendingState['mfa_enrolled_at'] === null &&
        $pendingState['mfa_last_used_step'] === null &&
        (int) $pendingState['session_version'] ===
            (int) $pendingAdmin['session_version'] + 1,
        'Resetting an abandoned Administrator enrollment must clear its pending secret and revoke sessions.'
    );
    assertMfaAdminReset(
        determineMfaAuthenticationStage(
            'Administrator',
            false
        ) === PCMS_MFA_AUTH_STAGE_ENROLL,
        'A reset Administrator must be forced through fresh enrollment at next password login.'
    );
    $failureCount = $pdo->prepare(
        'SELECT failure_count
         FROM public.login_attempts
         WHERE attempt_key = :attempt_key'
    );
    $failureCount->execute([
        'attempt_key' => loginThrottleAccountKey($actorEmployeeId),
    ]);
    $accountFailures = $failureCount->fetchColumn();
    $failureCount->execute([
        'attempt_key' => loginThrottleIpKey($fixtureIp),
    ]);
    assertMfaAdminReset(
        $accountFailures === false &&
        (int) $failureCount->fetchColumn() === 2,
        'Successful reset must clear only the actor account throttle and preserve IP-wide state.'
    );

    clearMfaAdminResetThrottle($pdo, $actorEmployeeId, $fixtureIp);
    $custodianResetTimestamp = $pendingResetTimestamp +
        (PCMS_MFA_TOTP_PERIOD * 2);
    $custodianReset = processMfaAdminReset(
        $pdo,
        $actorSession,
        $custodianTargetId,
        $custodianTargetVersion,
        $currentPassword,
        generateTotpCode($actorSecret, $custodianResetTimestamp),
        $fixtureIp,
        $custodianResetTimestamp
    );
    $custodianState = readMfaAdminResetUser(
        $pdo,
        $custodianTargetId
    );
    assertMfaAdminReset(
        $custodianReset['status'] === PCMS_MFA_ADMIN_RESET_SUCCESS &&
        !isUserAccountActive($custodianState['mfa_enabled']) &&
        $custodianState['mfa_secret_enc'] === null &&
        $custodianState['mfa_enrolled_at'] === null &&
        $custodianState['mfa_last_used_step'] === null &&
        (int) $custodianState['session_version'] ===
            $custodianTargetVersion + 1,
        'Resetting an enabled Property Custodian must clear MFA and increment session_version.'
    );
    assertMfaAdminReset(
        determineMfaAuthenticationStage(
            'Property Custodian',
            false
        ) === PCMS_MFA_AUTH_STAGE_COMPLETE,
        'A reset optional Property Custodian must return to password-only login.'
    );

    clearMfaAdminResetThrottle($pdo, $actorEmployeeId, $fixtureIp);
    $administratorTarget = $targets[$administratorEmployeeId];
    $administratorTargetId = (int) $administratorTarget['id'];
    $administratorTargetVersion =
        (int) $administratorTarget['session_version'];
    $administratorResetTimestamp = $custodianResetTimestamp +
        (PCMS_MFA_TOTP_PERIOD * 2);
    $administratorReset = processMfaAdminReset(
        $pdo,
        $actorSession,
        $administratorTargetId,
        $administratorTargetVersion,
        $currentPassword,
        generateTotpCode($actorSecret, $administratorResetTimestamp),
        $fixtureIp,
        $administratorResetTimestamp
    );
    assertMfaAdminReset(
        $administratorReset['status'] ===
            PCMS_MFA_ADMIN_RESET_SUCCESS &&
        (int) readMfaAdminResetUser(
            $pdo,
            $administratorTargetId
        )['session_version'] === $administratorTargetVersion + 1 &&
        determineMfaAuthenticationStage(
            'Administrator',
            false
        ) === PCMS_MFA_AUTH_STAGE_ENROLL,
        'Resetting an enabled Administrator must revoke sessions and force enrollment.'
    );

    $eventsForTargets->execute(['target_ids' => $eventListLiteral]);
    assertMfaAdminReset(
        (int) $eventsForTargets->fetchColumn() === 3,
        'Each successful reset must append exactly one MFA_RESET_BY_ADMIN event.'
    );
    $eventDetails = $pdo->prepare(
        "SELECT actor_user_id, target_user_id, description
         FROM public.security_events
         WHERE event_type = 'MFA_RESET_BY_ADMIN'
           AND target_user_id = ANY(:target_ids::int[])"
    );
    $eventDetails->execute(['target_ids' => $eventListLiteral]);
    $resetEvents = $eventDetails->fetchAll();
    $auditSafe = count($resetEvents) === 3;

    foreach ($resetEvents as $event) {
        $auditSafe = $auditSafe &&
            (int) $event['actor_user_id'] === $actorUserId &&
            (int) $event['target_user_id'] !== $actorUserId &&
            $event['description'] ===
                'Multi-factor authentication was reset by an Administrator.' &&
            preg_match(
                '/password|totp|otpauth|secret|v1:/i',
                (string) $event['description']
            ) !== 1;
    }

    assertMfaAdminReset(
        $auditSafe,
        'Reset events must identify actor and target while containing no password, TOTP, or secret material.'
    );

    clearMfaAdminResetThrottle($pdo, $actorEmployeeId, $fixtureIp);
    $auditFailureTarget = $targets[$auditFailureEmployeeId];
    $auditFailureTargetId = (int) $auditFailureTarget['id'];
    $auditFailureVersion =
        (int) $auditFailureTarget['session_version'];
    $actorBeforeAuditFailure = readMfaAdminResetUser(
        $pdo,
        $actorUserId
    );
    $auditFailureTimestamp = $administratorResetTimestamp +
        (PCMS_MFA_TOTP_PERIOD * 2);
    $auditFailureRolledBack = false;

    try {
        processMfaAdminReset(
            $pdo,
            $actorSession,
            $auditFailureTargetId,
            $auditFailureVersion,
            $currentPassword,
            generateTotpCode($actorSecret, $auditFailureTimestamp),
            $fixtureIp,
            $auditFailureTimestamp,
            static function (): void {
                throw new RuntimeException(
                    'Simulated append-only audit write failure.'
                );
            }
        );
    } catch (RuntimeException $exception) {
        $auditFailureRolledBack = true;
    }

    $afterAuditFailure = readMfaAdminResetUser(
        $pdo,
        $auditFailureTargetId
    );
    $actorAfterAuditFailure = readMfaAdminResetUser(
        $pdo,
        $actorUserId
    );
    $eventsForTargets->execute(['target_ids' => $eventListLiteral]);
    assertMfaAdminReset(
        $auditFailureRolledBack &&
        isUserAccountActive($afterAuditFailure['mfa_enabled']) &&
        $afterAuditFailure['mfa_secret_enc'] !== null &&
        (int) $afterAuditFailure['session_version'] ===
            $auditFailureVersion &&
        (int) $actorAfterAuditFailure['mfa_last_used_step'] ===
            (int) $actorBeforeAuditFailure['mfa_last_used_step'] &&
        (int) $eventsForTargets->fetchColumn() === 3,
        'An audit insertion failure must roll back the target reset and actor TOTP claim together.'
    );

    $pdo->rollBack();
    assertMfaAdminReset(
        (int) $pdo->query(
            'SELECT COUNT(*) FROM public.security_events'
        )->fetchColumn() === $baselineEventCount,
        'Administrator reset tests must leave no real users, MFA state, events, or throttle fixtures.'
    );
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $exception;
} finally {
    sodium_memzero($actorSecret);
    sodium_memzero($targetSecret);
}

echo "MFA Administrator reset tests passed: {$testsRun}" . PHP_EOL;
