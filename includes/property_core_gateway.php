<?php

require_once __DIR__ . '/property_core_service_client.php';

function currentPropertyCoreActor(): string
{
    return trim((string) ($_SESSION['user']['employee_id'] ?? ''));
}

function inventoryGatewayPayload(array $source): array
{
    return [
        'inventory_id' => trim((string) ($source['inventory_id'] ?? '')),
        'asset_name' => trim((string) ($source['asset_name'] ?? '')),
        'category' => trim((string) ($source['category'] ?? '')),
        'quantity' => $source['quantity'] ?? null,
        'condition' => trim((string) ($source['condition'] ?? '')),
    ];
}

function assetRegistrationGatewayPayload(array $source): array
{
    return [
        'asset_id' => trim((string) ($source['asset_id'] ?? '')),
        'inventory_id' => trim((string) ($source['inventory_id'] ?? '')),
        'brand' => trim((string) ($source['brand'] ?? '')),
        'model' => trim((string) ($source['model'] ?? '')),
        'serial_number' => trim((string) ($source['serial_number'] ?? '')),
        'acquisition_date' => trim((string) (
            $source['acquisition_date'] ?? ''
        )),
        'purchase_cost' => trim((string) ($source['purchase_cost'] ?? '')),
        'supplier' => trim((string) ($source['supplier'] ?? '')),
        'location' => trim((string) ($source['location'] ?? '')),
        'remarks' => trim((string) ($source['remarks'] ?? '')),
    ];
}

function assetUpdateGatewayPayload(array $source): array
{
    return [
        'asset_name' => trim((string) ($source['asset_name'] ?? '')),
        'category' => trim((string) ($source['category'] ?? '')),
        'brand' => trim((string) ($source['brand'] ?? '')),
        'model' => trim((string) ($source['model'] ?? '')),
        'serial_number' => trim((string) ($source['serial_number'] ?? '')),
        'supplier' => trim((string) ($source['supplier'] ?? '')),
        'location' => trim((string) ($source['location'] ?? '')),
        'remarks' => trim((string) ($source['remarks'] ?? '')),
    ];
}

function assetAssignmentGatewayPayload(array $source): array
{
    return [
        'employee_id' => trim((string) ($source['employee_id'] ?? '')),
        'custodian' => trim((string) ($source['custodian'] ?? '')),
        'department' => trim((string) ($source['department'] ?? '')),
        'date_assigned' => trim((string) (
            $source['date_assigned'] ?? ''
        )),
    ];
}

function dispositionRequestGatewayPayload(array $source): array
{
    return [
        'reason' => trim((string) ($source['reason'] ?? '')),
        'proposed_method' => trim((string) ($source['proposed_method'] ?? '')),
        'request_remarks' => trim((string) ($source['request_remarks'] ?? '')),
    ];
}

function dispositionApprovalGatewayPayload(array $source): array
{
    return [
        'institutional_approval_reference' => trim((string) (
            $source['institutional_approval_reference'] ?? ''
        )),
        'institutional_approver_name' => trim((string) (
            $source['institutional_approver_name'] ?? ''
        )),
        'institutional_approver_position' => trim((string) (
            $source['institutional_approver_position'] ?? ''
        )),
        'institutional_approval_date' => trim((string) (
            $source['institutional_approval_date'] ?? ''
        )),
        'approval_remarks' => trim((string) ($source['approval_remarks'] ?? '')),
    ];
}

function dispositionCompletionGatewayPayload(array $source): array
{
    return [
        'completion_reference' => trim((string) (
            $source['completion_reference'] ?? ''
        )),
        'completion_remarks' => trim((string) (
            $source['completion_remarks'] ?? ''
        )),
    ];
}

function dispositionDecisionGatewayPayload(array $source): array
{
    return [
        'status_note' => trim((string) ($source['status_note'] ?? '')),
    ];
}

function inventoryGatewayErrorKey(Throwable $error): string
{
    if ($error instanceof PropertyCoreServiceUnavailableException) {
        return 'service_unavailable';
    }

    if (!$error instanceof PropertyCoreServiceException) {
        return 'save_failed';
    }

    return match ($error->getServiceCode()) {
        'INVENTORY_LINKED_TO_ASSETS' => 'linked',
        'DUPLICATE_INVENTORY_ITEM' => 'duplicate_item',
        'INVALID_INVENTORY_ID' => 'invalid_id',
        'INVENTORY_NOT_FOUND' => 'not_found',
        default => 'save_failed',
    };
}

function assetGatewayErrorKey(Throwable $error): string
{
    if ($error instanceof PropertyCoreServiceUnavailableException) {
        return 'service_unavailable';
    }

    if (!$error instanceof PropertyCoreServiceException) {
        return 'save_failed';
    }

    return match ($error->getServiceCode()) {
        'INSUFFICIENT_INVENTORY_QUANTITY' => 'stock',
        'INVENTORY_NOT_FOUND',
        'INVALID_INVENTORY_ID' => 'inventory',
        'ASSET_UNDER_MAINTENANCE' => 'maintenance',
        'SOLD_ASSET_CHANGE_FORBIDDEN' => 'sold',
        'LOST_ASSET_CHANGE_FORBIDDEN' => 'lost',
        'ASSIGNED_ASSET_DELETE_FORBIDDEN' => 'assigned',
        'ASSET_HISTORY_DELETE_FORBIDDEN' => 'history',
        'INVALID_ASSET_ID',
        'DUPLICATE_ASSET_ID' => 'invalid_id',
        'ASSET_NOT_FOUND' => 'not_found',
        'ASSET_NOT_ASSIGNABLE',
        'ASSET_NOT_RETURNABLE',
        'ASSET_DELETE_FORBIDDEN',
        'ASSET_STATE_CHANGED' => 'state_changed',
        default => 'save_failed',
    };
}

function dispositionGatewayErrorKey(Throwable $error): string
{
    if ($error instanceof PropertyCoreServiceUnavailableException) {
        return 'service_unavailable';
    }

    if (!$error instanceof PropertyCoreServiceException) {
        return 'save_failed';
    }

    return match ($error->getServiceCode()) {
        'ASSET_NOT_FOUND',
        'DISPOSITION_NOT_FOUND',
        'INVALID_ASSET_ID',
        'INVALID_DISPOSITION_ID' => 'not_found',
        'INVALID_DISPOSITION_INPUT' => 'invalid_input',
        'INVALID_DISPOSITION_TRANSITION' => 'invalid_transition',
        'ASSET_DISPOSITION_NOT_ELIGIBLE',
        'DISPOSITION_APPROVAL_REQUIRED' => 'not_eligible',
        'ASSET_STATE_CHANGED' => 'state_changed',
        default => 'save_failed',
    };
}

function propertyCoreBusinessId(
    mixed $value,
    string $prefix
): ?string {
    $value = trim((string) $value);

    return preg_match(
        '/^' . preg_quote($prefix, '/') . '-\d{6}$/',
        $value
    ) === 1
        ? $value
        : null;
}
