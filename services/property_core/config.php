<?php

function loadPropertyCoreServiceEnv(string $path): void
{
    if (!is_file($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if (
            $trimmed === '' ||
            str_starts_with($trimmed, '#') ||
            !str_contains($trimmed, '=')
        ) {
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

if (getenv('PROPERTY_CORE_SKIP_ENV_FILE') !== 'true') {
    loadPropertyCoreServiceEnv(__DIR__ . '/.env');
}

function getPropertyCoreServiceDatabaseConfig(): array
{
    $config = [];

    foreach (
        ['HOST', 'PORT', 'NAME', 'USER', 'PASSWORD', 'SSLMODE']
        as $suffix
    ) {
        $value = trim((string) (
            getenv('PROPERTY_CORE_DB_' . $suffix) ?: ''
        ));

        if ($value === '') {
            throw new RuntimeException(
                'Property Core database configuration is incomplete.'
            );
        }

        $config[$suffix] = $value;
    }

    return $config;
}

function getPropertyCoreServiceToken(): string
{
    $token = trim((string) (getenv('PROPERTY_CORE_SERVICE_TOKEN') ?: ''));

    if (strlen($token) < 32) {
        throw new RuntimeException(
            'Property Core service credential is missing or too short.'
        );
    }

    return $token;
}

function propertyCoreWritesAllowed(): bool
{
    return trim((string) (
        getenv('PROPERTY_CORE_ALLOW_WRITES') ?: ''
    )) === 'true';
}

function getPropertyCoreServiceConnection(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = getPropertyCoreServiceDatabaseConfig();
    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
        $config['HOST'],
        $config['PORT'],
        $config['NAME'],
        $config['SSLMODE']
    );

    $pdo = new PDO(
        $dsn,
        $config['USER'],
        $config['PASSWORD'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    return $pdo;
}
