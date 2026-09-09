<?php

if (getenv('PROPERTY_CORE_RUN_OPERATIONAL_TESTS') !== 'true') {
    echo "Property Core Gate 3 operational tests skipped." . PHP_EOL;
    exit(0);
}

$testsRun = 0;

function assertPropertyCoreGate3(bool $condition, string $message): void
{
    global $testsRun;
    $testsRun++;

    if (!$condition) {
        throw new RuntimeException('FAILED: ' . $message);
    }
}

function propertyCoreGate3Http(
    string $method,
    string $url,
    ?string $token = null,
    ?string $actor = null
): array {
    $headers = ['Accept: application/json'];
    if ($token !== null) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    if ($actor !== null) {
        $headers[] = 'X-PCMS-Actor: ' . $actor;
    }
    $context = stream_context_create(['http' => [
        'method' => $method,
        'header' => implode("\r\n", $headers),
        'content' => $method === 'POST' ? '{}' : null,
        'ignore_errors' => true,
        'follow_location' => 0,
        'timeout' => 5.0,
    ]]);
    $body = @file_get_contents($url, false, $context);
    $status = 0;
    foreach (($http_response_header ?? []) as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches) === 1) {
            $status = (int) $matches[1];
        }
    }
    return [
        'status' => $status,
        'body' => json_decode(
            (string) $body,
            true,
            64,
            JSON_THROW_ON_ERROR
        ),
    ];
}

$root = dirname(__DIR__);
require_once $root . '/services/property_core/config.php';
require_once $root .
    '/services/property_core/src/PropertyCoreRepository.php';

$pdo = getPropertyCoreServiceConnection();
$repository = new PropertyCoreRepository($pdo);
$currentUser = (string) $pdo->query('SELECT current_user')->fetchColumn();
assertPropertyCoreGate3(
    $currentUser === 'pcms_property_core_service',
    'The live connection must use only the dedicated Property Core role.'
);

$role = $pdo->query(
    "SELECT rolcanlogin, rolsuper, rolinherit, rolcreaterole,
            rolcreatedb, rolreplication, rolbypassrls
     FROM pg_roles WHERE rolname = current_user"
)->fetch();
assertPropertyCoreGate3(
    $role &&
    $role['rolcanlogin'] === true &&
    $role['rolsuper'] === false &&
    $role['rolinherit'] === false &&
    $role['rolcreaterole'] === false &&
    $role['rolcreatedb'] === false &&
    $role['rolreplication'] === false &&
    $role['rolbypassrls'] === false,
    'The service role must retain its frozen least-privilege attributes.'
);

foreach (['inventory', 'assets'] as $table) {
    foreach (['SELECT', 'INSERT', 'UPDATE', 'DELETE'] as $privilege) {
        assertPropertyCoreGate3(
            (bool) $pdo->query(
                "SELECT has_table_privilege(
                    current_user,
                    'public.{$table}',
                    '{$privilege}'
                )"
            )->fetchColumn(),
            "The service role must have intended {$privilege} on {$table}."
        );
    }
}
foreach (['procurement', 'users', 'login_attempts', 'security_events'] as $table) {
    assertPropertyCoreGate3(
        !(bool) $pdo->query(
            "SELECT has_table_privilege(
                current_user,
                'public.{$table}',
                'SELECT'
            )"
        )->fetchColumn(),
        "The service role must not read {$table}."
    );
}
foreach (['maintenance', 'audits'] as $table) {
    assertPropertyCoreGate3(
        (bool) $pdo->query(
            "SELECT has_table_privilege(
                current_user,
                'public.{$table}',
                'SELECT'
            )"
        )->fetchColumn(),
        "The service role must read {$table} for lifecycle eligibility."
    );
    foreach (['INSERT', 'UPDATE', 'DELETE'] as $privilege) {
        assertPropertyCoreGate3(
            !(bool) $pdo->query(
                "SELECT has_table_privilege(
                    current_user,
                    'public.{$table}',
                    '{$privilege}'
                )"
            )->fetchColumn(),
            "The service role must not {$privilege} {$table}."
        );
    }
}

assertPropertyCoreGate3(
    (bool) $pdo->query(
        "SELECT has_table_privilege(
            current_user,
            'public.property_events',
            'INSERT'
        )"
    )->fetchColumn(),
    'The service role must have append-only Property Event access.'
);
foreach (['SELECT', 'UPDATE', 'DELETE'] as $privilege) {
    assertPropertyCoreGate3(
        !(bool) $pdo->query(
            "SELECT has_table_privilege(
                current_user,
                'public.property_events',
                '{$privilege}'
            )"
        )->fetchColumn(),
        "Property Events must deny {$privilege} to the service role."
    );
}
foreach (
    [
        'inventory_id_seq',
        'inventory_id_seq1',
        'asset_id_seq',
        'assets_id_seq',
        'property_events_id_seq',
    ] as $sequence
) {
    assertPropertyCoreGate3(
        (bool) $pdo->query(
            "SELECT has_sequence_privilege(
                current_user,
                'public.{$sequence}',
                'USAGE'
            )"
        )->fetchColumn(),
        "The service role must have USAGE on {$sequence}."
    );
}
assertPropertyCoreGate3(
    !(bool) $pdo->query(
        "SELECT has_sequence_privilege(
            current_user,
            'public.users_id_seq',
            'USAGE'
        )"
    )->fetchColumn(),
    'The service role must not use unrelated sequences.'
);
$eventPolicy = $pdo->query(
    "SELECT with_check
     FROM pg_policies
     WHERE schemaname = 'public'
       AND tablename = 'property_events'
       AND policyname = 'property_core_events_insert'"
)->fetchColumn();
assertPropertyCoreGate3(
    is_string($eventPolicy) &&
    str_contains($eventPolicy, 'Inventory') &&
    str_contains($eventPolicy, 'Asset Registry') &&
    !str_contains($eventPolicy, 'Procurement'),
    'Live Event RLS must allow only Inventory and Asset Registry modules.'
);

try {
    $pdo->query('SELECT employee_id FROM users LIMIT 1');
    assertPropertyCoreGate3(
        false,
        'The database must reject an actual out-of-bound Users read.'
    );
} catch (PDOException $error) {
    assertPropertyCoreGate3(
        $error->getCode() === '42501',
        'Out-of-bound Users reads must fail with insufficient privilege.'
    );
}

try {
    $pdo->beginTransaction();
    $pdo->exec('UPDATE maintenance SET id = id WHERE false');
    assertPropertyCoreGate3(
        false,
        'The database must reject an actual Maintenance write attempt.'
    );
} catch (PDOException $error) {
    assertPropertyCoreGate3(
        $error->getCode() === '42501',
        'Maintenance writes must fail with insufficient privilege.'
    );
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

$inventory = $repository->listInventory(['per_page' => 500]);
assertPropertyCoreGate3(
    $inventory['pagination']['per_page'] === 50,
    'Inventory pagination must cap page size at 50.'
);
assertPropertyCoreGate3(
    count($inventory['records']) <= 50,
    'Inventory responses must be bounded.'
);
assertPropertyCoreGate3(
    !array_key_exists('id', $inventory['records'][0] ?? []),
    'Inventory lists must not expose internal row IDs.'
);
$firstInventory = $inventory['records'][0] ?? null;
if ($firstInventory !== null) {
    $filtered = $repository->listInventory([
        'search' => $firstInventory['inventory_id'],
        'category' => $firstInventory['category'],
    ]);
    assertPropertyCoreGate3(
        $filtered['pagination']['total'] >= 1,
        'Inventory search and category filters must combine with AND.'
    );
    assertPropertyCoreGate3(
        $repository->findInventory(
            $firstInventory['inventory_id']
        )['inventory_id'] === $firstInventory['inventory_id'],
        'Inventory detail must resolve by business ID.'
    );
}
assertPropertyCoreGate3(
    array_key_exists('total_quantity', $repository->inventorySummary()),
    'Inventory summary must be available.'
);
assertPropertyCoreGate3(
    $repository->inventoryOptions(['search' => 'x'])['options'] === [],
    'Inventory options must require at least two search characters.'
);

$assets = $repository->listAssets(['per_page' => 500]);
assertPropertyCoreGate3(
    $assets['pagination']['per_page'] === 50 &&
    count($assets['records']) <= 50,
    'Asset responses must use bounded server pagination.'
);
assertPropertyCoreGate3(
    !array_key_exists('id', $assets['records'][0] ?? []),
    'Asset lists must not expose internal row IDs.'
);
$firstAsset = $assets['records'][0] ?? null;
if ($firstAsset !== null) {
    $filtered = $repository->listAssets([
        'search' => $firstAsset['asset_id'],
        'category' => $firstAsset['category'],
        'status' => $firstAsset['status'],
    ]);
    assertPropertyCoreGate3(
        $filtered['pagination']['total'] >= 1,
        'Asset search, category, and status filters must combine with AND.'
    );
    assertPropertyCoreGate3(
        $repository->findAsset(
            $firstAsset['asset_id']
        )['asset_id'] === $firstAsset['asset_id'],
        'Asset detail must resolve by business ID.'
    );
}
assertPropertyCoreGate3(
    array_key_exists('by_category', $repository->assetSummary()),
    'Asset summary must be available.'
);
assertPropertyCoreGate3(
    count($repository->assetFilters()['statuses']) === 5,
    'Asset filters must expose the frozen status set.'
);
assertPropertyCoreGate3(
    $repository->assetSuggestions([
        'field' => 'brand',
        'search' => 'x',
    ])['suggestions'] === [],
    'Asset suggestions must require at least two search characters.'
);

try {
    $repository->nextAssetBusinessId();
    assertPropertyCoreGate3(false, 'Gate 3 mutations must fail closed.');
} catch (PropertyCoreDomainException $error) {
    assertPropertyCoreGate3(
        $error->getDomainCode() === 'PROPERTY_CORE_READ_ONLY_GATE',
        'Gate 3 mutations must use the stable read-only error.'
    );
}

$token = getPropertyCoreServiceToken();
$baseUrl = 'http://127.0.0.1:8102';
$health = propertyCoreGate3Http('GET', $baseUrl . '/health');
assertPropertyCoreGate3(
    $health['status'] === 200 &&
    $health['body']['data']['data_access'] === 'read-only',
    'The live health endpoint must identify the read-only gate.'
);
$missing = propertyCoreGate3Http(
    'GET',
    $baseUrl . '/api/v1/inventory'
);
assertPropertyCoreGate3(
    $missing['status'] === 401,
    'The live service must reject missing bearer credentials.'
);
$wrong = propertyCoreGate3Http(
    'GET',
    $baseUrl . '/api/v1/assets',
    str_repeat('x', 48)
);
assertPropertyCoreGate3(
    $wrong['status'] === 401,
    'The live service must reject an incorrect bearer credential.'
);
$valid = propertyCoreGate3Http(
    'GET',
    $baseUrl . '/api/v1/inventory?per_page=500',
    $token
);
assertPropertyCoreGate3(
    $valid['status'] === 200 &&
    $valid['body']['data']['pagination']['per_page'] === 50,
    'Authenticated live Inventory reads must be bounded.'
);
$validAssets = propertyCoreGate3Http(
    'GET',
    $baseUrl . '/api/v1/assets?per_page=1',
    $token
);
assertPropertyCoreGate3(
    $validAssets['status'] === 200 &&
    count($validAssets['body']['data']['records']) <= 1,
    'Authenticated live Asset reads must honor pagination.'
);
$inventorySummaryHttp = propertyCoreGate3Http(
    'GET',
    $baseUrl . '/api/v1/inventory/summary',
    $token
);
assertPropertyCoreGate3(
    $inventorySummaryHttp['status'] === 200 &&
    isset($inventorySummaryHttp['body']['data']['total_quantity']),
    'The live Inventory summary endpoint must be available.'
);
$assetSummaryHttp = propertyCoreGate3Http(
    'GET',
    $baseUrl . '/api/v1/assets/summary',
    $token
);
assertPropertyCoreGate3(
    $assetSummaryHttp['status'] === 200 &&
    isset($assetSummaryHttp['body']['data']['by_category']),
    'The live Asset summary endpoint must be available.'
);
$filtersHttp = propertyCoreGate3Http(
    'GET',
    $baseUrl . '/api/v1/assets/filters',
    $token
);
assertPropertyCoreGate3(
    $filtersHttp['status'] === 200 &&
    count($filtersHttp['body']['data']['statuses']) === 5,
    'The live Asset filter endpoint must expose bounded values.'
);
$suggestionsHttp = propertyCoreGate3Http(
    'GET',
    $baseUrl . '/api/v1/assets/suggestions?field=brand&search=zz',
    $token
);
assertPropertyCoreGate3(
    $suggestionsHttp['status'] === 200 &&
    is_array($suggestionsHttp['body']['data']['suggestions']),
    'The live Asset suggestion endpoint must return a bounded list.'
);
if ($firstInventory !== null) {
    $inventoryDetailHttp = propertyCoreGate3Http(
        'GET',
        $baseUrl . '/api/v1/inventory/' .
            rawurlencode($firstInventory['inventory_id']),
        $token
    );
    assertPropertyCoreGate3(
        $inventoryDetailHttp['status'] === 200 &&
        $inventoryDetailHttp['body']['data']['inventory_id'] ===
            $firstInventory['inventory_id'],
        'The live Inventory detail endpoint must use business IDs.'
    );
    $inventoryOptionsHttp = propertyCoreGate3Http(
        'GET',
        $baseUrl . '/api/v1/inventory/options?search=' .
            rawurlencode(substr($firstInventory['asset_name'], 0, 2)),
        $token
    );
    assertPropertyCoreGate3(
        $inventoryOptionsHttp['status'] === 200 &&
        count($inventoryOptionsHttp['body']['data']['options']) <= 10,
        'The live Inventory options endpoint must remain bounded.'
    );
}
if ($firstAsset !== null) {
    $assetDetailHttp = propertyCoreGate3Http(
        'GET',
        $baseUrl . '/api/v1/assets/' .
            rawurlencode($firstAsset['asset_id']),
        $token
    );
    assertPropertyCoreGate3(
        $assetDetailHttp['status'] === 200 &&
        $assetDetailHttp['body']['data']['asset_id'] ===
            $firstAsset['asset_id'],
        'The live Asset detail endpoint must use business IDs.'
    );
    $assetSearchHttp = propertyCoreGate3Http(
        'GET',
        $baseUrl . '/api/v1/assets?search=' .
            rawurlencode($firstAsset['asset_id']) .
            '&status=' . rawurlencode($firstAsset['status']),
        $token
    );
    assertPropertyCoreGate3(
        $assetSearchHttp['status'] === 200 &&
        $assetSearchHttp['body']['data']['pagination']['total'] >= 1,
        'The live Asset search and dropdown filters must combine.'
    );
    $assetOptionsHttp = propertyCoreGate3Http(
        'GET',
        $baseUrl . '/api/v1/assets/options?search=' .
            rawurlencode(substr($firstAsset['asset_id'], 0, 3)),
        $token
    );
    assertPropertyCoreGate3(
        $assetOptionsHttp['status'] === 200 &&
        count($assetOptionsHttp['body']['data']['options']) <= 10,
        'The live Asset options endpoint must remain bounded.'
    );
}
$invalidFilter = propertyCoreGate3Http(
    'GET',
    $baseUrl . '/api/v1/assets?status=Invalid',
    $token
);
assertPropertyCoreGate3(
    $invalidFilter['status'] === 400 &&
    $invalidFilter['body']['error']['code'] ===
        'INVALID_ASSET_STATUS_FILTER',
    'Invalid Asset filters must use a stable client error.'
);
$write = propertyCoreGate3Http(
    'POST',
    $baseUrl . '/api/v1/assets/next-id',
    $token,
    'admin'
);
assertPropertyCoreGate3(
    $write['status'] === 503 &&
    $write['body']['error']['code'] === 'PROPERTY_CORE_READ_ONLY_GATE',
    'Authenticated mutation routes must remain disabled in Gate 3.'
);
$writeMissingToken = propertyCoreGate3Http(
    'POST',
    $baseUrl . '/api/v1/assets/next-id',
    null,
    'admin'
);
assertPropertyCoreGate3(
    $writeMissingToken['status'] === 401,
    'Lifecycle routes must reject a missing Bearer token.'
);
$writeWrongToken = propertyCoreGate3Http(
    'POST',
    $baseUrl . '/api/v1/assets/next-id',
    str_repeat('x', 48),
    'admin'
);
assertPropertyCoreGate3(
    $writeWrongToken['status'] === 401,
    'Lifecycle routes must reject an invalid Bearer token.'
);

echo "Property Core Gate 3 operational tests passed: {$testsRun}" .
    PHP_EOL;
