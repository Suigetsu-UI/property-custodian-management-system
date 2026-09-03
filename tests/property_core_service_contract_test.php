<?php

$testsRun = 0;

function assertPropertyCoreContract(bool $condition, string $message): void
{
    global $testsRun;
    $testsRun++;

    if (!$condition) {
        throw new RuntimeException('FAILED: ' . $message);
    }
}

$root = dirname(__DIR__);
$serviceRoot = $root . '/services/property_core';
$kernel = file_get_contents($serviceRoot . '/src/PropertyCoreApiKernel.php');
$repository = file_get_contents($serviceRoot . '/src/PropertyCoreRepository.php');
$frontController = file_get_contents($serviceRoot . '/public/index.php');
$serviceConfig = file_get_contents($serviceRoot . '/config.php');
$client = file_get_contents($root . '/includes/property_core_service_client.php');
$rootExample = file_get_contents($root . '/.env.example');
$serviceExample = file_get_contents($serviceRoot . '/.env.example');
$gitignore = file_get_contents($root . '/.gitignore');

assertPropertyCoreContract(
    str_contains($kernel, '/api/v1/inventory') &&
    str_contains($kernel, '/api/v1/assets'),
    'Property Core must expose versioned Inventory and Asset contracts.'
);
assertPropertyCoreContract(
    str_contains($kernel, '/api/v1/assets/register') &&
    str_contains($kernel, '(update|assign|return|delete)') &&
    str_contains($kernel, "'assign' =>") &&
    str_contains($kernel, "'return' =>") &&
    str_contains($kernel, "'delete' =>"),
    'Asset lifecycle mutations must have explicit service routes.'
);
assertPropertyCoreContract(
    str_contains($kernel, 'requireActor') &&
    str_contains($kernel, 'INVALID_ACTOR'),
    'Every mutation contract must carry a validated PCMS actor.'
);
assertPropertyCoreContract(
    str_contains($frontController, 'hash_equals') &&
    str_contains($frontController, 'HTTP_AUTHORIZATION') &&
    str_contains($frontController, "'Bearer '"),
    'The service must use constant-time bearer authentication.'
);
assertPropertyCoreContract(
    str_contains($frontController, "'success' => \$success") &&
    str_contains($frontController, "'data' => \$data") &&
    str_contains($frontController, "'error' => \$error"),
    'All HTTP responses must use the frozen JSON envelope.'
);
assertPropertyCoreContract(
    !str_contains($frontController, '$error->getMessage()') &&
    !str_contains($frontController, 'getTraceAsString') &&
    str_contains($frontController, 'Property Core service is temporarily unavailable.'),
    'Unexpected service failures must return only safe messages.'
);
assertPropertyCoreContract(
    str_contains($client, 'CURLOPT_CONNECTTIMEOUT_MS') &&
    str_contains($client, 'CURLOPT_TIMEOUT_MS') &&
    str_contains($client, "'timeout' => \$this->requestTimeoutMs / 1000") &&
    !str_contains($client, 'CURLOPT_FOLLOWLOCATION => true'),
    'The gateway must use bounded timeouts and never follow redirects.'
);
assertPropertyCoreContract(
    str_contains($client, 'Authorization: Bearer ') &&
    str_contains($client, 'X-PCMS-Actor: ') &&
    !str_contains($client, 'MFA_ENCRYPTION_KEY'),
    'The gateway must use only its service credential and authenticated actor.'
);
assertPropertyCoreContract(
    preg_match('/^PROPERTY_CORE_SERVICE_TOKEN=\s*$/m', $rootExample) === 1 &&
    preg_match('/^PROPERTY_CORE_SERVICE_TOKEN=\s*$/m', $serviceExample) === 1 &&
    preg_match('/^PROPERTY_CORE_DB_PASSWORD=\s*$/m', $serviceExample) === 1,
    'Committed examples must contain empty Property Core secret placeholders.'
);
assertPropertyCoreContract(
    !str_contains($rootExample, 'PROPERTY_CORE_DB_PASSWORD') &&
    !str_contains($serviceExample, 'MFA_ENCRYPTION_KEY') &&
    !str_contains($serviceExample, 'PROCUREMENT_SERVICE_TOKEN') &&
    preg_match('/^DB_PASSWORD=/m', $serviceExample) !== 1,
    'The main app and service must not share database, MFA, or Procurement secrets.'
);
assertPropertyCoreContract(
    str_contains($gitignore, 'services/property_core/.env') &&
    str_contains($serviceConfig, "loadPropertyCoreServiceEnv(__DIR__ . '/.env')") &&
    !str_contains($serviceConfig, '../../config/config.php'),
    'Property Core secrets must remain in its separately ignored environment file.'
);
assertPropertyCoreContract(
    str_contains($serviceConfig, "getenv('PROPERTY_CORE_DB_' . \$suffix)") &&
    str_contains($serviceConfig, 'PDO::ATTR_EMULATE_PREPARES => false') &&
    !str_contains($serviceConfig, "getenv('DB_PASSWORD')"),
    'The database adapter must be prepared only for the dedicated service role.'
);
assertPropertyCoreContract(
    str_contains($repository, 'implements PropertyCoreStore') &&
    str_contains($frontController, 'new PropertyCoreRepository(') &&
    str_contains($frontController, '$connection'),
    'The HTTP layer must depend on the replaceable Property Core store contract.'
);
assertPropertyCoreContract(
    str_contains($frontController, "'data_access' =>") &&
    str_contains($frontController, "'service' => 'property-core'"),
    'Health must identify Property Core and disclose its data-access readiness state.'
);
assertPropertyCoreContract(
    str_contains($serviceConfig, 'PROPERTY_CORE_SKIP_ENV_FILE') &&
    str_contains(file_get_contents($root . '/tests/property_core_service_http_test.php'), "'PROPERTY_CORE_SKIP_ENV_FILE'] = 'true'"),
    'HTTP tests must never load future live Property Core credentials.'
);
assertPropertyCoreContract(
    str_contains($client, '/api/v1/inventory/options') &&
    str_contains($client, '/api/v1/assets/options') &&
    str_contains($client, '/api/v1/assets/suggestions'),
    'The gateway must prepare bounded selector and autocomplete contracts.'
);
assertPropertyCoreContract(
    preg_match('/SELECT\s+\*/i', $repository) !== 1 &&
    str_contains($repository, 'LIMIT :limit OFFSET :offset') &&
    str_contains($repository, 'min($maximum'),
    'Read models must use explicit projections and bounded server pagination.'
);
assertPropertyCoreContract(
    str_contains($repository, "coalesce(custodian, '')") &&
    str_contains($repository, "coalesce(employee_id, '')") &&
    str_contains($repository, "coalesce(department, '')") &&
    str_contains($repository, "coalesce(supplier, '')"),
    'Asset search must cover the frozen custodian-oriented text fields.'
);
assertPropertyCoreContract(
    str_contains($repository, 'PROPERTY_CORE_READ_ONLY_GATE') &&
    str_contains($frontController, "? 'lifecycle-writes'") &&
    str_contains($frontController, ": 'read-only'") &&
    str_contains($serviceConfig, "=== 'true'"),
    'The service must expose reads while lifecycle writes fail closed unless explicitly enabled.'
);
assertPropertyCoreContract(
    str_contains($repository, "['brand', 'model', 'supplier']") &&
    str_contains($repository, 'minimum_search_length') &&
    str_contains($repository, 'quantity > 0'),
    'Options and suggestions must remain allowlisted, search-gated, and bounded.'
);

echo "Property Core service contract tests passed: {$testsRun}" . PHP_EOL;
