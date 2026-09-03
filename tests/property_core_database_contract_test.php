<?php

$testsRun = 0;

function assertPropertyCoreDatabase(bool $condition, string $message): void
{
    global $testsRun;
    $testsRun++;

    if (!$condition) {
        throw new RuntimeException('FAILED: ' . $message);
    }
}

$root = dirname(__DIR__);
$migrationPath = $root . '/database/microservices_v2_property_core.sql';
$migration = file_get_contents($migrationPath);

if ($migration === false) {
    throw new RuntimeException('FAILED: Property Core migration is missing.');
}

$executable = preg_replace('/--[^\r\n]*/', '', $migration);

assertPropertyCoreDatabase(
    str_contains($migration, 'CREATE ROLE pcms_property_core_service') &&
    str_contains($migration, 'NOLOGIN') &&
    str_contains($migration, 'NOSUPERUSER') &&
    str_contains($migration, 'NOCREATEDB') &&
    str_contains($migration, 'NOCREATEROLE') &&
    str_contains($migration, 'NOINHERIT') &&
    str_contains($migration, 'NOREPLICATION') &&
    str_contains($migration, 'NOBYPASSRLS'),
    'The service role must start with the frozen non-login posture.'
);
assertPropertyCoreDatabase(
    preg_match('/\bPASSWORD\s+[\'\"][^<\s]/i', $migration) !== 1 &&
    !str_contains($migration, 'PROPERTY_CORE_SERVICE_TOKEN='),
    'The migration must not contain a role password or service token.'
);
assertPropertyCoreDatabase(
    preg_match(
        '/GRANT\s+SELECT,\s*INSERT,\s*UPDATE,\s*DELETE\s+ON TABLE public\.inventory, public\.assets/is',
        $migration
    ) === 1,
    'Property Core must receive full lifecycle table privileges only on Inventory and Assets.'
);
assertPropertyCoreDatabase(
    preg_match(
        '/GRANT\s+SELECT\s+ON TABLE public\.maintenance, public\.audits/is',
        $migration
    ) === 1,
    'Maintenance and Audit access must remain read-only for lifecycle eligibility checks.'
);
assertPropertyCoreDatabase(
    preg_match(
        '/GRANT\s+INSERT\s+ON TABLE public\.property_events/is',
        $migration
    ) === 1,
    'Property event access must be append-only.'
);
assertPropertyCoreDatabase(
    str_contains($migration, "module IN ('Inventory', 'Asset Registry')"),
    'Event RLS must restrict Property Core to its two modules.'
);
assertPropertyCoreDatabase(
    str_contains($migration, 'public.inventory_id_seq') &&
    str_contains($migration, 'public.inventory_id_seq1') &&
    str_contains($migration, 'public.asset_id_seq') &&
    str_contains($migration, 'public.assets_id_seq') &&
    str_contains($migration, 'public.property_events_id_seq') &&
    !preg_match('/GRANT\s+ALL[^;]*SEQUENCE/is', $migration),
    'Only the five required sequences may be granted.'
);
assertPropertyCoreDatabase(
    substr_count($migration, 'CREATE POLICY property_core_inventory_') === 4 &&
    substr_count($migration, 'CREATE POLICY property_core_assets_') === 4,
    'Inventory and Assets must use four operation-specific RLS policies each.'
);
assertPropertyCoreDatabase(
    str_contains($migration, 'CREATE POLICY property_core_maintenance_select') &&
    str_contains($migration, 'CREATE POLICY property_core_audits_select') &&
    !str_contains($migration, 'property_core_maintenance_update') &&
    !str_contains($migration, 'property_core_audits_update'),
    'Operations-domain RLS must remain read-only.'
);
assertPropertyCoreDatabase(
    preg_match('/CREATE\s+EXTENSION\s+IF\s+NOT\s+EXISTS\s+pg_trgm\s+WITH\s+SCHEMA\s+extensions;/i', $migration) === 1 &&
    preg_match('/CREATE\s+EXTENSION[^;]*\bVERSION\b/i', $migration) !== 1,
    'pg_trgm must use the Supabase default version in the extensions schema.'
);
assertPropertyCoreDatabase(
    str_contains($migration, 'inventory_property_core_search_trgm_idx') &&
    str_contains($migration, 'assets_property_core_search_trgm_idx') &&
    str_contains($migration, 'inventory_property_core_available_options_idx'),
    'Only the three frozen search/option indexes must be introduced.'
);
assertPropertyCoreDatabase(
    str_contains($migration, "coalesce(custodian, '')") &&
    str_contains($migration, "coalesce(employee_id, '')") &&
    str_contains($migration, "coalesce(department, '')"),
    'The Asset search index must preserve Custodian and employee search semantics.'
);
assertPropertyCoreDatabase(
    str_contains($migration, 'WHERE quantity > 0'),
    'The registration-options index must be partial and match the bounded query predicate.'
);
assertPropertyCoreDatabase(
    preg_match('/\b(?:INSERT\s+INTO|UPDATE\s+public\.|DELETE\s+FROM)\b/i', $executable) !== 1,
    'Gate 2 must contain no production-data mutations.'
);
assertPropertyCoreDatabase(
    preg_match('/REVOKE[^;]+FROM\s+pcms_app/is', $executable) !== 1,
    'The executable Gate 2 phase must not revoke current pcms_app access.'
);
assertPropertyCoreDatabase(
    preg_match('/(?:GRANT|CREATE POLICY)[^;]+(?:public\.procurement|public\.users|public\.login_attempts|public\.security_events)[^;]*pcms_property_core_service/is', $executable) !== 1,
    'Property Core must not receive Procurement, user, login, or security-event access.'
);

echo "Property Core database contract tests passed: {$testsRun}" . PHP_EOL;
