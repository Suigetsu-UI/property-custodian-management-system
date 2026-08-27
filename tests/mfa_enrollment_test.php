<?php

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/pending_mfa.php';

$testsRun = 0;

function assertMfaEnrollment(
    bool $condition,
    string $message
): void {
    global $testsRun;
    $testsRun++;

    if (!$condition) {
        throw new RuntimeException('FAILED: ' . $message);
    }
}

function withMfaEnrollmentEnvironment(
    string $name,
    ?string $value,
    callable $operation
): mixed {
    $original = getenv($name);
    $hadEnvEntry = array_key_exists($name, $_ENV);
    $originalEnvEntry = $_ENV[$name] ?? null;

    if ($value === null) {
        putenv($name);
        unset($_ENV[$name]);
    } else {
        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
    }

    try {
        return $operation();
    } finally {
        if ($original === false) {
            putenv($name);
        } else {
            putenv($name . '=' . $original);
        }

        if ($hadEnvEntry) {
            $_ENV[$name] = $originalEnvEntry;
        } else {
            unset($_ENV[$name]);
        }
    }
}

$originalSession = $_SESSION ?? [];

try {
    $_SESSION = [
        'access_csrf_token' => 'test-csrf-token',
        'user' => ['id' => 999],
        'session_created_at' => 1,
        'session_last_activity_at' => 1,
    ];
    beginPendingMfaSession(
        42,
        7,
        PCMS_PENDING_MFA_PURPOSE_ENROLL,
        1000
    );

    assertMfaEnrollment(
        !isset($_SESSION['user']) &&
        !isset($_SESSION['session_created_at']) &&
        !isset($_SESSION['session_last_activity_at']),
        'Beginning pending MFA must not retain a full authenticated session.'
    );
    assertMfaEnrollment(
        array_keys($_SESSION['pending_mfa']) === [
            'user_id',
            'purpose',
            'expires_at',
            'session_version',
        ],
        'Pending MFA state must contain only user ID, purpose, expiry, and authenticated session version.'
    );
    assertMfaEnrollment(
        $_SESSION['pending_mfa'] === [
            'user_id' => 42,
            'purpose' => PCMS_PENDING_MFA_PURPOSE_ENROLL,
            'expires_at' => 1300,
            'session_version' => 7,
        ],
        'Pending MFA enrollment must expire after exactly five minutes.'
    );
    assertMfaEnrollment(
        getPendingMfaSession(
            PCMS_PENDING_MFA_PURPOSE_ENROLL,
            1299
        ) !== null,
        'Pending MFA state must remain valid before its expiry.'
    );
    assertMfaEnrollment(
        getPendingMfaSession(
            PCMS_PENDING_MFA_PURPOSE_ENROLL,
            1300
        ) === null &&
        !isset($_SESSION['pending_mfa']),
        'Expired pending MFA state must be rejected and cleared.'
    );
} finally {
    $_SESSION = $originalSession;
}

withMfaEnrollmentEnvironment(
    'MFA_REQUIRE_ADMINISTRATOR',
    'true',
    function (): void {
        assertMfaEnrollment(
            determineMfaAuthenticationStage('Administrator', false) ===
                PCMS_MFA_AUTH_STAGE_ENROLL,
            'A required Administrator without MFA must be forced to enroll.'
        );
        assertMfaEnrollment(
            determineMfaAuthenticationStage('Administrator', true) ===
                PCMS_MFA_AUTH_STAGE_CHALLENGE,
            'An enrolled required Administrator must proceed to a challenge, not full access.'
        );
    }
);
withMfaEnrollmentEnvironment(
    'MFA_REQUIRE_PROPERTY_CUSTODIAN',
    'false',
    function (): void {
        assertMfaEnrollment(
            determineMfaAuthenticationStage(
                'Property Custodian',
                false
            ) === PCMS_MFA_AUTH_STAGE_COMPLETE,
            'An optional Property Custodian must retain the current complete-login path.'
        );
        assertMfaEnrollment(
            determineMfaAuthenticationStage(
                'Property Custodian',
                true
            ) === PCMS_MFA_AUTH_STAGE_CHALLENGE,
            'An optional Property Custodian with MFA enabled must still complete an MFA challenge.'
        );
    }
);

$checkAuthSource = file_get_contents(__DIR__ . '/../auth/check_auth.php');
$authenticateSource = file_get_contents(__DIR__ . '/../auth/authenticate.php');
$enrollmentRouteSource = file_get_contents(__DIR__ . '/../auth/mfa_enroll.php');
$pendingHelperSource = file_get_contents(__DIR__ . '/../includes/pending_mfa.php');

foreach (
    [
        $checkAuthSource,
        $authenticateSource,
        $enrollmentRouteSource,
        $pendingHelperSource,
    ] as $source
) {
    if ($source === false) {
        throw new RuntimeException('Unable to inspect the MFA enrollment flow.');
    }
}

assertMfaEnrollment(
    str_contains($checkAuthSource, "!isset(\$_SESSION['user'])") &&
    !str_contains($checkAuthSource, "\$_SESSION['pending_mfa']"),
    'Pending MFA sessions must not satisfy normal module authentication.'
);
assertMfaEnrollment(
    str_contains($authenticateSource, 'determineMfaAuthenticationStage(') &&
    str_contains($authenticateSource, 'beginPendingMfaSession(') &&
    strpos($authenticateSource, 'beginPendingMfaSession(') <
        strpos($authenticateSource, '$_SESSION["user"]'),
    'Password authentication must branch to pending enrollment before full session creation.'
);
$enrollBranchStart = strpos(
    $authenticateSource,
    'if ($mfaStage === PCMS_MFA_AUTH_STAGE_ENROLL)'
);
$completeBranchStart = strpos(
    $authenticateSource,
    '} elseif ($mfaStage === PCMS_MFA_AUTH_STAGE_COMPLETE)'
);
$completeBranchEnd = strpos(
    $authenticateSource,
    '        } else {'
);
assertMfaEnrollment(
    $enrollBranchStart !== false &&
    $completeBranchStart !== false &&
    $completeBranchEnd !== false &&
    !str_contains(
        substr(
            $authenticateSource,
            $enrollBranchStart,
            $completeBranchStart - $enrollBranchStart
        ),
        'clearLoginAccountFailures('
    ) &&
    str_contains(
        substr(
            $authenticateSource,
            $completeBranchStart,
            $completeBranchEnd - $completeBranchStart
        ),
        'clearLoginAccountFailures('
    ),
    'Login throttle state must clear only on complete authentication, not pending enrollment.'
);
assertMfaEnrollment(
    str_contains($enrollmentRouteSource, 'requireValidAccessCsrfPost()') &&
    str_contains(
        $enrollmentRouteSource,
        "no-store, no-cache, must-revalidate, max-age=0"
    ) &&
    !str_contains($enrollmentRouteSource, "\$_SESSION['user'] =") &&
    !preg_match('/\bqr\b/i', $enrollmentRouteSource),
    'Enrollment must require CSRF, disable caching, avoid full session creation, and omit QR enrollment.'
);
assertMfaEnrollment(
    !preg_match(
        '/\$_SESSION\s*\[[^\]]*(?:secret|code|uri)/i',
        $pendingHelperSource . $enrollmentRouteSource
    ),
    'Plaintext MFA material must never be written to the PHP session.'
);

$pdo = getDbConnection();
$baselineEventCount = (int) $pdo->query(
    'SELECT COUNT(*) FROM public.security_events'
)->fetchColumn();
$fixtureEmployeeId = 'MFA-ENROLL-TEST-' . bin2hex(random_bytes(8));

withMfaEnrollmentEnvironment(
    'MFA_REQUIRE_ADMINISTRATOR',
    'true',
    function () use (
        $pdo,
        $fixtureEmployeeId,
        $baselineEventCount
    ): void {
        try {
            $pdo->beginTransaction();
            $insertUser = $pdo->prepare(
                'INSERT INTO public.users (
                    employee_id,
                    full_name,
                    password_hash,
                    role,
                    is_active
                 ) VALUES (
                    :employee_id,
                    :full_name,
                    :password_hash,
                    :role,
                    TRUE
                 )
                 RETURNING id, session_version'
            );
            $insertUser->execute([
                'employee_id' => $fixtureEmployeeId,
                'full_name' => 'MFA Enrollment Test Fixture',
                'password_hash' => password_hash(
                    bin2hex(random_bytes(16)),
                    PASSWORD_DEFAULT
                ),
                'role' => 'Administrator',
            ]);
            $inserted = $insertUser->fetch();
            $fixtureUserId = (int) $inserted['id'];
            $initialSessionVersion = (int) $inserted['session_version'];

            $firstLoad = loadPendingMfaEnrollment(
                $pdo,
                $fixtureUserId,
                $initialSessionVersion
            );
            assertMfaEnrollment(
                preg_match(
                    '/\A[A-Z2-7]{32}\z/',
                    $firstLoad['plain_secret']
                ) === 1 &&
                str_starts_with(
                    $firstLoad['encrypted_secret'],
                    'v1:'
                ),
                'First enrollment load must create a Base32 secret stored only in encrypted form.'
            );

            $readState = $pdo->prepare(
                'SELECT mfa_enabled, mfa_secret_enc, mfa_enrolled_at,
                        mfa_last_used_step, session_version
                 FROM public.users
                 WHERE id = :user_id'
            );
            $readState->execute(['user_id' => $fixtureUserId]);
            $pendingDatabaseState = $readState->fetch();
            assertMfaEnrollment(
                !isUserAccountActive(
                    $pendingDatabaseState['mfa_enabled']
                ) &&
                hash_equals(
                    $firstLoad['encrypted_secret'],
                    $pendingDatabaseState['mfa_secret_enc']
                ) &&
                $pendingDatabaseState['mfa_enrolled_at'] === null &&
                $pendingDatabaseState['mfa_last_used_step'] === null &&
                (int) $pendingDatabaseState['session_version'] ===
                    $initialSessionVersion,
                'Pending enrollment must leave MFA disabled and session_version unchanged.'
            );

            $changeSessionVersion = $pdo->prepare(
                'UPDATE public.users
                 SET session_version = :session_version
                 WHERE id = :user_id'
            );
            $changeSessionVersion->execute([
                'session_version' => $initialSessionVersion + 1,
                'user_id' => $fixtureUserId,
            ]);
            $staleLoadRejected = false;

            try {
                loadPendingMfaEnrollment(
                    $pdo,
                    $fixtureUserId,
                    $initialSessionVersion
                );
            } catch (RuntimeException $exception) {
                $staleLoadRejected = true;
            }

            assertMfaEnrollment(
                $staleLoadRejected,
                'Enrollment must reject a pending session after session_version changes.'
            );
            $changeSessionVersion->execute([
                'session_version' => $initialSessionVersion,
                'user_id' => $fixtureUserId,
            ]);

            $secondLoad = loadPendingMfaEnrollment(
                $pdo,
                $fixtureUserId,
                $initialSessionVersion
            );
            assertMfaEnrollment(
                hash_equals(
                    $firstLoad['plain_secret'],
                    $secondLoad['plain_secret']
                ) &&
                hash_equals(
                    $firstLoad['encrypted_secret'],
                    $secondLoad['encrypted_secret']
                ),
                'Repeated enrollment loads must reuse the same pending secret.'
            );

            assertMfaEnrollment(
                verifyTotpCode(
                    $firstLoad['plain_secret'],
                    'not-six-digits',
                    1234567890
                ) === null,
                'An invalid enrollment TOTP must be rejected.'
            );
            $readState->execute(['user_id' => $fixtureUserId]);
            $afterInvalidCode = $readState->fetch();
            assertMfaEnrollment(
                !isUserAccountActive($afterInvalidCode['mfa_enabled']) &&
                $afterInvalidCode['mfa_enrolled_at'] === null &&
                $afterInvalidCode['mfa_last_used_step'] === null,
                'An invalid TOTP must leave MFA disabled.'
            );

            $testTimestamp = 1234567890;
            $validCode = generateTotpCode(
                $firstLoad['plain_secret'],
                $testTimestamp
            );
            $matchedStep = verifyTotpCode(
                $firstLoad['plain_secret'],
                $validCode,
                $testTimestamp
            );
            assertMfaEnrollment(
                $matchedStep === intdiv(
                    $testTimestamp,
                    PCMS_MFA_TOTP_PERIOD
                ),
                'A valid enrollment TOTP must return its exact matched timestep.'
            );

            $changeSessionVersion->execute([
                'session_version' => $initialSessionVersion + 1,
                'user_id' => $fixtureUserId,
            ]);
            assertMfaEnrollment(
                completePendingMfaEnrollment(
                    $pdo,
                    $fixtureUserId,
                    $firstLoad['role'],
                    $firstLoad['encrypted_secret'],
                    $initialSessionVersion,
                    $matchedStep
                ) === null,
                'Enrollment completion must reject a stale authenticated session_version.'
            );
            $changeSessionVersion->execute([
                'session_version' => $initialSessionVersion,
                'user_id' => $fixtureUserId,
            ]);

            $newSessionVersion = completePendingMfaEnrollment(
                $pdo,
                $fixtureUserId,
                $firstLoad['role'],
                $firstLoad['encrypted_secret'],
                $initialSessionVersion,
                $matchedStep
            );
            assertMfaEnrollment(
                $newSessionVersion === $initialSessionVersion + 1,
                'Successful enrollment must increment session_version once.'
            );

            $readState->execute(['user_id' => $fixtureUserId]);
            $completedState = $readState->fetch();
            assertMfaEnrollment(
                isUserAccountActive($completedState['mfa_enabled']) &&
                $completedState['mfa_enrolled_at'] !== null &&
                (int) $completedState['mfa_last_used_step'] ===
                    $matchedStep &&
                (int) $completedState['session_version'] ===
                    $initialSessionVersion + 1,
                'Valid TOTP completion must enable MFA and store the matched timestep.'
            );
            assertMfaEnrollment(
                !claimMfaTimestep(
                    $pdo,
                    $fixtureUserId,
                    $matchedStep
                ),
                'The enrollment code timestep must be consumed against replay.'
            );
            assertMfaEnrollment(
                completePendingMfaEnrollment(
                    $pdo,
                    $fixtureUserId,
                    $firstLoad['role'],
                    $firstLoad['encrypted_secret'],
                    $initialSessionVersion,
                    $matchedStep
                ) === null,
                'A completed enrollment transition must not run twice.'
            );

            $readEvent = $pdo->prepare(
                'SELECT event_type, actor_user_id, target_user_id, description
                 FROM public.security_events
                 WHERE target_user_id = :target_user_id'
            );
            $readEvent->execute(['target_user_id' => $fixtureUserId]);
            $events = $readEvent->fetchAll();
            assertMfaEnrollment(
                count($events) === 1 &&
                $events[0]['event_type'] === 'MFA_ENROLLED' &&
                (int) $events[0]['actor_user_id'] === $fixtureUserId &&
                (int) $events[0]['target_user_id'] === $fixtureUserId,
                'Successful enrollment must append one MFA_ENROLLED event.'
            );

            $pdo->rollBack();

            $fixtureCount = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM public.users
                 WHERE employee_id = :employee_id'
            );
            $fixtureCount->execute([
                'employee_id' => $fixtureEmployeeId,
            ]);
            assertMfaEnrollment(
                (int) $fixtureCount->fetchColumn() === 0 &&
                (int) $pdo->query(
                    'SELECT COUNT(*) FROM public.security_events'
                )->fetchColumn() === $baselineEventCount,
                'Enrollment tests must leave no user or security-event fixtures.'
            );
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }
);

echo "MFA enrollment tests passed: {$testsRun}" . PHP_EOL;
