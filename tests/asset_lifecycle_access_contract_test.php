<?php

$testsRun = 0;

function assertLifecycleAccess(bool $condition, string $message): void
{
    global $testsRun;
    $testsRun++;

    if (!$condition) {
        throw new RuntimeException('FAILED: ' . $message);
    }
}

$root = dirname(__DIR__);
$index = file_get_contents($root . '/modules/lifecycle/index.php');
$threshold = file_get_contents($root . '/modules/lifecycle/update_threshold.php');
$category = file_get_contents($root . '/modules/lifecycle/save_category.php');
$sidebar = file_get_contents($root . '/includes/sidebar.php');

foreach ([$index, $threshold, $category] as $route) {
    assertLifecycleAccess(
        str_contains($route, 'requireAdministrator();'),
        'Every lifecycle configuration route must require a System Administrator.'
    );
}
foreach ([$threshold, $category] as $route) {
    assertLifecycleAccess(
        str_contains($route, 'requireValidAccessCsrfPost();'),
        'Every lifecycle configuration mutation must require POST and CSRF.'
    );
}
assertLifecycleAccess(
    preg_match(
        '/if \(isAdministrator\(\)\).*Lifecycle Settings.*User Management/s',
        $sidebar
    ) === 1,
    'Lifecycle Settings must only appear inside Administrator navigation.'
);
assertLifecycleAccess(
    !str_contains($index, 'Property Custodian') &&
    !str_contains($threshold, 'Property Custodian') &&
    !str_contains($category, 'Property Custodian'),
    'Lifecycle routes must not grant a Property Custodian exception.'
);

echo "Asset lifecycle access contract tests passed: {$testsRun}" . PHP_EOL;
