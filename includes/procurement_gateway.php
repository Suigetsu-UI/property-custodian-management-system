<?php

require_once __DIR__ . '/procurement_service_client.php';

function currentProcurementActor(): string
{
    return trim((string) ($_SESSION['user']['employee_id'] ?? ''));
}

function procurementFormPayload(array $source): array
{
    return [
        'procurement_id' => trim((string) ($source['procurement_id'] ?? '')),
        'item_name' => trim((string) ($source['item_name'] ?? '')),
        'category' => trim((string) ($source['category'] ?? '')),
        'quantity' => (int) ($source['quantity'] ?? 0),
        'supplier' => trim((string) ($source['supplier'] ?? '')),
        'requested_by' => trim((string) ($source['requested_by'] ?? '')),
        'request_date' => trim((string) ($source['request_date'] ?? '')),
        'status' => trim((string) ($source['status'] ?? '')),
        'approved_by' => trim((string) ($source['approved_by'] ?? '')),
        'approval_date' => trim((string) ($source['approval_date'] ?? '')),
        'delivery_date' => trim((string) ($source['delivery_date'] ?? '')),
        'remarks' => trim((string) ($source['remarks'] ?? '')),
    ];
}

function procurementGatewayErrorKey(Throwable $error): string
{
    if ($error instanceof ProcurementServiceUnavailableException) {
        return 'service_unavailable';
    }

    if (!$error instanceof ProcurementServiceException) {
        return 'save_failed';
    }

    return match ($error->getServiceCode()) {
        'INVALID_STATUS',
        'INVALID_STATUS_TRANSITION' => 'invalid_status',
        'DELIVERY_DATE_REQUIRED' => 'delivery_date',
        'INVENTORY_NEGATIVE' => 'inventory_negative',
        'INVALID_PROCUREMENT_ID' => 'invalid_id',
        'DUPLICATE_PROCUREMENT_ID' => 'duplicate_id',
        'DELIVERED_PROCUREMENT_DELETE_FORBIDDEN' => 'delivered',
        default => 'save_failed',
    };
}

function emptyProcurementSummary(bool $available = false): array
{
    return [
        'available' => $available,
        'total' => 0,
        'pending' => 0,
        'approved' => 0,
        'delivered' => 0,
        'rejected' => 0,
        'open' => 0,
        'by_supplier' => [],
        'recent' => [],
        'recently_delivered' => [],
    ];
}

function getProcurementSummarySafely(): array
{
    static $summary = null;

    if (is_array($summary)) {
        return $summary;
    }

    try {
        $summary = ['available' => true] +
            getProcurementServiceClient()->summary();
    } catch (Throwable $error) {
        $summary = emptyProcurementSummary(false);
    }

    return $summary;
}
