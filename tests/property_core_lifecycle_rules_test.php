<?php

require_once dirname(__DIR__) .
    '/services/property_core/src/PropertyCoreLifecycleRules.php';
require_once dirname(__DIR__) .
    '/services/property_core/src/PropertyCoreTransactionRunner.php';

$testsRun = 0;

function assertPropertyCoreLifecycle(bool $condition, string $message): void
{
    global $testsRun;
    $testsRun++;

    if (!$condition) {
        throw new RuntimeException('FAILED: ' . $message);
    }
}

function expectPropertyCoreLifecycleError(
    callable $operation,
    string $code,
    string $message
): void {
    try {
        $operation();
        assertPropertyCoreLifecycle(false, $message);
    } catch (PropertyCoreDomainException $error) {
        assertPropertyCoreLifecycle(
            $error->getDomainCode() === $code,
            $message
        );
    }
}

$inventory = PropertyCoreLifecycleRules::inventoryInput([
    'inventory_id' => 'INV-000101',
    'asset_name' => 'Laptop',
    'category' => 'Computer',
    'quantity' => '2',
    'condition' => 'Good',
], true);
assertPropertyCoreLifecycle(
    $inventory['quantity'] === 2 &&
    $inventory['inventory_id'] === 'INV-000101',
    'Valid Inventory creation input must normalize.'
);
assertPropertyCoreLifecycle(
    PropertyCoreLifecycleRules::inventoryInput([
        'asset_name' => 'Laptop',
        'category' => 'Computer',
        'quantity' => '0',
        'condition' => 'Good',
    ], false)['quantity'] === 0,
    'Inventory updates may set quantity to zero.'
);
expectPropertyCoreLifecycleError(
    fn () => PropertyCoreLifecycleRules::inventoryInput([
        'inventory_id' => 'INV-000102',
        'asset_name' => 'Laptop',
        'category' => 'Computer',
        'quantity' => '0',
        'condition' => 'Good',
    ], true),
    'INVALID_INVENTORY_QUANTITY',
    'New Inventory must reject zero quantity.'
);
expectPropertyCoreLifecycleError(
    fn () => PropertyCoreLifecycleRules::inventoryInput([
        'inventory_id' => 'bad',
        'asset_name' => 'Laptop',
        'category' => 'Computer',
        'quantity' => '1',
        'condition' => 'Good',
    ], true),
    'INVALID_INVENTORY_ID',
    'Inventory must reject malformed business IDs.'
);
expectPropertyCoreLifecycleError(
    fn () => PropertyCoreLifecycleRules::inventoryInput([
        'inventory_id' => 'INV-000102',
        'asset_name' => '',
        'category' => 'Computer',
        'quantity' => '1',
        'condition' => 'Good',
    ], true),
    'INVALID_PROPERTY_CORE_INPUT',
    'Inventory must reject missing required fields.'
);

$registration = PropertyCoreLifecycleRules::assetRegistrationInput([
    'asset_id' => 'AST-000101',
    'inventory_id' => 'INV-000101',
    'brand' => 'Example',
    'model' => 'Model',
    'serial_number' => 'SERIAL-1',
    'acquisition_date' => '2026-08-29',
    'purchase_cost' => '1000.50',
    'supplier' => 'Supplier',
    'location' => 'ICT Office',
    'remarks' => '',
    'asset_name' => 'Untrusted posted name',
    'category' => 'Untrusted posted category',
]);
assertPropertyCoreLifecycle(
    $registration['purchase_cost'] === 1000.50,
    'Valid Asset registration input must normalize purchase cost.'
);
assertPropertyCoreLifecycle(
    !array_key_exists('asset_name', $registration) &&
    !array_key_exists('category', $registration),
    'Registration must not trust posted Asset name or category.'
);
expectPropertyCoreLifecycleError(
    fn () => PropertyCoreLifecycleRules::assetRegistrationInput([
        'asset_id' => 'AST-000102',
        'inventory_id' => 'INV-000101',
        'acquisition_date' => '2026-02-30',
        'purchase_cost' => '1',
    ]),
    'ACQUISITION_DATE_REQUIRED',
    'Registration must reject impossible acquisition dates.'
);
expectPropertyCoreLifecycleError(
    fn () => PropertyCoreLifecycleRules::assetRegistrationInput([
        'asset_id' => 'AST-000102',
        'inventory_id' => 'INV-000101',
        'acquisition_date' => '2026-08-29',
        'purchase_cost' => '-1',
    ]),
    'INVALID_PURCHASE_COST',
    'Registration must reject negative purchase cost.'
);
expectPropertyCoreLifecycleError(
    fn () => PropertyCoreLifecycleRules::assetRegistrationInput([
        'asset_id' => 'bad',
        'inventory_id' => 'INV-000101',
        'acquisition_date' => '2026-08-29',
        'purchase_cost' => '1',
    ]),
    'INVALID_ASSET_ID',
    'Registration must reject malformed Asset IDs.'
);
expectPropertyCoreLifecycleError(
    fn () => PropertyCoreLifecycleRules::assetRegistrationInput([
        'asset_id' => 'AST-000102',
        'inventory_id' => 'bad',
        'acquisition_date' => '2026-08-29',
        'purchase_cost' => '1',
    ]),
    'INVALID_INVENTORY_ID',
    'Registration must reject malformed Inventory IDs.'
);

$assignment = PropertyCoreLifecycleRules::assignmentInput([
    'employee_id' => 'EMP-001',
    'custodian' => 'John',
    'department' => 'ICT',
    'date_assigned' => '2026-08-29',
]);
assertPropertyCoreLifecycle(
    $assignment['custodian'] === 'John',
    'Valid assignment input must normalize.'
);
expectPropertyCoreLifecycleError(
    fn () => PropertyCoreLifecycleRules::assignmentInput([
        'employee_id' => 'EMP-001',
        'custodian' => '',
        'department' => 'ICT',
        'date_assigned' => '2026-08-29',
    ]),
    'INVALID_PROPERTY_CORE_INPUT',
    'Assignment must require a Custodian.'
);
expectPropertyCoreLifecycleError(
    fn () => PropertyCoreLifecycleRules::assignmentInput([
        'employee_id' => 'EMP-001',
        'custodian' => 'John',
        'department' => 'ICT',
        'date_assigned' => 'bad',
    ]),
    'ASSIGNMENT_DATE_REQUIRED',
    'Assignment must require a valid date.'
);

$available = ['status' => 'Available'];
$assigned = ['status' => 'Assigned'];
$maintenance = ['status' => 'Under Maintenance'];
$lost = ['status' => 'Lost'];
PropertyCoreLifecycleRules::assertAssignable($available, false);
assertPropertyCoreLifecycle(true, 'Available Assets must be assignable.');
expectPropertyCoreLifecycleError(
    fn () => PropertyCoreLifecycleRules::assertAssignable($assigned, false),
    'ASSET_NOT_ASSIGNABLE',
    'Assigned Assets must not be assigned twice.'
);
expectPropertyCoreLifecycleError(
    fn () => PropertyCoreLifecycleRules::assertAssignable($maintenance, false),
    'ASSET_UNDER_MAINTENANCE',
    'Assets under Maintenance must not be assigned.'
);
expectPropertyCoreLifecycleError(
    fn () => PropertyCoreLifecycleRules::assertAssignable($available, true),
    'ASSET_UNDER_MAINTENANCE',
    'Active Maintenance must block assignment.'
);
expectPropertyCoreLifecycleError(
    fn () => PropertyCoreLifecycleRules::assertAssignable($lost, false),
    'LOST_ASSET_CHANGE_FORBIDDEN',
    'Lost Assets must not be assigned.'
);

PropertyCoreLifecycleRules::assertReturnable($assigned, false);
assertPropertyCoreLifecycle(true, 'Assigned Assets must be returnable.');
expectPropertyCoreLifecycleError(
    fn () => PropertyCoreLifecycleRules::assertReturnable($available, false),
    'ASSET_NOT_RETURNABLE',
    'Available Assets must not be returned again.'
);
expectPropertyCoreLifecycleError(
    fn () => PropertyCoreLifecycleRules::assertReturnable($assigned, true),
    'ASSET_UNDER_MAINTENANCE',
    'Active Maintenance must block returns.'
);
expectPropertyCoreLifecycleError(
    fn () => PropertyCoreLifecycleRules::assertReturnable($lost, false),
    'LOST_ASSET_CHANGE_FORBIDDEN',
    'Lost Assets must not be returned.'
);

PropertyCoreLifecycleRules::assertDeletable($available, false, false);
assertPropertyCoreLifecycle(true, 'Available Assets without history may be deleted.');
expectPropertyCoreLifecycleError(
    fn () => PropertyCoreLifecycleRules::assertDeletable(
        $assigned,
        false,
        false
    ),
    'ASSIGNED_ASSET_DELETE_FORBIDDEN',
    'Assigned Assets must not be deleted.'
);
expectPropertyCoreLifecycleError(
    fn () => PropertyCoreLifecycleRules::assertDeletable(
        $maintenance,
        false,
        false
    ),
    'ASSET_UNDER_MAINTENANCE',
    'Assets under Maintenance must not be deleted.'
);
expectPropertyCoreLifecycleError(
    fn () => PropertyCoreLifecycleRules::assertDeletable(
        $available,
        true,
        false
    ),
    'ASSET_UNDER_MAINTENANCE',
    'Active Maintenance must block deletion.'
);
expectPropertyCoreLifecycleError(
    fn () => PropertyCoreLifecycleRules::assertDeletable($lost, false, false),
    'LOST_ASSET_CHANGE_FORBIDDEN',
    'Lost Assets must not be deleted.'
);
expectPropertyCoreLifecycleError(
    fn () => PropertyCoreLifecycleRules::assertDeletable($available, false, true),
    'ASSET_HISTORY_DELETE_FORBIDDEN',
    'Maintenance or Audit history must block deletion.'
);

$active = false;
$log = [];
$runner = new PropertyCoreTransactionRunner(
    function () use (&$active, &$log): bool {
        $active = true;
        $log[] = 'begin';
        return true;
    },
    function () use (&$active, &$log): bool {
        $active = false;
        $log[] = 'commit';
        return true;
    },
    function () use (&$active, &$log): bool {
        $active = false;
        $log[] = 'rollback';
        return true;
    },
    function () use (&$active): bool {
        return $active;
    }
);
$value = $runner->run(function () use (&$log): string {
    $log[] = 'asset-insert';
    $log[] = 'inventory-update';
    $log[] = 'event-insert';
    return 'committed';
});
assertPropertyCoreLifecycle(
    $value === 'committed' &&
    $log === [
        'begin',
        'asset-insert',
        'inventory-update',
        'event-insert',
        'commit',
    ],
    'Successful lifecycle work must commit once.'
);

$active = false;
$log = [];
$runner = new PropertyCoreTransactionRunner(
    function () use (&$active, &$log): bool {
        $active = true;
        $log[] = 'begin';
        return true;
    },
    function () use (&$active, &$log): bool {
        $active = false;
        $log[] = 'commit';
        return true;
    },
    function () use (&$active, &$log): bool {
        $active = false;
        $log[] = 'rollback';
        return true;
    },
    function () use (&$active): bool {
        return $active;
    }
);
try {
    $runner->run(function () use (&$log): void {
        $log[] = 'asset-insert';
        throw new RuntimeException('Injected deterministic test failure.');
    });
    assertPropertyCoreLifecycle(false, 'Injected failure must be rethrown.');
} catch (RuntimeException $error) {
    assertPropertyCoreLifecycle(
        $error->getMessage() === 'Injected deterministic test failure.',
        'Injected failure must remain visible to the caller.'
    );
}
assertPropertyCoreLifecycle(
    $log === ['begin', 'asset-insert', 'rollback'] && !$active,
    'Failure after a simulated Asset insert must roll back completely.'
);

$rollbackScenarios = [
    'registration_after_asset_insert' => [
        'inventory' => 1,
        'assets' => 0,
        'events' => 0,
        'operations' => ['asset_insert'],
    ],
    'registration_before_event' => [
        'inventory' => 1,
        'assets' => 0,
        'events' => 0,
        'operations' => ['asset_insert', 'inventory_decrement'],
    ],
    'deletion_before_restore' => [
        'inventory' => 0,
        'assets' => 1,
        'events' => 0,
        'operations' => ['asset_delete'],
    ],
    'assignment_before_event' => [
        'inventory' => 1,
        'assets' => 1,
        'events' => 0,
        'status' => 'Available',
        'operations' => ['asset_assign'],
    ],
    'return_before_event' => [
        'inventory' => 1,
        'assets' => 1,
        'events' => 0,
        'status' => 'Assigned',
        'operations' => ['asset_return'],
    ],
];

foreach ($rollbackScenarios as $name => $scenario) {
    $state = $scenario;
    unset($state['operations']);
    $initialState = $state;
    $snapshot = [];
    $active = false;
    $runner = new PropertyCoreTransactionRunner(
        function () use (&$active, &$snapshot, &$state): bool {
            $snapshot = $state;
            $active = true;
            return true;
        },
        function () use (&$active): bool {
            $active = false;
            return true;
        },
        function () use (&$active, &$snapshot, &$state): bool {
            $state = $snapshot;
            $active = false;
            return true;
        },
        function () use (&$active): bool {
            return $active;
        }
    );

    try {
        $runner->run(function () use (&$state, $scenario): void {
            foreach ($scenario['operations'] as $operation) {
                match ($operation) {
                    'asset_insert' => $state['assets']++,
                    'inventory_decrement' => $state['inventory']--,
                    'asset_delete' => $state['assets']--,
                    'asset_assign' => $state['status'] = 'Assigned',
                    'asset_return' => $state['status'] = 'Available',
                };
            }
            throw new RuntimeException('Injected lifecycle failure.');
        });
    } catch (RuntimeException $error) {
        // Expected deterministic failure.
    }

    assertPropertyCoreLifecycle(
        $state === $initialState && !$active,
        "Rollback scenario {$name} must restore the complete initial state."
    );
}

echo "Property Core lifecycle rule tests passed: {$testsRun}" . PHP_EOL;
