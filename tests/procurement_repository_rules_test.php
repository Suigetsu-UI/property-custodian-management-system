<?php

require_once __DIR__ . '/../services/procurement/src/ProcurementRepository.php';

$testsRun = 0;

function assertProcurementRules(bool $condition, string $message): void
{
    global $testsRun;
    $testsRun++;

    if (!$condition) {
        throw new RuntimeException('FAILED: ' . $message);
    }
}

function expectProcurementRuleError(
    ReflectionMethod $method,
    object $repository,
    array $input,
    string $expectedCode
): void {
    try {
        $method->invoke($repository, $input, true);
        assertProcurementRules(false, 'Expected rule error ' . $expectedCode . '.');
    } catch (ProcurementDomainException $error) {
        assertProcurementRules(
            $error->getDomainCode() === $expectedCode,
            'Expected ' . $expectedCode . ', received ' . $error->getDomainCode() . '.'
        );
    }
}

$reflection = new ReflectionClass(ProcurementRepository::class);
$repository = $reflection->newInstanceWithoutConstructor();
$normalize = $reflection->getMethod('normalizeInput');
$valid = [
    'procurement_id' => 'PRC-000900',
    'item_name' => 'Laptop',
    'category' => 'Computer',
    'quantity' => 2,
    'supplier' => 'Supplier A',
    'requested_by' => 'Custodian',
    'request_date' => '2026-08-28',
    'status' => 'Pending',
    'approved_by' => '',
    'approval_date' => '',
    'delivery_date' => '2026-08-29',
    'remarks' => '',
];

$normalized = $normalize->invoke($repository, $valid, true);
assertProcurementRules($normalized['quantity'] === 2, 'Positive quantity must be preserved.');
assertProcurementRules($normalized['delivery_date'] === null, 'Non-delivered records must clear Delivery Date.');
assertProcurementRules($normalized['procurement_id'] === 'PRC-000900', 'Create must preserve the issued business ID.');

expectProcurementRuleError(
    $normalize,
    $repository,
    array_replace($valid, ['status' => 'Unknown']),
    'INVALID_STATUS'
);

$invalidQuantity = $valid;
$invalidQuantity['quantity'] = 0;
expectProcurementRuleError(
    $normalize,
    $repository,
    $invalidQuantity,
    'INVALID_PROCUREMENT_INPUT'
);

$deliveredWithoutDate = $valid;
$deliveredWithoutDate['status'] = 'Delivered';
$deliveredWithoutDate['delivery_date'] = '';
expectProcurementRuleError(
    $normalize,
    $repository,
    $deliveredWithoutDate,
    'DELIVERY_DATE_REQUIRED'
);

$invalidDate = $valid;
$invalidDate['request_date'] = '2026-02-31';
expectProcurementRuleError(
    $normalize,
    $repository,
    $invalidDate,
    'INVALID_DATE'
);

$transitions = $reflection->getReflectionConstant('ALLOWED_TRANSITIONS')->getValue();
assertProcurementRules($transitions['Delivered'] === ['Delivered'], 'Delivered must remain terminal.');
assertProcurementRules(!in_array('Delivered', $transitions['Rejected'], true), 'Rejected must not transition directly to Delivered.');
assertProcurementRules(in_array('Delivered', $transitions['Approved'], true), 'Approved must be deliverable.');

echo "Procurement repository rule tests passed: {$testsRun}" . PHP_EOL;
