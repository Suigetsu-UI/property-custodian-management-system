<?php

require_once __DIR__ . '/../services/property_core/src/PropertyCoreRepository.php';

$testsRun = 0;

function assertGate4b(bool $condition, string $message): void
{
    global $testsRun;
    $testsRun++;

    if (!$condition) {
        throw new RuntimeException('FAILED: ' . $message);
    }
}

function gate4bEnv(string $name): string
{
    $value = trim((string) (getenv($name) ?: ''));

    if ($value === '') {
        throw new RuntimeException(
            'Gate 4B test configuration is incomplete: ' . $name
        );
    }

    return $value;
}

function gate4bConnection(string $userName, string $passwordName): PDO
{
    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s;sslmode=require',
        gate4bEnv('PROPERTY_CORE_TEST_DB_HOST'),
        gate4bEnv('PROPERTY_CORE_TEST_DB_PORT'),
        gate4bEnv('PROPERTY_CORE_TEST_DB_NAME')
    );

    return new PDO(
        $dsn,
        gate4bEnv($userName),
        gate4bEnv($passwordName),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}

function gate4bServiceConnection(): PDO
{
    return gate4bConnection(
        'PROPERTY_CORE_TEST_SERVICE_USER',
        'PROPERTY_CORE_TEST_SERVICE_PASSWORD'
    );
}

function gate4bObserverConnection(): PDO
{
    return gate4bConnection(
        'PROPERTY_CORE_TEST_OBSERVER_USER',
        'PROPERTY_CORE_TEST_OBSERVER_PASSWORD'
    );
}

function cleanupGate4bFixtures(PDO $pdo): void
{
    $pdo->beginTransaction();

    try {
        $pdo->exec(
            "DELETE FROM maintenance
             WHERE asset_id IN (
                SELECT id FROM assets WHERE asset_id LIKE 'AST-99%'
             )"
        );
        $pdo->exec(
            "DELETE FROM audits
             WHERE asset_id IN (
                SELECT id FROM assets WHERE asset_id LIKE 'AST-99%'
             )"
        );
        $pdo->exec("DELETE FROM assets WHERE asset_id LIKE 'AST-99%'");
        $pdo->exec(
            "DELETE FROM property_events
             WHERE business_id LIKE 'AST-99%'
                OR business_id LIKE 'INV-99%'
                OR related_business_id LIKE 'AST-99%'
                OR related_business_id LIKE 'INV-99%'"
        );
        $pdo->exec("DELETE FROM inventory WHERE inventory_id LIKE 'INV-99%'");
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function insertGate4bInventory(
    PDO $pdo,
    string $businessId,
    int $quantity
): int {
    $statement = $pdo->prepare(
        "INSERT INTO inventory (
            inventory_id, asset_name, category, quantity, condition
         ) VALUES (
            :inventory_id, :asset_name, 'GATE4B TEST', :quantity, 'Good'
         )
         RETURNING id"
    );
    $statement->execute([
        'inventory_id' => $businessId,
        'asset_name' => 'GATE4B ' . $businessId,
        'quantity' => $quantity,
    ]);
    return (int) $statement->fetchColumn();
}

function insertGate4bAsset(
    PDO $pdo,
    string $businessId,
    int $inventoryRowId,
    string $status = 'Available'
): int {
    $statement = $pdo->prepare(
        "INSERT INTO assets (
            asset_id, asset_name, category, inventory_id, status,
            location, acquisition_date, purchase_cost
         ) VALUES (
            :asset_id, :asset_name, 'GATE4B TEST', :inventory_id, :status,
            'GATE4B LAB', CURRENT_DATE, 1.00
         )
         RETURNING id"
    );
    $statement->execute([
        'asset_id' => $businessId,
        'asset_name' => 'GATE4B ' . $businessId,
        'inventory_id' => $inventoryRowId,
        'status' => $status,
    ]);
    return (int) $statement->fetchColumn();
}

function gate4bRegistrationInput(
    string $assetId,
    string $inventoryId
): array {
    return [
        'asset_id' => $assetId,
        'inventory_id' => $inventoryId,
        'brand' => 'GATE4B',
        'model' => 'Operational Test',
        'serial_number' => $assetId . '-SERIAL',
        'acquisition_date' => '2026-08-29',
        'purchase_cost' => '1.00',
        'supplier' => 'GATE4B TEST SUPPLIER',
        'location' => 'GATE4B LAB',
        'remarks' => 'Synthetic isolated integration-test fixture.',
    ];
}

function gate4bScalar(PDO $pdo, string $sql, array $params = []): mixed
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    return $statement->fetchColumn();
}

function startGate4bWorker(
    string $operation,
    string $assetId,
    string $inventoryId,
    float $releaseAt
): array {
    $command = [
        PHP_BINARY,
        __FILE__,
        '--worker',
        $operation,
        $assetId,
        $inventoryId,
        sprintf('%.6F', $releaseAt),
    ];
    $pipes = [];
    $process = proc_open(
        $command,
        [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes,
        __DIR__,
        null,
        ['bypass_shell' => true]
    );

    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start Gate 4B worker.');
    }

    fclose($pipes[0]);
    return [$process, $pipes];
}

function finishGate4bWorker(array $worker): array
{
    [$process, $pipes] = $worker;
    $stdout = trim(stream_get_contents($pipes[1]));
    $stderr = trim(stream_get_contents($pipes[2]));
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    if ($exitCode !== 0) {
        throw new RuntimeException(
            'Gate 4B worker failed: ' . ($stderr !== '' ? $stderr : $stdout)
        );
    }

    $result = json_decode($stdout, true);

    if (!is_array($result)) {
        throw new RuntimeException('Gate 4B worker returned invalid output.');
    }

    return $result;
}

function runGate4bWorkers(array $operations): array
{
    $releaseAt = microtime(true) + 1.5;
    $workers = [];

    foreach ($operations as $operation) {
        $workers[] = startGate4bWorker(
            $operation['operation'],
            $operation['asset_id'],
            $operation['inventory_id'],
            $releaseAt
        );
    }

    return array_map('finishGate4bWorker', $workers);
}

if (($argv[1] ?? '') === '--worker') {
    try {
        $pdo = gate4bServiceConnection();
        $repository = new PropertyCoreRepository($pdo, true);
        $operation = (string) ($argv[2] ?? '');
        $assetId = (string) ($argv[3] ?? '');
        $inventoryId = (string) ($argv[4] ?? '');
        $releaseAt = (float) ($argv[5] ?? 0);

        while (microtime(true) < $releaseAt) {
            usleep(1000);
        }

        if ($operation === 'register') {
            $repository->registerAsset(
                gate4bRegistrationInput($assetId, $inventoryId),
                'GATE4B-WORKER'
            );
        } elseif ($operation === 'delete') {
            $repository->deleteAsset($assetId, 'GATE4B-WORKER');
        } else {
            throw new LogicException('Unsupported Gate 4B worker operation.');
        }

        echo json_encode([
            'status' => 'success',
            'operation' => $operation,
            'asset_id' => $assetId,
        ], JSON_THROW_ON_ERROR) . PHP_EOL;
        exit(0);
    } catch (PropertyCoreDomainException $error) {
        echo json_encode([
            'status' => 'rejected',
            'code' => $error->getDomainCode(),
        ], JSON_THROW_ON_ERROR) . PHP_EOL;
        exit(0);
    } catch (Throwable $error) {
        fwrite(STDERR, get_class($error) . ': ' . $error->getMessage());
        exit(1);
    }
}

$observer = gate4bObserverConnection();
$service = gate4bServiceConnection();
$repository = new PropertyCoreRepository($service, true);

assertGate4b(
    gate4bScalar($observer, 'SELECT current_user') === 'pcms_app',
    'Observer setup must use the isolated pcms_app identity.'
);
assertGate4b(
    gate4bScalar($service, 'SELECT current_user') ===
        'pcms_property_core_service',
    'Lifecycle operations must use the isolated Property Core identity.'
);
assertGate4b(
    (string) gate4bScalar(
        $observer,
        "SELECT obj_description('gate4b_test'::regnamespace, 'pg_namespace')"
    ) ===
        'Isolated Gate 4B transaction rollback verification only; never deploy to production.',
    'Rollback triggers must exist only in the marked isolated test schema.'
);

try {
    cleanupGate4bFixtures($observer);

    // Inventory create/update/delete against real PostgreSQL.
    $createdInventory = $repository->createInventory([
        'inventory_id' => 'INV-990010',
        'asset_name' => 'GATE4B Inventory CRUD',
        'category' => 'GATE4B TEST',
        'quantity' => 2,
        'condition' => 'Good',
    ], 'GATE4B-TEST');
    assertGate4b(
        $createdInventory['quantity'] === 2,
        'Inventory creation must persist the requested positive quantity.'
    );
    $updatedInventory = $repository->updateInventory('INV-990010', [
        'asset_name' => 'GATE4B Inventory CRUD',
        'category' => 'GATE4B TEST',
        'quantity' => 5,
        'condition' => 'Good',
    ], 'GATE4B-TEST');
    assertGate4b(
        $updatedInventory['quantity'] === 5,
        'Inventory update must persist the new quantity.'
    );
    $repository->deleteInventory('INV-990010', 'GATE4B-TEST');
    assertGate4b(
        (int) gate4bScalar(
            $observer,
            'SELECT COUNT(*) FROM inventory WHERE inventory_id = ?',
            ['INV-990010']
        ) === 0,
        'Eligible Inventory deletion must remove the row.'
    );
    assertGate4b(
        (int) gate4bScalar(
            $observer,
            "SELECT COUNT(*) FROM property_events
             WHERE business_id = 'INV-990010'
               AND event_type IN ('Added', 'Adjusted', 'Deleted')"
        ) === 3,
        'Inventory CRUD must record all three lifecycle events.'
    );

    // Quantity-one registration race through separate PHP processes/connections.
    insertGate4bInventory($observer, 'INV-990001', 1);
    $raceResults = runGate4bWorkers([
        [
            'operation' => 'register',
            'asset_id' => 'AST-990001',
            'inventory_id' => 'INV-990001',
        ],
        [
            'operation' => 'register',
            'asset_id' => 'AST-990002',
            'inventory_id' => 'INV-990001',
        ],
    ]);
    $raceSuccesses = array_filter(
        $raceResults,
        static fn (array $result): bool => $result['status'] === 'success'
    );
    $raceRejections = array_filter(
        $raceResults,
        static fn (array $result): bool => $result['status'] === 'rejected'
    );
    assertGate4b(
        count($raceSuccesses) === 1 && count($raceRejections) === 1,
        'Quantity-one concurrency must produce one success and one rejection.'
    );
    assertGate4b(
        array_values($raceRejections)[0]['code'] ===
            'INSUFFICIENT_INVENTORY_QUANTITY',
        'The losing registration must fail with the safe stock rejection.'
    );
    assertGate4b(
        (int) gate4bScalar(
            $observer,
            "SELECT quantity FROM inventory
             WHERE inventory_id = 'INV-990001'"
        ) === 0,
        'The quantity-one race must decrement Inventory exactly once.'
    );
    assertGate4b(
        (int) gate4bScalar(
            $observer,
            "SELECT COUNT(*) FROM assets
             WHERE inventory_id = (
                SELECT id FROM inventory WHERE inventory_id = 'INV-990001'
             )"
        ) === 1,
        'The failed registration must leave no partial Asset.'
    );
    assertGate4b(
        (int) gate4bScalar(
            $observer,
            "SELECT COUNT(*) FROM property_events
             WHERE module = 'Asset Registry'
               AND event_type = 'Registered'
               AND related_business_id = 'INV-990001'"
        ) === 1,
        'The successful race registration must record one Asset event.'
    );
    assertGate4b(
        (int) gate4bScalar(
            $observer,
            "SELECT COUNT(*) FROM property_events
             WHERE module = 'Inventory'
               AND event_type = 'Stock Decreased'
               AND business_id = 'INV-990001'
               AND quantity_delta = -1"
        ) === 1,
        'The successful race registration must record one -1 event.'
    );
    assertGate4b(
        (int) gate4bScalar(
            $observer,
            "SELECT COUNT(*) FROM inventory WHERE quantity < 0"
        ) === 0,
        'Inventory quantity must never become negative.'
    );

    // Eligible deletion restores exactly one unit and cannot be repeated.
    $deleteInventoryRow = insertGate4bInventory(
        $observer,
        'INV-990002',
        4
    );
    insertGate4bAsset(
        $observer,
        'AST-990003',
        $deleteInventoryRow
    );
    $repository->deleteAsset('AST-990003', 'GATE4B-TEST');
    assertGate4b(
        (int) gate4bScalar(
            $observer,
            "SELECT quantity FROM inventory
             WHERE inventory_id = 'INV-990002'"
        ) === 5,
        'Eligible Asset deletion must restore exactly one Inventory unit.'
    );
    assertGate4b(
        (int) gate4bScalar(
            $observer,
            "SELECT COUNT(*) FROM assets WHERE asset_id = 'AST-990003'"
        ) === 0,
        'Eligible Asset deletion must remove the Asset.'
    );
    $repeatDeleteRejected = false;

    try {
        $repository->deleteAsset('AST-990003', 'GATE4B-TEST');
    } catch (PropertyCoreDomainException $error) {
        $repeatDeleteRejected = $error->getDomainCode() === 'ASSET_NOT_FOUND';
    }

    assertGate4b(
        $repeatDeleteRejected,
        'Repeating the deletion must fail safely as Asset not found.'
    );
    assertGate4b(
        (int) gate4bScalar(
            $observer,
            "SELECT quantity FROM inventory
             WHERE inventory_id = 'INV-990002'"
        ) === 5,
        'Repeated deletion must not restore a second Inventory unit.'
    );
    assertGate4b(
        (int) gate4bScalar(
            $observer,
            "SELECT COUNT(*) FROM property_events
             WHERE business_id = 'AST-990003'
                OR related_business_id = 'AST-990003'"
        ) === 2,
        'Repeated deletion must not duplicate lifecycle events.'
    );

    // Assignment and return preserve Inventory quantity.
    $custodyInventoryRow = insertGate4bInventory(
        $observer,
        'INV-990003',
        5
    );
    insertGate4bAsset(
        $observer,
        'AST-990004',
        $custodyInventoryRow
    );
    $assigned = $repository->assignAsset('AST-990004', [
        'employee_id' => 'GATE4B-EMPLOYEE',
        'custodian' => 'GATE4B Test Custodian',
        'department' => 'GATE4B Test Department',
        'date_assigned' => '2026-08-29',
    ], 'GATE4B-TEST');
    assertGate4b(
        $assigned['status'] === 'Assigned' &&
        $assigned['custodian'] === 'GATE4B Test Custodian',
        'Assignment must populate custody and set Assigned status.'
    );
    assertGate4b(
        (int) gate4bScalar(
            $observer,
            "SELECT quantity FROM inventory
             WHERE inventory_id = 'INV-990003'"
        ) === 5,
        'Assignment must not change Inventory quantity.'
    );
    $returned = $repository->returnAsset('AST-990004', 'GATE4B-TEST');
    assertGate4b(
        $returned['status'] === 'Available' &&
        $returned['custodian'] === null &&
        $returned['employee_id'] === null &&
        $returned['department'] === null,
        'Return must restore Available status and clear custody.'
    );
    assertGate4b(
        (int) gate4bScalar(
            $observer,
            "SELECT quantity FROM inventory
             WHERE inventory_id = 'INV-990003'"
        ) === 5,
        'Return must not change Inventory quantity.'
    );

    // Real PostgreSQL rollback after Asset INSERT and Inventory decrement.
    insertGate4bInventory($observer, 'INV-990004', 1);
    $service->exec(
        "SELECT set_config(
            'pcms.gate4b_fail_event', 'registration', false
         )"
    );
    $registrationRollbackObserved = false;

    try {
        $repository->registerAsset(
            gate4bRegistrationInput('AST-990005', 'INV-990004'),
            'GATE4B-TEST'
        );
    } catch (PDOException $error) {
        $registrationRollbackObserved = str_contains(
            $error->getMessage(),
            'GATE4B_FORCED_REGISTRATION_EVENT_FAILURE'
        );
    } finally {
        $service->exec(
            "SELECT set_config('pcms.gate4b_fail_event', '', false)"
        );
    }

    assertGate4b(
        $registrationRollbackObserved,
        'The isolated registration failure trigger must execute.'
    );
    assertGate4b(
        (int) gate4bScalar(
            $observer,
            "SELECT quantity FROM inventory
             WHERE inventory_id = 'INV-990004'"
        ) === 1 &&
        (int) gate4bScalar(
            $observer,
            "SELECT COUNT(*) FROM assets WHERE asset_id = 'AST-990005'"
        ) === 0 &&
        (int) gate4bScalar(
            $observer,
            "SELECT COUNT(*) FROM property_events
             WHERE business_id = 'AST-990005'
                OR related_business_id = 'AST-990005'"
        ) === 0,
        'Registration failure must roll back Asset, quantity, and events.'
    );

    // Real PostgreSQL rollback after Asset delete and before restoration.
    $rollbackDeleteInventoryRow = insertGate4bInventory(
        $observer,
        'INV-990005',
        4
    );
    insertGate4bAsset(
        $observer,
        'AST-990006',
        $rollbackDeleteInventoryRow
    );
    $service->exec(
        "SELECT set_config('pcms.gate4b_fail_restore', 'on', false)"
    );
    $deletionRollbackObserved = false;

    try {
        $repository->deleteAsset('AST-990006', 'GATE4B-TEST');
    } catch (PDOException $error) {
        $deletionRollbackObserved = str_contains(
            $error->getMessage(),
            'GATE4B_FORCED_INVENTORY_RESTORE_FAILURE'
        );
    } finally {
        $service->exec(
            "SELECT set_config('pcms.gate4b_fail_restore', '', false)"
        );
    }

    assertGate4b(
        $deletionRollbackObserved,
        'The isolated deletion failure trigger must execute.'
    );
    assertGate4b(
        (int) gate4bScalar(
            $observer,
            "SELECT quantity FROM inventory
             WHERE inventory_id = 'INV-990005'"
        ) === 4 &&
        (int) gate4bScalar(
            $observer,
            "SELECT COUNT(*) FROM assets WHERE asset_id = 'AST-990006'"
        ) === 1 &&
        (int) gate4bScalar(
            $observer,
            "SELECT COUNT(*) FROM property_events
             WHERE business_id = 'AST-990006'
                OR related_business_id = 'AST-990006'"
        ) === 0,
        'Deletion failure must roll back Asset deletion, events, and quantity.'
    );

    // Property Events RLS enforcement using the service identity.
    $eventInsert = $service->prepare(
        "INSERT INTO property_events (
            module, event_type, business_id, event_date, performed_by
         ) VALUES (
            :module, 'GATE4B RLS TEST', :business_id,
            CURRENT_DATE, 'GATE4B-TEST'
         )"
    );
    foreach ([
        'Inventory' => 'INV-990080',
        'Asset Registry' => 'AST-990080',
    ] as $module => $businessId) {
        $eventInsert->execute([
            'module' => $module,
            'business_id' => $businessId,
        ]);
    }
    $rejectedModules = [];

    foreach (
        ['Procurement', 'Maintenance', 'Audit', 'Users']
        as $index => $module
    ) {
        try {
            $eventInsert->execute([
                'module' => $module,
                'business_id' => 'INV-99008' . ($index + 1),
            ]);
        } catch (PDOException) {
            $rejectedModules[] = $module;
        }
    }

    assertGate4b(
        $rejectedModules === [
            'Procurement', 'Maintenance', 'Audit', 'Users',
        ],
        'Property Events RLS/check constraints must reject every foreign module.'
    );
    assertGate4b(
        (int) gate4bScalar(
            $observer,
            "SELECT COUNT(*) FROM property_events
             WHERE event_type = 'GATE4B RLS TEST'"
        ) === 2,
        'Only Inventory and Asset Registry RLS test events may exist.'
    );

    // Concurrent registration/deletion on one Inventory row must not deadlock.
    $lockInventoryRow = insertGate4bInventory(
        $observer,
        'INV-990006',
        1
    );
    insertGate4bAsset(
        $observer,
        'AST-990007',
        $lockInventoryRow
    );
    $lockResults = runGate4bWorkers([
        [
            'operation' => 'register',
            'asset_id' => 'AST-990008',
            'inventory_id' => 'INV-990006',
        ],
        [
            'operation' => 'delete',
            'asset_id' => 'AST-990007',
            'inventory_id' => 'INV-990006',
        ],
    ]);
    assertGate4b(
        count(array_filter(
            $lockResults,
            static fn (array $result): bool =>
                $result['status'] === 'success'
        )) === 2,
        'Concurrent registration/deletion must both complete without deadlock.'
    );
    assertGate4b(
        (int) gate4bScalar(
            $observer,
            "SELECT quantity FROM inventory
             WHERE inventory_id = 'INV-990006'"
        ) === 1 &&
        (int) gate4bScalar(
            $observer,
            "SELECT COUNT(*) FROM assets
             WHERE inventory_id = (
                SELECT id FROM inventory WHERE inventory_id = 'INV-990006'
             )"
        ) === 1 &&
        (int) gate4bScalar(
            $observer,
            "SELECT COUNT(*) FROM assets WHERE asset_id = 'AST-990008'"
        ) === 1,
        'Canonical lock ordering must preserve one unit and the new Asset.'
    );

    echo "Property Core Gate 4B operational tests passed: {$testsRun}" .
        PHP_EOL;
} finally {
    cleanupGate4bFixtures($observer);
}
