<?php

require_once __DIR__ . '/../includes/procurement_service_client.php';

$testsRun = 0;
$requests = [];

function assertProcurementClient(bool $condition, string $message): void
{
    global $testsRun;
    $testsRun++;

    if (!$condition) {
        throw new RuntimeException('FAILED: ' . $message);
    }
}

$transport = static function (
    string $method,
    string $path,
    array $query,
    ?array $body,
    ?string $actor
) use (&$requests): array {
    $requests[] = compact('method', 'path', 'query', 'body', 'actor');

    return [
        'status' => $method === 'POST' && $path === '/api/v1/procurements'
            ? 201
            : 200,
        'body' => [
            'success' => true,
            'data' => $path === '/api/v1/procurements/next-id'
                ? ['procurement_id' => 'PRC-000101']
                : ['path' => $path, 'query' => $query, 'body' => $body],
            'error' => null,
        ],
    ];
};

$client = new ProcurementServiceClient(
    'http://127.0.0.1:8101',
    str_repeat('t', 32),
    250,
    500,
    $transport
);

$list = $client->list(['page' => 2, 'per_page' => 25, 'search' => 'Laptop']);
assertProcurementClient($list['query']['page'] === 2, 'List filters must be sent in one coarse-grained request.');
assertProcurementClient($requests[0]['path'] === '/api/v1/procurements', 'List must use the versioned endpoint.');

$id = $client->nextBusinessId('admin');
assertProcurementClient($id === 'PRC-000101', 'Next-ID response must be unwrapped.');
assertProcurementClient($requests[1]['actor'] === 'admin', 'Mutation actor must be sent to the transport.');

$client->create([
    'procurement_id' => 'PRC-000101',
    'status' => 'Pending',
], 'admin');
assertProcurementClient($requests[2]['method'] === 'POST', 'Create must use POST.');
assertProcurementClient($requests[2]['body']['procurement_id'] === 'PRC-000101', 'Create payload must remain server-side JSON.');

$client->update('PRC-000101', ['status' => 'Approved'], 'admin2');
assertProcurementClient(str_ends_with($requests[3]['path'], '/update'), 'Update must use an explicit mutation endpoint.');

$client->delete('PRC-000101', 'admin3');
assertProcurementClient(str_ends_with($requests[4]['path'], '/delete'), 'Delete must use an explicit POST mutation endpoint.');

$errorClient = new ProcurementServiceClient(
    'http://127.0.0.1:8101',
    str_repeat('t', 32),
    250,
    500,
    static fn (): array => [
        'status' => 404,
        'body' => [
            'success' => false,
            'data' => null,
            'error' => [
                'code' => 'PROCUREMENT_NOT_FOUND',
                'message' => 'The requested procurement record was not found.',
            ],
        ],
    ]
);

try {
    $errorClient->find('PRC-999999');
    assertProcurementClient(false, 'Service errors must throw.');
} catch (ProcurementServiceException $error) {
    assertProcurementClient($error->getServiceCode() === 'PROCUREMENT_NOT_FOUND', 'Stable service error codes must reach the gateway.');
    assertProcurementClient($error->getHttpStatus() === 404, 'Service HTTP status must be preserved.');
}

$invalidClient = new ProcurementServiceClient(
    'http://127.0.0.1:8101',
    str_repeat('t', 32),
    250,
    500,
    static fn (): array => ['status' => 200, 'body' => ['unexpected' => true]]
);

try {
    $invalidClient->health();
    assertProcurementClient(false, 'Invalid envelopes must fail closed.');
} catch (ProcurementServiceUnavailableException $error) {
    assertProcurementClient(true, 'Invalid envelopes must be treated as unavailable.');
}

$unavailableClient = new ProcurementServiceClient(
    'http://127.0.0.1:1',
    str_repeat('t', 32),
    250,
    500
);
$started = microtime(true);

try {
    $unavailableClient->health();
    assertProcurementClient(false, 'Unavailable service must throw.');
} catch (ProcurementServiceUnavailableException $error) {
    assertProcurementClient(
        microtime(true) - $started < 2.0,
        'Unavailable service must fail within the bounded timeout.'
    );
}

echo "Procurement service client tests passed: {$testsRun}" . PHP_EOL;
