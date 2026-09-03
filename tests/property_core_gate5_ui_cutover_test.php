<?php

$testsRun = 0;

function assertPropertyCoreGate5(bool $condition, string $message): void
{
    global $testsRun;
    $testsRun++;
    if (!$condition) {
        throw new RuntimeException('FAILED: ' . $message);
    }
}

function gate5Source(string $root, string $path): string
{
    $source = file_get_contents($root . '/' . $path);
    if ($source === false) {
        throw new RuntimeException('Could not read ' . $path);
    }
    return $source;
}

$root = dirname(__DIR__);
$inventoryIndex = gate5Source($root, 'modules/inventory/index.php');
$inventoryTable = gate5Source($root, 'modules/inventory/inventory_table.php');
$inventoryJs = gate5Source($root, 'assets/js/inventory.js');
$assetIndex = gate5Source($root, 'modules/asset_registry/index.php');
$assetTable = gate5Source($root, 'modules/asset_registry/asset_table.php');
$assetForm = gate5Source($root, 'modules/asset_registry/asset_form.php');
$assetJs = gate5Source($root, 'assets/js/asset_registry.js');
$repository = gate5Source(
    $root,
    'services/property_core/src/PropertyCoreRepository.php'
);
$example = gate5Source($root, 'services/property_core/.env.example');

assertPropertyCoreGate5(
    str_contains($inventoryIndex, 'getPropertyCoreServiceClient()') &&
    str_contains($inventoryIndex, 'listInventory(') &&
    str_contains($inventoryIndex, '$inventoryFilters') &&
    str_contains($inventoryIndex, "'per_page' => 25"),
    'Inventory page must use one paged Property Core list request.'
);
assertPropertyCoreGate5(
    str_contains($inventoryTable, 'data-inventory-id=') &&
    !str_contains($inventoryTable, 'data-record=') &&
    !str_contains($inventoryTable, 'json_encode($inventory'),
    'Inventory rows must expose only their business ID, not full records.'
);
assertPropertyCoreGate5(
    str_contains($inventoryTable, 'total_pages') &&
    str_contains($inventoryTable, 'Showing') &&
    str_contains($inventoryTable, 'Previous') &&
    str_contains($inventoryTable, 'Next'),
    'Inventory UI must provide server-side paging and a result count.'
);
assertPropertyCoreGate5(
    str_contains($inventoryJs, 'view_inventory.php?') &&
    str_contains($inventoryJs, "format: 'json'") &&
    !str_contains($inventoryJs, 'JSON.parse(row.dataset'),
    'Inventory detail must load on demand instead of reading embedded payloads.'
);

assertPropertyCoreGate5(
    str_contains($assetIndex, 'listAssets($assetFilters)') &&
    str_contains($assetIndex, 'assetFilters()') &&
    str_contains($assetIndex, "'per_page' => 25"),
    'Asset Registry must use server-side list and filter contracts.'
);
assertPropertyCoreGate5(
    str_contains($assetTable, 'data-asset-id=') &&
    !str_contains($assetTable, 'data-asset=') &&
    !str_contains($assetTable, 'json_encode($asset'),
    'Asset rows must expose only their business ID, not full records.'
);
assertPropertyCoreGate5(
    str_contains($assetTable, 'total_pages') &&
    str_contains($assetTable, 'Showing') &&
    str_contains($assetTable, 'asset_id=') &&
    !str_contains($assetTable, 'name="id"'),
    'Asset list and actions must use paged business-ID navigation.'
);
assertPropertyCoreGate5(
    str_contains($repository, "'asset_id, asset_name, category, custodian, status'") &&
    !str_contains(
        substr(
            $repository,
            strpos($repository, 'private const ASSET_LIST_COLUMNS'),
            180
        ),
        'serial_number'
    ),
    'Asset list projection must stay minimal; full detail is on demand.'
);
assertPropertyCoreGate5(
    str_contains($assetJs, "view_asset.php?") &&
    str_contains($assetJs, "format: 'json'") &&
    !str_contains($assetJs, 'JSON.parse(row.dataset'),
    'Asset detail and action modals must load one record on demand.'
);

assertPropertyCoreGate5(
    str_contains($assetForm, 'inventoryItemSearch') &&
    str_contains($assetForm, 'name="inventory_id"') &&
    !str_contains($assetForm, '<select name="inventory_id"'),
    'Asset registration must use a bounded Inventory typeahead.'
);
assertPropertyCoreGate5(
    str_contains($assetJs, 'query.length < 2') &&
    str_contains($assetJs, 'AbortController') &&
    str_contains($assetJs, '}, 250)') &&
    str_contains($assetJs, 'inventory_options.php?'),
    'Inventory selector must enforce two characters, debounce, and cancel stale requests.'
);
assertPropertyCoreGate5(
    str_contains(
        gate5Source($root, 'modules/asset_registry/inventory_options.php'),
        "'limit' => 10"
    ) &&
    str_contains(
        gate5Source($root, 'modules/asset_registry/inventory_options.php'),
        'inventoryOptions('
    ),
    'Inventory option endpoint must be service-backed and capped at 10.'
);

$writeRoutes = [
    'modules/inventory/save_inventory.php' => ['createInventory(', 'updateInventory('],
    'modules/inventory/delete_inventory.php' => ['deleteInventory('],
    'modules/asset_registry/register_asset.php' => ['registerAsset('],
    'modules/asset_registry/update_asset.php' => ['updateAsset('],
    'modules/asset_registry/save_assignment.php' => ['assignAsset('],
    'modules/asset_registry/return_asset.php' => ['returnAsset('],
    'modules/asset_registry/delete_asset.php' => ['deleteAsset('],
];

foreach ($writeRoutes as $path => $serviceCalls) {
    $source = gate5Source($root, $path);
    assertPropertyCoreGate5(
        str_contains($source, 'check_auth.php') &&
        str_contains($source, 'requireValidAccessCsrfPost()'),
        $path . ' must preserve authentication and POST CSRF validation.'
    );
    assertPropertyCoreGate5(
        array_reduce(
            $serviceCalls,
            static fn (bool $found, string $call): bool =>
                $found || str_contains($source, $call),
            false
        ),
        $path . ' must call its Property Core lifecycle endpoint.'
    );
    assertPropertyCoreGate5(
        !preg_match(
            '/\b(?:SELECT|INSERT\s+INTO|UPDATE\s+(?:assets|inventory)|DELETE\s+FROM)\b/i',
            $source
        ) &&
        !str_contains($source, 'getDbConnection') &&
        !str_contains($source, 'includes/database.php'),
        $path . ' must not retain a direct database fallback.'
    );
}

$normalUiFiles = array_merge(
    glob($root . '/modules/inventory/*.php') ?: [],
    glob($root . '/modules/asset_registry/*.php') ?: []
);
foreach ($normalUiFiles as $path) {
    $source = file_get_contents($path) ?: '';
    assertPropertyCoreGate5(
        !str_contains($source, 'getDbConnection') &&
        !str_contains($source, 'includes/database.php'),
        basename($path) . ' must not access the database directly.'
    );
}

$browserSources = $inventoryJs . $assetJs . $inventoryTable . $assetTable;
assertPropertyCoreGate5(
    !str_contains($browserSources, 'PROPERTY_CORE_SERVICE_TOKEN') &&
    !str_contains($browserSources, 'Authorization: Bearer'),
    'The internal service token must never reach browser code or table markup.'
);
assertPropertyCoreGate5(
    preg_match('/^PROPERTY_CORE_ALLOW_WRITES=false$/m', $example) === 1,
    'Property Core production write capability must remain fail-closed by default.'
);

echo "Property Core Gate 5 UI cutover tests passed: {$testsRun}" . PHP_EOL;
