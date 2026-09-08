<?php

require_once __DIR__ . '/../includes/property_core_service_client.php';

$testsRun = 0;
$requests = [];

function assertPropertyCoreClient(bool $condition, string $message): void
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
    $data = ['path' => $path, 'query' => $query, 'body' => $body];

    if ($path === '/api/v1/inventory/next-id') {
        $data = ['inventory_id' => 'INV-000101'];
    } elseif ($path === '/api/v1/assets/next-id') {
        $data = ['asset_id' => 'AST-000101'];
    }

    return [
        'status' => $method === 'POST' && in_array(
            $path,
            ['/api/v1/inventory', '/api/v1/assets/register'],
            true
        ) ? 201 : 200,
        'body' => [
            'success' => true,
            'data' => $data,
            'error' => null,
        ],
    ];
};

$client = new PropertyCoreServiceClient(
    'http://127.0.0.1:8102',
    str_repeat('t', 32),
    250,
    500,
    $transport
);

$inventory = $client->listInventory([
    'page' => 2,
    'per_page' => 25,
    'search' => 'Laptop',
    'category' => 'ICT Equipment',
]);
assertPropertyCoreClient($inventory['query']['page'] === 2, 'Inventory filters must be sent in one request.');
assertPropertyCoreClient($requests[0]['path'] === '/api/v1/inventory', 'Inventory list must use the versioned endpoint.');

$client->findInventory('INV-000001');
assertPropertyCoreClient($requests[1]['path'] === '/api/v1/inventory/INV-000001', 'Inventory lookup must use a business ID.');

$client->inventorySummary();
assertPropertyCoreClient($requests[2]['path'] === '/api/v1/inventory/summary', 'Inventory summary must use one coarse-grained endpoint.');

$client->inventoryOptions(['search' => 'lap', 'limit' => 20]);
assertPropertyCoreClient($requests[3]['query']['limit'] === 20, 'Inventory options must preserve bounded parameters.');

$inventoryId = $client->nextInventoryBusinessId('admin');
assertPropertyCoreClient($inventoryId === 'INV-000101', 'Inventory next-ID must be unwrapped.');
assertPropertyCoreClient($requests[4]['actor'] === 'admin', 'Inventory mutation actor must reach the transport.');

$client->createInventory(['inventory_id' => $inventoryId], 'admin');
$client->updateInventory($inventoryId, ['quantity' => 4], 'admin2');
$client->deleteInventory($inventoryId, 'admin3');
assertPropertyCoreClient($requests[5]['method'] === 'POST', 'Inventory create must use POST.');
assertPropertyCoreClient(str_ends_with($requests[6]['path'], '/update'), 'Inventory update must use an explicit endpoint.');
assertPropertyCoreClient(str_ends_with($requests[7]['path'], '/delete'), 'Inventory delete must use an explicit endpoint.');

$assets = $client->listAssets([
    'search' => 'John',
    'status' => 'Assigned',
    'location' => 'ICT Office',
]);
assertPropertyCoreClient($assets['query']['search'] === 'John', 'Custodian search must be forwarded to Asset listing.');

$client->findAsset('AST-000001');
$client->assetSummary();
$client->assetOptions(['search' => 'lap', 'limit' => 10]);
$client->assetFilters();
$client->assetSuggestions(['field' => 'brand', 'q' => 'De', 'limit' => 10]);
assertPropertyCoreClient($requests[9]['path'] === '/api/v1/assets/AST-000001', 'Asset lookup must use a business ID.');
assertPropertyCoreClient($requests[10]['path'] === '/api/v1/assets/summary', 'Asset summary must be coarse grained.');
assertPropertyCoreClient($requests[11]['query']['limit'] === 10, 'Asset options must preserve their limit.');
assertPropertyCoreClient($requests[12]['path'] === '/api/v1/assets/filters', 'Asset filters must use one endpoint.');
assertPropertyCoreClient($requests[13]['query']['field'] === 'brand', 'Suggestions must preserve the approved field.');

$client->lifecycleConfiguration();
$client->updateLifecycleSettings(['aging_threshold_percent' => 75], 'admin');
$client->saveCategoryUsefulLife([
    'category' => 'Computer',
    'useful_life_months' => 60,
], 'admin2');
assertPropertyCoreClient($requests[14]['path'] === '/api/v1/lifecycle/configuration', 'Lifecycle configuration must use its versioned endpoint.');
assertPropertyCoreClient($requests[15]['body']['aging_threshold_percent'] === 75, 'Threshold updates must preserve their payload.');
assertPropertyCoreClient($requests[16]['actor'] === 'admin2', 'Useful-life changes must preserve the Administrator actor.');

$assetId = $client->nextAssetBusinessId('admin');
assertPropertyCoreClient($assetId === 'AST-000101', 'Asset next-ID must be unwrapped.');

$client->registerAsset(['asset_id' => $assetId, 'inventory_id' => 'INV-000001'], 'admin');
$client->updateAsset($assetId, ['location' => 'Office'], 'admin2');
$client->assignAsset($assetId, ['custodian' => 'John'], 'admin2');
$client->returnAsset($assetId, 'admin2');
$client->deleteAsset($assetId, 'admin3');
assertPropertyCoreClient($requests[18]['path'] === '/api/v1/assets/register', 'Asset registration must use its lifecycle endpoint.');
assertPropertyCoreClient(str_ends_with($requests[19]['path'], '/update'), 'Asset update must use an explicit endpoint.');
assertPropertyCoreClient(str_ends_with($requests[20]['path'], '/assign'), 'Asset assignment must use an explicit endpoint.');
assertPropertyCoreClient(str_ends_with($requests[21]['path'], '/return'), 'Asset return must use an explicit endpoint.');
assertPropertyCoreClient(str_ends_with($requests[22]['path'], '/delete'), 'Asset delete must use an explicit endpoint.');
assertPropertyCoreClient($requests[22]['actor'] === 'admin3', 'Asset mutation actor must reach the transport.');

$errorClient = new PropertyCoreServiceClient(
    'http://127.0.0.1:8102',
    str_repeat('t', 32),
    250,
    500,
    static fn (): array => [
        'status' => 404,
        'body' => [
            'success' => false,
            'data' => null,
            'error' => [
                'code' => 'ASSET_NOT_FOUND',
                'message' => 'The requested Asset was not found.',
            ],
        ],
    ]
);

try {
    $errorClient->findAsset('AST-999999');
    assertPropertyCoreClient(false, 'Service errors must throw.');
} catch (PropertyCoreServiceException $error) {
    assertPropertyCoreClient($error->getServiceCode() === 'ASSET_NOT_FOUND', 'Stable service codes must reach the gateway.');
    assertPropertyCoreClient($error->getHttpStatus() === 404, 'Service HTTP status must be preserved.');
}

$invalidClient = new PropertyCoreServiceClient(
    'http://127.0.0.1:8102',
    str_repeat('t', 32),
    250,
    500,
    static fn (): array => ['status' => 200, 'body' => ['unexpected' => true]]
);

try {
    $invalidClient->health();
    assertPropertyCoreClient(false, 'Invalid envelopes must fail closed.');
} catch (PropertyCoreServiceUnavailableException $error) {
    assertPropertyCoreClient(true, 'Invalid envelopes must be treated as unavailable.');
}

$unavailableClient = new PropertyCoreServiceClient(
    'http://127.0.0.1:1',
    str_repeat('t', 32),
    250,
    500
);
$started = microtime(true);

try {
    $unavailableClient->health();
    assertPropertyCoreClient(false, 'Unavailable service must throw.');
} catch (PropertyCoreServiceUnavailableException $error) {
    assertPropertyCoreClient(microtime(true) - $started < 2.0, 'Unavailable service must fail within the bounded timeout.');
}

echo "Property Core service client tests passed: {$testsRun}" . PHP_EOL;
