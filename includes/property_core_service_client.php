<?php

require_once __DIR__ . '/../config/config.php';

final class PropertyCoreServiceUnavailableException extends RuntimeException
{
}

final class PropertyCoreServiceException extends RuntimeException
{
    public function __construct(
        private readonly string $serviceCode,
        string $message,
        private readonly int $httpStatus = 500
    ) {
        parent::__construct($message);
    }

    public function getServiceCode(): string
    {
        return $this->serviceCode;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}

final class PropertyCoreServiceClient
{
    /** @var null|callable(string, string, array, ?array, ?string): array */
    private $transport;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $token,
        private readonly int $connectTimeoutMs = 1500,
        private readonly int $requestTimeoutMs = 5000,
        ?callable $transport = null
    ) {
        $this->transport = $transport;
    }

    public static function fromEnvironment(): self
    {
        $baseUrl = rtrim(
            trim((string) (getenv('PROPERTY_CORE_SERVICE_URL') ?: '')),
            '/'
        );
        $token = trim((string) (
            getenv('PROPERTY_CORE_SERVICE_TOKEN') ?: ''
        ));

        if ($baseUrl === '' || strlen($token) < 32) {
            throw new PropertyCoreServiceUnavailableException(
                'Property Core service configuration is incomplete.'
            );
        }

        return new self(
            $baseUrl,
            $token,
            self::boundedTimeout(
                getenv('PROPERTY_CORE_SERVICE_CONNECT_TIMEOUT_MS'),
                1500,
                250,
                10000
            ),
            self::boundedTimeout(
                getenv('PROPERTY_CORE_SERVICE_TIMEOUT_MS'),
                5000,
                500,
                30000
            )
        );
    }

    public function health(): array
    {
        return $this->request('GET', '/health');
    }

    public function listInventory(array $filters = []): array
    {
        return $this->request('GET', '/api/v1/inventory', $filters);
    }

    public function findInventory(string $businessId): array
    {
        return $this->request(
            'GET',
            '/api/v1/inventory/' . rawurlencode($businessId)
        );
    }

    public function inventorySummary(): array
    {
        return $this->request('GET', '/api/v1/inventory/summary');
    }

    public function inventoryOptions(array $filters = []): array
    {
        return $this->request(
            'GET',
            '/api/v1/inventory/options',
            $filters
        );
    }

    public function nextInventoryBusinessId(string $actor): string
    {
        $data = $this->request(
            'POST',
            '/api/v1/inventory/next-id',
            [],
            [],
            $actor
        );

        return (string) ($data['inventory_id'] ?? '');
    }

    public function createInventory(array $record, string $actor): array
    {
        return $this->request(
            'POST',
            '/api/v1/inventory',
            [],
            $record,
            $actor
        );
    }

    public function updateInventory(
        string $businessId,
        array $record,
        string $actor
    ): array {
        return $this->request(
            'POST',
            '/api/v1/inventory/' . rawurlencode($businessId) . '/update',
            [],
            $record,
            $actor
        );
    }

    public function deleteInventory(string $businessId, string $actor): array
    {
        return $this->request(
            'POST',
            '/api/v1/inventory/' . rawurlencode($businessId) . '/delete',
            [],
            [],
            $actor
        );
    }

    public function listAssets(array $filters = []): array
    {
        return $this->request('GET', '/api/v1/assets', $filters);
    }

    public function findAsset(string $businessId): array
    {
        return $this->request(
            'GET',
            '/api/v1/assets/' . rawurlencode($businessId)
        );
    }

    public function assetSummary(): array
    {
        return $this->request('GET', '/api/v1/assets/summary');
    }

    public function assetOptions(array $filters = []): array
    {
        return $this->request('GET', '/api/v1/assets/options', $filters);
    }

    public function assetFilters(): array
    {
        return $this->request('GET', '/api/v1/assets/filters');
    }

    public function assetSuggestions(array $filters): array
    {
        return $this->request(
            'GET',
            '/api/v1/assets/suggestions',
            $filters
        );
    }

    public function lifecycleConfiguration(): array
    {
        return $this->request('GET', '/api/v1/lifecycle/configuration');
    }

    public function updateLifecycleSettings(
        array $settings,
        string $actor
    ): array {
        return $this->request(
            'POST',
            '/api/v1/lifecycle/settings',
            [],
            $settings,
            $actor
        );
    }

    public function saveCategoryUsefulLife(
        array $configuration,
        string $actor
    ): array {
        return $this->request(
            'POST',
            '/api/v1/lifecycle/categories',
            [],
            $configuration,
            $actor
        );
    }

    public function nextAssetBusinessId(string $actor): string
    {
        $data = $this->request(
            'POST',
            '/api/v1/assets/next-id',
            [],
            [],
            $actor
        );

        return (string) ($data['asset_id'] ?? '');
    }

    public function registerAsset(array $record, string $actor): array
    {
        return $this->request(
            'POST',
            '/api/v1/assets/register',
            [],
            $record,
            $actor
        );
    }

    public function updateAsset(
        string $businessId,
        array $record,
        string $actor
    ): array {
        return $this->request(
            'POST',
            '/api/v1/assets/' . rawurlencode($businessId) . '/update',
            [],
            $record,
            $actor
        );
    }

    public function assignAsset(
        string $businessId,
        array $assignment,
        string $actor
    ): array {
        return $this->request(
            'POST',
            '/api/v1/assets/' . rawurlencode($businessId) . '/assign',
            [],
            $assignment,
            $actor
        );
    }

    public function returnAsset(string $businessId, string $actor): array
    {
        return $this->request(
            'POST',
            '/api/v1/assets/' . rawurlencode($businessId) . '/return',
            [],
            [],
            $actor
        );
    }

    public function deleteAsset(string $businessId, string $actor): array
    {
        return $this->request(
            'POST',
            '/api/v1/assets/' . rawurlencode($businessId) . '/delete',
            [],
            [],
            $actor
        );
    }

    private function request(
        string $method,
        string $path,
        array $query = [],
        ?array $body = null,
        ?string $actor = null
    ): array {
        if ($this->transport !== null) {
            return $this->unwrapResponse(($this->transport)(
                $method,
                $path,
                $query,
                $body,
                $actor
            ));
        }

        $url = $this->baseUrl . $path;

        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $this->token,
        ];
        $payload = null;

        if ($body !== null) {
            $payload = json_encode(
                $body,
                JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
            );

            if ($payload === false) {
                throw new PropertyCoreServiceException(
                    'INVALID_GATEWAY_PAYLOAD',
                    'The Property Core request could not be encoded.',
                    500
                );
            }

            $headers[] = 'Content-Type: application/json';
        }

        if ($actor !== null && trim($actor) !== '') {
            $headers[] = 'X-PCMS-Actor: ' . trim($actor);
        }

        if (function_exists('curl_init')) {
            [$status, $raw] = $this->requestWithCurl(
                $method,
                $url,
                $headers,
                $payload
            );
        } else {
            [$status, $raw] = $this->requestWithStreams(
                $method,
                $url,
                $headers,
                $payload
            );
        }

        try {
            $envelope = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new PropertyCoreServiceUnavailableException(
                'Property Core service returned an invalid response.'
            );
        }

        return $this->unwrapResponse([
            'status' => $status,
            'body' => $envelope,
        ]);
    }

    private function requestWithCurl(
        string $method,
        string $url,
        array $headers,
        ?string $payload
    ): array {
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT_MS => $this->connectTimeoutMs,
            CURLOPT_TIMEOUT_MS => $this->requestTimeoutMs,
            CURLOPT_NOSIGNAL => true,
        ]);

        if ($payload !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $payload);
        }

        $raw = curl_exec($handle);
        $curlError = curl_error($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if ($raw === false || $curlError !== '') {
            throw new PropertyCoreServiceUnavailableException(
                'Property Core service is temporarily unavailable.'
            );
        }

        return [$status, (string) $raw];
    }

    private function requestWithStreams(
        string $method,
        string $url,
        array $headers,
        ?string $payload
    ): array {
        $options = [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'ignore_errors' => true,
            'follow_location' => 0,
            'max_redirects' => 0,
            'protocol_version' => 1.1,
            'timeout' => $this->requestTimeoutMs / 1000,
        ];

        if ($payload !== null) {
            $options['content'] = $payload;
        }

        $context = stream_context_create(['http' => $options]);
        $raw = @file_get_contents($url, false, $context);
        $responseHeaders = $http_response_header ?? [];
        $status = 0;

        foreach ($responseHeaders as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches) === 1) {
                $status = (int) $matches[1];
            }
        }

        if ($raw === false || $status === 0) {
            throw new PropertyCoreServiceUnavailableException(
                'Property Core service is temporarily unavailable.'
            );
        }

        return [$status, (string) $raw];
    }

    private function unwrapResponse(array $response): array
    {
        $status = (int) ($response['status'] ?? 500);
        $envelope = $response['body'] ?? null;

        if (!is_array($envelope) || !array_key_exists('success', $envelope)) {
            throw new PropertyCoreServiceUnavailableException(
                'Property Core service returned an invalid response.'
            );
        }

        if ($envelope['success'] === true && $status >= 200 && $status < 300) {
            return is_array($envelope['data'] ?? null)
                ? $envelope['data']
                : [];
        }

        $error = is_array($envelope['error'] ?? null)
            ? $envelope['error']
            : [];

        throw new PropertyCoreServiceException(
            (string) ($error['code'] ?? 'PROPERTY_CORE_SERVICE_ERROR'),
            (string) ($error['message'] ?? 'The Property Core request failed.'),
            $status > 0 ? $status : 500
        );
    }

    private static function boundedTimeout(
        mixed $value,
        int $default,
        int $minimum,
        int $maximum
    ): int {
        $timeout = filter_var($value, FILTER_VALIDATE_INT);

        if ($timeout === false) {
            return $default;
        }

        return max($minimum, min($maximum, (int) $timeout));
    }
}

function getPropertyCoreServiceClient(): PropertyCoreServiceClient
{
    static $client = null;

    if (!$client instanceof PropertyCoreServiceClient) {
        $client = PropertyCoreServiceClient::fromEnvironment();
    }

    return $client;
}
