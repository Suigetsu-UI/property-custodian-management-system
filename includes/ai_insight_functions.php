<?php

/*
|--------------------------------------------------------------------------
| AI-Assisted Asset Attention
|--------------------------------------------------------------------------
| The scoring functions in this file are deterministic and explainable.
| They accept arrays and return arrays so they can be tested without HTML
| or a database connection. The evidence loader uses four bulk read queries.
*/

const AI_INSIGHT_RULE_VERSION = 'AIAA-V1.0';

function getAiInsightToday(): string
{
    return (new DateTimeImmutable(
        'now',
        new DateTimeZone('Asia/Manila')
    ))->format('Y-m-d');
}

function parseAiInsightDate(?string $value): ?DateTimeImmutable
{
    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $value,
        new DateTimeZone('Asia/Manila')
    );

    return $date instanceof DateTimeImmutable &&
        $date->format('Y-m-d') === $value
            ? $date
            : null;
}

function calculateAssetAgeAttention(
    array $asset,
    string $analysisDate
): array {
    $analysis = parseAiInsightDate($analysisDate);
    $acquired = parseAiInsightDate(
        $asset['acquisition_date'] ?? null
    );

    if (!$analysis) {
        throw new InvalidArgumentException(
            'A valid analysis date is required.'
        );
    }

    if (!$acquired) {
        return [
            'points' => 0,
            'years' => null,
            'factors' => [],
            'notices' => [
                'Acquisition date is unavailable; no age points were assigned.',
            ],
        ];
    }

    if ($acquired > $analysis) {
        return [
            'points' => 0,
            'years' => null,
            'factors' => [],
            'notices' => [
                'Acquisition date is after the analysis date; no age points were assigned.',
            ],
        ];
    }

    $years = $acquired->diff($analysis)->y;
    $points = match (true) {
        $years >= 7 => 15,
        $years >= 5 => 10,
        $years >= 3 => 5,
        default => 0,
    };

    $factors = [];

    if ($points > 0) {
        $factors[] = match ($points) {
            15 => 'Asset age is seven years or more.',
            10 => 'Asset age is between five and seven years.',
            default => 'Asset age is between three and five years.',
        };
    }

    return [
        'points' => $points,
        'years' => $years,
        'factors' => $factors,
        'notices' => [],
    ];
}

function calculateCorrectiveMaintenanceAttention(
    array $maintenanceRows,
    array $events,
    string $analysisDate
): array {
    $analysis = parseAiInsightDate($analysisDate);

    if (!$analysis) {
        throw new InvalidArgumentException(
            'A valid analysis date is required.'
        );
    }

    $caseIds = [];

    foreach ($events as $event) {
        if (
            ($event['module'] ?? '') !== 'Maintenance' ||
            ($event['outcome'] ?? '') !== 'Corrective' ||
            !in_array(
                $event['event_type'] ?? '',
                ['Started', 'Completed'],
                true
            )
        ) {
            continue;
        }

        $eventDate = parseAiInsightDate(
            $event['event_date'] ?? null
        );

        if (!$eventDate || $eventDate > $analysis) {
            continue;
        }

        $windowStart = $analysis->modify('-365 days');

        if ($eventDate < $windowStart) {
            continue;
        }

        $businessId = trim((string) (
            $event['business_id'] ?? ''
        ));

        if ($businessId !== '') {
            $caseIds[$businessId] = true;
        }
    }

    $activeBonus = 0;
    $activeStatus = null;
    $missingHistory = false;

    foreach ($maintenanceRows as $row) {
        if (($row['maintenance_type'] ?? '') !== 'Corrective') {
            continue;
        }

        $status = (string) ($row['status'] ?? '');
        $maintenanceId = trim((string) (
            $row['maintenance_id'] ?? ''
        ));

        if ($status === 'In Progress' && $activeBonus < 10) {
            $activeBonus = 10;
            $activeStatus = 'In Progress';
        }

        if ($status === 'Scheduled' && $activeBonus < 5) {
            $scheduledDate = parseAiInsightDate(
                $row['scheduled_date'] ?? null
            );

            if ($scheduledDate && $scheduledDate <= $analysis) {
                $activeBonus = 5;
                $activeStatus = 'Scheduled';
            }
        }

        if (
            $status === 'Completed' &&
            $maintenanceId !== '' &&
            !isset($caseIds[$maintenanceId])
        ) {
            $scheduledDate = parseAiInsightDate(
                $row['scheduled_date'] ?? null
            );

            if ($scheduledDate && $scheduledDate <= $analysis) {
                $missingHistory = true;
            }
        }
    }

    $caseCount = count($caseIds);
    $casePoints = min(30, $caseCount * 10);
    $points = min(40, $casePoints + $activeBonus);
    $factors = [];

    if ($caseCount > 0) {
        $factors[] = sprintf(
            '%d distinct corrective maintenance %s recorded within the last 365 days.',
            $caseCount,
            $caseCount === 1 ? 'case was' : 'cases were'
        );
    }

    if ($activeStatus !== null) {
        $factors[] = sprintf(
            'A corrective maintenance record is currently %s.',
            strtolower($activeStatus)
        );
    }

    $notices = [];

    if ($missingHistory) {
        $notices[] =
            'A completed corrective record lacks a qualifying historical event and was not counted as a completed case.';
    }

    return [
        'points' => $points,
        'case_count' => $caseCount,
        'active_bonus' => $activeBonus,
        'active_status' => $activeStatus,
        'factors' => $factors,
        'notices' => $notices,
    ];
}

function calculateConditionAttention(
    array $asset,
    array $auditRows,
    string $analysisDate
): array {
    $analysis = parseAiInsightDate($analysisDate);

    if (!$analysis) {
        throw new InvalidArgumentException(
            'A valid analysis date is required.'
        );
    }

    $candidates = [];
    $assetStatus = (string) ($asset['status'] ?? '');

    if ($assetStatus === 'Lost') {
        $candidates[] = [
            'points' => 35,
            'code' => 'current_lost',
            'factor' => 'Current Asset status is Lost.',
            'floor' => 75,
            'floor_reason' => 'Current Asset status is Lost',
        ];
    } elseif ($assetStatus === 'Under Maintenance') {
        $candidates[] = [
            'points' => 25,
            'code' => 'current_under_maintenance',
            'factor' => 'Current Asset status is Under Maintenance.',
            'floor' => null,
            'floor_reason' => null,
        ];
    }

    $eligibleAudits = array_values(array_filter(
        $auditRows,
        static function (array $row) use ($analysis): bool {
            $auditDate = parseAiInsightDate(
                $row['audit_date'] ?? null
            );

            return $auditDate instanceof DateTimeImmutable &&
                $auditDate <= $analysis;
        }
    ));

    usort(
        $eligibleAudits,
        static function (array $left, array $right): int {
            $dateOrder = strcmp(
                (string) ($right['audit_date'] ?? ''),
                (string) ($left['audit_date'] ?? '')
            );

            if ($dateOrder !== 0) {
                return $dateOrder;
            }

            return ((int) ($right['id'] ?? 0)) <=>
                ((int) ($left['id'] ?? 0));
        }
    );

    $latestCompleted = null;

    foreach ($eligibleAudits as $row) {
        if (($row['status'] ?? '') === 'Completed') {
            $latestCompleted = $row;
            break;
        }
    }

    if ($latestCompleted) {
        $completedCandidate = match (
            (string) ($latestCompleted['result'] ?? '')
        ) {
            'Missing' => [
                'points' => 35,
                'code' => 'completed_missing',
                'factor' => 'The latest completed Audit recorded a Missing finding.',
                'floor' => 75,
                'floor_reason' => 'Completed Missing audit finding',
            ],
            'Damaged' => [
                'points' => 25,
                'code' => 'completed_damaged',
                'factor' => 'The latest completed Audit recorded a Damaged finding.',
                'floor' => 50,
                'floor_reason' => 'Completed Damaged audit finding',
            ],
            'For Investigation' => [
                'points' => 15,
                'code' => 'completed_investigation',
                'factor' => 'The latest completed Audit requires further investigation.',
                'floor' => 25,
                'floor_reason' => 'Completed For Investigation audit finding',
            ],
            default => null,
        };

        if ($completedCandidate !== null) {
            $candidates[] = $completedCandidate;
        }
    }

    foreach ($eligibleAudits as $row) {
        if (
            ($row['status'] ?? '') === 'Completed' ||
            !in_array(
                $row['result'] ?? '',
                ['Missing', 'Damaged', 'For Investigation'],
                true
            )
        ) {
            continue;
        }

        $candidates[] = [
            'points' => 10,
            'code' => 'provisional_adverse_audit',
            'factor' => sprintf(
                'An incomplete Audit has a provisional %s finding.',
                (string) $row['result']
            ),
            'floor' => null,
            'floor_reason' => null,
        ];

        break;
    }

    usort(
        $candidates,
        static function (array $left, array $right): int {
            $pointOrder = $right['points'] <=> $left['points'];

            if ($pointOrder !== 0) {
                return $pointOrder;
            }

            return ((int) ($right['floor'] ?? 0)) <=>
                ((int) ($left['floor'] ?? 0));
        }
    );

    $strongest = $candidates[0] ?? [
        'points' => 0,
        'code' => 'normal',
        'factor' => null,
        'floor' => null,
        'floor_reason' => null,
    ];

    return [
        'points' => (int) $strongest['points'],
        'code' => $strongest['code'],
        'priority_floor' => $strongest['floor'],
        'priority_floor_reason' => $strongest['floor_reason'],
        'factors' => $strongest['factor'] !== null
            ? [$strongest['factor']]
            : [],
        'notices' => [],
    ];
}

function calculateRecurringAttention(
    array $events,
    string $analysisDate
): array {
    $analysis = parseAiInsightDate($analysisDate);

    if (!$analysis) {
        throw new InvalidArgumentException(
            'A valid analysis date is required.'
        );
    }

    $windowStart = $analysis->modify('-365 days');
    $transitionKeys = [];

    foreach ($events as $event) {
        if (
            ($event['module'] ?? '') !== 'Asset Registry' ||
            ($event['event_type'] ?? '') !== 'Status Changed' ||
            !in_array(
                $event['to_status'] ?? '',
                ['Under Maintenance', 'Lost'],
                true
            )
        ) {
            continue;
        }

        $eventDate = parseAiInsightDate(
            $event['event_date'] ?? null
        );

        if (
            !$eventDate ||
            $eventDate < $windowStart ||
            $eventDate > $analysis
        ) {
            continue;
        }

        $relatedId = trim((string) (
            $event['related_business_id'] ?? ''
        ));

        $key = $relatedId !== ''
            ? 'related:' . $relatedId
            : 'event:' . (string) ($event['id'] ?? '') . ':' .
                (string) ($event['event_date'] ?? '') . ':' .
                (string) ($event['to_status'] ?? '');

        $transitionKeys[$key] = true;
    }

    $transitionCount = count($transitionKeys);
    $points = match (true) {
        $transitionCount >= 3 => 10,
        $transitionCount >= 2 => 5,
        default => 0,
    };

    return [
        'points' => $points,
        'transition_count' => $transitionCount,
        'factors' => $points > 0
            ? [sprintf(
                '%d separate transitions into an attention state were detected within the last 365 days.',
                $transitionCount
            )]
            : [],
        'notices' => [],
    ];
}

function getAttentionLevel(int $score): string
{
    return match (true) {
        $score >= 75 => 'Critical',
        $score >= 50 => 'High',
        $score >= 25 => 'Moderate',
        default => 'Low',
    };
}

function applyAttentionPriorityFloor(
    int $baseScore,
    ?int $minimum,
    ?string $reason = null
): array {
    $boundedBase = min(100, max(0, $baseScore));
    $validMinimum = $minimum !== null
        ? min(100, max(0, $minimum))
        : null;
    $finalScore = $validMinimum !== null
        ? max($boundedBase, $validMinimum)
        : $boundedBase;

    return [
        'score' => min(100, $finalScore),
        'priority_floor' => [
            'required' => $validMinimum !== null,
            'applied' => $validMinimum !== null &&
                $boundedBase < $validMinimum,
            'minimum' => $validMinimum,
            'reason' => $reason,
        ],
    ];
}

function buildSuggestedHumanAction(
    string $level,
    array $condition,
    array $maintenance
): string {
    return match (true) {
        in_array(
            $condition['code'],
            ['current_lost', 'completed_missing'],
            true
        ) =>
            'Urgently verify the Asset location, accountability, and applicable institutional loss procedure.',

        $condition['code'] === 'completed_damaged' =>
            'Prioritize physical inspection and assess whether repair or replacement should be considered.',

        ($maintenance['case_count'] ?? 0) >= 2 =>
            'Inspect the Asset and review whether recurring corrective work indicates a deeper issue.',

        in_array(
            $condition['code'],
            ['completed_investigation', 'provisional_adverse_audit'],
            true
        ) =>
            'Review the recent Asset history and consider a physical inspection before deciding any action.',

        $condition['code'] === 'current_under_maintenance' =>
            'Review the active Maintenance record and verify the Asset condition before returning it to service.',

        $level === 'High' || $level === 'Critical' =>
            'Prioritize the Asset for physical inspection and review its Maintenance and Audit history.',

        $level === 'Moderate' =>
            'Review the Asset history during the next scheduled property check and consider physical inspection.',

        default =>
            'Continue routine monitoring and keep the Asset record current.',
    };
}

function scoreAssetAttention(
    array $asset,
    array $maintenanceRows,
    array $auditRows,
    array $events,
    string $analysisDate
): array {
    $age = calculateAssetAgeAttention(
        $asset,
        $analysisDate
    );
    $maintenance = calculateCorrectiveMaintenanceAttention(
        $maintenanceRows,
        $events,
        $analysisDate
    );
    $condition = calculateConditionAttention(
        $asset,
        $auditRows,
        $analysisDate
    );
    $recurring = calculateRecurringAttention(
        $events,
        $analysisDate
    );

    $components = [
        'asset_age' => $age['points'],
        'corrective_maintenance' => $maintenance['points'],
        'audit_condition' => $condition['points'],
        'recurring_attention' => $recurring['points'],
    ];

    $baseScore = array_sum($components);
    $finalized = applyAttentionPriorityFloor(
        $baseScore,
        $condition['priority_floor'],
        $condition['priority_floor_reason']
    );
    $score = $finalized['score'];
    $level = getAttentionLevel($score);
    $factors = array_values(array_merge(
        $age['factors'],
        $maintenance['factors'],
        $condition['factors'],
        $recurring['factors']
    ));

    if (empty($factors)) {
        $factors[] =
            'No elevated attention factors were detected from the recorded information.';
    }

    $notices = array_values(array_unique(array_merge(
        $age['notices'],
        $maintenance['notices'],
        $condition['notices'],
        $recurring['notices']
    )));

    $componentLabels = [
        'asset_age' => 'Asset age',
        'corrective_maintenance' => 'Corrective maintenance',
        'audit_condition' => 'Audit / current condition',
        'recurring_attention' => 'Recurring attention states',
    ];
    $primaryOrder = [
        'audit_condition',
        'corrective_maintenance',
        'recurring_attention',
        'asset_age',
    ];
    $primaryKey = $primaryOrder[0];

    foreach ($primaryOrder as $key) {
        if ($components[$key] > $components[$primaryKey]) {
            $primaryKey = $key;
        }
    }

    $primaryFactor = max($components) > 0
        ? $componentLabels[$primaryKey]
        : 'No elevated factor';

    return [
        'rule_version' => AI_INSIGHT_RULE_VERSION,
        'analysis_date' => $analysisDate,
        'score' => $score,
        'base_score' => min(100, $baseScore),
        'level' => $level,
        'components' => $components,
        'priority_floor' => $finalized['priority_floor'],
        'factors' => $factors,
        'data_quality_notices' => $notices,
        'primary_factor' => $primaryFactor,
        'suggested_action' => buildSuggestedHumanAction(
            $level,
            $condition,
            $maintenance
        ),
        'evidence_summary' => [
            'asset_age_years' => $age['years'],
            'corrective_case_count' => $maintenance['case_count'],
            'active_corrective_status' => $maintenance['active_status'],
            'condition_code' => $condition['code'],
            'attention_transition_count' => $recurring['transition_count'],
        ],
    ];
}

function loadAiInsightEvidence(
    PDO $pdo,
    string $analysisDate
): array {
    if (!parseAiInsightDate($analysisDate)) {
        throw new InvalidArgumentException(
            'A valid analysis date is required.'
        );
    }

    $assetStatement = $pdo->query(
        "SELECT
            id,
            asset_id,
            asset_name,
            category,
            brand,
            model,
            acquisition_date,
            custodian,
            status
         FROM assets
         WHERE status <> 'Sold'
         ORDER BY asset_id"
    );
    $assets = $assetStatement->fetchAll();

    $maintenanceStatement = $pdo->query(
        "SELECT
            m.id,
            m.maintenance_id,
            m.asset_id,
            m.maintenance_type,
            m.scheduled_date,
            m.status
         FROM maintenance m
         WHERE m.maintenance_type = 'Corrective'
         ORDER BY m.id"
    );
    $maintenanceRows = $maintenanceStatement->fetchAll();

    $auditStatement = $pdo->prepare(
        "SELECT
            au.id,
            au.audit_id,
            au.asset_id,
            au.audit_date,
            au.result,
            au.status
         FROM audits au
         WHERE au.audit_date <= :analysis_date
         ORDER BY au.audit_date DESC, au.id DESC"
    );
    $auditStatement->execute([
        'analysis_date' => $analysisDate,
    ]);
    $auditRows = $auditStatement->fetchAll();

    $eventStatement = $pdo->prepare(
        "SELECT
            id,
            module,
            event_type,
            business_id,
            related_business_id,
            event_date,
            to_status,
            outcome
         FROM property_events
         WHERE event_date <= :analysis_date
           AND (
                (
                    module = 'Maintenance'
                    AND event_type IN ('Started', 'Completed')
                )
                OR
                (
                    module = 'Asset Registry'
                    AND event_type = 'Status Changed'
                    AND to_status IN ('Under Maintenance', 'Lost')
                )
           )
         ORDER BY event_date, id"
    );
    $eventStatement->execute([
        'analysis_date' => $analysisDate,
    ]);
    $events = $eventStatement->fetchAll();

    $maintenanceByAsset = [];

    foreach ($maintenanceRows as $row) {
        $maintenanceByAsset[(int) $row['asset_id']][] = $row;
    }

    $auditsByAsset = [];

    foreach ($auditRows as $row) {
        $auditsByAsset[(int) $row['asset_id']][] = $row;
    }

    $assetInternalIds = [];

    foreach ($assets as $asset) {
        $assetInternalIds[$asset['asset_id']] = (int) $asset['id'];
    }

    $eventsByAsset = [];

    foreach ($events as $event) {
        $businessAssetId = ($event['module'] ?? '') === 'Maintenance'
            ? (string) ($event['related_business_id'] ?? '')
            : (string) ($event['business_id'] ?? '');

        if (isset($assetInternalIds[$businessAssetId])) {
            $eventsByAsset[$assetInternalIds[$businessAssetId]][] = $event;
        }
    }

    return [
        'assets' => $assets,
        'maintenance_by_asset' => $maintenanceByAsset,
        'audits_by_asset' => $auditsByAsset,
        'events_by_asset' => $eventsByAsset,
    ];
}

function buildAiInsightAnalyses(
    array $evidence,
    string $analysisDate
): array {
    $analyses = [];

    foreach ($evidence['assets'] ?? [] as $asset) {
        $internalId = (int) $asset['id'];
        $analysis = scoreAssetAttention(
            $asset,
            $evidence['maintenance_by_asset'][$internalId] ?? [],
            $evidence['audits_by_asset'][$internalId] ?? [],
            $evidence['events_by_asset'][$internalId] ?? [],
            $analysisDate
        );

        $analyses[] = array_merge(
            $analysis,
            [
                'asset_internal_id' => $internalId,
                'asset_id' => $asset['asset_id'],
                'asset_name' => $asset['asset_name'],
                'category' => $asset['category'],
                'brand' => $asset['brand'],
                'model' => $asset['model'],
                'custodian' => $asset['custodian'],
                'current_status' => $asset['status'],
            ]
        );
    }

    usort(
        $analyses,
        static function (array $left, array $right): int {
            $scoreOrder = $right['score'] <=> $left['score'];

            return $scoreOrder !== 0
                ? $scoreOrder
                : strcmp($left['asset_id'], $right['asset_id']);
        }
    );

    return $analyses;
}

function summarizeAiInsightLevels(array $analyses): array
{
    $summary = [
        'Critical' => 0,
        'High' => 0,
        'Moderate' => 0,
        'Low' => 0,
    ];

    foreach ($analyses as $analysis) {
        $level = $analysis['level'] ?? '';

        if (array_key_exists($level, $summary)) {
            $summary[$level]++;
        }
    }

    return $summary;
}
