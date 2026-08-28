<?php

require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../includes/procurement_gateway.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

requireValidAccessCsrfPost();

try {
    $procurementId = getProcurementServiceClient()->nextBusinessId(
        currentProcurementActor()
    );

    if (preg_match('/^PRC-\d{6}$/', $procurementId) !== 1) {
        throw new RuntimeException('Invalid Procurement ID response.');
    }

    $_SESSION['pending_procurement_ids'] ??= [];
    $_SESSION['pending_procurement_ids'][$procurementId] = true;

    echo json_encode([
        'procurement_id' => $procurementId,
    ]);
} catch (Throwable $error) {
    http_response_code(503);
    echo json_encode([
        'error' => 'Procurement service is temporarily unavailable.',
    ]);
}

exit;
