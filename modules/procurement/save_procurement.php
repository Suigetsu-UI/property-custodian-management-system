<?php

require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../includes/procurement_gateway.php';

requireValidAccessCsrfPost();

$payload = procurementFormPayload($_POST);
$businessId = $payload['procurement_id'];
$creating = trim((string) ($_POST['id'] ?? '')) === '';

if ($creating) {
    $validFormat = preg_match('/^PRC-\d{6}$/', $businessId) === 1;
    $issuedToSession = isset(
        $_SESSION['pending_procurement_ids'][$businessId]
    );

    if (!$validFormat || !$issuedToSession) {
        header('Location: index.php?error=invalid_id');
        exit;
    }
}

try {
    $client = getProcurementServiceClient();

    if ($creating) {
        $client->create($payload, currentProcurementActor());
        unset($_SESSION['pending_procurement_ids'][$businessId]);

        if (empty($_SESSION['pending_procurement_ids'])) {
            unset($_SESSION['pending_procurement_ids']);
        }
    } else {
        $client->update(
            $businessId,
            $payload,
            currentProcurementActor()
        );
    }
} catch (Throwable $error) {
    header(
        'Location: index.php?error=' .
        rawurlencode(procurementGatewayErrorKey($error))
    );
    exit;
}

header('Location: index.php');
exit;
