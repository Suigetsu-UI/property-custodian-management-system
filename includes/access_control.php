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

function isPropertyCustodian(): bool
{
    return currentUserRole() === 'Property Custodian';
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
    $accessDeniedTitle = 'System Administrator access is required';
    $accessDeniedMessage =
        'Your account can use the Property Custodian modules, but only a ' .
        'System Administrator can manage user accounts, security, and ' .
        'lifecycle configuration.';
    require __DIR__ . '/../access_denied.php';
    exit;
}

function requirePropertyCustodian(): void
{
    if (isPropertyCustodian()) {
        return;
    }

    http_response_code(403);
    $accessDeniedTitle = 'Property Custodian access is required';
    $accessDeniedMessage =
        'System Administrators have read-only access to disposition records. ' .
        'Only the Property Custodian can create or advance a disposition ' .
        'transaction after external institutional approval.';
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

function getSubmittedAccessCsrfToken(): ?string
{
    $token = $_POST['csrf_token'] ??
        ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);

    return is_string($token) ? $token : null;
}

function requireValidAccessCsrfPost(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit('Method not allowed.');
    }

    if (!isValidAccessCsrfToken(getSubmittedAccessCsrfToken())) {
        http_response_code(403);
        exit('Invalid security token. Please return to PCMS and try again.');
    }
}
