<?php

require_once __DIR__ . '/ProcurementStore.php';
require_once __DIR__ . '/ProcurementDomainException.php';

final class ProcurementApiKernel
{
    public function __construct(private readonly ProcurementStore $store)
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
            if ($method === 'GET' && $path === '/api/v1/procurements') {
                return $this->success(200, $this->store->list($query));
            }

            if ($method === 'GET' && $path === '/api/v1/procurements/summary') {
                return $this->success(200, $this->store->summary());
            }

            if ($method === 'POST' && $path === '/api/v1/procurements/next-id') {
                $this->requireActor($actor);
                return $this->success(200, [
                    'procurement_id' => $this->store->nextBusinessId(),
                ]);
            }

            if ($method === 'POST' && $path === '/api/v1/procurements') {
                return $this->success(
                    201,
                    $this->store->create($body, $this->requireActor($actor))
                );
            }

            if (preg_match(
                '#^/api/v1/procurements/(PRC-\d{6})$#',
                $path,
                $matches
            ) === 1 && $method === 'GET') {
                return $this->success(200, $this->store->find($matches[1]));
            }

            if (preg_match(
                '#^/api/v1/procurements/(PRC-\d{6})/(update|delete)$#',
                $path,
                $matches
            ) === 1 && $method === 'POST') {
                $validActor = $this->requireActor($actor);
                $data = $matches[2] === 'update'
                    ? $this->store->update($matches[1], $body, $validActor)
                    : $this->store->delete($matches[1], $validActor);
                return $this->success(200, $data);
            }

            return $this->failure(
                405,
                'METHOD_NOT_ALLOWED',
                'The requested operation is not allowed.'
            );
        } catch (ProcurementDomainException $error) {
            return $this->failure(
                $error->getHttpStatus(),
                $error->getDomainCode(),
                $error->getMessage()
            );
        }
    }

    private function requireActor(string $actor): string
    {
        $actor = trim($actor);

        if (
            $actor === '' ||
            strlen($actor) > 50 ||
            preg_match('/^[A-Za-z0-9._@-]+$/', $actor) !== 1
        ) {
            throw new ProcurementDomainException(
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
