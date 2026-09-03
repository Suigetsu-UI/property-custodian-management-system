<?php

$root = dirname(__DIR__);
$assertions = 0;

function assertHostForgeDeployment(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$dockerfile = file_get_contents($root . '/Dockerfile');
$entrypoint = file_get_contents($root . '/deploy/hostforge-entrypoint.sh');
$router = file_get_contents($root . '/deploy/hostforge_web_router.php');
$session = file_get_contents($root . '/includes/session.php');
$dockerignore = file_get_contents($root . '/.dockerignore');

assertHostForgeDeployment(
    str_contains($dockerfile, 'pdo_pgsql') &&
    str_contains($dockerfile, 'sodium') &&
    str_contains($dockerfile, 'curl'),
    'The deployment image must include required PHP extensions.'
);
assertHostForgeDeployment(
    str_contains($dockerfile, 'USER pcms'),
    'The deployment image must run as a non-root user.'
);
assertHostForgeDeployment(
    str_contains($entrypoint, 'APP_ROLE') &&
    str_contains($entrypoint, 'procurement') &&
    str_contains($entrypoint, 'property_core'),
    'One image must support isolated web and service application roles.'
);
assertHostForgeDeployment(
    str_contains($router, "'/healthz'") &&
    str_contains($router, "'database'") &&
    str_contains($router, "'services'") &&
    str_contains($router, "'tests'"),
    'The web router must expose health while blocking internal source paths.'
);
assertHostForgeDeployment(
    str_contains($session, "getenv('PCMS_FORCE_HTTPS')"),
    'Hosted HTTPS must be configurable without trusting arbitrary proxy headers.'
);
assertHostForgeDeployment(
    str_contains($dockerignore, '.env') &&
    str_contains($dockerignore, 'services/procurement/.env') &&
    str_contains($dockerignore, 'services/property_core/.env'),
    'Secret environment files must be excluded from the Docker context.'
);

echo "HostForge deployment contract tests passed: {$assertions}\n";
