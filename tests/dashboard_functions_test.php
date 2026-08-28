<?php

require_once __DIR__ . '/../includes/dashboard_functions.php';

$testsRun = 0;

function assertDashboard(bool $condition, string $message): void
{
    global $testsRun;
    $testsRun++;

    if (!$condition) {
        throw new RuntimeException('FAILED: ' . $message);
    }
}

function dashboardTuple(array $event): array
{
    return [
        (string) $event['event_date'],
        (string) $event['created_at'],
        (int) $event['id'],
    ];
}

function dashboardTupleIsOrdered(array $newer, array $older): bool
{
    if ($newer[0] !== $older[0]) {
        return $newer[0] > $older[0];
    }

    if ($newer[1] !== $older[1]) {
        return $newer[1] > $older[1];
    }

    return $newer[2] >= $older[2];
}

$pdo = getDbConnection();
$today = getDashboardToday();
$dashboard = buildDashboardData($pdo, true, $today);

$assetCounts = $pdo->query(
    "SELECT
        COUNT(*) AS total,
        COUNT(*) FILTER (WHERE status = 'Available') AS available,
        COUNT(*) FILTER (WHERE status = 'Assigned') AS assigned,
        COUNT(*) FILTER (WHERE status = 'Under Maintenance') AS maintenance,
        COUNT(*) FILTER (WHERE status = 'Lost') AS lost
     FROM assets"
)->fetch();

assertDashboard(
    $dashboard['kpis']['total'] === (int) $assetCounts['total'],
    'Total Assets must match the database.'
);
assertDashboard(
    $dashboard['kpis']['available'] === (int) $assetCounts['available'],
    'Available Assets must match the database.'
);
assertDashboard(
    $dashboard['kpis']['assigned'] === (int) $assetCounts['assigned'],
    'Assigned Assets must match the database.'
);
assertDashboard(
    $dashboard['kpis']['under_maintenance'] ===
        (int) $assetCounts['maintenance'],
    'Under Maintenance Assets must match the database.'
);
assertDashboard(
    $dashboard['kpis']['lost'] === (int) $assetCounts['lost'],
    'Lost Assets must match the database.'
);
assertDashboard(
    $dashboard['kpis']['needs_attention'] ===
        $dashboard['ai']['critical'] + $dashboard['ai']['high'],
    'Needs Attention must equal Critical plus High AI levels only.'
);

$operational = $pdo->query(
    "SELECT
        (SELECT COUNT(*) FROM maintenance
         WHERE status IN ('Scheduled', 'In Progress')) AS maintenance,
        (SELECT COUNT(*) FROM audits
         WHERE status IN ('Scheduled', 'Ongoing')) AS audits,
        (SELECT COALESCE(SUM(quantity), 0) FROM inventory) AS inventory"
)->fetch();
$procurementSummary = getProcurementSummarySafely();

assertDashboard(
    $dashboard['operational']['open_procurement'] ===
        (int) ($procurementSummary['open'] ?? 0),
    'Open Procurement must come from the Procurement service summary.'
);
assertDashboard(
    $dashboard['operational']['procurement_available'] ===
        (bool) ($procurementSummary['available'] ?? false),
    'Dashboard must expose Procurement service availability.'
);
assertDashboard(
    $dashboard['operational']['active_maintenance'] ===
        (int) $operational['maintenance'],
    'Active Maintenance must include Scheduled and In Progress only.'
);
assertDashboard(
    $dashboard['operational']['pending_audits'] ===
        (int) $operational['audits'],
    'Pending Audits must include Scheduled and Ongoing only.'
);
assertDashboard(
    $dashboard['operational']['inventory_units'] ===
        (int) $operational['inventory'],
    'Inventory Units must use the sum of current quantity.'
);
assertDashboard(
    !array_key_exists('low_stock', $dashboard['operational']) &&
    !array_key_exists('inventory_alerts', $dashboard['kpis']),
    'Dashboard data must not expose a pseudo low-stock alert.'
);

assertDashboard(
    count($dashboard['activity_months']) === 6,
    'The activity chart must always contain six calendar months.'
);

$expectedFirstMonth = (new DateTimeImmutable($today))
    ->modify('first day of this month')
    ->modify('-5 months')
    ->format('Y-m');
$expectedLastMonth = (new DateTimeImmutable($today))->format('Y-m');

assertDashboard(
    $dashboard['activity_months'][0]['key'] === $expectedFirstMonth &&
    $dashboard['activity_months'][5]['key'] === $expectedLastMonth,
    'The activity range must run from five months ago through this month.'
);

$eventCountStatement = $pdo->prepare(
    "SELECT COUNT(*)
     FROM property_events
     WHERE event_date >= :range_start
       AND event_date <= :today"
);
$eventCountStatement->execute([
    'range_start' => $expectedFirstMonth . '-01',
    'today' => $today,
]);
$chartTotal = array_sum(array_column(
    $dashboard['activity_months'],
    'count'
));

assertDashboard(
    $chartTotal === (int) $eventCountStatement->fetchColumn(),
    'The activity chart must include history through today only.'
);
assertDashboard(
    count($dashboard['recent_events']) <= DASHBOARD_RECENT_EVENT_LIMIT,
    'Recent Property Activity must contain at most eight events.'
);

foreach ($dashboard['recent_events'] as $event) {
    assertDashboard(
        $event['event_date'] <= $today,
        'Recent Property Activity must exclude future events.'
    );
}

for ($index = 1; $index < count($dashboard['recent_events']); $index++) {
    assertDashboard(
        dashboardTupleIsOrdered(
            dashboardTuple($dashboard['recent_events'][$index - 1]),
            dashboardTuple($dashboard['recent_events'][$index])
        ),
        'Recent Property Activity must use deterministic descending order.'
    );
}

$directAnalyses = buildAiInsightAnalyses(
    loadAiInsightEvidence($pdo, $today),
    $today
);
$directAi = summarizeAiInsightLevels($directAnalyses);

assertDashboard(
    $dashboard['ai']['critical'] === (int) $directAi['Critical'] &&
    $dashboard['ai']['high'] === (int) $directAi['High'] &&
    $dashboard['ai']['moderate'] === (int) $directAi['Moderate'] &&
    $dashboard['ai']['low'] === (int) $directAi['Low'],
    'Dashboard AI levels must reuse the AI Insights scoring result.'
);

$administratorLabels = array_column(
    getDashboardQuickLinks(true),
    'label'
);
$custodianLabels = array_column(
    getDashboardQuickLinks(false),
    'label'
);

assertDashboard(
    in_array('User Management', $administratorLabels, true),
    'Administrators must receive the User Management shortcut.'
);
assertDashboard(
    !in_array('User Management', $custodianLabels, true),
    'Property Custodians must not receive the User Management shortcut.'
);
assertDashboard(
    getExternalAnnouncements() === [],
    'Announcements must remain an empty external integration source.'
);

echo "Dashboard read-only tests passed: {$testsRun}" . PHP_EOL;
