<?php

/*
|--------------------------------------------------------------------------
| Minimal .env Loader
|--------------------------------------------------------------------------
| No Composer, no framework, no external dotenv package.
| Reads KEY=VALUE lines from .env into getenv()/$_ENV, skipping comments
| and blank lines. Existing environment variables are never overwritten.
*/

function loadEnv(string $path): void
{
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        if (strpos($trimmed, '=') === false) {
            continue;
        }

        [$key, $value] = array_pad(explode('=', $trimmed, 2), 2, '');

        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");

        if ($key !== '' && getenv($key) === false) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
        }
    }
}

loadEnv(__DIR__ . '/../.env');

/*
|--------------------------------------------------------------------------
| Public Application Path
|--------------------------------------------------------------------------
| Local XAMPP uses /property-custodian-management-system/. A hosted domain
| can set PCMS_BASE_URL=/ without changing application links or redirects.
*/

function normalizePcmsBaseUrl(mixed $value): string
{
    $baseUrl = trim((string) $value);

    if ($baseUrl === '') {
        return '/property-custodian-management-system/';
    }

    $path = parse_url($baseUrl, PHP_URL_PATH);

    if (!is_string($path) || $path === '') {
        throw new RuntimeException('PCMS_BASE_URL must be a valid URL path.');
    }

    return '/' . trim($path, '/') . (
        trim($path, '/') === '' ? '' : '/'
    );
}

define('BASE_URL', normalizePcmsBaseUrl(getenv('PCMS_BASE_URL') ?: ''));

/*
|--------------------------------------------------------------------------
| Database Configuration Accessor
|--------------------------------------------------------------------------
*/

function getDatabaseConfig(): array
{
    $required = [
        'DB_HOST',
        'DB_PORT',
        'DB_NAME',
        'DB_USER',
        'DB_PASSWORD',
        'DB_SSLMODE'
    ];

    $config = [];

    foreach ($required as $key) {
        $value = getenv($key);

        if ($value === false || $value === '') {
            throw new RuntimeException(
                "Missing required environment variable: {$key}. Check your .env file."
            );
        }

        $config[$key] = $value;
    }

    return $config;
}
