<?php

require_once __DIR__ . '/../includes/session.php';

$assertions = 0;

function assertSessionVerification(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$_SESSION = [
    'user' => [
        'employee_id' => 'admin',
        'session_version' => 1,
    ],
    'access_csrf_token' => 'preserve-this-token',
];

$unavailable = resolvePcmsSessionUser(
    static function (): ?array {
        throw new RuntimeException('Simulated temporary database failure.');
    }
);

assertSessionVerification(
    $unavailable['available'] === false && $unavailable['user'] === null,
    'A temporary verification failure must be distinguishable from an invalid account.'
);
assertSessionVerification(
    isset($_SESSION['user']) &&
    $_SESSION['access_csrf_token'] === 'preserve-this-token',
    'A temporary verification failure must preserve the authenticated session and CSRF token.'
);

$missing = resolvePcmsSessionUser(static fn (): ?array => null);
assertSessionVerification(
    $missing['available'] === true && $missing['user'] === null,
    'A successful lookup with no account must remain an invalid-session result.'
);

$verified = [
    'id' => 1,
    'employee_id' => 'admin',
    'full_name' => 'System Administrator',
    'role' => 'Administrator',
    'is_active' => true,
    'session_version' => 1,
];
$available = resolvePcmsSessionUser(static fn (): array => $verified);
assertSessionVerification(
    $available['available'] === true && $available['user'] === $verified,
    'A successful verification lookup must return the current account.'
);

$checkAuthSource = file_get_contents(__DIR__ . '/../auth/check_auth.php');
assertSessionVerification(
    str_contains($checkAuthSource, "http_response_code(503)") &&
    str_contains($checkAuthSource, "header('Retry-After: 5')"),
    'The authentication guard must fail closed with a retryable service-unavailable response.'
);
assertSessionVerification(
    strpos($checkAuthSource, "if (!\$verification['available'])") <
    strpos($checkAuthSource, 'destroyPcmsSession()'),
    'Temporary verification failure handling must run before genuine session revocation.'
);

echo "Session verification resilience tests passed: {$assertions}\n";
