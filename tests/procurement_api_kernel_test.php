<?php

require_once __DIR__ . '/../services/procurement/src/ProcurementApiKernel.php';

$testsRun = 0;

function assertProcurementApi(bool $condition, string $message): void
{
    global $testsRun;
    $testsRun++;

    if (!$condition) {
        throw new RuntimeException('FAILED: ' . $message);
    }
}

final class FakeProcurementStore implements ProcurementStore
{
    private array $records = [];
    private int $next = 61;

    public function __construct()
    {
        for ($number = 1; $number <= 60; $number++) {
            $id = 'PRC-' . str_pad((string) $number, 6, '0', STR_PAD_LEFT);
            $this->records[$id] = [
                'id' => $number,
                'procurement_id' => $id,
                'item_name' => $number % 2 === 0 ? 'Laptop' : 'Chair',
                'category' => $number % 2 === 0 ? 'Computer' : 'Furniture',
                'quantity' => 1,
                'supplier' => $number % 2 === 0 ? 'Supplier A' : 'Supplier B',
                'status' => $number % 3 === 0 ? 'Approved' : 'Pending',
            ];
        }
    }

    public function list(array $filters): array
    {
        $search = strtolower(trim((string) ($filters['search'] ?? '')));
        $status = trim((string) ($filters['status'] ?? ''));
        $supplier = trim((string) ($filters['supplier'] ?? ''));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, min(50, (int) ($filters['per_page'] ?? 25)));
        $records = array_values(array_filter(
            $this->records,
            static function (array $record) use ($search, $status, $supplier): bool {
                $text = strtolower(
                    $record['procurement_id'] . ' ' .
                    $record['item_name'] . ' ' .
                    $record['supplier']
                );
                return ($search === '' || str_contains($text, $search)) &&
                    ($status === '' || $record['status'] === $status) &&
                    ($supplier === '' || $record['supplier'] === $supplier);
            }
        ));
        $total = count($records);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);

        return [
            'records' => array_slice($records, ($page - 1) * $perPage, $perPage),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $pages,
            ],
            'filters' => ['suppliers' => ['Supplier A', 'Supplier B']],
        ];
    }

    public function find(string $businessId): array
    {
        if (!isset($this->records[$businessId])) {
            throw new ProcurementDomainException(
                'PROCUREMENT_NOT_FOUND',
                'The requested procurement record was not found.',
                404
            );
        }
        return $this->records[$businessId];
    }

    public function nextBusinessId(): string
    {
        return 'PRC-' . str_pad((string) $this->next++, 6, '0', STR_PAD_LEFT);
    }

    public function create(array $input, string $actor): array
    {
        if (!in_array($input['status'] ?? '', ['Pending', 'Approved', 'Rejected', 'Delivered'], true)) {
            throw new ProcurementDomainException(
                'INVALID_STATUS',
                'The submitted Procurement status is invalid.',
                422
            );
        }
        $id = (string) ($input['procurement_id'] ?? '');
        $this->records[$id] = ['actor' => $actor] + $input;
        return $this->records[$id];
    }

    public function update(string $businessId, array $input, string $actor): array
    {
        $this->find($businessId);
        $this->records[$businessId] = $this->records[$businessId] + $input;
        $this->records[$businessId]['actor'] = $actor;
        return $this->records[$businessId];
    }

    public function delete(string $businessId, string $actor): array
    {
        $this->find($businessId);
        unset($this->records[$businessId]);
        return ['procurement_id' => $businessId, 'deleted' => true, 'actor' => $actor];
    }

    public function summary(): array
    {
        return ['total' => count($this->records), 'open' => count($this->records)];
    }
}

$kernel = new ProcurementApiKernel(new FakeProcurementStore());

$list = $kernel->dispatch('GET', '/api/v1/procurements');
assertProcurementApi($list['status'] === 200, 'List endpoint must return HTTP 200.');
assertProcurementApi($list['body']['success'] === true, 'List must use the success envelope.');
assertProcurementApi(count($list['body']['data']['records']) === 25, 'List must default to 25 rows.');
assertProcurementApi($list['body']['data']['pagination']['total'] === 60, 'List must return matching totals.');

$page = $kernel->dispatch('GET', '/api/v1/procurements', ['page' => 3]);
assertProcurementApi(count($page['body']['data']['records']) === 10, 'Third page must contain the remaining ten records.');

$filtered = $kernel->dispatch('GET', '/api/v1/procurements', [
    'search' => 'Laptop',
    'status' => 'Approved',
    'supplier' => 'Supplier A',
]);
assertProcurementApi($filtered['body']['data']['pagination']['total'] === 10, 'Search and filters must combine with AND semantics.');

$single = $kernel->dispatch('GET', '/api/v1/procurements/PRC-000001');
assertProcurementApi($single['body']['data']['procurement_id'] === 'PRC-000001', 'Single-record retrieval must use the business ID.');

$missing = $kernel->dispatch('GET', '/api/v1/procurements/PRC-999999');
assertProcurementApi($missing['status'] === 404 && $missing['body']['error']['code'] === 'PROCUREMENT_NOT_FOUND', 'Missing records must use a stable 404 error envelope.');

$invalidActor = $kernel->dispatch('POST', '/api/v1/procurements/next-id');
assertProcurementApi($invalidActor['status'] === 422 && $invalidActor['body']['error']['code'] === 'INVALID_ACTOR', 'Mutations must require a valid actor.');

$next = $kernel->dispatch('POST', '/api/v1/procurements/next-id', [], [], 'admin');
assertProcurementApi($next['body']['data']['procurement_id'] === 'PRC-000061', 'Next-ID endpoint must return a sequence-style business ID.');

$created = $kernel->dispatch('POST', '/api/v1/procurements', [], [
    'procurement_id' => 'PRC-000061',
    'item_name' => 'Printer',
    'status' => 'Pending',
], 'admin');
assertProcurementApi($created['status'] === 201 && $created['body']['data']['actor'] === 'admin', 'Create must return HTTP 201 and preserve the authenticated actor.');

$invalid = $kernel->dispatch('POST', '/api/v1/procurements', [], [
    'procurement_id' => 'PRC-000062',
    'status' => 'Unknown',
], 'admin');
assertProcurementApi($invalid['status'] === 422 && $invalid['body']['error']['code'] === 'INVALID_STATUS', 'Invalid input must use a safe validation envelope.');

$updated = $kernel->dispatch('POST', '/api/v1/procurements/PRC-000061/update', [], [
    'status' => 'Approved',
], 'admin2');
assertProcurementApi($updated['body']['data']['actor'] === 'admin2', 'Update must route the actor to the store.');

$deleted = $kernel->dispatch('POST', '/api/v1/procurements/PRC-000061/delete', [], [], 'admin3');
assertProcurementApi($deleted['body']['data']['deleted'] === true, 'Delete endpoint must return an explicit deletion result.');

$summary = $kernel->dispatch('GET', '/api/v1/procurements/summary');
assertProcurementApi($summary['body']['data']['total'] === 60, 'Summary must reflect service-owned records.');

$method = $kernel->dispatch('DELETE', '/api/v1/procurements/PRC-000001');
assertProcurementApi($method['status'] === 405 && $method['body']['error']['code'] === 'METHOD_NOT_ALLOWED', 'Unsafe or unsupported methods must be rejected.');

echo "Procurement API kernel tests passed: {$testsRun}" . PHP_EOL;
