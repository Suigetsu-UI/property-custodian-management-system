<?php

require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../includes/property_core_gateway.php';

requireAdministrator();
requireValidAccessCsrfPost();

$threshold = filter_input(
    INPUT_POST,
    'aging_threshold_percent',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1, 'max_range' => 99]]
);

if ($threshold === false || $threshold === null) {
    header('Location: index.php?message=invalid_threshold');
    exit;
}

try {
    getPropertyCoreServiceClient()->updateLifecycleSettings(
        ['aging_threshold_percent' => $threshold],
        currentPropertyCoreActor()
    );
    header('Location: index.php?message=threshold_saved');
} catch (Throwable $error) {
    header('Location: index.php?message=save_failed');
}
exit;
