<?php

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/login_throttle.php';

$testsRun = 0;

function assertLoginThrottle(bool $condition, string $message): void
{
    global $testsRun;
    $testsRun++;

    if (!$condition) {
        throw new RuntimeException('FAILED: ' . $message);
    }
}

function cleanupLoginThrottleFixtures(
    PDO $pdo,
    array $employeeIds,
    array $ipAddresses
): void {
    $keys = [];

    foreach ($employeeIds as $employeeId) {
        $keys[] = loginThrottleAccountKey($employeeId);
    }

    foreach ($ipAddresses as $ipAddress) {
        $ipKey = loginThrottleIpKey($ipAddress);

        if ($ipKey !== null) {
            $keys[] = $ipKey;
        }
    }

    $keys = array_values(array_unique($keys));

    if ($keys === []) {
        return;
    }

    $placeholders = implode(', ', array_fill(0, count($keys), '?'));
    $stmt = $pdo->prepare(
        'DELETE FROM login_attempts WHERE attempt_key IN (' . $placeholders . ')'
    );
    $stmt->execute($keys);
}

function runThrottleDecision(
    PDO $pdo,
    string $employeeId,
    string $ipAddress,
    string $outcome,
    int $holdMicroseconds = 0,
    ?string $readyFile = null
): string {
    try {
        $pdo->beginTransaction();
        acquireLoginThrottleLocks($pdo, $employeeId, $ipAddress);

        if ($readyFile !== null) {
            file_put_contents($readyFile, 'ready');
        }

        if (isLoginAttemptBlocked($pdo, $employeeId, $ipAddress)) {
            $pdo->commit();
            return 'BLOCKED';
        }

        if ($holdMicroseconds > 0) {
            usleep($holdMicroseconds);
        }

        if ($outcome === 'success') {
            clearLoginAccountFailures($pdo, $employeeId);
        } else {
            recordLoginFailure($pdo, $employeeId, $ipAddress);
        }

        $pdo->commit();

        return $outcome === 'success' ? 'SUCCESS' : 'VERIFIED';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

function startThrottleWorker(
    string $employeeId,
    string $ipAddress,
    string $outcome = 'failure',
    int $holdMicroseconds = 250000,
    ?string $readyFile = null
): array {
    $command = [
        PHP_BINARY,
        __FILE__,
        '--worker',
        $employeeId,
        $ipAddress,
        $outcome,
        (string) $holdMicroseconds,
        $readyFile ?? '',
    ];
    $pipes = [];
    $process = proc_open(
        $command,
        [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes,
        __DIR__
    );

    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start throttle test worker.');
    }

    fclose($pipes[0]);

    return [$process, $pipes];
}

function finishThrottleWorker(array $worker): string
{
    [$process, $pipes] = $worker;
    $stdout = trim(stream_get_contents($pipes[1]));
    $stderr = trim(stream_get_contents($pipes[2]));
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    if ($exitCode !== 0) {
        throw new RuntimeException(
            'Throttle worker failed: ' . ($stderr !== '' ? $stderr : $stdout)
        );
    }

    return $stdout;
}

function runParallelThrottleCase(array $attempts): array
{
    $workers = [];

    foreach ($attempts as $attempt) {
        $workers[] = startThrottleWorker(
            $attempt['employee_id'],
            $attempt['ip_address']
        );
    }

    $results = [];
    $errors = [];

    foreach ($workers as $worker) {
        try {
            $results[] = finishThrottleWorker($worker);
        } catch (Throwable $e) {
            $errors[] = $e;
        }
    }

    if ($errors !== []) {
        throw $errors[0];
    }

    return $results;
}

if (($argv[1] ?? '') === '--worker') {
    try {
        $pdo = getDbConnection();
        $result = runThrottleDecision(
            $pdo,
            (string) ($argv[2] ?? ''),
            (string) ($argv[3] ?? ''),
            (string) ($argv[4] ?? 'failure'),
            (int) ($argv[5] ?? 250000),
            ($argv[6] ?? '') !== '' ? (string) $argv[6] : null
        );
        echo $result . PHP_EOL;
        exit(0);
    } catch (Throwable $e) {
        fwrite(STDERR, get_class($e) . ': ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
}

$pdo = getDbConnection();
$authenticateSource = file_get_contents(__DIR__ . '/../auth/authenticate.php');

if ($authenticateSource === false) {
    throw new RuntimeException('Unable to inspect the authentication handler.');
}

$flowPositions = [];

foreach ([
    'requireValidAccessCsrfPost()',
    '$pdo->beginTransaction()',
    'acquireLoginThrottleLocks(',
    'isLoginAttemptBlocked(',
    'password_verify(',
    '$pdo->commit()',
    'session_regenerate_id(true)',
] as $flowStep) {
    $position = strpos($authenticateSource, $flowStep);
    assertLoginThrottle(
        $position !== false,
        'Authentication flow must contain ' . $flowStep . '.'
    );
    $flowPositions[] = $position;
}

$sortedPositions = $flowPositions;
sort($sortedPositions, SORT_NUMERIC);

assertLoginThrottle(
    $flowPositions === array_values(array_unique($flowPositions)) &&
    $flowPositions === $sortedPositions,
    'CSRF, transaction, locks, throttle check, password verification, commit, and session creation must remain ordered.'
);
assertLoginThrottle(
    strpos($authenticateSource, 'clearLoginAccountFailures(') < strpos($authenticateSource, '$pdo->commit()') &&
    strpos($authenticateSource, 'recordLoginFailure(') < strpos($authenticateSource, '$pdo->commit()'),
    'Success clearing and failure recording must both occur before commit.'
);
$catchPosition = strpos($authenticateSource, '} catch (Throwable $e) {');
$genericFailurePosition = strrpos(
    $authenticateSource,
    'header("Location: login.php?error=invalid")'
);
assertLoginThrottle(
    $catchPosition !== false &&
    $genericFailurePosition !== false &&
    $catchPosition < $genericFailurePosition &&
    strpos(
        substr($authenticateSource, $catchPosition, $genericFailurePosition - $catchPosition),
        '$authenticatedUser = null;'
    ) !== false,
    'Database and lock exceptions must fail closed through the generic login response.'
);

$fixturePrefix = 'SECURITY-THROTTLE-' . bin2hex(random_bytes(6));
$sameAccount = $fixturePrefix . '-SAME-ACCOUNT';
$sameIp = '203.0.113.10';
$manyIps = [];
$manyAccounts = [];
$allEmployeeIds = [$sameAccount];
$allIpAddresses = [$sameIp];

for ($index = 1; $index <= 10; $index++) {
    $manyIps[] = '198.51.100.' . $index;
    $manyAccounts[] = $fixturePrefix . '-ACCOUNT-' . $index;
}

$allEmployeeIds = array_merge($allEmployeeIds, $manyAccounts);
$allIpAddresses = array_merge($allIpAddresses, $manyIps);

try {
    cleanupLoginThrottleFixtures($pdo, $allEmployeeIds, $allIpAddresses);

    $sameScopeResults = runParallelThrottleCase(
        array_fill(0, 10, [
            'employee_id' => $sameAccount,
            'ip_address' => $sameIp,
        ])
    );
    assertLoginThrottle(
        count(array_filter($sameScopeResults, fn ($value) => $value === 'VERIFIED')) === 5,
        'Ten parallel failures for one account and IP must admit exactly five verifications.'
    );

    cleanupLoginThrottleFixtures($pdo, [$sameAccount], $manyIps);
    $accountScopedAttempts = [];

    foreach ($manyIps as $ipAddress) {
        $accountScopedAttempts[] = [
            'employee_id' => $sameAccount,
            'ip_address' => $ipAddress,
        ];
    }

    $accountScopeResults = runParallelThrottleCase($accountScopedAttempts);
    assertLoginThrottle(
        count(array_filter($accountScopeResults, fn ($value) => $value === 'VERIFIED')) === 5,
        'One account across several IPs must admit exactly five verifications.'
    );

    cleanupLoginThrottleFixtures($pdo, $manyAccounts, [$sameIp]);
    $ipScopedAttempts = [];

    foreach ($manyAccounts as $employeeId) {
        $ipScopedAttempts[] = [
            'employee_id' => $employeeId,
            'ip_address' => $sameIp,
        ];
    }

    $ipScopeResults = runParallelThrottleCase($ipScopedAttempts);
    assertLoginThrottle(
        count(array_filter($ipScopeResults, fn ($value) => $value === 'VERIFIED')) === 5,
        'Several accounts from one IP must admit exactly five verifications.'
    );

    cleanupLoginThrottleFixtures($pdo, [$sameAccount], [$sameIp]);

    for ($attempt = 1; $attempt <= PCMS_LOGIN_MAX_FAILURES; $attempt++) {
        assertLoginThrottle(
            runThrottleDecision($pdo, $sameAccount, $sameIp, 'failure', 0) === 'VERIFIED',
            'Each of the first five sequential failures must reach verification.'
        );
    }

    assertLoginThrottle(
        runThrottleDecision($pdo, $sameAccount, $sameIp, 'failure', 0) === 'BLOCKED',
        'The sixth sequential request must be rejected before verification.'
    );

    $keys = loginThrottleKeys($sameAccount, $sameIp);
    $placeholders = implode(', ', array_fill(0, count($keys), '?'));
    $expireStmt = $pdo->prepare(
        "UPDATE login_attempts
         SET window_started_at = CURRENT_TIMESTAMP - INTERVAL '16 minutes',
             locked_until = NULL,
             updated_at = CURRENT_TIMESTAMP
         WHERE attempt_key IN (" . $placeholders . ')'
    );
    $expireStmt->execute($keys);

    assertLoginThrottle(
        runThrottleDecision($pdo, $sameAccount, $sameIp, 'failure', 0) === 'VERIFIED',
        'An expired window must admit a new verification.'
    );

    $countStmt = $pdo->prepare(
        'SELECT failure_count FROM login_attempts WHERE attempt_key = ?'
    );
    $countStmt->execute([loginThrottleAccountKey($sameAccount)]);
    assertLoginThrottle(
        (int) $countStmt->fetchColumn() === 1,
        'The first failure after window expiration must reset the count to one.'
    );

    cleanupLoginThrottleFixtures($pdo, [$sameAccount], [$sameIp]);
    recordLoginFailure($pdo, $sameAccount, $sameIp);
    recordLoginFailure($pdo, $sameAccount, $sameIp);
    assertLoginThrottle(
        runThrottleDecision($pdo, $sameAccount, $sameIp, 'success', 0) === 'SUCCESS',
        'An allowed successful login must complete inside the protected section.'
    );
    $countStmt->execute([loginThrottleAccountKey($sameAccount)]);
    assertLoginThrottle(
        $countStmt->fetchColumn() === false,
        'A successful login must clear the account throttle state before commit.'
    );
    $countStmt->execute([loginThrottleIpKey($sameIp)]);
    assertLoginThrottle(
        (int) $countStmt->fetchColumn() === 2,
        'A successful login must preserve the existing IP-wide failure state.'
    );

    cleanupLoginThrottleFixtures($pdo, [$sameAccount], [$sameIp]);
    $readyFile = tempnam(sys_get_temp_dir(), 'pcms_throttle_');

    if ($readyFile === false) {
        throw new RuntimeException('Unable to create the concurrency marker.');
    }

    unlink($readyFile);
    $successWorker = startThrottleWorker(
        $sameAccount,
        $sameIp,
        'success',
        400000,
        $readyFile
    );
    $deadline = microtime(true) + 5;

    while (!is_file($readyFile) && microtime(true) < $deadline) {
        usleep(10000);
    }

    if (!is_file($readyFile)) {
        finishThrottleWorker($successWorker);
        throw new RuntimeException('Success worker did not acquire its throttle locks.');
    }

    $failureWorker = startThrottleWorker(
        $sameAccount,
        $sameIp,
        'failure',
        0
    );
    $successResult = null;
    $failureResult = null;
    $workerErrors = [];

    try {
        $successResult = finishThrottleWorker($successWorker);
    } catch (Throwable $e) {
        $workerErrors[] = $e;
    }

    try {
        $failureResult = finishThrottleWorker($failureWorker);
    } catch (Throwable $e) {
        $workerErrors[] = $e;
    }

    if ($workerErrors !== []) {
        throw $workerErrors[0];
    }

    assertLoginThrottle(
        $successResult === 'SUCCESS',
        'The concurrent successful decision must complete.'
    );
    assertLoginThrottle(
        $failureResult === 'VERIFIED',
        'The overlapping failure must run after the successful decision commits.'
    );
    @unlink($readyFile);
    $countStmt->execute([loginThrottleAccountKey($sameAccount)]);
    assertLoginThrottle(
        (int) $countStmt->fetchColumn() === 1,
        'A failure serialized after success must remain as the newest account state.'
    );

    $transactionRequired = false;

    try {
        acquireLoginThrottleLocks($pdo, $sameAccount, $sameIp);
    } catch (LogicException $e) {
        $transactionRequired = true;
    }

    assertLoginThrottle(
        $transactionRequired,
        'Lock acquisition outside a transaction must fail closed.'
    );

    echo "Login throttle concurrency tests passed: {$testsRun}" . PHP_EOL;
} finally {
    if (isset($readyFile) && is_string($readyFile)) {
        @unlink($readyFile);
    }

    cleanupLoginThrottleFixtures($pdo, $allEmployeeIds, $allIpAddresses);
}
