<?php

$testsRun = 0;

function assertPropertyCoreGate4(bool $condition, string $message): void
{
    global $testsRun;
    $testsRun++;

    if (!$condition) {
        throw new RuntimeException('FAILED: ' . $message);
    }
}

function methodSource(
    string $source,
    string $start,
    string $end
): string {
    $startAt = strpos($source, $start);
    $endAt = strpos($source, $end, $startAt === false ? 0 : $startAt + 1);

    if ($startAt === false || $endAt === false) {
        throw new RuntimeException('Could not isolate method source.');
    }

    return substr($source, $startAt, $endAt - $startAt);
}

$root = dirname(__DIR__);
$repository = file_get_contents(
    $root . '/services/property_core/src/PropertyCoreRepository.php'
);
$rules = file_get_contents(
    $root . '/services/property_core/src/PropertyCoreLifecycleRules.php'
);
$runner = file_get_contents(
    $root . '/services/property_core/src/PropertyCoreTransactionRunner.php'
);
$config = file_get_contents($root . '/services/property_core/config.php');
$front = file_get_contents(
    $root . '/services/property_core/public/index.php'
);
$example = file_get_contents(
    $root . '/services/property_core/.env.example'
);
$migration = file_get_contents(
    $root . '/database/microservices_v2_property_core.sql'
);

assertPropertyCoreGate4(
    preg_match('/^PROPERTY_CORE_ALLOW_WRITES=false$/m', $example) === 1 &&
    str_contains($config, "=== 'true'") &&
    str_contains($front, 'propertyCoreWritesAllowed()'),
    'Lifecycle writes must be explicitly and strictly enabled.'
);
assertPropertyCoreGate4(
    str_contains($repository, 'private readonly bool $writesAllowed = false') &&
    substr_count($repository, '$this->assertWritesAllowed();') >= 10,
    'Every lifecycle operation must fail closed by default.'
);
assertPropertyCoreGate4(
    str_contains($runner, 'beginTransaction') &&
    str_contains($runner, 'commit') &&
    str_contains($runner, 'rollBack') &&
    str_contains($runner, 'catch (Throwable $error)'),
    'All lifecycle work must use a rollback-safe transaction runner.'
);

$registration = methodSource(
    $repository,
    'public function registerAsset',
    'public function updateAsset'
);
$registrationLock = strpos($registration, 'FROM inventory');
$registrationInsert = strpos($registration, 'INSERT INTO assets');
$registrationDecrement = strpos($registration, 'quantity = quantity - 1');
$registrationAssetEvent = strpos($registration, "'Registered'");
$registrationInventoryEvent = strpos($registration, "'Stock Decreased'");
assertPropertyCoreGate4(
    $registrationLock !== false &&
    str_contains($registration, 'FOR UPDATE') &&
    $registrationInsert > $registrationLock &&
    $registrationDecrement > $registrationInsert &&
    $registrationAssetEvent > $registrationDecrement &&
    $registrationInventoryEvent > $registrationAssetEvent,
    'Registration must lock stock, insert one Asset, decrement once, then record both events.'
);
assertPropertyCoreGate4(
    str_contains($registration, 'quantity > 0') &&
    str_contains($registration, 'rowCount() !== 1') &&
    str_contains($registration, 'INSUFFICIENT_INVENTORY_QUANTITY'),
    'Registration must revalidate stock and prevent negative quantity.'
);
assertPropertyCoreGate4(
    str_contains($rules, "!array_key_exists('asset_name'") === false &&
    !str_contains($registration, '$record[\'asset_name\']') &&
    !str_contains($registration, '$record[\'category\']'),
    'Registration must source Asset name and category only from locked Inventory.'
);

$deletion = methodSource(
    $repository,
    'public function deleteAsset',
    'private function pagedList'
);
$relationship = strpos($deletion, 'SELECT inventory_id FROM assets');
$inventoryLock = strpos($deletion, 'FROM inventory WHERE id');
$assetLock = strpos($deletion, '$this->lockAsset');
assertPropertyCoreGate4(
    $relationship !== false &&
    $inventoryLock > $relationship &&
    $assetLock > $inventoryLock,
    'Deletion must identify the relationship then lock Inventory before Asset.'
);
assertPropertyCoreGate4(
    str_contains($deletion, 'assertDeletable') &&
    str_contains($deletion, "'Stock Increased'") &&
    str_contains($deletion, 'quantity = quantity + 1'),
    'Eligible deletion must revalidate state and restore exactly one unit.'
);
assertPropertyCoreGate4(
    str_contains($rules, 'ASSET_HISTORY_DELETE_FORBIDDEN') &&
    str_contains($rules, 'ASSIGNED_ASSET_DELETE_FORBIDDEN') &&
    str_contains($rules, 'ASSET_UNDER_MAINTENANCE') &&
    str_contains($rules, 'LOST_ASSET_CHANGE_FORBIDDEN'),
    'Deletion must preserve all existing eligibility restrictions.'
);

$assignment = methodSource(
    $repository,
    'public function assignAsset',
    'public function returnAsset'
);
assertPropertyCoreGate4(
    str_contains($assignment, 'assertAssignable') &&
    str_contains($assignment, "status = 'Assigned'") &&
    str_contains($assignment, "'Assigned'") &&
    !str_contains($assignment, 'UPDATE inventory'),
    'Assignment must change Asset custody, record history, and leave Inventory unchanged.'
);

$return = methodSource(
    $repository,
    'public function returnAsset',
    'public function deleteAsset'
);
assertPropertyCoreGate4(
    str_contains($return, 'assertReturnable') &&
    str_contains($return, "status = 'Available'") &&
    str_contains($return, "'Returned'") &&
    !str_contains($return, 'UPDATE inventory'),
    'Return must clear custody, record history, and leave Inventory unchanged.'
);

$createInventory = methodSource(
    $repository,
    'public function createInventory',
    'public function updateInventory'
);
$updateInventory = methodSource(
    $repository,
    'public function updateInventory',
    'public function deleteInventory'
);
$deleteInventory = methodSource(
    $repository,
    'public function deleteInventory',
    'public function listAssets'
);
assertPropertyCoreGate4(
    str_contains($createInventory, 'DUPLICATE_INVENTORY_ITEM') &&
    str_contains($createInventory, "'Added'") &&
    str_contains($updateInventory, 'FOR UPDATE') &&
    str_contains($updateInventory, "'Adjusted'") &&
    str_contains($deleteInventory, 'INVENTORY_LINKED_TO_ASSETS') &&
    str_contains($deleteInventory, "'Deleted'"),
    'Inventory create, update, and eligible delete rules must be preserved.'
);

assertPropertyCoreGate4(
    str_contains($migration, 'GRANT SELECT, INSERT, UPDATE, DELETE') &&
    str_contains($migration, 'public.inventory, public.assets') &&
    str_contains($migration, 'GRANT SELECT') &&
    str_contains($migration, 'public.maintenance, public.audits') &&
    str_contains($migration, "module IN ('Inventory', 'Asset Registry')"),
    'The lifecycle engine must stay within the frozen Gate 2 privilege boundary.'
);
assertPropertyCoreGate4(
    !str_contains($repository, 'force_failure') &&
    !str_contains($repository, 'sleep(') &&
    !str_contains($repository, 'usleep('),
    'Production requests must expose no test-failure or artificial-delay hook.'
);
assertPropertyCoreGate4(
    preg_match('/SELECT\s+\*/i', $repository) !== 1,
    'Lifecycle queries must use explicit projections.'
);

echo "Property Core Gate 4 contract tests passed: {$testsRun}" . PHP_EOL;
