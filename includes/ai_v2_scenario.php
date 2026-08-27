<?php

require_once __DIR__ . '/ai_v2_recommendations.php';

function getAllowedAiScenarioAuditResults(): array
{
    return ['unchanged', 'Verified', 'For Investigation', 'Damaged', 'Missing'];
}

function getAllowedAiScenarioStatuses(): array
{
    return ['unchanged', 'Available', 'Assigned', 'Under Maintenance', 'Lost'];
}

function validateAiScenarioInput(array $input): array
{
    $auditResult = trim((string) ($input['audit_result'] ?? 'unchanged'));
    $assetStatus = trim((string) ($input['asset_status'] ?? 'unchanged'));
    $correctiveRaw = $input['additional_corrective_cases'] ?? 0;
    $correctiveCount = filter_var(
        $correctiveRaw,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 0, 'max_range' => 3]]
    );

    if (!in_array($auditResult, getAllowedAiScenarioAuditResults(), true)) {
        throw new InvalidArgumentException('The simulated Audit result is not allowed.');
    }
    if (!in_array($assetStatus, getAllowedAiScenarioStatuses(), true)) {
        throw new InvalidArgumentException('The simulated Asset status is not allowed.');
    }
    if ($correctiveCount === false) {
        throw new InvalidArgumentException('Additional corrective cases must be between 0 and 3.');
    }

    return [
        'audit_result' => $auditResult,
        'asset_status' => $assetStatus,
        'additional_corrective_cases' => (int) $correctiveCount,
    ];
}

function simulateAiV2Scenario(
    array $asset,
    array $maintenanceRows,
    array $auditRows,
    array $events,
    string $analysisDate,
    array $input
): array {
    $scenario = validateAiScenarioInput($input);
    $current = buildAiV2Analysis(
        $asset,
        $maintenanceRows,
        $auditRows,
        $events,
        $analysisDate
    );
    $simulatedAsset = $asset;
    $simulatedAudits = $auditRows;
    $simulatedEvents = $events;

    if ($scenario['asset_status'] !== 'unchanged') {
        $simulatedAsset['status'] = $scenario['asset_status'];
    }

    if ($scenario['audit_result'] !== 'unchanged') {
        $simulatedAudits[] = [
            'id' => PHP_INT_MAX,
            'audit_id' => 'SIM-AUDIT',
            'asset_id' => (int) ($asset['id'] ?? 0),
            'audit_date' => $analysisDate,
            'result' => $scenario['audit_result'],
            'status' => 'Completed',
        ];
    }

    for ($index = 1; $index <= $scenario['additional_corrective_cases']; $index++) {
        $simulatedEvents[] = [
            'id' => PHP_INT_MAX - $index,
            'module' => 'Maintenance',
            'event_type' => 'Completed',
            'business_id' => 'SIM-MNT-' . $index,
            'related_business_id' => (string) ($asset['asset_id'] ?? ''),
            'event_date' => $analysisDate,
            'to_status' => 'Completed',
            'outcome' => 'Corrective',
            'description' => 'Read-only simulated corrective case',
        ];
    }

    $simulated = buildAiV2Analysis(
        $simulatedAsset,
        $maintenanceRows,
        $simulatedAudits,
        $simulatedEvents,
        $analysisDate
    );
    $changedFactors = [];

    foreach ($current['components'] as $key => $value) {
        $newValue = (int) ($simulated['components'][$key] ?? 0);
        if ($newValue !== (int) $value) {
            $changedFactors[] = [
                'factor' => $key,
                'current' => (int) $value,
                'simulated' => $newValue,
            ];
        }
    }
    if ($current['primary_concern'] !== $simulated['primary_concern']) {
        $changedFactors[] = [
            'factor' => 'primary_concern',
            'current' => $current['primary_concern'],
            'simulated' => $simulated['primary_concern'],
        ];
    }

    return [
        'asset_id' => (string) ($asset['asset_id'] ?? ''),
        'analysis_date' => $analysisDate,
        'scenario' => $scenario,
        'current' => [
            'score' => $current['score'],
            'level' => $current['level'],
            'primary_concern' => $current['primary_concern'],
        ],
        'simulated' => [
            'score' => $simulated['score'],
            'level' => $simulated['level'],
            'primary_concern' => $simulated['primary_concern'],
            'recommendation' => $simulated['recommendation'],
        ],
        'difference' => $simulated['score'] - $current['score'],
        'changed_factors' => $changedFactors,
        'no_records_changed' => true,
    ];
}
