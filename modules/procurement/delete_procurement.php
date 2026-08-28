<?php

require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../includes/procurement_gateway.php';

requireValidAccessCsrfPost();

$businessId = trim((string) ($_POST['procurement_id'] ?? ''));

if (preg_match('/^PRC-\d{6}$/', $businessId) !== 1) {
    header('Location: index.php?error=invalid_id');
    exit;
}

try {
    getProcurementServiceClient()->delete(
        $businessId,
        currentProcurementActor()
    );
} catch (Throwable $error) {
    header(
        'Location: index.php?error=' .
        rawurlencode(procurementGatewayErrorKey($error))
    );
    exit;
}

header('Location: index.php');
exit;
