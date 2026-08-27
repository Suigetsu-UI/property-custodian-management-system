<?php

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/mfa_account_security.php';

$testsRun = 0;

function assertMfaAccountSecurity(
    bool $condition,
    string $message
): void {
    global $testsRun;
    $testsRun++;

    if (!$condition) {
        throw new RuntimeException('FAILED: ' . $message);
    }
}

function clearMfaAccountSecurityThrottle(
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

function readMfaAccountSecurityUser(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT mfa_enabled, mfa_secret_enc, mfa_enrolled_at,
                mfa_last_used_step, session_version
         FROM public.users
         WHERE id = :user_id'
    );
    $stmt->execute(['user_id' => $userId]);
    $state = $stmt->fetch();

    if (!is_array($state)) {
        throw new RuntimeException('Unable to read the MFA test fixture.');
    }

    return $state;
}

$pageSource = file_get_contents(
    __DIR__ . '/../modules/account/security.php'
);
$enableSource = file_get_contents(
    __DIR__ . '/../modules/account/mfa_enable.php'
);
$disableSource = file_get_contents(
    __DIR__ . '/../modules/account/mfa_disable.php'
);
$enrollmentSource = file_get_contents(
    __DIR__ . '/../auth/mfa_enroll.php'
);
$sidebarSource = file_get_contents(__DIR__ . '/../includes/sidebar.php');

foreach (
    [
        $pageSource,
        $enableSource,
        $disableSource,
        $enrollmentSource,
        $sidebarSource,
    ] as $source
) {
    if ($source === false) {
        throw new RuntimeException(
            'Unable to inspect the Account Security flow.'
        );
    }
}

assertMfaAccountSecurity(
    str_contains(
        $pageSource,
        "require_once __DIR__ . '/../../auth/check_auth.php'"
    ) &&
    str_contains($sidebarSource, "'label' => 'Account Security'"),
    'Account Security must require normal authentication and appear for both roles.'
);
assertMfaAccountSecurity(
    str_contains($pageSource, '$securityState[\'mfa_required\']') &&
    str_contains($pageSource, 'MFA cannot be disabled because it is required') &&
    str_contains($pageSource, '$securityState[\'can_disable\']'),
    'Required-role accounts must receive an informational state without a disable path.'
);
assertMfaAccountSecurity(
    str_contains($enableSource, 'requireValidAccessCsrfPost()') &&
    str_contains($disableSource, 'requireValidAccessCsrfPost()') &&
    str_contains($pageSource, 'getAccessCsrfToken()'),
    'Every Account Security mutation must use POST and CSRF protection.'
);
assertMfaAccountSecurity(
    str_contains(
        $enableSource,
        'PCMS_PENDING_MFA_PURPOSE_SETTINGS_ENROLL'
    ) &&
    str_contains(
        $enrollmentSource,
        'PCMS_PENDING_MFA_PURPOSE_SETTINGS_ENROLL'
    ),
    'Voluntary enrollment must use its distinct settings_enroll purpose.'
);
assertMfaAccountSecurity(
    str_contains(
        $enrollmentSource,
        'no-store, no-cache, must-revalidate, max-age=0'
    ) &&
    !preg_match(
        '/\$_SESSION\s*\[[^\]]*(?:secret|code|uri)/i',
        $enableSource . $enrollmentSource
    ),
    'Enrollment secrets must be non-cacheable and never stored in the PHP session.'
);
assertMfaAccountSecurity(
    str_contains($disableSource, "\$_POST['current_password']") &&
    str_contains($disableSource, "\$_POST['totp_code']") &&
    !preg_match('/forgot authenticator|backup code|secret question|master password|self[- ]reset/i', $pageSource),
    'Disable must require both current factors and expose no self-recovery mechanism.'
);
assertMfaAccountSecurity(
    strpos($disableSource, "PCMS_MFA_SETTINGS_SUCCESS") <
        strpos($disableSource, 'destroyPcmsSession()') &&
    str_contains($enrollmentSource, 'destroyPcmsSession()'),
    'Successful enable and disable routes must revoke the current PHP session.'
);

$pdo = getDbConnection();
$baselineEventCount = (int) $pdo->query(
    'SELECT COUNT(*) FROM public.security_events'
)->fetchColumn();
$fixtureSuffix = bin2hex(random_bytes(6));
$administratorEmployeeId = 'MFA-SET-ADMIN-' . $fixtureSuffix;
$custodianEmployeeId = 'MFA-SET-CUST-' . $fixtureSuffix;
$fixtureIp = '198.51.100.88';
$currentPassword = 'Fixture-' . bin2hex(random_bytes(12));
$testTimestamp = 1234567890;
$currentStep = intdiv($testTimestamp, PCMS_MFA_TOTP_PERIOD);
$plainSecret = generateMfaSecret();
$encryptedSecret = encryptMfaSecret($plainSecret);

try {
    $pdo->beginTransaction();

    $insertEnabledUser = $pdo->prepare(
        'INSERT INTO public.users (
            employee_id, full_name, password_hash, role, is_active,
            mfa_enabled, mfa_secret_enc, mfa_enrolled_at,
            mfa_last_used_step
         ) VALUES (
            :employee_id, :full_name, :password_hash, :role, TRUE,
            TRUE, :mfa_secret_enc, CURRENT_TIMESTAMP,
            :mfa_last_used_step
         )
         RETURNING id, session_version'
    );
    $insertEnabledUser->execute([
        'employee_id' => $administratorEmployeeId,
        'full_name' => 'MFA Settings Administrator Fixture',
        'password_hash' => password_hash(
            $currentPassword,
            PASSWORD_DEFAULT
        ),
        'role' => 'Administrator',
        'mfa_secret_enc' => $encryptedSecret,
        'mfa_last_used_step' => $currentStep - 2,
    ]);
    $administrator = $insertEnabledUser->fetch();
    $administratorUserId = (int) $administrator['id'];
    $administratorSession = [
        'id' => $administratorUserId,
        'employee_id' => $administratorEmployeeId,
        'role' => 'Administrator',
        'session_version' => (int) $administrator['session_version'],
    ];

    $insertCustodian = $pdo->prepare(
        'INSERT INTO public.users (
            employee_id, full_name, password_hash, role, is_active
         ) VALUES (
            :employee_id, :full_name, :password_hash,
            :role, TRUE
         )
         RETURNING id, session_version'
    );
    $insertCustodian->execute([
        'employee_id' => $custodianEmployeeId,
        'full_name' => 'MFA Settings Custodian Fixture',
        'password_hash' => password_hash(
            $currentPassword,
            PASSWORD_DEFAULT
        ),
        'role' => 'Property Custodian',
    ]);
    $custodian = $insertCustodian->fetch();
    $custodianUserId = (int) $custodian['id'];
    $initialSessionVersion = (int) $custodian['session_version'];
    $custodianSession = [
        'id' => $custodianUserId,
        'employee_id' => $custodianEmployeeId,
        'role' => 'Property Custodian',
        'session_version' => $initialSessionVersion,
    ];

    $administratorState = loadMfaAccountSecurityState(
        $pdo,
        $administratorSession
    );
    assertMfaAccountSecurity(
        $administratorState['mfa_enabled'] &&
        $administratorState['mfa_required'] &&
        !$administratorState['can_enable'] &&
        !$administratorState['can_disable'],
        'An enabled Administrator must be shown as required without enable or disable actions.'
    );
    $administratorDisable = processMfaSettingsDisable(
        $pdo,
        $administratorSession,
        $currentPassword,
        generateTotpCode($plainSecret, $testTimestamp),
        $fixtureIp,
        $testTimestamp
    );
    assertMfaAccountSecurity(
        $administratorDisable['status'] ===
            PCMS_MFA_SETTINGS_REQUIRED &&
        isUserAccountActive(
            readMfaAccountSecurityUser(
                $pdo,
                $administratorUserId
            )['mfa_enabled']
        ),
        'An Administrator disable attempt must be rejected without changing MFA.'
    );

    $disabledState = loadMfaAccountSecurityState(
        $pdo,
        $custodianSession
    );
    assertMfaAccountSecurity(
        !$disabledState['mfa_enabled'] &&
        !$disabledState['mfa_required'] &&
        $disabledState['can_enable'] &&
        !$disabledState['can_disable'],
        'A disabled optional Property Custodian must receive the Enable MFA action.'
    );
    $pendingUser = prepareMfaSettingsEnrollment(
        $pdo,
        $custodianSession
    );
    assertMfaAccountSecurity(
        $pendingUser === [
            'id' => $custodianUserId,
            'session_version' => $initialSessionVersion,
        ],
        'Enable must verify the current account and authenticated session_version.'
    );

    $originalSession = $_SESSION ?? [];
    $_SESSION = [
        'user' => $custodianSession,
        'session_created_at' => 1,
        'session_last_activity_at' => 1,
        'access_csrf_token' => 'settings-test-token',
    ];
    beginPendingMfaSession(
        $custodianUserId,
        $initialSessionVersion,
        PCMS_PENDING_MFA_PURPOSE_SETTINGS_ENROLL,
        1000
    );
    assertMfaAccountSecurity(
        !isset($_SESSION['user']) &&
        $_SESSION['pending_mfa'] === [
            'user_id' => $custodianUserId,
            'purpose' => PCMS_PENDING_MFA_PURPOSE_SETTINGS_ENROLL,
            'expires_at' => 1300,
            'session_version' => $initialSessionVersion,
        ] &&
        !isset($_SESSION['pending_mfa']['plain_secret']),
        'Settings enrollment must revoke normal access and store only bounded pending metadata.'
    );
    $_SESSION = $originalSession;

    $firstEnrollment = loadPendingMfaEnrollment(
        $pdo,
        $custodianUserId,
        $initialSessionVersion,
        PCMS_PENDING_MFA_PURPOSE_SETTINGS_ENROLL
    );
    $secondEnrollment = loadPendingMfaEnrollment(
        $pdo,
        $custodianUserId,
        $initialSessionVersion,
        PCMS_PENDING_MFA_PURPOSE_SETTINGS_ENROLL
    );
    assertMfaAccountSecurity(
        str_starts_with($firstEnrollment['encrypted_secret'], 'v1:') &&
        hash_equals(
            $firstEnrollment['plain_secret'],
            $secondEnrollment['plain_secret']
        ) &&
        hash_equals(
            $firstEnrollment['encrypted_secret'],
            $secondEnrollment['encrypted_secret']
        ),
        'Voluntary enrollment must create one encrypted pending secret and reuse it on reload.'
    );
    assertMfaAccountSecurity(
        verifyTotpCode(
            $firstEnrollment['plain_secret'],
            'invalid',
            $testTimestamp
        ) === null &&
        !isUserAccountActive(
            readMfaAccountSecurityUser(
                $pdo,
                $custodianUserId
            )['mfa_enabled']
        ),
        'An invalid voluntary enrollment code must leave MFA disabled.'
    );

    $pdo->prepare(
        'UPDATE public.users
         SET session_version = session_version + 1
         WHERE id = ?'
    )->execute([$custodianUserId]);
    $staleEnrollmentRejected = false;

    try {
        loadPendingMfaEnrollment(
            $pdo,
            $custodianUserId,
            $initialSessionVersion,
            PCMS_PENDING_MFA_PURPOSE_SETTINGS_ENROLL
        );
    } catch (RuntimeException $exception) {
        $staleEnrollmentRejected = true;
    }

    assertMfaAccountSecurity(
        $staleEnrollmentRejected,
        'Voluntary enrollment must reject a stale authenticated session_version.'
    );
    $pdo->prepare(
        'UPDATE public.users
         SET session_version = ?
         WHERE id = ?'
    )->execute([$initialSessionVersion, $custodianUserId]);

    $enrollmentCode = generateTotpCode(
        $firstEnrollment['plain_secret'],
        $testTimestamp
    );
    $enrollmentStep = verifyTotpCode(
        $firstEnrollment['plain_secret'],
        $enrollmentCode,
        $testTimestamp
    );
    $enabledSessionVersion = completePendingMfaEnrollment(
        $pdo,
        $custodianUserId,
        $firstEnrollment['role'],
        $firstEnrollment['encrypted_secret'],
        $initialSessionVersion,
        (int) $enrollmentStep,
        PCMS_PENDING_MFA_PURPOSE_SETTINGS_ENROLL
    );
    assertMfaAccountSecurity(
        $enabledSessionVersion === $initialSessionVersion + 1,
        'Successful voluntary enrollment must increment session_version.'
    );
    $enabledDatabaseState = readMfaAccountSecurityUser(
        $pdo,
        $custodianUserId
    );
    assertMfaAccountSecurity(
        isUserAccountActive($enabledDatabaseState['mfa_enabled']) &&
        $enabledDatabaseState['mfa_enrolled_at'] !== null &&
        (int) $enabledDatabaseState['mfa_last_used_step'] ===
            $enrollmentStep,
        'A valid voluntary enrollment code must enable MFA and consume its timestep.'
    );
    $eventCount = $pdo->prepare(
        'SELECT event_type, COUNT(*) AS event_count
         FROM public.security_events
         WHERE target_user_id = :target_user_id
         GROUP BY event_type'
    );
    $eventCount->execute(['target_user_id' => $custodianUserId]);
    $eventsByType = array_column(
        $eventCount->fetchAll(),
        'event_count',
        'event_type'
    );
    assertMfaAccountSecurity(
        (int) ($eventsByType['MFA_ENROLLED'] ?? 0) === 1,
        'Successful voluntary enrollment must append one MFA_ENROLLED event.'
    );
    assertMfaAccountSecurity(
        determineMfaAuthenticationStage(
            'Property Custodian',
            true
        ) === PCMS_MFA_AUTH_STAGE_CHALLENGE,
        'An optional user who enables MFA must require TOTP at the next login.'
    );

    $enabledSession = $custodianSession;
    $enabledSession['session_version'] = $enabledSessionVersion;
    $validDisableTimestamp = $testTimestamp +
        (PCMS_MFA_TOTP_PERIOD * 2);
    $validDisableCode = generateTotpCode(
        $firstEnrollment['plain_secret'],
        $validDisableTimestamp
    );

    clearMfaAccountSecurityThrottle(
        $pdo,
        $custodianEmployeeId,
        $fixtureIp
    );
    $wrongPassword = processMfaSettingsDisable(
        $pdo,
        $enabledSession,
        'wrong-password',
        $validDisableCode,
        $fixtureIp,
        $validDisableTimestamp
    );
    assertMfaAccountSecurity(
        $wrongPassword['status'] === PCMS_MFA_SETTINGS_INVALID &&
        (int) readMfaAccountSecurityUser(
            $pdo,
            $custodianUserId
        )['mfa_last_used_step'] === $enrollmentStep,
        'Wrong-password disable must fail generically without consuming a valid TOTP.'
    );
    $wrongTotp = processMfaSettingsDisable(
        $pdo,
        $enabledSession,
        $currentPassword,
        '000000',
        $fixtureIp,
        $validDisableTimestamp
    );
    assertMfaAccountSecurity(
        $wrongTotp['status'] === PCMS_MFA_SETTINGS_INVALID &&
        isUserAccountActive(
            readMfaAccountSecurityUser(
                $pdo,
                $custodianUserId
            )['mfa_enabled']
        ),
        'Wrong-TOTP disable must fail generically and leave MFA enabled.'
    );

    clearMfaAccountSecurityThrottle(
        $pdo,
        $custodianEmployeeId,
        $fixtureIp
    );
    $pdo->prepare(
        'UPDATE public.users
         SET mfa_last_used_step = ?
         WHERE id = ?'
    )->execute([
        intdiv($validDisableTimestamp, PCMS_MFA_TOTP_PERIOD),
        $custodianUserId,
    ]);
    $replayedTotp = processMfaSettingsDisable(
        $pdo,
        $enabledSession,
        $currentPassword,
        $validDisableCode,
        $fixtureIp,
        $validDisableTimestamp
    );
    assertMfaAccountSecurity(
        $replayedTotp['status'] === PCMS_MFA_SETTINGS_INVALID,
        'A replayed TOTP must not disable MFA.'
    );

    clearMfaAccountSecurityThrottle(
        $pdo,
        $custodianEmployeeId,
        $fixtureIp
    );
    $blockResults = [];

    for ($attempt = 1; $attempt <= PCMS_LOGIN_MAX_FAILURES; $attempt++) {
        $blockResults[] = processMfaSettingsDisable(
            $pdo,
            $enabledSession,
            'wrong-password',
            'invalid',
            $fixtureIp,
            $validDisableTimestamp
        );
    }

    assertMfaAccountSecurity(
        array_column(array_slice($blockResults, 0, 4), 'status') === [
            PCMS_MFA_SETTINGS_INVALID,
            PCMS_MFA_SETTINGS_INVALID,
            PCMS_MFA_SETTINGS_INVALID,
            PCMS_MFA_SETTINGS_INVALID,
        ] &&
        $blockResults[4]['status'] === PCMS_MFA_SETTINGS_BLOCKED,
        'The fifth failed disable reauthentication must begin the shared cooldown.'
    );
    $blockedAgain = processMfaSettingsDisable(
        $pdo,
        $enabledSession,
        $currentPassword,
        $validDisableCode,
        $fixtureIp,
        $validDisableTimestamp
    );
    assertMfaAccountSecurity(
        $blockedAgain['status'] === PCMS_MFA_SETTINGS_BLOCKED,
        'A blocked disable request must be rejected before factor verification.'
    );

    clearMfaAccountSecurityThrottle(
        $pdo,
        $custodianEmployeeId,
        $fixtureIp
    );
    $staleSession = $enabledSession;
    $staleSession['session_version']--;
    assertMfaAccountSecurity(
        processMfaSettingsDisable(
            $pdo,
            $staleSession,
            $currentPassword,
            $validDisableCode,
            $fixtureIp,
            $validDisableTimestamp
        )['status'] === PCMS_MFA_SETTINGS_STALE,
        'Disable must reject a stale authenticated session_version.'
    );

    $freshDisableTimestamp = $validDisableTimestamp +
        (PCMS_MFA_TOTP_PERIOD * 2);
    $freshDisableStep = intdiv(
        $freshDisableTimestamp,
        PCMS_MFA_TOTP_PERIOD
    );
    $freshDisableCode = generateTotpCode(
        $firstEnrollment['plain_secret'],
        $freshDisableTimestamp
    );
    $pdo->prepare(
        'UPDATE public.users
         SET mfa_last_used_step = ?
         WHERE id = ?'
    )->execute([$freshDisableStep - 2, $custodianUserId]);
    recordLoginFailure($pdo, $custodianEmployeeId, $fixtureIp);
    recordLoginFailure($pdo, $custodianEmployeeId, $fixtureIp);
    $disabled = processMfaSettingsDisable(
        $pdo,
        $enabledSession,
        $currentPassword,
        $freshDisableCode,
        $fixtureIp,
        $freshDisableTimestamp
    );
    assertMfaAccountSecurity(
        $disabled['status'] === PCMS_MFA_SETTINGS_SUCCESS &&
        $disabled['session_version'] === $enabledSessionVersion + 1,
        'Valid password plus a fresh current TOTP must disable optional MFA.'
    );
    $finalState = readMfaAccountSecurityUser(
        $pdo,
        $custodianUserId
    );
    assertMfaAccountSecurity(
        !isUserAccountActive($finalState['mfa_enabled']) &&
        $finalState['mfa_secret_enc'] === null &&
        $finalState['mfa_enrolled_at'] === null &&
        $finalState['mfa_last_used_step'] === null &&
        (int) $finalState['session_version'] ===
            $enabledSessionVersion + 1,
        'Successful disable must clear every MFA field and increment session_version.'
    );
    $eventCount->execute(['target_user_id' => $custodianUserId]);
    $eventsByType = array_column(
        $eventCount->fetchAll(),
        'event_count',
        'event_type'
    );
    assertMfaAccountSecurity(
        (int) ($eventsByType['MFA_ENROLLED'] ?? 0) === 1 &&
        (int) ($eventsByType['MFA_DISABLED'] ?? 0) === 1,
        'Successful disable must append one MFA_DISABLED event beside MFA_ENROLLED.'
    );
    $failureCount = $pdo->prepare(
        'SELECT failure_count
         FROM public.login_attempts
         WHERE attempt_key = :attempt_key'
    );
    $failureCount->execute([
        'attempt_key' => loginThrottleAccountKey($custodianEmployeeId),
    ]);
    $accountThrottle = $failureCount->fetchColumn();
    $failureCount->execute([
        'attempt_key' => loginThrottleIpKey($fixtureIp),
    ]);
    assertMfaAccountSecurity(
        $accountThrottle === false &&
        (int) $failureCount->fetchColumn() === 2,
        'Successful disable must clear only the account throttle and preserve IP-wide state.'
    );
    assertMfaAccountSecurity(
        determineMfaAuthenticationStage(
            'Property Custodian',
            false
        ) === PCMS_MFA_AUTH_STAGE_COMPLETE,
        'An optional user returns to password-only login after disabling MFA.'
    );

    $pdo->rollBack();
    assertMfaAccountSecurity(
        (int) $pdo->query(
            'SELECT COUNT(*) FROM public.security_events'
        )->fetchColumn() === $baselineEventCount,
        'Account Security tests must leave no real users, MFA state, events, or throttle fixtures.'
    );
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $exception;
} finally {
    sodium_memzero($plainSecret);
}

echo "MFA Account Security tests passed: {$testsRun}" . PHP_EOL;
