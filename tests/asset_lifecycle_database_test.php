<?php

if (getenv('PCMS_RUN_LIFECYCLE_DB_TESTS') !== 'true') {
    echo "Asset lifecycle database tests skipped." . PHP_EOL;
    exit(0);
}

require_once __DIR__ . '/../services/property_core/config.php';
require_once __DIR__ . '/../services/property_core/src/PropertyCoreRepository.php';

$testsRun = 0;

function assertLifecycleDatabase(bool $condition, string $message): void
{
    global $testsRun;
    $testsRun++;

    if (!$condition) {
        throw new RuntimeException('FAILED: ' . $message);
    }
}

$pdo = getPropertyCoreServiceConnection();

assertLifecycleDatabase(
    $pdo->query('SELECT current_user')->fetchColumn() ===
        'pcms_property_core_service',
    'Lifecycle access must use the dedicated Property Core role.'
);

foreach (
    [
        'asset_lifecycle_settings',
        'asset_category_useful_life',
        'asset_lifecycle_config_events',
    ] as $table
) {
    $privilege = $pdo->query(
        "SELECT has_table_privilege(
            current_user,
            'public.{$table}',
            'SELECT'
        )"
    )->fetchColumn();
    $rls = $pdo->query(
        "SELECT relrowsecurity
         FROM pg_class
         WHERE oid = 'public.{$table}'::regclass"
    )->fetchColumn();

    assertLifecycleDatabase(
        $privilege === true,
        "Property Core must have SELECT on {$table}."
    );
    assertLifecycleDatabase(
        $rls === true,
        "RLS must be enabled on {$table}."
    );
}

assertLifecycleDatabase(
    $pdo->query(
        "SELECT has_table_privilege(
            current_user,
            'public.asset_lifecycle_config_events',
            'UPDATE'
        )"
    )->fetchColumn() === false,
    'Configuration history must deny UPDATE to Property Core.'
);
assertLifecycleDatabase(
    $pdo->query(
        "SELECT has_table_privilege(
            current_user,
            'public.asset_lifecycle_config_events',
            'DELETE'
        )"
    )->fetchColumn() === false,
    'Configuration history must deny DELETE to Property Core.'
);

$originalThreshold = (int) $pdo->query(
    'SELECT aging_threshold_percent FROM asset_lifecycle_settings WHERE id = 1'
)->fetchColumn();

$pdo->beginTransaction();

try {
    $temporaryThreshold = $originalThreshold === 99
        ? 98
        : $originalThreshold + 1;
    $update = $pdo->prepare(
        "UPDATE asset_lifecycle_settings
         SET aging_threshold_percent = :threshold,
             updated_by = 'lifecycle-db-test'
         WHERE id = 1"
    );
    $update->execute(['threshold' => $temporaryThreshold]);
    assertLifecycleDatabase(
        $update->rowCount() === 1,
        'The singleton threshold must be updateable by Property Core.'
    );

    $insert = $pdo->prepare(
        "INSERT INTO asset_category_useful_life (
            category, useful_life_months, remarks, updated_by
         ) VALUES (
            :category, 60, 'Rolled-back lifecycle database test',
            'lifecycle-db-test'
         )"
    );
    $testCategory = '__PCMS_LIFECYCLE_DB_TEST__';
    $insert->execute(['category' => $testCategory]);
    assertLifecycleDatabase(
        $insert->rowCount() === 1,
        'Property Core must be able to create a category useful-life value.'
    );

    $event = $pdo->prepare(
        "INSERT INTO asset_lifecycle_config_events (
            setting_type, category, new_value, changed_by
         ) VALUES (
            'CATEGORY_USEFUL_LIFE', :category, '60',
            'lifecycle-db-test'
         )"
    );
    $event->execute(['category' => $testCategory]);
    assertLifecycleDatabase(
        $event->rowCount() === 1,
        'Property Core must be able to append configuration history.'
    );

    $inventory = $pdo->prepare(
        "INSERT INTO inventory (
            inventory_id, asset_name, category, quantity, condition
         ) VALUES (
            'INV-999998', 'Lifecycle Database Test', :category, 0, 'Good'
         ) RETURNING id"
    );
    $inventory->execute(['category' => $testCategory]);
    $inventoryRowId = (int) $inventory->fetchColumn();
    $asset = $pdo->prepare(
        "INSERT INTO assets (
            asset_id, asset_name, category, acquisition_date,
            inventory_id, status
         ) VALUES (
            'AST-999998', 'Lifecycle Database Test', :category,
            current_date - interval '61 months', :inventory_id, 'Available'
         )"
    );
    $asset->execute([
        'category' => $testCategory,
        'inventory_id' => $inventoryRowId,
    ]);

    $repository = new PropertyCoreRepository($pdo, true);
    $page = $repository->listAssets([
        'search' => 'Lifecycle Database Test',
        'lifecycle' => 'Retirement Review',
        'page' => 1,
        'per_page' => 25,
    ]);
    assertLifecycleDatabase(
        ($page['pagination']['total'] ?? 0) === 1,
        'The SQL read model must combine search with lifecycle filtering.'
    );
    assertLifecycleDatabase(
        ($page['records'][0]['lifecycle'] ?? '') === 'Retirement Review',
        'An Asset beyond useful life must be classified for Retirement Review.'
    );
    assertLifecycleDatabase(
        ($page['records'][0]['asset_age_months'] ?? 0) >= 61,
        'The SQL read model must calculate factual Asset age in months.'
    );
} finally {
    $pdo->rollBack();
}

assertLifecycleDatabase(
    (int) $pdo->query(
        'SELECT aging_threshold_percent FROM asset_lifecycle_settings WHERE id = 1'
    )->fetchColumn() === $originalThreshold,
    'The test must restore the original threshold.'
);
assertLifecycleDatabase(
    (int) $pdo->query(
        "SELECT COUNT(*) FROM asset_category_useful_life
         WHERE category = '__PCMS_LIFECYCLE_DB_TEST__'"
    )->fetchColumn() === 0,
    'The test must leave no category fixture.'
);

echo "Asset lifecycle database tests passed: {$testsRun}" . PHP_EOL;
