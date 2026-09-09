<?php

$root = dirname(__DIR__);
$testsRun = 0;
function assertDispositionContract(bool $condition, string $message): void
{
    global $testsRun;
    $testsRun++;
    if (!$condition) {
        throw new RuntimeException('FAILED: ' . $message);
    }
}

$migration = file_get_contents($root . '/database/asset_disposition_v1.sql');
$repository = file_get_contents($root . '/services/property_core/src/PropertyCoreRepository.php');
$access = file_get_contents($root . '/includes/access_control.php');
$ai = file_get_contents($root . '/includes/ai_insight_functions.php');
$handlers = ['request.php', 'approve.php', 'complete.php', 'reject.php', 'cancel.php'];

assertDispositionContract(str_contains($migration, "'Sold'") && str_contains($migration, 'ON DELETE RESTRICT'), 'Sold status and retained disposition history must be enforced by schema.');
assertDispositionContract(str_contains($migration, 'ENABLE ROW LEVEL SECURITY') && str_contains($migration, 'GRANT SELECT, INSERT, UPDATE') && !str_contains($migration, 'GRANT DELETE'), 'Disposition storage must use RLS and deny service deletion.');
assertDispositionContract(str_contains($migration, 'asset_dispositions_one_open_per_asset_uidx'), 'Only one open disposition may exist per Asset.');
assertDispositionContract(str_contains($repository, "status = 'Sold'") && str_contains($repository, 'Inventory was unchanged.') && !preg_match('/completeDisposition[\s\S]{0,5000}UPDATE inventory/', $repository), 'Sale completion must preserve the Asset and leave Inventory unchanged.');
assertDispositionContract(str_contains($access, 'function requirePropertyCustodian'), 'The web layer must have a dedicated Property Custodian authorization guard.');
foreach ($handlers as $handler) {
    $source = file_get_contents($root . '/modules/dispositions/' . $handler);
    assertDispositionContract(str_contains($source, 'requirePropertyCustodian();') && str_contains($source, 'requireValidAccessCsrfPost();'), $handler . ' must require Property Custodian role and CSRF-protected POST.');
}
assertDispositionContract(str_contains($ai, "WHERE status <> 'Sold'"), 'Sold Assets must be excluded from the active AI portfolio.');

echo "Asset disposition contract tests passed: {$testsRun}" . PHP_EOL;
