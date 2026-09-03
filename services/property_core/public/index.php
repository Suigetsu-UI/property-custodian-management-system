<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/PropertyCoreRepository.php';
require_once __DIR__ . '/../src/PropertyCoreApiKernel.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

function propertyCoreApiRespond(
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

function propertyCoreApiError(
    int $status,
    string $code,
    string $message
): never {
    propertyCoreApiRespond($status, false, null, [
        'code' => $code,
        'message' => $message,
    ]);
}

function propertyCoreApiAuthenticate(): void
{
    try {
        $expected = getPropertyCoreServiceToken();
    } catch (Throwable $error) {
        propertyCoreApiError(
            503,
            'SERVICE_NOT_CONFIGURED',
            'Property Core service is temporarily unavailable.'
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
        propertyCoreApiError(
            401,
            'SERVICE_AUTHENTICATION_REQUIRED',
            'Valid service authentication is required.'
        );
    }
}

function propertyCoreApiJsonBody(): array
{
    $raw = file_get_contents('php://input');

    if ($raw === false || trim($raw) === '') {
        return [];
    }

    try {
        $body = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException $error) {
        propertyCoreApiError(
            400,
            'INVALID_JSON',
            'The request body must contain valid JSON.'
        );
    }

    if (!is_array($body)) {
        propertyCoreApiError(
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
    $writesAllowed = propertyCoreWritesAllowed();
    propertyCoreApiRespond(200, true, [
        'service' => 'property-core',
        'status' => 'healthy',
        'api_version' => 'v1',
        'data_access' => $writesAllowed
            ? 'lifecycle-writes'
            : 'read-only',
    ], null);
}

if (!str_starts_with($path, '/api/v1/')) {
    propertyCoreApiError(
        404,
        'ROUTE_NOT_FOUND',
        'The requested route was not found.'
    );
}

propertyCoreApiAuthenticate();

try {
    $connection = getPropertyCoreServiceConnection();
    $kernel = new PropertyCoreApiKernel(
        new PropertyCoreRepository(
            $connection,
            propertyCoreWritesAllowed()
        )
    );
    $response = $kernel->dispatch(
        $method,
        $path,
        $_GET,
        $method === 'POST' ? propertyCoreApiJsonBody() : [],
        trim((string) ($_SERVER['HTTP_X_PCMS_ACTOR'] ?? ''))
    );
    propertyCoreApiRespond(
        (int) $response['status'],
        (bool) $response['body']['success'],
        $response['body']['data'],
        $response['body']['error']
    );
} catch (Throwable $error) {
    error_log('Property Core service request failed.');
    propertyCoreApiError(
        503,
        'PROPERTY_CORE_SERVICE_UNAVAILABLE',
        'Property Core service is temporarily unavailable.'
    );
}
