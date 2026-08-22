<?php

require_once "../../auth/check_auth.php";
require_once __DIR__ . "/../../includes/database.php";

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['suggestions' => []]);
    exit;
}

$allowedFields = [
    'brand' => 'brand',
    'model' => 'model',
    'supplier' => 'supplier',
];

$field = trim((string) ($_GET['field'] ?? ''));
$query = trim((string) ($_GET['q'] ?? ''));
$queryLength = function_exists('mb_strlen')
    ? mb_strlen($query, 'UTF-8')
    : strlen($query);

if (!array_key_exists($field, $allowedFields)) {
    http_response_code(400);
    echo json_encode(['suggestions' => []]);
    exit;
}

if ($queryLength < 2) {
    echo json_encode(['suggestions' => []]);
    exit;
}

if ($queryLength > 100) {
    $query = function_exists('mb_substr')
        ? mb_substr($query, 0, 100, 'UTF-8')
        : substr($query, 0, 100);
}

$column = $allowedFields[$field];
$escapedQuery = str_replace(
    ['\\', '%', '_'],
    ['\\\\', '\\%', '\\_'],
    $query
);

try {
    $pdo = getDbConnection();

    $stmt = $pdo->prepare(
        "SELECT value
         FROM (
             SELECT DISTINCT trim({$column}) AS value
             FROM assets
             WHERE {$column} IS NOT NULL
               AND trim({$column}) <> ''
         ) AS saved_values
         WHERE lower(value) LIKE lower(:search) ESCAPE '\\'
         ORDER BY lower(value), value
         LIMIT 10"
    );

    $stmt->execute([
        'search' => $escapedQuery . '%',
    ]);

    echo json_encode([
        'suggestions' => array_column($stmt->fetchAll(), 'value'),
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode(['suggestions' => []]);
}
