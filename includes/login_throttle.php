<?php

const PCMS_LOGIN_MAX_FAILURES = 5;

function loginThrottleAccountKey(string $employeeId): string
{
    return 'account:' . hash('sha256', strtolower(trim($employeeId)));
}

function loginThrottleIpKey(string $ipAddress): ?string
{
    $normalized = trim($ipAddress);

    return $normalized === ''
        ? null
        : 'ip:' . hash('sha256', $normalized);
}

function loginThrottleKeys(string $employeeId, string $ipAddress): array
{
    return array_values(array_unique(array_filter([
        loginThrottleAccountKey($employeeId),
        loginThrottleIpKey($ipAddress),
    ])));
}

function acquireLoginThrottleLocks(
    PDO $pdo,
    string $employeeId,
    string $ipAddress
): void {
    if (!$pdo->inTransaction()) {
        throw new LogicException(
            'Login throttle locks require an active database transaction.'
        );
    }

    $keys = loginThrottleKeys($employeeId, $ipAddress);
    sort($keys, SORT_STRING);

    $stmt = $pdo->prepare(
        'SELECT pg_advisory_xact_lock(
            hashtextextended(:attempt_key, 0)
        )'
    );

    foreach ($keys as $key) {
        $stmt->execute(['attempt_key' => $key]);
    }
}

function isLoginAttemptBlocked(
    PDO $pdo,
    string $employeeId,
    string $ipAddress
): bool {
    $keys = loginThrottleKeys($employeeId, $ipAddress);
    $placeholders = [];
    $params = [];

    foreach ($keys as $index => $key) {
        $placeholder = ':attempt_key_' . $index;
        $placeholders[] = $placeholder;
        $params[$placeholder] = $key;
    }

    $stmt = $pdo->prepare(
        'SELECT 1
         FROM login_attempts
         WHERE attempt_key IN (' . implode(', ', $placeholders) . ')
           AND locked_until > CURRENT_TIMESTAMP
         LIMIT 1'
    );
    $stmt->execute($params);

    return (bool) $stmt->fetchColumn();
}

function recordLoginFailure(
    PDO $pdo,
    string $employeeId,
    string $ipAddress
): void {
    $stmt = $pdo->prepare(
        "INSERT INTO login_attempts (
            attempt_key,
            failure_count,
            window_started_at,
            locked_until,
            updated_at
         ) VALUES (
            :attempt_key,
            1,
            CURRENT_TIMESTAMP,
            NULL,
            CURRENT_TIMESTAMP
         )
         ON CONFLICT (attempt_key) DO UPDATE SET
            failure_count = CASE
                WHEN login_attempts.window_started_at <= CURRENT_TIMESTAMP - INTERVAL '15 minutes'
                    THEN 1
                ELSE login_attempts.failure_count + 1
            END,
            window_started_at = CASE
                WHEN login_attempts.window_started_at <= CURRENT_TIMESTAMP - INTERVAL '15 minutes'
                    THEN CURRENT_TIMESTAMP
                ELSE login_attempts.window_started_at
            END,
            locked_until = CASE
                WHEN (
                    CASE
                        WHEN login_attempts.window_started_at <= CURRENT_TIMESTAMP - INTERVAL '15 minutes'
                            THEN 1
                        ELSE login_attempts.failure_count + 1
                    END
                ) >= " . PCMS_LOGIN_MAX_FAILURES . "
                    THEN CURRENT_TIMESTAMP + INTERVAL '15 minutes'
                ELSE NULL
            END,
            updated_at = CURRENT_TIMESTAMP"
    );

    foreach (loginThrottleKeys($employeeId, $ipAddress) as $key) {
        $stmt->execute(['attempt_key' => $key]);
    }
}

function clearLoginAccountFailures(PDO $pdo, string $employeeId): void
{
    $stmt = $pdo->prepare(
        'DELETE FROM login_attempts WHERE attempt_key = :attempt_key'
    );
    $stmt->execute([
        'attempt_key' => loginThrottleAccountKey($employeeId),
    ]);
}

function pruneExpiredLoginAttempts(PDO $pdo): void
{
    $pdo->exec(
        "DELETE FROM login_attempts
         WHERE updated_at < CURRENT_TIMESTAMP - INTERVAL '1 day'"
    );
}
