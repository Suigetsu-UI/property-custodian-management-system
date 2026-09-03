<?php

declare(strict_types=1);

$basePath = '/property-custodian-management-system';
$requestPath = parse_url(
    (string) ($_SERVER['REQUEST_URI'] ?? '/'),
    PHP_URL_PATH
);
$requestPath = is_string($requestPath) ? $requestPath : '/';

if ($requestPath === '/healthz') {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'service' => 'pcms-web',
        'status' => 'healthy',
    ], JSON_THROW_ON_ERROR);
    return true;
}

if ($requestPath === '/') {
    header('Location: ' . $basePath . '/auth/login.php', true, 302);
    return true;
}

if (
    $requestPath !== $basePath &&
    !str_starts_with($requestPath, $basePath . '/')
) {
    http_response_code(404);
    echo 'Not Found';
    return true;
}

$relativePath = ltrim(substr($requestPath, strlen($basePath)), '/');
$segments = $relativePath === '' ? [] : explode('/', $relativePath);
$firstSegment = $segments[0] ?? '';
$blockedSegments = [
    'config',
    'database',
    'deploy',
    'docs',
    'includes',
    'services',
    'tests',
];

if (
    in_array($firstSegment, $blockedSegments, true) ||
    str_starts_with(basename($relativePath), '.')
) {
    http_response_code(404);
    echo 'Not Found';
    return true;
}

$publicPath = '/srv' . $requestPath;

if (is_file($publicPath) || is_dir($publicPath)) {
    return false;
}

http_response_code(404);
echo 'Not Found';
return true;
