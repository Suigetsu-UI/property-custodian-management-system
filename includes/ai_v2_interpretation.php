<?php

require_once __DIR__ . '/ai_v2_evidence.php';

function classifyAiEvidenceRecency(
    DateTimeImmutable $evidenceDate,
    DateTimeImmutable $analysisDate
): ?string {
    if ($evidenceDate > $analysisDate) {
        return null;
    }

    $days = (int) $evidenceDate->diff($analysisDate)->format('%a');

    return match (true) {
        $days <= 90 => 'Recent',
        $days <= 180 => 'Intermediate',
        $days <= 365 => 'Earlier',
        default => 'Historical context',
    };
}

function analyzeAiMaintenancePattern(
    array $maintenanceRows,
    array $events,
    string $analysisDate
): array {
    $analysis = parseAiInsightDate($analysisDate);

    if (!$analysis) {
        throw new InvalidArgumentException('A valid analysis date is required.');
    }

    $cases = [];

    foreach ($maintenanceRows as $row) {
        if (($row['maintenance_type'] ?? '') !== 'Corrective') {
            continue;
        }

        $businessId = trim((string) ($row['maintenance_id'] ?? ''));
        $date = parseAiInsightDate($row['scheduled_date'] ?? null);

        if ($businessId !== '' && $date && $date <= $analysis) {
            $cases[$businessId] = $date;
        }
    }

    foreach ($events as $event) {
        if (
            ($event['module'] ?? '') !== 'Maintenance' ||
            ($event['outcome'] ?? '') !== 'Corrective'
        ) {
            continue;
        }

        $businessId = trim((string) ($event['business_id'] ?? ''));
        $date = parseAiInsightDate($event['event_date'] ?? null);

        if ($businessId === '' || !$date || $date > $analysis) {
            continue;
        }

        if (!isset($cases[$businessId]) || $date > $cases[$businessId]) {
            $cases[$businessId] = $date;
        }
    }

    $within90 = 0;
    $within365 = 0;
    $latestDate = null;

    foreach ($cases as $date) {
        $days = (int) $date->diff($analysis)->format('%a');
        $within90 += $days <= 90 ? 1 : 0;
        $within365 += $days <= 365 ? 1 : 0;

        if ($latestDate === null || $date > $latestDate) {
            $latestDate = $date;
        }
    }

    $classification = match (true) {
        $within90 >= 2 => 'Recurring and recent',
        $within365 >= 2 => 'Recurring',
        count($cases) >= 1 => 'Isolated',
        default => 'No corrective pattern',
    };

    return [
        'classification' => $classification,
        'unique_corrective_cases' => count($cases),
        'within_90_days' => $within90,
        'within_365_days' => $within365,
        'latest_date' => $latestDate?->format('Y-m-d'),
    ];
}

function analyzeAiAuditTrend(array $auditRows, string $analysisDate): array
{
    $analysis = parseAiInsightDate($analysisDate);

    if (!$analysis) {
        throw new InvalidArgumentException('A valid analysis date is required.');
    }

    $severity = [
        'Verified' => 1,
        'For Investigation' => 2,
        'Damaged' => 3,
        'Missing' => 4,
    ];
    $eligible = [];

    foreach ($auditRows as $row) {
        $date = parseAiInsightDate($row['audit_date'] ?? null);
        $result = (string) ($row['result'] ?? '');

        if (
            ($row['status'] ?? '') !== 'Completed' ||
            !$date ||
            $date > $analysis ||
            !isset($severity[$result])
        ) {
            continue;
        }

        $row['_severity'] = $severity[$result];
        $eligible[] = $row;
    }

    usort($eligible, static function (array $left, array $right): int {
        $dateOrder = strcmp((string) $left['audit_date'], (string) $right['audit_date']);

        return $dateOrder !== 0
            ? $dateOrder
            : ((int) ($left['id'] ?? 0)) <=> ((int) ($right['id'] ?? 0));
    });

    $eligible = array_slice($eligible, -3);
    $values = array_column($eligible, '_severity');
    $classification = 'Insufficient History';

    if (count($values) >= 2) {
        $increasing = true;
        $decreasing = true;
        $stable = true;

        for ($index = 1; $index < count($values); $index++) {
            $increasing = $increasing && $values[$index] > $values[$index - 1];
            $decreasing = $decreasing && $values[$index] < $values[$index - 1];
            $stable = $stable && $values[$index] === $values[$index - 1];
        }

        $classification = match (true) {
            $increasing => 'Worsening',
            $decreasing => 'Improving',
            $stable => 'Stable',
            default => 'Mixed',
        };
    }

    return [
        'classification' => $classification,
        'audits_considered' => count($eligible),
        'history' => array_map(static fn (array $row): array => [
            'audit_id' => (string) ($row['audit_id'] ?? ''),
            'date' => (string) ($row['audit_date'] ?? ''),
            'result' => (string) ($row['result'] ?? ''),
        ], $eligible),
    ];
}

function calculateAiEvidenceCoverage(
    array $asset,
    array $maintenanceRows,
    array $auditRows,
    array $events,
    string $analysisDate
): array {
    $analysis = parseAiInsightDate($analysisDate);

    if (!$analysis) {
        throw new InvalidArgumentException('A valid analysis date is required.');
    }

    $acquired = parseAiInsightDate($asset['acquisition_date'] ?? null);
    $signals = [
        'acquisition_date' => $acquired !== null && $acquired <= $analysis,
        'maintenance_history' => false,
        'completed_audit' => false,
        'historical_events' => false,
    ];

    foreach ($maintenanceRows as $row) {
        $date = parseAiInsightDate($row['scheduled_date'] ?? null);
        if ($date && $date <= $analysis) {
            $signals['maintenance_history'] = true;
            break;
        }
    }

    foreach ($auditRows as $row) {
        $date = parseAiInsightDate($row['audit_date'] ?? null);
        if (($row['status'] ?? '') === 'Completed' && $date && $date <= $analysis) {
            $signals['completed_audit'] = true;
            break;
        }
    }

    foreach ($events as $event) {
        $date = parseAiInsightDate($event['event_date'] ?? null);
        if ($date && $date <= $analysis) {
            $signals['historical_events'] = true;
            if (($event['module'] ?? '') === 'Maintenance') {
                $signals['maintenance_history'] = true;
            }
        }
    }

    $count = count(array_filter($signals));

    return [
        'label' => match (true) {
            $count === 4 => 'Strong',
            $count >= 2 => 'Moderate',
            default => 'Limited',
        },
        'count' => $count,
        'signals' => $signals,
    ];
}

function determineAiPrimaryConcern(
    array $asset,
    array $v1,
    array $maintenancePattern
): string {
    $conditionCode = (string) ($v1['evidence_summary']['condition_code'] ?? 'normal');
    $concerns = [];

    if (in_array($conditionCode, [
        'completed_missing', 'completed_damaged', 'completed_investigation',
        'provisional_adverse_audit',
    ], true)) {
        $concerns[] = 'Audit / Condition';
    }

    if (str_starts_with($maintenancePattern['classification'], 'Recurring')) {
        $concerns[] = 'Recurring Maintenance';
    } elseif (($v1['components']['corrective_maintenance'] ?? 0) > 0) {
        $concerns[] = 'Maintenance';
    }

    if (
        in_array(($asset['status'] ?? ''), ['Lost', 'Under Maintenance'], true) &&
        str_starts_with($conditionCode, 'current_')
    ) {
        $concerns[] = 'Asset Status';
    }

    if (($v1['components']['asset_age'] ?? 0) > 0) {
        $concerns[] = 'Asset Age';
    }

    $concerns = array_values(array_unique($concerns));

    if (count($concerns) >= 2) {
        return 'Multiple Concerns';
    }

    return $concerns[0] ?? 'No Significant Concern';
}

function buildAiEvidenceTimeline(
    array $asset,
    array $maintenanceRows,
    array $auditRows,
    array $events,
    string $analysisDate
): array {
    $analysis = parseAiInsightDate($analysisDate);

    if (!$analysis) {
        throw new InvalidArgumentException('A valid analysis date is required.');
    }

    $timeline = [];
    $seen = [];

    $append = static function (array $item) use (&$timeline, &$seen, $analysis): void {
        $date = parseAiInsightDate($item['date'] ?? null);
        if (!$date || $date > $analysis) {
            return;
        }

        $key = implode('|', [
            $item['type'] ?? '', $item['business_id'] ?? '',
            $item['date'] ?? '', $item['summary'] ?? '',
        ]);
        if (isset($seen[$key])) {
            return;
        }

        $seen[$key] = true;
        $item['recency'] = classifyAiEvidenceRecency($date, $analysis);
        $timeline[] = $item;
    };

    foreach ($events as $event) {
        $module = (string) ($event['module'] ?? '');
        $eventType = (string) ($event['event_type'] ?? 'Activity');
        $outcome = trim((string) ($event['outcome'] ?? ''));
        $toStatus = trim((string) ($event['to_status'] ?? ''));
        $detail = $outcome !== '' ? $outcome : $toStatus;
        $append([
            'date' => (string) ($event['event_date'] ?? ''),
            'type' => $module,
            'business_id' => (string) ($event['business_id'] ?? ''),
            'summary' => trim($eventType . ($detail !== '' ? ': ' . $detail : '')),
        ]);
    }

    $eventBusinessIds = array_fill_keys(array_map(
        static fn (array $event): string => (string) ($event['business_id'] ?? ''),
        $events
    ), true);

    foreach ($maintenanceRows as $row) {
        $businessId = (string) ($row['maintenance_id'] ?? '');
        if (isset($eventBusinessIds[$businessId])) {
            continue;
        }
        $append([
            'date' => (string) ($row['scheduled_date'] ?? ''),
            'type' => 'Maintenance',
            'business_id' => $businessId,
            'summary' => trim((string) ($row['maintenance_type'] ?? '') .
                ' Maintenance: ' . (string) ($row['status'] ?? 'Recorded')),
        ]);
    }

    foreach ($auditRows as $row) {
        $businessId = (string) ($row['audit_id'] ?? '');
        if (isset($eventBusinessIds[$businessId])) {
            continue;
        }
        $append([
            'date' => (string) ($row['audit_date'] ?? ''),
            'type' => 'Audit',
            'business_id' => $businessId,
            'summary' => trim((string) ($row['status'] ?? 'Recorded') .
                ' Audit: ' . (string) ($row['result'] ?? 'No result')),
        ]);
    }

    $acquired = (string) ($asset['acquisition_date'] ?? '');
    $hasRegistration = false;
    foreach ($events as $event) {
        if (($event['module'] ?? '') === 'Asset Registry' &&
            ($event['event_type'] ?? '') === 'Registered') {
            $hasRegistration = true;
            break;
        }
    }
    if (!$hasRegistration && $acquired !== '') {
        $append([
            'date' => $acquired,
            'type' => 'Asset Registry',
            'business_id' => (string) ($asset['asset_id'] ?? ''),
            'summary' => 'Asset acquisition recorded',
        ]);
    }

    usort($timeline, static function (array $left, array $right): int {
        $dateOrder = strcmp((string) $right['date'], (string) $left['date']);
        return $dateOrder !== 0
            ? $dateOrder
            : strcmp((string) $right['business_id'], (string) $left['business_id']);
    });

    return $timeline;
}
