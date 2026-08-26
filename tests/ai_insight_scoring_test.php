<?php

require_once __DIR__ . '/../includes/ai_insight_functions.php';

$analysisDate = '2026-08-26';
$testsRun = 0;

function assertAiInsight(
    bool $condition,
    string $message
): void {
    global $testsRun;
    $testsRun++;

    if (!$condition) {
        throw new RuntimeException(
            'FAILED: ' . $message
        );
    }
}

function fixtureAsset(array $overrides = []): array
{
    return array_merge(
        [
            'id' => 1,
            'asset_id' => 'AST-TEST001',
            'asset_name' => 'Fixture Asset',
            'acquisition_date' => '2026-01-01',
            'status' => 'Available',
        ],
        $overrides
    );
}

function fixtureAudit(
    string $result,
    string $status = 'Completed',
    string $date = '2026-08-01',
    int $id = 1
): array {
    return [
        'id' => $id,
        'audit_id' => 'AUD-TEST' . $id,
        'asset_id' => 1,
        'audit_date' => $date,
        'result' => $result,
        'status' => $status,
    ];
}

function fixtureMaintenanceEvent(
    string $businessId,
    string $eventType = 'Completed',
    string $date = '2026-08-01',
    int $id = 1
): array {
    return [
        'id' => $id,
        'module' => 'Maintenance',
        'event_type' => $eventType,
        'business_id' => $businessId,
        'related_business_id' => 'AST-TEST001',
        'event_date' => $date,
        'to_status' => $eventType,
        'outcome' => 'Corrective',
    ];
}

function fixtureStatusEvent(
    int $id,
    string $relatedId,
    string $status = 'Under Maintenance'
): array {
    return [
        'id' => $id,
        'module' => 'Asset Registry',
        'event_type' => 'Status Changed',
        'business_id' => 'AST-TEST001',
        'related_business_id' => $relatedId,
        'event_date' => '2026-08-01',
        'to_status' => $status,
        'outcome' => null,
    ];
}

$low = scoreAssetAttention(
    fixtureAsset(),
    [],
    [],
    [],
    $analysisDate
);
assertAiInsight(
    $low['score'] === 0 && $low['level'] === 'Low',
    'A baseline Asset must score Low at zero.'
);

$investigation = scoreAssetAttention(
    fixtureAsset(),
    [],
    [fixtureAudit('For Investigation')],
    [],
    $analysisDate
);
assertAiInsight(
    $investigation['score'] >= 25,
    'Completed For Investigation must apply a minimum score of 25.'
);

$damaged = scoreAssetAttention(
    fixtureAsset(),
    [],
    [fixtureAudit('Damaged')],
    [],
    $analysisDate
);
assertAiInsight(
    $damaged['score'] >= 50,
    'Completed Damaged must apply a minimum score of 50.'
);

$missing = scoreAssetAttention(
    fixtureAsset(),
    [],
    [fixtureAudit('Missing')],
    [],
    $analysisDate
);
assertAiInsight(
    $missing['score'] >= 75,
    'Completed Missing must apply a minimum score of 75.'
);

$lost = scoreAssetAttention(
    fixtureAsset(['status' => 'Lost']),
    [],
    [],
    [],
    $analysisDate
);
assertAiInsight(
    $lost['score'] >= 75,
    'Current Lost status must apply a minimum score of 75.'
);

$correctiveRows = [
    [
        'maintenance_id' => 'MNT-TEST003',
        'maintenance_type' => 'Corrective',
        'scheduled_date' => '2026-08-20',
        'status' => 'In Progress',
    ],
];
$correctiveEvents = [
    fixtureMaintenanceEvent('MNT-TEST001', 'Completed', '2026-04-01', 1),
    fixtureMaintenanceEvent('MNT-TEST002', 'Completed', '2026-06-01', 2),
    fixtureMaintenanceEvent('MNT-TEST003', 'Started', '2026-08-20', 3),
];
$corrective = scoreAssetAttention(
    fixtureAsset(),
    $correctiveRows,
    [],
    $correctiveEvents,
    $analysisDate
);
assertAiInsight(
    $corrective['components']['corrective_maintenance'] === 40,
    'Three corrective cases plus active work must cap at 40 points.'
);

$recurringEvents = [
    fixtureStatusEvent(1, 'MNT-TEST001'),
    fixtureStatusEvent(2, 'AUD-TEST001', 'Lost'),
    fixtureStatusEvent(3, 'MNT-TEST002'),
];
$recurring = scoreAssetAttention(
    fixtureAsset(),
    [],
    [],
    $recurringEvents,
    $analysisDate
);
assertAiInsight(
    $recurring['components']['recurring_attention'] === 10,
    'Three distinct attention transitions must contribute 10 points.'
);

$duplicateEvents = [
    fixtureMaintenanceEvent('MNT-DUPLICATE', 'Started', '2026-07-01', 1),
    fixtureMaintenanceEvent('MNT-DUPLICATE', 'Completed', '2026-07-03', 2),
];
$duplicate = scoreAssetAttention(
    fixtureAsset(),
    [],
    [],
    $duplicateEvents,
    $analysisDate
);
assertAiInsight(
    $duplicate['evidence_summary']['corrective_case_count'] === 1,
    'Duplicate Maintenance business IDs must count as one case.'
);

$futureScheduled = scoreAssetAttention(
    fixtureAsset(),
    [[
        'maintenance_id' => 'MNT-FUTURE',
        'maintenance_type' => 'Corrective',
        'scheduled_date' => '2026-09-01',
        'status' => 'Scheduled',
    ]],
    [],
    [],
    $analysisDate
);
assertAiInsight(
    $futureScheduled['components']['corrective_maintenance'] === 0,
    'Future scheduled Maintenance must be ignored.'
);

$missingAcquisition = scoreAssetAttention(
    fixtureAsset(['acquisition_date' => null]),
    [],
    [],
    [],
    $analysisDate
);
assertAiInsight(
    $missingAcquisition['components']['asset_age'] === 0 &&
    count($missingAcquisition['data_quality_notices']) === 1,
    'Missing acquisition date must produce zero points and a notice.'
);

$futureAcquisition = scoreAssetAttention(
    fixtureAsset(['acquisition_date' => '2026-09-01']),
    [],
    [],
    [],
    $analysisDate
);
assertAiInsight(
    $futureAcquisition['components']['asset_age'] === 0 &&
    count($futureAcquisition['data_quality_notices']) === 1,
    'Future acquisition date must produce zero points and a notice.'
);

$noDoubleCondition = scoreAssetAttention(
    fixtureAsset(['status' => 'Under Maintenance']),
    [],
    [fixtureAudit('Damaged')],
    [],
    $analysisDate
);
assertAiInsight(
    $noDoubleCondition['components']['audit_condition'] === 25,
    'Damaged and Under Maintenance must contribute condition points once.'
);
assertAiInsight(
    $noDoubleCondition['primary_factor'] === 'Audit / current condition',
    'A tied direct condition must be presented as the primary factor.'
);

$bounded = applyAttentionPriorityFloor(120, null);
assertAiInsight(
    $bounded['score'] === 100,
    'Attention Score must be capped at 100.'
);

echo "AI insight scoring tests passed: {$testsRun}\n";
