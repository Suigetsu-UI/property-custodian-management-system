<?php

require_once __DIR__ . '/ai_v2_interpretation.php';
require_once __DIR__ . '/ai_v2_presentation.php';

function buildAiV2Recommendation(
    string $primaryConcern,
    array $v1,
    array $maintenancePattern,
    array $auditTrend
): array {
    $level = (string) ($v1['level'] ?? 'Low');
    $recommendations = [
        'Audit / Condition' => [
            'code' => 'physical-verification',
            'title' => 'Consider checking this Asset in person.',
            'reason' => 'The latest Audit or condition record may need follow-up.',
            'steps' => [
                'Inspect the Asset\'s current condition and location.',
                'Review the latest completed Audit and its details.',
                'Confirm whether repair or another follow-up is needed.',
                'Record the final decision in the appropriate PCMS module.',
            ],
        ],
        'Recurring Maintenance' => [
            'code' => 'recurring-maintenance-review',
            'title' => 'Review whether the same problem keeps coming back.',
            'reason' => 'This Asset has needed corrective maintenance more than once, including recent cases.',
            'steps' => [
                'Compare the recent maintenance records.',
                'Check whether the repairs involved the same issue.',
                'Inspect the Asset again.',
                'Consider whether further repair is still practical.',
            ],
        ],
        'Maintenance' => [
            'code' => 'maintenance-follow-up',
            'title' => 'Review the recent maintenance work.',
            'reason' => 'Maintenance history is the main reason this Asset currently needs attention.',
            'steps' => [
                'Review the linked maintenance record.',
                'Check the Asset\'s current condition.',
                'Confirm whether another repair or follow-up is needed.',
                'Keep the maintenance result and Asset status current.',
            ],
        ],
        'Asset Status' => [
            'code' => 'status-verification',
            'title' => 'Check why the Asset has its current status.',
            'reason' => 'The current status may require accountability or service follow-up.',
            'steps' => [
                'Confirm the Asset\'s location and physical condition.',
                'Review the record connected to the current status.',
                'Decide the next step after checking the available information.',
                'Follow the school\'s applicable property procedure.',
            ],
        ],
        'Asset Age' => [
            'code' => 'age-review',
            'title' => 'Include this Asset in the next condition review.',
            'reason' => 'The Asset\'s age is the main reason it currently needs attention.',
            'steps' => [
                'Check the Asset during the next scheduled property review.',
                'Review whether it is still serviceable.',
                'Look at its recent maintenance history.',
                'Consider future replacement planning if its condition worsens.',
            ],
        ],
        'Multiple Concerns' => [
            'code' => 'combined-review',
            'title' => 'Review the Asset\'s condition and recent history together.',
            'reason' => 'Several parts of the available records may need attention.',
            'steps' => [
                'Check the Asset in person.',
                'Review its Audit, maintenance, and status history together.',
                'Decide which issue should be handled first.',
                'Record the final decision in the appropriate PCMS module.',
            ],
        ],
        'No Significant Concern' => [
            'code' => 'routine-monitoring',
            'title' => 'Continue normal monitoring.',
            'reason' => 'No major concern was found in the available records.',
            'steps' => [
                'Keep the Asset, Audit, and maintenance records current.',
                'Include the Asset in regular school property checks.',
            ],
        ],
    ];

    $result = $recommendations[$primaryConcern] ??
        $recommendations['No Significant Concern'];
    $reasonDetails = [];

    if (str_starts_with($maintenancePattern['classification'], 'Recurring')) {
        $reasonDetails[] =
            'Corrective maintenance has happened more than once in the available history.';
    }
    if (($auditTrend['classification'] ?? '') === 'Worsening') {
        $reasonDetails[] =
            'The recent completed Audit results have become more concerning.';
    }
    if (in_array($level, ['Critical', 'High'], true)) {
        $reasonDetails[] = 'This Asset currently needs ' . strtolower($level) .
            ' attention based on the recorded information.';
    }

    if ($reasonDetails) {
        $result['reason'] .= ' ' . implode(' ', $reasonDetails);
    }

    return $result;
}

function buildAiV2Analysis(
    array $asset,
    array $maintenanceRows,
    array $auditRows,
    array $events,
    string $analysisDate
): array {
    $v1 = scoreAssetAttention(
        $asset,
        $maintenanceRows,
        $auditRows,
        $events,
        $analysisDate
    );
    $maintenancePattern = analyzeAiMaintenancePattern(
        $maintenanceRows,
        $events,
        $analysisDate
    );
    $auditTrend = analyzeAiAuditTrend($auditRows, $analysisDate);
    $coverage = calculateAiEvidenceCoverage(
        $asset,
        $maintenanceRows,
        $auditRows,
        $events,
        $analysisDate
    );
    $primaryConcern = determineAiPrimaryConcern(
        $asset,
        $v1,
        $maintenancePattern
    );
    $timeline = buildAiEvidenceTimeline(
        $asset,
        $maintenanceRows,
        $auditRows,
        $events,
        $analysisDate
    );
    $presentation = buildAiV2Presentation(
        $v1,
        $maintenancePattern,
        $auditTrend,
        $coverage,
        $primaryConcern
    );

    return array_merge($v1, [
        'interpretation_version' => AI_V2_INTERPRETATION_VERSION,
        'asset_internal_id' => (int) ($asset['id'] ?? 0),
        'asset_id' => (string) ($asset['asset_id'] ?? ''),
        'asset_name' => (string) ($asset['asset_name'] ?? ''),
        'category' => (string) ($asset['category'] ?? ''),
        'brand' => $asset['brand'] ?? null,
        'model' => $asset['model'] ?? null,
        'custodian' => $asset['custodian'] ?? null,
        'current_status' => (string) ($asset['status'] ?? ''),
        'maintenance_pattern' => $maintenancePattern,
        'audit_trend' => $auditTrend,
        'evidence_coverage' => $coverage,
        'primary_concern' => $primaryConcern,
        'recommendation' => buildAiV2Recommendation(
            $primaryConcern,
            $v1,
            $maintenancePattern,
            $auditTrend
        ),
        'presentation' => $presentation,
        'timeline' => $timeline,
        'most_recent_concern_date' => $timeline[0]['date'] ?? '',
    ]);
}

function buildAiV2Portfolio(array $evidence, string $analysisDate): array
{
    $analyses = [];

    foreach ($evidence['assets'] ?? [] as $asset) {
        $internalId = (int) $asset['id'];
        $analyses[] = buildAiV2Analysis(
            $asset,
            $evidence['maintenance_by_asset'][$internalId] ?? [],
            $evidence['audits_by_asset'][$internalId] ?? [],
            $evidence['events_by_asset'][$internalId] ?? [],
            $analysisDate
        );
    }

    usort($analyses, static function (array $left, array $right): int {
        $scoreOrder = ((int) $right['score']) <=> ((int) $left['score']);
        return $scoreOrder !== 0
            ? $scoreOrder
            : strcmp((string) $left['asset_id'], (string) $right['asset_id']);
    });

    return $analyses;
}

function summarizeAiV2Portfolio(array $analyses): array
{
    $summary = [
        'total' => count($analyses),
        'levels' => summarizeAiInsightLevels($analyses),
        'coverage' => ['Strong' => 0, 'Moderate' => 0, 'Limited' => 0],
        'recurring' => 0,
        'highest' => $analyses[0] ?? null,
        'most_common_concern' => 'No Assets analyzed',
    ];
    $concerns = [];

    foreach ($analyses as $analysis) {
        $coverage = (string) ($analysis['evidence_coverage']['label'] ?? 'Limited');
        if (isset($summary['coverage'][$coverage])) {
            $summary['coverage'][$coverage]++;
        }
        if (str_starts_with(
            (string) ($analysis['maintenance_pattern']['classification'] ?? ''),
            'Recurring'
        )) {
            $summary['recurring']++;
        }
        $concern = (string) ($analysis['primary_concern'] ?? 'No Significant Concern');
        $concerns[$concern] = ($concerns[$concern] ?? 0) + 1;
    }

    if ($concerns) {
        uksort($concerns, static function (string $left, string $right) use ($concerns): int {
            $countOrder = $concerns[$right] <=> $concerns[$left];
            return $countOrder !== 0 ? $countOrder : strcmp($left, $right);
        });
        $summary['most_common_concern'] = array_key_first($concerns);
    }

    return $summary;
}
