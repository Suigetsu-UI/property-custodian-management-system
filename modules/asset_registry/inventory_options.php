<?php

require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../includes/property_core_gateway.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['options' => []]);
    exit;
}

$query = substr(trim((string) ($_GET['q'] ?? '')), 0, 200);
if (strlen($query) < 2) {
    echo json_encode(['options' => [], 'minimum_search_length' => 2]);
    exit;
}

try {
    $result = getPropertyCoreServiceClient()->inventoryOptions([
        'search' => $query,
        'limit' => 10,
    ]);
    echo json_encode(
        ['options' => array_values($result['options'] ?? [])],
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );
} catch (Throwable $error) {
    http_response_code(503);
    echo json_encode(['options' => []]);
}
exit;
