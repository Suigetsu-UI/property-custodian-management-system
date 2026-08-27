<?php

require_once __DIR__ . '/../includes/ai_v2_recommendations.php';

$testsRun = 0;
function assertAiPresentation(bool $condition, string $message): void
{
    global $testsRun;
    $testsRun++;
    if (!$condition) {
        throw new RuntimeException('FAILED: ' . $message);
    }
}

$asset = [
    'id' => 1,
    'asset_id' => 'AST-PRESENTATION',
    'asset_name' => 'Presentation Fixture',
    'category' => 'ICT Equipment',
    'brand' => null,
    'model' => null,
    'acquisition_date' => '2020-01-01',
    'custodian' => null,
    'status' => 'Available',
];
$events = [
    [
        'id' => 1, 'module' => 'Maintenance', 'event_type' => 'Completed',
        'business_id' => 'MNT-PRESENT-1', 'related_business_id' => 'AST-PRESENTATION',
        'event_date' => '2026-07-01', 'to_status' => 'Completed', 'outcome' => 'Corrective',
    ],
    [
        'id' => 2, 'module' => 'Maintenance', 'event_type' => 'Completed',
        'business_id' => 'MNT-PRESENT-2', 'related_business_id' => 'AST-PRESENTATION',
        'event_date' => '2026-08-01', 'to_status' => 'Completed', 'outcome' => 'Corrective',
    ],
];
$audits = [
    [
        'id' => 1, 'audit_id' => 'AUD-PRESENT-1', 'asset_id' => 1,
        'audit_date' => '2026-01-01', 'result' => 'Verified', 'status' => 'Completed',
    ],
    [
        'id' => 2, 'audit_id' => 'AUD-PRESENT-2', 'asset_id' => 1,
        'audit_date' => '2026-08-01', 'result' => 'Damaged', 'status' => 'Completed',
    ],
];
$date = '2026-08-26';
$original = scoreAssetAttention($asset, [], $audits, $events, $date);
$humanized = buildAiV2Analysis($asset, [], $audits, $events, $date);

assertAiPresentation($humanized['score'] === $original['score'], 'Humanized presentation must not change the score.');
assertAiPresentation($humanized['level'] === $original['level'], 'Humanized presentation must not change the attention level.');
assertAiPresentation($humanized['components'] === $original['components'], 'Humanized presentation must not change component points.');
assertAiPresentation($humanized['priority_floor'] === $original['priority_floor'], 'Humanized presentation must not change the priority floor.');
assertAiPresentation($humanized['maintenance_pattern']['classification'] === 'Recurring and recent', 'Maintenance classification must remain unchanged.');
assertAiPresentation($humanized['audit_trend']['classification'] === 'Worsening', 'Audit classification must remain unchanged.');
assertAiPresentation($humanized['evidence_coverage']['label'] === 'Strong', 'Coverage classification must remain unchanged.');
assertAiPresentation($humanized['primary_concern'] === 'Multiple Concerns', 'Primary concern must remain unchanged.');
assertAiPresentation($humanized['presentation']['maintenance_label'] === 'Repeated maintenance needs', 'Maintenance wording must be plain language.');
assertAiPresentation(str_contains($humanized['presentation']['audit_explanation'], 'more concerning'), 'Audit wording must explain what changed.');
assertAiPresentation($humanized['presentation']['coverage']['label'] === 'Good amount of information', 'Coverage wording must avoid statistical language.');
assertAiPresentation(str_ends_with($humanized['recommendation']['title'], '.'), 'Recommendation title must read as a natural sentence.');

$visibleCopy = json_encode([
    $humanized['presentation'],
    $humanized['recommendation'],
]);
foreach (['recency window', 'severity progression', 'evidence signals', 'recommendation rationale'] as $term) {
    assertAiPresentation(!str_contains(strtolower($visibleCopy), $term), 'Visible wording must avoid "' . $term . '".');
}

echo "AI V2 presentation tests passed: {$testsRun}\n";
