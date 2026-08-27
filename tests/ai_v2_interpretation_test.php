<?php

require_once __DIR__ . '/../includes/ai_v2_recommendations.php';

$testsRun = 0;

function assertAiV2(bool $condition, string $message): void
{
    global $testsRun;
    $testsRun++;
    if (!$condition) {
        throw new RuntimeException('FAILED: ' . $message);
    }
}

function aiV2Date(string $date): DateTimeImmutable
{
    return new DateTimeImmutable($date, new DateTimeZone('Asia/Manila'));
}

$analysis = aiV2Date('2026-08-26');
$recencyCases = [
    ['2026-08-26', 'Recent'], ['2026-05-28', 'Recent'],
    ['2026-05-27', 'Intermediate'], ['2026-02-27', 'Intermediate'],
    ['2026-02-26', 'Earlier'], ['2025-08-26', 'Earlier'],
    ['2025-08-25', 'Historical context'], ['2026-08-27', null],
];
foreach ($recencyCases as [$date, $expected]) {
    assertAiV2(
        classifyAiEvidenceRecency(aiV2Date($date), $analysis) === $expected,
        $date . ' must classify as ' . ($expected ?? 'future/excluded') . '.'
    );
}

$maintenanceEvent = static fn (string $id, string $date): array => [
    'module' => 'Maintenance', 'event_type' => 'Completed',
    'business_id' => $id, 'event_date' => $date, 'outcome' => 'Corrective',
];
$none = analyzeAiMaintenancePattern([], [], '2026-08-26');
assertAiV2($none['classification'] === 'No corrective pattern', 'Zero corrective cases must have no pattern.');
$isolated = analyzeAiMaintenancePattern([], [$maintenanceEvent('MNT-1', '2025-01-01')], '2026-08-26');
assertAiV2($isolated['classification'] === 'Isolated', 'One old corrective case must remain isolated.');
$duplicate = analyzeAiMaintenancePattern([], [
    $maintenanceEvent('MNT-1', '2026-07-01'), $maintenanceEvent('MNT-1', '2026-07-02'),
], '2026-08-26');
assertAiV2($duplicate['unique_corrective_cases'] === 1, 'Duplicate Maintenance IDs must count once.');
$recurring = analyzeAiMaintenancePattern([], [
    $maintenanceEvent('MNT-1', '2025-10-01'), $maintenanceEvent('MNT-2', '2026-03-01'),
], '2026-08-26');
assertAiV2($recurring['classification'] === 'Recurring', 'Two cases within 365 days must be recurring.');
$recent = analyzeAiMaintenancePattern([], [
    $maintenanceEvent('MNT-1', '2026-08-01'), $maintenanceEvent('MNT-2', '2026-08-20'),
], '2026-08-26');
assertAiV2($recent['classification'] === 'Recurring and recent', 'Two cases within 90 days must be recurring and recent.');
$old = analyzeAiMaintenancePattern([], [
    $maintenanceEvent('MNT-1', '2024-01-01'), $maintenanceEvent('MNT-2', '2024-02-01'),
], '2026-08-26');
assertAiV2($old['classification'] === 'Isolated', 'Old cases must not falsely trigger recurrence.');

$audit = static fn (int $id, string $date, string $result): array => [
    'id' => $id, 'audit_id' => 'AUD-' . $id, 'audit_date' => $date,
    'result' => $result, 'status' => 'Completed',
];
assertAiV2(analyzeAiAuditTrend([$audit(1, '2026-01-01', 'Verified')], '2026-08-26')['classification'] === 'Insufficient History', 'One Audit must be insufficient.');
assertAiV2(analyzeAiAuditTrend([$audit(1, '2026-01-01', 'Verified'), $audit(2, '2026-02-01', 'Damaged')], '2026-08-26')['classification'] === 'Worsening', 'Increasing severity must be worsening.');
assertAiV2(analyzeAiAuditTrend([$audit(1, '2026-01-01', 'Missing'), $audit(2, '2026-02-01', 'Verified')], '2026-08-26')['classification'] === 'Improving', 'Decreasing severity must be improving.');
assertAiV2(analyzeAiAuditTrend([$audit(1, '2026-01-01', 'Verified'), $audit(2, '2026-02-01', 'Verified')], '2026-08-26')['classification'] === 'Stable', 'Equal results must be stable.');
assertAiV2(analyzeAiAuditTrend([
    $audit(1, '2026-01-01', 'Verified'), $audit(2, '2026-02-01', 'Damaged'),
    $audit(3, '2026-03-01', 'For Investigation'),
], '2026-08-26')['classification'] === 'Mixed', 'A direction reversal must be mixed.');
$latestThree = analyzeAiAuditTrend([
    $audit(1, '2025-01-01', 'Missing'), $audit(2, '2026-01-01', 'Verified'),
    $audit(3, '2026-02-01', 'For Investigation'), $audit(4, '2026-03-01', 'Damaged'),
    $audit(5, '2027-01-01', 'Missing'),
], '2026-08-26');
assertAiV2($latestThree['classification'] === 'Worsening' && $latestThree['audits_considered'] === 3, 'Only the latest three non-future Audits must be used.');

$asset = ['id' => 1, 'asset_id' => 'AST-TEST', 'asset_name' => 'Fixture', 'acquisition_date' => null, 'status' => 'Available'];
$coverageLimited = calculateAiEvidenceCoverage($asset, [], [], [], '2026-08-26');
assertAiV2($coverageLimited['label'] === 'Limited', 'Zero coverage signals must be Limited.');
$asset['acquisition_date'] = '2025-01-01';
$coverageModerate = calculateAiEvidenceCoverage($asset, [[
    'scheduled_date' => '2026-01-01',
]], [], [], '2026-08-26');
assertAiV2($coverageModerate['label'] === 'Moderate', 'Two or three coverage signals must be Moderate.');
$coverageStrong = calculateAiEvidenceCoverage($asset, [[
    'scheduled_date' => '2026-01-01',
]], [$audit(1, '2026-02-01', 'Verified')], [[
    'event_date' => '2026-03-01',
]], '2026-08-26');
assertAiV2($coverageStrong['label'] === 'Strong', 'Four coverage signals must be Strong.');

$resolved = resolveAiEvidenceAssetBusinessId(
    ['module' => 'Maintenance', 'business_id' => 'MNT-INDIRECT', 'related_business_id' => null],
    ['AST-000001' => 1], ['MNT-INDIRECT' => 'AST-000001'], []
);
assertAiV2($resolved === 'AST-000001', 'Indirect Maintenance evidence must resolve through the Maintenance record.');
$timeline = buildAiEvidenceTimeline($asset, [], [], [[
    'module' => 'Asset Registry', 'event_type' => 'Status Changed',
    'business_id' => 'AST-TEST', 'event_date' => '2026-08-27',
]], '2026-08-26');
assertAiV2(count($timeline) === 1 && $timeline[0]['summary'] === 'Asset acquisition recorded', 'Future timeline evidence must be excluded.');

echo "AI V2 interpretation tests passed: {$testsRun}\n";
