<?php

require_once __DIR__ . '/../services/property_core/src/PropertyCoreApiKernel.php';

$testsRun = 0;

function assertPropertyCoreApi(bool $condition, string $message): void
{
    global $testsRun;
    $testsRun++;

    if (!$condition) {
        throw new RuntimeException('FAILED: ' . $message);
    }
}

final class FakePropertyCoreStore implements PropertyCoreStore
{
    public array $calls = [];

    private function called(string $operation, array $arguments = []): void
    {
        $this->calls[] = compact('operation', 'arguments');
    }

    public function listInventory(array $filters): array
    {
        $this->called(__FUNCTION__, $filters);
        return [
            'records' => [['inventory_id' => 'INV-000001']],
            'pagination' => ['page' => 1, 'per_page' => 25, 'total' => 1],
            'filters' => $filters,
        ];
    }

    public function findInventory(string $businessId): array
    {
        $this->called(__FUNCTION__, [$businessId]);
        return ['inventory_id' => $businessId];
    }

    public function inventorySummary(): array
    {
        $this->called(__FUNCTION__);
        return ['total_items' => 5, 'total_quantity' => 9];
    }

    public function inventoryOptions(array $filters): array
    {
        $this->called(__FUNCTION__, $filters);
        return ['options' => [], 'limit' => 20];
    }

    public function nextInventoryBusinessId(): string
    {
        $this->called(__FUNCTION__);
        return 'INV-000006';
    }

    public function createInventory(array $input, string $actor): array
    {
        $this->called(__FUNCTION__, [$input, $actor]);
        return ['inventory_id' => $input['inventory_id'], 'actor' => $actor];
    }

    public function updateInventory(
        string $businessId,
        array $input,
        string $actor
    ): array {
        $this->called(__FUNCTION__, [$businessId, $input, $actor]);
        return ['inventory_id' => $businessId, 'actor' => $actor] + $input;
    }

    public function deleteInventory(string $businessId, string $actor): array
    {
        $this->called(__FUNCTION__, [$businessId, $actor]);
        return ['inventory_id' => $businessId, 'deleted' => true];
    }

    public function listAssets(array $filters): array
    {
        $this->called(__FUNCTION__, $filters);
        return [
            'records' => [['asset_id' => 'AST-000001']],
            'pagination' => ['page' => 1, 'per_page' => 25, 'total' => 1],
            'filters' => $filters,
        ];
    }

    public function findAsset(string $businessId): array
    {
        $this->called(__FUNCTION__, [$businessId]);
        return ['asset_id' => $businessId];
    }

    public function assetSummary(): array
    {
        $this->called(__FUNCTION__);
        return ['total' => 2, 'available' => 1, 'assigned' => 1];
    }

    public function assetOptions(array $filters): array
    {
        $this->called(__FUNCTION__, $filters);
        return ['options' => [], 'limit' => 20];
    }

    public function assetFilters(): array
    {
        $this->called(__FUNCTION__);
        return ['categories' => [], 'statuses' => [], 'locations' => []];
    }

    public function assetSuggestions(array $filters): array
    {
        $this->called(__FUNCTION__, $filters);
        return ['suggestions' => ['Dell']];
    }

    public function nextAssetBusinessId(): string
    {
        $this->called(__FUNCTION__);
        return 'AST-000003';
    }

    public function registerAsset(array $input, string $actor): array
    {
        $this->called(__FUNCTION__, [$input, $actor]);
        return ['asset_id' => $input['asset_id'], 'actor' => $actor];
    }

    public function updateAsset(
        string $businessId,
        array $input,
        string $actor
    ): array {
        $this->called(__FUNCTION__, [$businessId, $input, $actor]);
        return ['asset_id' => $businessId, 'actor' => $actor] + $input;
    }

    public function assignAsset(
        string $businessId,
        array $input,
        string $actor
    ): array {
        $this->called(__FUNCTION__, [$businessId, $input, $actor]);
        return [
            'asset_id' => $businessId,
            'status' => 'Assigned',
            'actor' => $actor,
        ];
    }

    public function returnAsset(string $businessId, string $actor): array
    {
        $this->called(__FUNCTION__, [$businessId, $actor]);
        return ['asset_id' => $businessId, 'status' => 'Available'];
    }

    public function deleteAsset(string $businessId, string $actor): array
    {
        $this->called(__FUNCTION__, [$businessId, $actor]);
        return ['asset_id' => $businessId, 'deleted' => true];
    }
}

$store = new FakePropertyCoreStore();
$kernel = new PropertyCoreApiKernel($store);

$inventory = $kernel->dispatch('GET', '/api/v1/inventory', [
    'search' => 'Laptop',
    'category' => 'ICT Equipment',
    'condition' => 'Good',
    'page' => 2,
]);
assertPropertyCoreApi($inventory['status'] === 200, 'Inventory list must return HTTP 200.');
assertPropertyCoreApi($inventory['body']['success'] === true, 'Inventory list must use the success envelope.');
assertPropertyCoreApi($inventory['body']['data']['filters']['page'] === 2, 'Inventory filters must reach the store together.');

$inventorySearch = $kernel->dispatch('GET', '/api/v1/inventory/search', ['search' => 'ink']);
assertPropertyCoreApi($inventorySearch['status'] === 200, 'Inventory search alias must use the list contract.');

$inventoryRecord = $kernel->dispatch('GET', '/api/v1/inventory/INV-000001');
assertPropertyCoreApi($inventoryRecord['body']['data']['inventory_id'] === 'INV-000001', 'Inventory lookup must use its business ID.');

$inventorySummary = $kernel->dispatch('GET', '/api/v1/inventory/summary');
assertPropertyCoreApi($inventorySummary['body']['data']['total_items'] === 5, 'Inventory summary must be coarse grained.');

$inventoryOptions = $kernel->dispatch('GET', '/api/v1/inventory/options', ['search' => 'lap', 'limit' => 20]);
assertPropertyCoreApi($inventoryOptions['body']['data']['limit'] === 20, 'Inventory options must support bounded lookup filters.');

$invalidActor = $kernel->dispatch('POST', '/api/v1/inventory/next-id');
assertPropertyCoreApi($invalidActor['status'] === 422 && $invalidActor['body']['error']['code'] === 'INVALID_ACTOR', 'Inventory mutations must require a valid actor.');

$nextInventory = $kernel->dispatch('POST', '/api/v1/inventory/next-id', [], [], 'admin');
assertPropertyCoreApi($nextInventory['body']['data']['inventory_id'] === 'INV-000006', 'Inventory next-ID must use the versioned contract.');

$createdInventory = $kernel->dispatch('POST', '/api/v1/inventory', [], ['inventory_id' => 'INV-000006'], 'admin');
assertPropertyCoreApi($createdInventory['status'] === 201 && $createdInventory['body']['data']['actor'] === 'admin', 'Inventory create must return HTTP 201 and preserve the actor.');

$updatedInventory = $kernel->dispatch('POST', '/api/v1/inventory/INV-000006/update', [], ['quantity' => 4], 'admin2');
assertPropertyCoreApi($updatedInventory['body']['data']['quantity'] === 4, 'Inventory update must route its payload.');

$deletedInventory = $kernel->dispatch('POST', '/api/v1/inventory/INV-000006/delete', [], [], 'admin3');
assertPropertyCoreApi($deletedInventory['body']['data']['deleted'] === true, 'Inventory deletion must use an explicit POST operation.');

$assets = $kernel->dispatch('GET', '/api/v1/assets', [
    'search' => 'John',
    'category' => 'ICT Equipment',
    'status' => 'Assigned',
    'location' => 'ICT Office',
]);
assertPropertyCoreApi($assets['status'] === 200, 'Asset list must return HTTP 200.');
assertPropertyCoreApi($assets['body']['data']['filters']['search'] === 'John', 'Asset search and filters must reach the store together.');

$assetSearch = $kernel->dispatch('GET', '/api/v1/assets/search', ['search' => 'John']);
assertPropertyCoreApi($assetSearch['status'] === 200, 'Asset search alias must use the list contract.');

$assetRecord = $kernel->dispatch('GET', '/api/v1/assets/AST-000001');
assertPropertyCoreApi($assetRecord['body']['data']['asset_id'] === 'AST-000001', 'Asset lookup must use its business ID.');

$assetSummary = $kernel->dispatch('GET', '/api/v1/assets/summary');
assertPropertyCoreApi($assetSummary['body']['data']['assigned'] === 1, 'Asset summary must be coarse grained.');

$assetOptions = $kernel->dispatch('GET', '/api/v1/assets/options', ['search' => 'lap', 'limit' => 10]);
assertPropertyCoreApi($assetOptions['body']['data']['limit'] === 20, 'Asset options route must reach the store.');

$assetFilters = $kernel->dispatch('GET', '/api/v1/assets/filters');
assertPropertyCoreApi(isset($assetFilters['body']['data']['locations']), 'Asset filter values must have one coarse-grained endpoint.');

$suggestions = $kernel->dispatch('GET', '/api/v1/assets/suggestions', ['field' => 'brand', 'q' => 'De']);
assertPropertyCoreApi($suggestions['body']['data']['suggestions'] === ['Dell'], 'Asset autocomplete must use its bounded suggestions route.');

$nextAsset = $kernel->dispatch('POST', '/api/v1/assets/next-id', [], [], 'admin');
assertPropertyCoreApi($nextAsset['body']['data']['asset_id'] === 'AST-000003', 'Asset next-ID must use the versioned contract.');

$registered = $kernel->dispatch('POST', '/api/v1/assets/register', [], ['asset_id' => 'AST-000003'], 'admin');
assertPropertyCoreApi($registered['status'] === 201 && $registered['body']['data']['actor'] === 'admin', 'Registration must return HTTP 201 and preserve the actor.');

$assigned = $kernel->dispatch('POST', '/api/v1/assets/AST-000003/assign', [], ['custodian' => 'John'], 'admin2');
assertPropertyCoreApi($assigned['body']['data']['status'] === 'Assigned', 'Assignment must use an explicit POST operation.');

$returned = $kernel->dispatch('POST', '/api/v1/assets/AST-000003/return', [], [], 'admin2');
assertPropertyCoreApi($returned['body']['data']['status'] === 'Available', 'Return must use an explicit POST operation.');

$updated = $kernel->dispatch('POST', '/api/v1/assets/AST-000003/update', [], ['location' => 'Office'], 'admin3');
assertPropertyCoreApi($updated['body']['data']['location'] === 'Office', 'Asset update must route its payload.');

$deleted = $kernel->dispatch('POST', '/api/v1/assets/AST-000003/delete', [], [], 'admin3');
assertPropertyCoreApi($deleted['body']['data']['deleted'] === true, 'Asset deletion must use an explicit POST operation.');

$method = $kernel->dispatch('DELETE', '/api/v1/assets/AST-000001');
assertPropertyCoreApi($method['status'] === 405 && $method['body']['error']['code'] === 'METHOD_NOT_ALLOWED', 'Unsupported methods must be rejected safely.');

$invalidId = $kernel->dispatch('GET', '/api/v1/assets/1');
assertPropertyCoreApi($invalidId['status'] === 405, 'Internal numeric IDs must not be accepted by the public contract.');

echo "Property Core API kernel tests passed: {$testsRun}" . PHP_EOL;
