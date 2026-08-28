<?php

$testsRun = 0;

function assertProcurementContract(bool $condition, string $message): void
{
    global $testsRun;
    $testsRun++;

    if (!$condition) {
        throw new RuntimeException('FAILED: ' . $message);
    }
}

$root = dirname(__DIR__);
$directSqlPattern = '/(?:\bFROM\s+procurement\b|\bINSERT\s+INTO\s+procurement\b|\bUPDATE\s+procurement\s+SET\b|\bDELETE\s+FROM\s+procurement\b)/i';
$directAccessFiles = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }

    $path = str_replace('\\', '/', $file->getPathname());

    if (
        str_contains($path, '/services/procurement/') ||
        str_contains($path, '/tests/')
    ) {
        continue;
    }

    $source = file_get_contents($file->getPathname());

    if ($source === false) {
        continue;
    }

    foreach (token_get_all($source) as $token) {
        if (
            is_array($token) &&
            $token[0] === T_CONSTANT_ENCAPSED_STRING &&
            preg_match($directSqlPattern, $token[1]) === 1
        ) {
            $directAccessFiles[] = $path;
            break;
        }
    }
}

assertProcurementContract(
    $directAccessFiles === [],
    'Main PCMS runtime must not contain direct Procurement table SQL: ' .
        implode(', ', $directAccessFiles)
);

$repository = file_get_contents(
    $root . '/services/procurement/src/ProcurementRepository.php'
);
$frontController = file_get_contents(
    $root . '/services/procurement/public/index.php'
);
$client = file_get_contents(
    $root . '/includes/procurement_service_client.php'
);
$migration = file_get_contents(
    $root . '/database/microservices_v1_procurement.sql'
);
$example = file_get_contents($root . '/.env.example');
$serviceExample = file_get_contents(
    $root . '/services/procurement/.env.example'
);
$saveRoute = file_get_contents(
    $root . '/modules/procurement/save_procurement.php'
);
$deleteRoute = file_get_contents(
    $root . '/modules/procurement/delete_procurement.php'
);

assertProcurementContract(
    preg_match('/SELECT\s+\*/i', $repository) !== 1,
    'Procurement service must not SELECT *.'
);
assertProcurementContract(
    str_contains($repository, 'LIMIT :limit OFFSET :offset') &&
    str_contains($repository, 'COUNT(*)'),
    'List endpoint must use bounded SQL pagination and matching totals.'
);
assertProcurementContract(
    str_contains($repository, 'beginTransaction()') &&
    str_contains($repository, 'FOR UPDATE') &&
    str_contains($repository, 'recordPropertyEvent'),
    'Writes must preserve transactions, row locks, and event recording.'
);
assertProcurementContract(
    str_contains($repository, "'Delivered' => ['Delivered']") &&
    str_contains($repository, "'Rejected' => ['Pending', 'Approved', 'Rejected']"),
    'Settled Procurement transition rules must remain in the service.'
);
assertProcurementContract(
    str_contains($frontController, 'hash_equals') &&
    str_contains($frontController, 'HTTP_AUTHORIZATION') &&
    str_contains($frontController, '/api/v1/'),
    'Service must use constant-time bearer authentication and versioned APIs.'
);
assertProcurementContract(
    str_contains($client, 'CURLOPT_CONNECTTIMEOUT_MS') &&
    str_contains($client, 'CURLOPT_TIMEOUT_MS') &&
    str_contains($client, "'timeout' => \$this->requestTimeoutMs / 1000") &&
    !str_contains($client, 'CURLOPT_FOLLOWLOCATION => true'),
    'Gateway must use bounded timeouts, no infinite retry, and no redirect following.'
);
assertProcurementContract(
    str_contains($saveRoute, 'check_auth.php') &&
    str_contains($saveRoute, 'requireValidAccessCsrfPost') &&
    str_contains($deleteRoute, 'requireValidAccessCsrfPost'),
    'Browser mutations must remain behind authentication and CSRF validation.'
);
assertProcurementContract(
    preg_match('/^PROCUREMENT_SERVICE_TOKEN=\s*$/m', $example) === 1 &&
    preg_match('/^PROCUREMENT_SERVICE_TOKEN=\s*$/m', $serviceExample) === 1 &&
    preg_match('/^PROCUREMENT_DB_PASSWORD=\s*$/m', $serviceExample) === 1 &&
    !str_contains($example, 'PROCUREMENT_DB_PASSWORD'),
    '.env.example must contain empty secret placeholders only.'
);
assertProcurementContract(
    str_contains(
        file_get_contents($root . '/.gitignore'),
        'services/procurement/.env'
    ) &&
    str_contains(
        file_get_contents($root . '/services/procurement/config.php'),
        "loadProcurementServiceEnv(__DIR__ . '/.env')"
    ) &&
    !str_contains(
        file_get_contents($root . '/services/procurement/config.php'),
        "../../config/config.php"
    ),
    'Procurement service secrets must be isolated from the main PCMS .env.'
);
assertProcurementContract(
    preg_match('/PASSWORD\s+[\'\"][^<\s]/i', $migration) !== 1,
    'Database migration must not contain an actual role password.'
);
assertProcurementContract(
    str_contains($migration, 'TO pcms_procurement_service') &&
    str_contains($migration, 'FROM pcms_app') &&
    str_contains($migration, 'public.procurement') &&
    str_contains($migration, 'public.inventory') &&
    str_contains($migration, 'public.property_events'),
    'Owner migration must define least-privilege ownership and cutover.'
);
assertProcurementContract(
    str_contains($migration, 'REVOKE ALL ON TABLE public.users') &&
    str_contains($migration, 'public.security_events') &&
    str_contains($migration, 'public.assets'),
    'Procurement role must be denied unrelated and security data.'
);
assertProcurementContract(
    str_contains(file_get_contents($root . '/modules/procurement/index.php'), 'Procurement service is temporarily unavailable') &&
    str_contains(file_get_contents($root . '/dashboard.php'), 'Service temporarily unavailable'),
    'Procurement and Dashboard must fail gracefully when the service is unavailable.'
);

echo "Procurement service contract tests passed: {$testsRun}" . PHP_EOL;
