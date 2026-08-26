<?php

const PCMS_SESSION_IDLE_TIMEOUT = 1800;
const PCMS_SESSION_ABSOLUTE_LIFETIME = 28800;

function isPcmsHttpsRequest(): bool
{
    return (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (string) ($_SERVER['SERVER_PORT'] ?? '') === '443'
    );
}

function sendPcmsSecurityHeaders(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('X-Frame-Options: SAMEORIGIN');
    header("Content-Security-Policy: frame-ancestors 'self'");
}

function destroyPcmsSession(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

function startPcmsSession(): bool
{
    sendPcmsSecurityHeaders();

    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => isPcmsHttpsRequest(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    if (empty($_SESSION['user'])) {
        return true;
    }

    $now = time();
    $createdAt = (int) ($_SESSION['session_created_at'] ?? $now);
    $lastActivityAt = (int) ($_SESSION['session_last_activity_at'] ?? $now);

    if (
        $now - $lastActivityAt > PCMS_SESSION_IDLE_TIMEOUT ||
        $now - $createdAt > PCMS_SESSION_ABSOLUTE_LIFETIME
    ) {
        destroyPcmsSession();
        return false;
    }

    $_SESSION['session_created_at'] = $createdAt;
    $_SESSION['session_last_activity_at'] = $now;

    return true;
}
