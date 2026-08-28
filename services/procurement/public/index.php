<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/ProcurementRepository.php';
require_once __DIR__ . '/../src/ProcurementApiKernel.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

function procurementApiRespond(
    int $status,
    bool $success,
    ?array $data,
    ?array $error
): never {
    http_response_code($status);
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'error' => $error,
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function procurementApiError(
    int $status,
    string $code,
    string $message
): never {
    procurementApiRespond($status, false, null, [
        'code' => $code,
        'message' => $message,
    ]);
}

function procurementApiAuthenticate(): void
{
    try {
        $expected = getProcurementServiceToken();
    } catch (Throwable $error) {
        procurementApiError(
            503,
            'SERVICE_NOT_CONFIGURED',
            'Procurement service is temporarily unavailable.'
        );
    }

    $authorization = trim((string) (
        $_SERVER['HTTP_AUTHORIZATION'] ?? ''
    ));
    $prefix = 'Bearer ';
    $provided = str_starts_with($authorization, $prefix)
        ? substr($authorization, strlen($prefix))
        : '';

    if ($provided === '' || !hash_equals($expected, $provided)) {
        procurementApiError(
            401,
            'SERVICE_AUTHENTICATION_REQUIRED',
            'Valid service authentication is required.'
        );
    }
}

function procurementApiJsonBody(): array
{
    $raw = file_get_contents('php://input');

    if ($raw === false || trim($raw) === '') {
        return [];
    }

    try {
        $body = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException $error) {
        procurementApiError(
            400,
            'INVALID_JSON',
            'The request body must contain valid JSON.'
        );
    }

    if (!is_array($body)) {
        procurementApiError(
            400,
            'INVALID_JSON',
            'The request body must contain a JSON object.'
        );
    }

    return $body;
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$path = parse_url(
    (string) ($_SERVER['REQUEST_URI'] ?? '/'),
    PHP_URL_PATH
);
$path = '/' . trim((string) $path, '/');

if ($method === 'GET' && $path === '/health') {
    procurementApiRespond(200, true, [
        'service' => 'procurement',
        'status' => 'healthy',
        'api_version' => 'v1',
    ], null);
}

if (!str_starts_with($path, '/api/v1/')) {
    procurementApiError(404, 'ROUTE_NOT_FOUND', 'The requested route was not found.');
}

procurementApiAuthenticate();

try {
    $kernel = new ProcurementApiKernel(
        new ProcurementRepository(getProcurementServiceConnection())
    );
    $response = $kernel->dispatch(
        $method,
        $path,
        $_GET,
        $method === 'POST' ? procurementApiJsonBody() : [],
        trim((string) ($_SERVER['HTTP_X_PCMS_ACTOR'] ?? ''))
    );
    procurementApiRespond(
        (int) $response['status'],
        (bool) $response['body']['success'],
        $response['body']['data'],
        $response['body']['error']
    );
} catch (Throwable $error) {
    error_log('Procurement service request failed.');
    procurementApiError(
        500,
        'PROCUREMENT_SERVICE_ERROR',
        'The Procurement request could not be completed.'
    );
}
