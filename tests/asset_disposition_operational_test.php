<?php

if (getenv('PROPERTY_CORE_RUN_DISPOSITION_TESTS') !== 'true') {
    echo "Asset disposition operational tests skipped." . PHP_EOL;
    exit(0);
}

$root = dirname(__DIR__);
require_once $root . '/services/property_core/config.php';
require_once $root . '/services/property_core/src/PropertyCoreRepository.php';

$testsRun = 0;
function assertDispositionOperational(bool $condition, string $message): void
{
    global $testsRun;
    $testsRun++;
    if (!$condition) {
        throw new RuntimeException('FAILED: ' . $message);
    }
}

$pdo = getPropertyCoreServiceConnection();
$repository = new PropertyCoreRepository($pdo);

assertDispositionOperational(
    $pdo->query('SELECT current_user')->fetchColumn() === 'pcms_property_core_service',
    'Operational verification must use the dedicated Property Core role.'
);
foreach (['SELECT', 'INSERT', 'UPDATE'] as $privilege) {
    assertDispositionOperational(
        (bool) $pdo->query("SELECT has_table_privilege(current_user, 'public.asset_dispositions', '{$privilege}')")->fetchColumn(),
        "Property Core must have {$privilege} on disposition records."
    );
}
assertDispositionOperational(
    !(bool) $pdo->query("SELECT has_table_privilege(current_user, 'public.asset_dispositions', 'DELETE')")->fetchColumn(),
    'Disposition history must deny deletion to Property Core.'
);
foreach (['asset_dispositions_id_seq', 'asset_disposition_business_id_seq'] as $sequence) {
    assertDispositionOperational(
        (bool) $pdo->query("SELECT has_sequence_privilege(current_user, 'public.{$sequence}', 'USAGE')")->fetchColumn(),
        "Property Core must have sequence usage for {$sequence}."
    );
}
foreach (['SELECT', 'INSERT', 'UPDATE', 'DELETE'] as $privilege) {
    assertDispositionOperational(
        !(bool) $pdo->query("SELECT has_table_privilege('pcms_app', 'public.asset_dispositions', '{$privilege}')")->fetchColumn(),
        "The main application role must not have direct {$privilege} access."
    );
}

$rls = $pdo->query(
    "SELECT relrowsecurity FROM pg_class
     WHERE oid = 'public.asset_dispositions'::regclass"
)->fetchColumn();
assertDispositionOperational((bool) $rls, 'Disposition records must have RLS enabled.');

$policies = $pdo->query(
    "SELECT command FROM (
        SELECT cmd AS command FROM pg_policies
        WHERE schemaname = 'public' AND tablename = 'asset_dispositions'
          AND 'pcms_property_core_service' = ANY(roles)
     ) policy_commands"
)->fetchAll(PDO::FETCH_COLUMN);
sort($policies);
assertDispositionOperational(
    $policies === ['INSERT', 'SELECT', 'UPDATE'],
    'RLS must allow only Select, Insert, and Update operations.'
);

$trigger = $pdo->query(
    "SELECT 1 FROM pg_trigger
     WHERE tgrelid = 'public.assets'::regclass
       AND tgname = 'assets_sold_terminal_guard'
       AND NOT tgisinternal"
)->fetchColumn();
assertDispositionOperational((bool) $trigger, 'The database must enforce the terminal Sold state.');

$page = $repository->listDispositions(['per_page' => 500]);
assertDispositionOperational(
    $page['pagination']['per_page'] === 50 && count($page['records']) <= 50,
    'Disposition history must use bounded server pagination.'
);

echo "Asset disposition operational tests passed: {$testsRun}" . PHP_EOL;
