<?php

require_once __DIR__ . '/../includes/ai_v2_scenario.php';

$testsRun = 0;
function assertAiScenario(bool $condition, string $message): void
{
    global $testsRun;
    $testsRun++;
    if (!$condition) {
        throw new RuntimeException('FAILED: ' . $message);
    }
}
function expectAiScenarioInvalid(array $input, string $message): void
{
    try {
        validateAiScenarioInput($input);
    } catch (InvalidArgumentException) {
        assertAiScenario(true, $message);
        return;
    }
    assertAiScenario(false, $message);
}

expectAiScenarioInvalid(['audit_result' => 'Destroyed'], 'Invalid Audit result must be rejected.');
expectAiScenarioInvalid(['additional_corrective_cases' => 4], 'Corrective count above three must be rejected.');
expectAiScenarioInvalid(['additional_corrective_cases' => -1], 'Negative corrective count must be rejected.');
expectAiScenarioInvalid(['asset_status' => 'Disposed'], 'Invalid Asset status must be rejected.');

$asset = [
    'id' => 1, 'asset_id' => 'AST-TEST001', 'asset_name' => 'Fixture Asset',
    'category' => 'ICT Equipment', 'brand' => 'Test', 'model' => 'One',
    'acquisition_date' => '2026-01-01', 'custodian' => null, 'status' => 'Available',
];
$current = buildAiV2Analysis($asset, [], [], [], '2026-08-26');
$scenario = simulateAiV2Scenario($asset, [], [], [], '2026-08-26', [
    'audit_result' => 'Damaged', 'additional_corrective_cases' => 2,
    'asset_status' => 'Under Maintenance',
]);
assertAiScenario($current['score'] === 0, 'Baseline fixture must remain unchanged at zero.');
assertAiScenario($scenario['current']['score'] === 0, 'Scenario must report the unchanged current score.');
assertAiScenario($scenario['simulated']['score'] >= 50, 'Damaged simulation must use the V1 priority floor.');
assertAiScenario($scenario['difference'] === $scenario['simulated']['score'], 'Scenario difference must be calculated from the two scores.');
assertAiScenario($scenario['no_records_changed'] === true, 'Scenario must explicitly confirm no records changed.');
assertAiScenario(count($scenario['changed_factors']) >= 2, 'Scenario must explain changed factors.');
assertAiScenario($asset['status'] === 'Available', 'Scenario must not mutate the source Asset array.');

$analyses = buildAiV2Portfolio([
    'assets' => [$asset, array_merge($asset, ['id' => 2, 'asset_id' => 'AST-TEST002'])],
    'maintenance_by_asset' => [], 'audits_by_asset' => [], 'events_by_asset' => [],
], '2026-08-26');
assertAiScenario(count($analyses) === 2 && $analyses[0]['score'] === $analyses[1]['score'], 'Two-Asset comparison data must use the same V2 pipeline.');

$endpoint = file_get_contents(__DIR__ . '/../modules/ai_insights/analyze_scenario.php');
assertAiScenario(str_contains($endpoint, "auth/check_auth.php"), 'Scenario endpoint must require authentication.');
assertAiScenario(str_contains($endpoint, 'isValidAccessCsrfToken'), 'Scenario endpoint must require CSRF validation.');
assertAiScenario(!preg_match('/\\b(?:INSERT|UPDATE|DELETE)\\b/i', $endpoint), 'Scenario endpoint must contain no database mutation statement.');

echo "AI V2 scenario tests passed: {$testsRun}\n";
