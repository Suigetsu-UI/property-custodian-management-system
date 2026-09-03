<?php

$testsRun = 0;

function assertPropertyCoreHttp(bool $condition, string $message): void
{
    global $testsRun;
    $testsRun++;

    if (!$condition) {
        throw new RuntimeException('FAILED: ' . $message);
    }
}

function propertyCoreHttpRequest(string $url, ?string $token = null): array
{
    $headers = ['Accept: application/json'];

    if ($token !== null) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    $context = stream_context_create(['http' => [
        'method' => 'GET',
        'header' => implode("\r\n", $headers),
        'ignore_errors' => true,
        'follow_location' => 0,
        'timeout' => 1.0,
    ]]);
    $body = @file_get_contents($url, false, $context);
    $status = 0;

    foreach (($http_response_header ?? []) as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches) === 1) {
            $status = (int) $matches[1];
        }
    }

    return ['status' => $status, 'body' => (string) $body];
}

$socket = stream_socket_server('tcp://127.0.0.1:0', $errorNumber, $errorMessage);

if ($socket === false) {
    throw new RuntimeException('Could not reserve a local test port.');
}

$address = stream_socket_get_name($socket, false);
$port = (int) substr(strrchr($address, ':'), 1);
fclose($socket);

$root = dirname(__DIR__);
$token = str_repeat('s', 40);
$command = escapeshellarg(PHP_BINARY) .
    ' -S 127.0.0.1:' . $port .
    ' ' . escapeshellarg($root . '/services/property_core/router.php');
$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];
$environment = getenv();
$environment['PROPERTY_CORE_SERVICE_TOKEN'] = $token;
$environment['PROPERTY_CORE_SKIP_ENV_FILE'] = 'true';
$process = proc_open(
    $command,
    $descriptors,
    $pipes,
    $root,
    $environment,
    ['bypass_shell' => true, 'create_process_group' => true]
);

if (!is_resource($process)) {
    throw new RuntimeException('Could not start the local Property Core test process.');
}

try {
    $baseUrl = 'http://127.0.0.1:' . $port;
    $health = null;

    for ($attempt = 0; $attempt < 20; $attempt++) {
        usleep(50000);
        $candidate = propertyCoreHttpRequest($baseUrl . '/health');

        if ($candidate['status'] === 200) {
            $health = $candidate;
            break;
        }
    }

    assertPropertyCoreHttp($health !== null, 'Health endpoint must become available.');
    $healthBody = json_decode($health['body'], true, 32, JSON_THROW_ON_ERROR);
    assertPropertyCoreHttp($healthBody['success'] === true, 'Health must use the success envelope.');
    assertPropertyCoreHttp($healthBody['data']['service'] === 'property-core', 'Health must identify only Property Core.');
    assertPropertyCoreHttp($healthBody['data']['data_access'] === 'read-only', 'Health must identify the Gate 3 read-only data contract.');
    assertPropertyCoreHttp(!str_contains($health['body'], 'PASSWORD'), 'Health must not expose database configuration.');
    assertPropertyCoreHttp(!str_contains($health['body'], 'MFA'), 'Health must not expose MFA configuration.');
    assertPropertyCoreHttp(!str_contains($health['body'], $token), 'Health must not expose the service token.');

    $missing = propertyCoreHttpRequest($baseUrl . '/api/v1/inventory');
    $missingBody = json_decode($missing['body'], true, 32, JSON_THROW_ON_ERROR);
    assertPropertyCoreHttp($missing['status'] === 401, 'Missing service credential must return HTTP 401.');
    assertPropertyCoreHttp($missingBody['error']['code'] === 'SERVICE_AUTHENTICATION_REQUIRED', 'Missing credential must use a stable error code.');

    $wrong = propertyCoreHttpRequest(
        $baseUrl . '/api/v1/assets',
        str_repeat('x', 40)
    );
    assertPropertyCoreHttp($wrong['status'] === 401, 'Wrong service credential must be rejected.');
    assertPropertyCoreHttp(!str_contains($wrong['body'], $token), 'Authentication errors must never echo the service token.');

    $valid = propertyCoreHttpRequest($baseUrl . '/api/v1/inventory', $token);
    $validBody = json_decode($valid['body'], true, 32, JSON_THROW_ON_ERROR);
    assertPropertyCoreHttp($valid['status'] === 503, 'Valid authentication must still fail closed before DB provisioning.');
    assertPropertyCoreHttp($validBody['error']['code'] === 'PROPERTY_CORE_SERVICE_UNAVAILABLE', 'Pending data access must use a safe stable error code.');
    assertPropertyCoreHttp(!str_contains($valid['body'], 'PROPERTY_CORE_DB_'), 'Configuration errors must not expose environment names.');
} finally {
    proc_terminate($process);
    foreach ($pipes as $pipe) {
        fclose($pipe);
    }
    proc_close($process);
}

echo "Property Core service HTTP tests passed: {$testsRun}" . PHP_EOL;
