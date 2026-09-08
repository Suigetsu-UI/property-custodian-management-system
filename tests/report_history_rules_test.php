<?php

require_once __DIR__ . '/../includes/report_functions.php';

$testsRun = 0;

function assertReportHistory(bool $condition, string $message): void
{
    global $testsRun;
    $testsRun++;

    if (!$condition) {
        throw new RuntimeException('FAILED: ' . $message);
    }
}

function reportFixtureIds(array $events): array
{
    return array_values(array_map(
        static fn (array $event): string => (string) $event['business_id'],
        array_filter(
            $events,
            static fn (array $event): bool => str_starts_with(
                (string) ($event['business_id'] ?? ''),
                'RPT-99'
            )
        )
    ));
}

function reportSourceSection(
    string $source,
    string $start,
    string $end
): string {
    $startAt = strpos($source, $start);
    $endAt = $startAt === false
        ? false
        : strpos($source, $end, $startAt + strlen($start));

    if ($startAt === false || $endAt === false) {
        throw new RuntimeException('Unable to isolate source section.');
    }

    return substr($source, $startAt, $endAt - $startAt);
}

$bounds = buildReportDateBounds('2026-09-09', '2026-09-09');

assertReportHistory(
    $bounds === [
        'start_date' => '2026-09-09',
        'end_date' => '2026-09-09',
        'end_exclusive' => '2026-09-10',
    ],
    'A Daily report must use a start-inclusive and next-day-exclusive boundary.'
);
assertReportHistory(
    getReportEventModules('Asset Report') === ['Asset Registry'],
    'Asset reports must permit only Asset Registry events.'
);
assertReportHistory(
    getReportEventModules('Maintenance Report') === ['Maintenance'],
    'Maintenance reports must permit only Maintenance events.'
);
assertReportHistory(
    getReportEventModules('Full System Report') === [
        'Procurement',
        'Inventory',
        'Asset Registry',
        'Maintenance',
        'Audit',
    ],
    'Full reports must permit exactly the five property-management modules.'
);

$viewSource = file_get_contents(
    __DIR__ . '/../modules/reports/view_report.php'
);
$downloadSource = file_get_contents(
    __DIR__ . '/../modules/reports/download_report.php'
);

assertReportHistory(
    !str_contains($viewSource, 'Current System Snapshot') &&
    !str_contains($downloadSource, 'CURRENT SYSTEM SNAPSHOT'),
    'Period reports must not append current-state snapshots.'
);
assertReportHistory(
    !preg_match('/generate(?:Procurement|Inventory|Asset|Maintenance|Audit|FullSystem)Summary\s*\(/', $viewSource) &&
    !preg_match('/generate(?:Procurement|Inventory|Asset|Maintenance|Audit|FullSystem)Summary\s*\(/', $downloadSource),
    'Report renderers must not query current business records for inclusion.'
);

$propertyCoreSource = file_get_contents(
    __DIR__ . '/../services/property_core/src/PropertyCoreRepository.php'
);
$procurementSource = file_get_contents(
    __DIR__ . '/../services/procurement/src/ProcurementRepository.php'
);
$inventoryUpdateSource = reportSourceSection(
    $propertyCoreSource,
    'public function updateInventory(',
    'public function deleteInventory('
);
$assetUpdateSource = reportSourceSection(
    $propertyCoreSource,
    'public function updateAsset(',
    'public function assignAsset('
);
$procurementUpdateSource = reportSourceSection(
    $procurementSource,
    'public function update(',
    'public function delete('
);

assertReportHistory(
    str_contains($inventoryUpdateSource, "'event_type' => \$delta !== 0") &&
    str_contains($inventoryUpdateSource, "'Updated'"),
    'Inventory detail-only updates must create a dated Inventory event.'
);
assertReportHistory(
    str_contains($assetUpdateSource, "'event_type' => 'Updated'") &&
    str_contains($assetUpdateSource, "'module' => 'Asset Registry'"),
    'Asset detail updates must create a dated Asset Registry event.'
);
assertReportHistory(
    str_contains($procurementUpdateSource, "'event_type' => 'Updated'") &&
    str_contains($procurementUpdateSource, "'module' => 'Procurement'"),
    'Procurement detail updates must create a dated Procurement event.'
);

$pdo = getDbConnection();
$pdo->beginTransaction();

try {
    $insert = $pdo->prepare(
        "INSERT INTO property_events (
            module, event_type, business_id, event_date, performed_by
         ) VALUES (
            :module, :event_type, :business_id, :event_date, 'REPORT-TEST'
         )"
    );
    $fixtures = [
        ['Asset Registry', 'Assigned', 'RPT-990001', '2026-09-08'],
        ['Asset Registry', 'Registered', 'RPT-990002', '2026-09-09'],
        ['Maintenance', 'Completed', 'RPT-990003', '2026-09-09'],
        ['Inventory', 'Stock Increased', 'RPT-990004', '2026-09-09'],
        ['Asset Registry', 'Returned', 'RPT-990005', '2026-09-10'],
    ];

    foreach ($fixtures as [$module, $eventType, $businessId, $eventDate]) {
        $insert->execute([
            'module' => $module,
            'event_type' => $eventType,
            'business_id' => $businessId,
            'event_date' => $eventDate,
        ]);
    }

    $assetReport = generateHistoricalActivityReport(
        'Asset Report',
        '2026-09-09',
        '2026-09-09',
        $pdo
    );
    $maintenanceReport = generateHistoricalActivityReport(
        'Maintenance Report',
        '2026-09-09',
        '2026-09-09',
        $pdo
    );
    $fullReport = generateHistoricalActivityReport(
        'Full System Report',
        '2026-09-09',
        '2026-09-09',
        $pdo
    );

    assertReportHistory(
        reportFixtureIds($assetReport['events']) === ['RPT-990002'],
        'A September 9 Asset report must exclude September 8, September 10, and non-Asset events.'
    );
    assertReportHistory(
        reportFixtureIds($maintenanceReport['events']) === ['RPT-990003'],
        'A September 9 Maintenance report must include only the Maintenance event.'
    );
    assertReportHistory(
        reportFixtureIds($fullReport['events']) === [
            'RPT-990004',
            'RPT-990003',
            'RPT-990002',
        ],
        'A September 9 Full report must combine supported modules without leaking adjacent dates.'
    );
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

echo "Historical report rules passed: {$testsRun}" . PHP_EOL;
