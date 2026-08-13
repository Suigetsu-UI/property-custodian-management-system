<?php

require_once __DIR__ . '/../config/config.php';

/*
|--------------------------------------------------------------------------
| Reusable PDO Connection
|--------------------------------------------------------------------------
| Provides a single reusable PDO connection to Supabase PostgreSQL.
| No schema, business queries, or module logic belongs in this file.
*/

function getDbConnection(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = getDatabaseConfig();

    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
        $config['DB_HOST'],
        $config['DB_PORT'],
        $config['DB_NAME'],
        $config['DB_SSLMODE']
    );

    try {
        $pdo = new PDO(
            $dsn,
            $config['DB_USER'],
            $config['DB_PASSWORD'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    } catch (PDOException $e) {
        throw new RuntimeException(
            'Database connection failed. Check the database configuration.'
        );
    }

    return $pdo;
}