<?php

require_once __DIR__ . '/../config/config.php';

function getAllowedUserRoles(): array
{
    return [
        'Administrator',
        'Property Custodian',
    ];
}

function currentUserRole(): string
{
    return trim((string) ($_SESSION['user']['role'] ?? ''));
}

function userRoleLabel(string $role): string
{
    return $role === 'Administrator'
        ? 'System Administrator'
        : $role;
}

function currentUserRoleLabel(): string
{
    return userRoleLabel(currentUserRole());
}

function isAdministrator(): bool
{
    return currentUserRole() === 'Administrator';
}

function isUserAccountActive(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }

    return in_array(
        strtolower(trim((string) $value)),
        ['1', 't', 'true', 'yes', 'on'],
        true
    );
}

function requireAdministrator(): void
{
    if (isAdministrator()) {
        return;
    }

    http_response_code(403);
    require __DIR__ . '/../access_denied.php';
    exit;
}

function getAccessCsrfToken(): string
{
    if (empty($_SESSION['access_csrf_token'])) {
        $_SESSION['access_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['access_csrf_token'];
}

function isValidAccessCsrfToken(?string $token): bool
{
    $sessionToken = (string) (
        $_SESSION['access_csrf_token'] ?? ''
    );

    return $sessionToken !== '' &&
        is_string($token) &&
        hash_equals($sessionToken, $token);
}
