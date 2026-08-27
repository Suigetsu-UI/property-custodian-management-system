<?php

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/mfa_challenge.php';

$testsRun = 0;

function assertMfaChallenge(bool $condition, string $message): void
{
    global $testsRun;
    $testsRun++;

    if (!$condition) {
        throw new RuntimeException('FAILED: ' . $message);
    }
}

function clearMfaChallengeThrottle(
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

$authenticateSource = file_get_contents(__DIR__ . '/../auth/authenticate.php');
$routeSource = file_get_contents(__DIR__ . '/../auth/mfa_challenge.php');
$helperSource = file_get_contents(__DIR__ . '/../includes/mfa_challenge.php');

foreach ([$authenticateSource, $routeSource, $helperSource] as $source) {
    if ($source === false) {
        throw new RuntimeException('Unable to inspect the MFA challenge flow.');
    }
}

assertMfaChallenge(
    str_contains(
        $authenticateSource,
        'PCMS_PENDING_MFA_PURPOSE_CHALLENGE'
    ) &&
    str_contains($authenticateSource, "'mfa_challenge.php'"),
    'Password authentication must create and route a pending MFA challenge.'
);
assertMfaChallenge(
    str_contains($routeSource, 'requireValidAccessCsrfPost()') &&
    str_contains(
        $routeSource,
        'no-store, no-cache, must-revalidate, max-age=0'
    ) &&
    !str_contains($routeSource, "\$_SESSION['user'] ="),
    'The challenge route must require CSRF, disable caching, and delegate full-session creation.'
);
assertMfaChallenge(
    !preg_match('/\b(?:qr|trusted|recovery|skip)\b/i', $routeSource) &&
    !str_contains($routeSource, 'mfa_secret_enc') &&
    !str_contains($routeSource, 'plain_secret'),
    'The challenge route must expose no secret, QR, trusted-device, recovery, or skip path.'
);
assertMfaChallenge(
    strpos($routeSource, 'processPendingMfaChallenge(') <
        strpos($routeSource, 'establishMfaAuthenticatedSession('),
    'The route must complete the database challenge before creating a full session.'
);

$lockPosition = strpos($helperSource, 'acquireLoginThrottleLocks(');
$blockPosition = strpos($helperSource, 'isLoginAttemptBlocked(', $lockPosition);
$decryptPosition = strpos($helperSource, 'decryptMfaSecret(', $blockPosition);
$verifyPosition = strpos($helperSource, 'verifyTotpCode(', $decryptPosition);
$claimPosition = strpos($helperSource, 'claimMfaTimestep(', $verifyPosition);
assertMfaChallenge(
    $lockPosition !== false &&
    $blockPosition !== false &&
    $decryptPosition !== false &&
    $verifyPosition !== false &&
    $claimPosition !== false &&
    $lockPosition < $blockPosition &&
    $blockPosition < $decryptPosition &&
    $decryptPosition < $verifyPosition &&
    $verifyPosition < $claimPosition,
    'Throttle locking and block checks must precede TOTP decryption, verification, and replay claiming.'
);
assertMfaChallenge(
    str_contains($helperSource, "'MFA_CHALLENGE_BLOCKED'") &&
    str_contains($helperSource, "null,\n                    (int) \$user['id']"),
    'The fifth challenge failure must append the required system-authored security event.'
);

$originalSession = $_SESSION ?? [];

try {
    $_SESSION = [];
    beginPendingMfaSession(
        17,
        3,
        PCMS_PENDING_MFA_PURPOSE_CHALLENGE,
        1000
    );
    $pending = getPendingMfaSession(
        PCMS_PENDING_MFA_PURPOSE_CHALLENGE,
        1299
    );
    assertMfaChallenge(
        $pending !== null &&
        $pending['user_id'] === 17 &&
        $pending['session_version'] === 3 &&
        $pending['expires_at'] === 1300,
        'A pending challenge must retain the authenticated user version for five minutes.'
    );
    assertMfaChallenge(
        getPendingMfaSession(
            PCMS_PENDING_MFA_PURPOSE_ENROLL,
            1299
        ) === null,
        'A pending challenge must not be accepted as an enrollment session.'
    );
} finally {
    $_SESSION = $originalSession;
}

$pdo = getDbConnection();
$baselineEventCount = (int) $pdo->query(
    'SELECT COUNT(*) FROM public.security_events'
)->fetchColumn();
$fixtureEmployeeId = 'MFA-CHAL-' . bin2hex(random_bytes(7));
$fixtureIp = '192.0.2.77';
$testTimestamp = 1234567890;
$currentStep = intdiv($testTimestamp, PCMS_MFA_TOTP_PERIOD);
$plainSecret = generateMfaSecret();
$encryptedSecret = encryptMfaSecret($plainSecret);

try {
    $pdo->beginTransaction();
    $insertUser = $pdo->prepare(
        'INSERT INTO public.users (
            employee_id,
            full_name,
            password_hash,
            role,
            is_active,
            mfa_enabled,
            mfa_secret_enc,
            mfa_enrolled_at,
            mfa_last_used_step
         ) VALUES (
            :employee_id,
            :full_name,
            :password_hash,
            :role,
            TRUE,
            TRUE,
            :mfa_secret_enc,
            CURRENT_TIMESTAMP,
            :mfa_last_used_step
         )
         RETURNING id, session_version'
    );
    $insertUser->bindValue(':employee_id', $fixtureEmployeeId);
    $insertUser->bindValue(':full_name', 'MFA Challenge Test Fixture');
    $insertUser->bindValue(
        ':password_hash',
        password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)
    );
    $insertUser->bindValue(':role', 'Administrator');
    $insertUser->bindValue(':mfa_secret_enc', $encryptedSecret);
    $insertUser->bindValue(
        ':mfa_last_used_step',
        $currentStep - 2,
        PDO::PARAM_INT
    );
    $insertUser->execute();
    $inserted = $insertUser->fetch();
    $fixtureUserId = (int) $inserted['id'];
    $sessionVersion = (int) $inserted['session_version'];
    $pendingState = [
        'user_id' => $fixtureUserId,
        'purpose' => PCMS_PENDING_MFA_PURPOSE_CHALLENGE,
        'expires_at' => time() + PCMS_PENDING_MFA_LIFETIME,
        'session_version' => $sessionVersion,
    ];

    assertMfaChallenge(
        isPendingMfaChallengeCurrent($pdo, $pendingState),
        'An active enabled account with the same session_version must satisfy the pending challenge state.'
    );

    $wrongPurpose = $pendingState;
    $wrongPurpose['purpose'] = PCMS_PENDING_MFA_PURPOSE_ENROLL;
    assertMfaChallenge(
        processPendingMfaChallenge(
            $pdo,
            $wrongPurpose,
            '000000',
            $fixtureIp,
            $testTimestamp
        )['status'] === PCMS_MFA_CHALLENGE_STALE,
        'A non-challenge pending purpose must be rejected.'
    );

    $expiredPending = $pendingState;
    $expiredPending['expires_at'] = time() - 1;
    assertMfaChallenge(
        processPendingMfaChallenge(
            $pdo,
            $expiredPending,
            '000000',
            $fixtureIp,
            $testTimestamp
        )['status'] === PCMS_MFA_CHALLENGE_STALE,
        'An expired pending challenge must be rejected before verification.'
    );

    $updateUser = $pdo->prepare(
        'UPDATE public.users
         SET session_version = :session_version
         WHERE id = :user_id'
    );
    $updateUser->execute([
        'session_version' => $sessionVersion + 1,
        'user_id' => $fixtureUserId,
    ]);
    assertMfaChallenge(
        processPendingMfaChallenge(
            $pdo,
            $pendingState,
            '000000',
            $fixtureIp,
            $testTimestamp
        )['status'] === PCMS_MFA_CHALLENGE_STALE,
        'A changed session_version must invalidate the pending challenge.'
    );
    $updateUser->execute([
        'session_version' => $sessionVersion,
        'user_id' => $fixtureUserId,
    ]);

    $pdo->prepare(
        'UPDATE public.users SET is_active = FALSE WHERE id = ?'
    )->execute([$fixtureUserId]);
    assertMfaChallenge(
        processPendingMfaChallenge(
            $pdo,
            $pendingState,
            '000000',
            $fixtureIp,
            $testTimestamp
        )['status'] === PCMS_MFA_CHALLENGE_STALE,
        'An inactive account must invalidate the pending challenge.'
    );
    $pdo->prepare(
        'UPDATE public.users SET is_active = TRUE WHERE id = ?'
    )->execute([$fixtureUserId]);

    $pdo->prepare(
        'UPDATE public.users
         SET mfa_enabled = FALSE,
             mfa_secret_enc = NULL,
             mfa_enrolled_at = NULL,
             mfa_last_used_step = NULL
         WHERE id = ?'
    )->execute([$fixtureUserId]);
    assertMfaChallenge(
        processPendingMfaChallenge(
            $pdo,
            $pendingState,
            '000000',
            $fixtureIp,
            $testTimestamp
        )['status'] === PCMS_MFA_CHALLENGE_STALE,
        'An account whose MFA state was disabled must invalidate the challenge.'
    );
    $restoreMfa = $pdo->prepare(
        'UPDATE public.users
         SET mfa_enabled = TRUE,
             mfa_secret_enc = :mfa_secret_enc,
             mfa_enrolled_at = CURRENT_TIMESTAMP,
             mfa_last_used_step = :mfa_last_used_step
         WHERE id = :user_id'
    );
    $restoreMfa->execute([
        'mfa_secret_enc' => $encryptedSecret,
        'mfa_last_used_step' => $currentStep - 2,
        'user_id' => $fixtureUserId,
    ]);

    clearMfaChallengeThrottle($pdo, $fixtureEmployeeId, $fixtureIp);
    $malformed = processPendingMfaChallenge(
        $pdo,
        $pendingState,
        'not-a-code',
        $fixtureIp,
        $testTimestamp
    );
    assertMfaChallenge(
        $malformed['status'] === PCMS_MFA_CHALLENGE_INVALID,
        'A malformed authenticator code must count as an invalid challenge.'
    );
    $failureCount = $pdo->prepare(
        'SELECT failure_count
         FROM public.login_attempts
         WHERE attempt_key = :attempt_key'
    );
    $failureCount->execute([
        'attempt_key' => loginThrottleAccountKey($fixtureEmployeeId),
    ]);
    assertMfaChallenge(
        (int) $failureCount->fetchColumn() === 1,
        'A malformed code must increment the account throttle.'
    );

    clearMfaChallengeThrottle($pdo, $fixtureEmployeeId, $fixtureIp);
    $validCode = generateTotpCode($plainSecret, $testTimestamp);
    $pdo->prepare(
        'UPDATE public.users SET mfa_last_used_step = ? WHERE id = ?'
    )->execute([$currentStep, $fixtureUserId]);
    $replay = processPendingMfaChallenge(
        $pdo,
        $pendingState,
        $validCode,
        $fixtureIp,
        $testTimestamp
    );
    assertMfaChallenge(
        $replay['status'] === PCMS_MFA_CHALLENGE_INVALID,
        'A replayed valid timestep must be rejected as a failed challenge.'
    );
    $failureCount->execute([
        'attempt_key' => loginThrottleAccountKey($fixtureEmployeeId),
    ]);
    assertMfaChallenge(
        (int) $failureCount->fetchColumn() === 1,
        'A replayed code must increment the account throttle.'
    );

    clearMfaChallengeThrottle($pdo, $fixtureEmployeeId, $fixtureIp);
    $pdo->prepare(
        'UPDATE public.users SET mfa_last_used_step = ? WHERE id = ?'
    )->execute([$currentStep - 2, $fixtureUserId]);
    $blockResults = [];

    for ($attempt = 1; $attempt <= PCMS_LOGIN_MAX_FAILURES; $attempt++) {
        $blockResults[] = processPendingMfaChallenge(
            $pdo,
            $pendingState,
            'invalid',
            $fixtureIp,
            $testTimestamp
        );
    }

    assertMfaChallenge(
        array_column(array_slice($blockResults, 0, 4), 'status') === [
            PCMS_MFA_CHALLENGE_INVALID,
            PCMS_MFA_CHALLENGE_INVALID,
            PCMS_MFA_CHALLENGE_INVALID,
            PCMS_MFA_CHALLENGE_INVALID,
        ] &&
        $blockResults[4]['status'] === PCMS_MFA_CHALLENGE_BLOCKED &&
        $blockResults[4]['newly_blocked'] === true,
        'The fifth failed challenge must begin the 15-minute cooldown.'
    );
    $eventCount = $pdo->prepare(
        "SELECT COUNT(*)
         FROM public.security_events
         WHERE target_user_id = :target_user_id
           AND event_type = 'MFA_CHALLENGE_BLOCKED'"
    );
    $eventCount->execute(['target_user_id' => $fixtureUserId]);
    assertMfaChallenge(
        (int) $eventCount->fetchColumn() === 1,
        'The fifth failure must append exactly one MFA_CHALLENGE_BLOCKED event.'
    );
    $blockedAgain = processPendingMfaChallenge(
        $pdo,
        $pendingState,
        $validCode,
        $fixtureIp,
        $testTimestamp
    );
    $eventCount->execute(['target_user_id' => $fixtureUserId]);
    assertMfaChallenge(
        $blockedAgain['status'] === PCMS_MFA_CHALLENGE_BLOCKED &&
        $blockedAgain['newly_blocked'] === false &&
        (int) $eventCount->fetchColumn() === 1,
        'Requests during cooldown must be rejected before TOTP verification without duplicate events.'
    );

    clearMfaChallengeThrottle($pdo, $fixtureEmployeeId, $fixtureIp);
    recordLoginFailure($pdo, $fixtureEmployeeId, $fixtureIp);
    recordLoginFailure($pdo, $fixtureEmployeeId, $fixtureIp);
    $success = processPendingMfaChallenge(
        $pdo,
        $pendingState,
        $validCode,
        $fixtureIp,
        $testTimestamp
    );
    assertMfaChallenge(
        $success['status'] === PCMS_MFA_CHALLENGE_SUCCESS &&
        $success['matched_step'] === $currentStep &&
        $success['user']['id'] === $fixtureUserId &&
        $success['user']['session_version'] === $sessionVersion,
        'A fresh valid TOTP must complete the challenge with the current user version.'
    );
    $failureCount->execute([
        'attempt_key' => loginThrottleAccountKey($fixtureEmployeeId),
    ]);
    $accountFailures = $failureCount->fetchColumn();
    $failureCount->execute([
        'attempt_key' => loginThrottleIpKey($fixtureIp),
    ]);
    assertMfaChallenge(
        $accountFailures === false &&
        (int) $failureCount->fetchColumn() === 2,
        'Successful MFA must clear only the account throttle and preserve IP-wide state.'
    );

    clearMfaChallengeThrottle($pdo, $fixtureEmployeeId, $fixtureIp);
    $pdo->prepare(
        'UPDATE public.users
         SET mfa_secret_enc = :mfa_secret_enc,
             mfa_last_used_step = :mfa_last_used_step
         WHERE id = :user_id'
    )->execute([
        'mfa_secret_enc' => 'v1:invalid:invalid',
        'mfa_last_used_step' => $currentStep - 2,
        'user_id' => $fixtureUserId,
    ]);
    $cryptoFailureClosed = false;

    try {
        processPendingMfaChallenge(
            $pdo,
            $pendingState,
            $validCode,
            $fixtureIp,
            $testTimestamp
        );
    } catch (RuntimeException $exception) {
        $cryptoFailureClosed = true;
    }

    assertMfaChallenge(
        $cryptoFailureClosed,
        'An invalid encrypted secret must fail closed without authenticating.'
    );

    $pdo->rollBack();
    assertMfaChallenge(
        (int) $pdo->query(
            'SELECT COUNT(*) FROM public.security_events'
        )->fetchColumn() === $baselineEventCount,
        'Challenge tests must leave no security-event fixtures.'
    );
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $exception;
} finally {
    sodium_memzero($plainSecret);
}

startPcmsSession();
$_SESSION = ['access_csrf_token' => 'challenge-test-token'];
beginPendingMfaSession(
    99,
    4,
    PCMS_PENDING_MFA_PURPOSE_CHALLENGE,
    1000
);
$oldSessionId = session_id();
establishMfaAuthenticatedSession([
    'id' => 99,
    'employee_id' => 'MFA-SESSION-TEST',
    'name' => 'MFA Session Test',
    'role' => 'Administrator',
    'session_version' => 4,
], 2000);
assertMfaChallenge(
    session_id() !== $oldSessionId &&
    !isset($_SESSION['pending_mfa']) &&
    $_SESSION['user']['id'] === 99 &&
    $_SESSION['user']['session_version'] === 4 &&
    $_SESSION['session_created_at'] === 2000 &&
    $_SESSION['session_last_activity_at'] === 2000,
    'Full authentication must rotate the session ID, clear pending state, and set current session metadata.'
);
destroyPcmsSession();

echo "MFA challenge tests passed: {$testsRun}" . PHP_EOL;
