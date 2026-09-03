<?php

require_once __DIR__ . '/PropertyCoreStore.php';
require_once __DIR__ . '/PropertyCoreDomainException.php';

final class PropertyCoreApiKernel
{
    public function __construct(private readonly PropertyCoreStore $store)
    {
    }

    public function dispatch(
        string $method,
        string $path,
        array $query = [],
        array $body = [],
        string $actor = ''
    ): array {
        $method = strtoupper($method);
        $path = '/' . trim($path, '/');

        try {
            $response = $this->dispatchInventory(
                $method,
                $path,
                $query,
                $body,
                $actor
            );

            if ($response !== null) {
                return $response;
            }

            $response = $this->dispatchAssets(
                $method,
                $path,
                $query,
                $body,
                $actor
            );

            if ($response !== null) {
                return $response;
            }

            return $this->failure(
                405,
                'METHOD_NOT_ALLOWED',
                'The requested operation is not allowed.'
            );
        } catch (PropertyCoreDomainException $error) {
            return $this->failure(
                $error->getHttpStatus(),
                $error->getDomainCode(),
                $error->getMessage()
            );
        }
    }

    private function dispatchInventory(
        string $method,
        string $path,
        array $query,
        array $body,
        string $actor
    ): ?array {
        if (
            $method === 'GET' &&
            in_array($path, ['/api/v1/inventory', '/api/v1/inventory/search'], true)
        ) {
            return $this->success(200, $this->store->listInventory($query));
        }

        if ($method === 'GET' && $path === '/api/v1/inventory/summary') {
            return $this->success(200, $this->store->inventorySummary());
        }

        if ($method === 'GET' && $path === '/api/v1/inventory/options') {
            return $this->success(200, $this->store->inventoryOptions($query));
        }

        if ($method === 'POST' && $path === '/api/v1/inventory/next-id') {
            $this->requireActor($actor);
            return $this->success(200, [
                'inventory_id' => $this->store->nextInventoryBusinessId(),
            ]);
        }

        if ($method === 'POST' && $path === '/api/v1/inventory') {
            return $this->success(
                201,
                $this->store->createInventory(
                    $body,
                    $this->requireActor($actor)
                )
            );
        }

        if (preg_match(
            '#^/api/v1/inventory/(INV-\d{6})$#',
            $path,
            $matches
        ) === 1 && $method === 'GET') {
            return $this->success(
                200,
                $this->store->findInventory($matches[1])
            );
        }

        if (preg_match(
            '#^/api/v1/inventory/(INV-\d{6})/(update|delete)$#',
            $path,
            $matches
        ) === 1 && $method === 'POST') {
            $validActor = $this->requireActor($actor);
            $data = $matches[2] === 'update'
                ? $this->store->updateInventory(
                    $matches[1],
                    $body,
                    $validActor
                )
                : $this->store->deleteInventory($matches[1], $validActor);
            return $this->success(200, $data);
        }

        return null;
    }

    private function dispatchAssets(
        string $method,
        string $path,
        array $query,
        array $body,
        string $actor
    ): ?array {
        if (
            $method === 'GET' &&
            in_array($path, ['/api/v1/assets', '/api/v1/assets/search'], true)
        ) {
            return $this->success(200, $this->store->listAssets($query));
        }

        if ($method === 'GET' && $path === '/api/v1/assets/summary') {
            return $this->success(200, $this->store->assetSummary());
        }

        if ($method === 'GET' && $path === '/api/v1/assets/options') {
            return $this->success(200, $this->store->assetOptions($query));
        }

        if ($method === 'GET' && $path === '/api/v1/assets/filters') {
            return $this->success(200, $this->store->assetFilters());
        }

        if ($method === 'GET' && $path === '/api/v1/assets/suggestions') {
            return $this->success(200, $this->store->assetSuggestions($query));
        }

        if ($method === 'POST' && $path === '/api/v1/assets/next-id') {
            $this->requireActor($actor);
            return $this->success(200, [
                'asset_id' => $this->store->nextAssetBusinessId(),
            ]);
        }

        if (
            $method === 'POST' &&
            in_array($path, ['/api/v1/assets', '/api/v1/assets/register'], true)
        ) {
            return $this->success(
                201,
                $this->store->registerAsset(
                    $body,
                    $this->requireActor($actor)
                )
            );
        }

        if (preg_match(
            '#^/api/v1/assets/(AST-\d{6})$#',
            $path,
            $matches
        ) === 1 && $method === 'GET') {
            return $this->success(200, $this->store->findAsset($matches[1]));
        }

        if (preg_match(
            '#^/api/v1/assets/(AST-\d{6})/(update|assign|return|delete)$#',
            $path,
            $matches
        ) === 1 && $method === 'POST') {
            $validActor = $this->requireActor($actor);
            $businessId = $matches[1];
            $data = match ($matches[2]) {
                'update' => $this->store->updateAsset(
                    $businessId,
                    $body,
                    $validActor
                ),
                'assign' => $this->store->assignAsset(
                    $businessId,
                    $body,
                    $validActor
                ),
                'return' => $this->store->returnAsset(
                    $businessId,
                    $validActor
                ),
                'delete' => $this->store->deleteAsset(
                    $businessId,
                    $validActor
                ),
            };
            return $this->success(200, $data);
        }

        return null;
    }

    private function requireActor(string $actor): string
    {
        $actor = trim($actor);

        if (
            $actor === '' ||
            strlen($actor) > 50 ||
            preg_match('/^[A-Za-z0-9._@-]+$/', $actor) !== 1
        ) {
            throw new PropertyCoreDomainException(
                'INVALID_ACTOR',
                'A valid PCMS actor identifier is required.',
                422
            );
        }

        return $actor;
    }

    private function success(int $status, array $data): array
    {
        return [
            'status' => $status,
            'body' => [
                'success' => true,
                'data' => $data,
                'error' => null,
            ],
        ];
    }

    private function failure(
        int $status,
        string $code,
        string $message
    ): array {
        return [
            'status' => $status,
            'body' => [
                'success' => false,
                'data' => null,
                'error' => [
                    'code' => $code,
                    'message' => $message,
                ],
            ],
        ];
    }
}
