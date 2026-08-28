<?php

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/ai_insight_functions.php';
require_once __DIR__ . '/procurement_gateway.php';

const DASHBOARD_TIMEZONE = 'Asia/Manila';
const DASHBOARD_ATTENTION_LIMIT = 8;
const DASHBOARD_RECENT_EVENT_LIMIT = 8;

function dashboardAiFactorLabel(string $factor): string
{
    return match ($factor) {
        'Audit / current condition' => 'Asset condition',
        'Corrective maintenance' => 'Maintenance history',
        'Recurring attention states' => 'Repeated attention needs',
        'Asset age' => 'Asset age',
        default => 'No major concern found',
    };
}

function getDashboardToday(): string
{
    return (new DateTimeImmutable(
        'now',
        new DateTimeZone(DASHBOARD_TIMEZONE)
    ))->format('Y-m-d');
}

function getDashboardAiSummary(PDO $pdo, string $today): array
{
    $evidence = loadAiInsightEvidence($pdo, $today);
    $analyses = buildAiInsightAnalyses($evidence, $today);
    $levels = summarizeAiInsightLevels($analyses);

    return [
        'critical' => (int) ($levels['Critical'] ?? 0),
        'high' => (int) ($levels['High'] ?? 0),
        'moderate' => (int) ($levels['Moderate'] ?? 0),
        'low' => (int) ($levels['Low'] ?? 0),
        'highest' => $analyses[0] ?? null,
        'analyses' => $analyses,
    ];
}

function getDashboardKpis(PDO $pdo, array $aiSummary): array
{
    $statement = $pdo->query(
        "SELECT
            COUNT(*) AS total,
            COUNT(*) FILTER (WHERE status = 'Available') AS available,
            COUNT(*) FILTER (WHERE status = 'Assigned') AS assigned,
            COUNT(*) FILTER (
                WHERE status = 'Under Maintenance'
            ) AS under_maintenance,
            COUNT(*) FILTER (WHERE status = 'Lost') AS lost
         FROM assets"
    );
    $counts = $statement->fetch();

    return [
        'total' => (int) ($counts['total'] ?? 0),
        'available' => (int) ($counts['available'] ?? 0),
        'assigned' => (int) ($counts['assigned'] ?? 0),
        'under_maintenance' =>
            (int) ($counts['under_maintenance'] ?? 0),
        'lost' => (int) ($counts['lost'] ?? 0),
        'needs_attention' =>
            (int) ($aiSummary['critical'] ?? 0) +
            (int) ($aiSummary['high'] ?? 0),
    ];
}

function getOperationalIndicators(PDO $pdo): array
{
    $statement = $pdo->query(
        "SELECT
            (
                SELECT COUNT(*)
                FROM maintenance
                WHERE status IN ('Scheduled', 'In Progress')
            ) AS active_maintenance,
            (
                SELECT COUNT(*)
                FROM audits
                WHERE status IN ('Scheduled', 'Ongoing')
            ) AS pending_audits,
            (
                SELECT COALESCE(SUM(quantity), 0)
                FROM inventory
            ) AS inventory_units"
    );
    $counts = $statement->fetch();
    $procurement = getProcurementSummarySafely();

    return [
        'open_procurement' =>
            (int) ($procurement['open'] ?? 0),
        'procurement_available' =>
            (bool) ($procurement['available'] ?? false),
        'active_maintenance' =>
            (int) ($counts['active_maintenance'] ?? 0),
        'pending_audits' =>
            (int) ($counts['pending_audits'] ?? 0),
        'inventory_units' =>
            (int) ($counts['inventory_units'] ?? 0),
    ];
}

function dashboardAttentionItem(
    int $priority,
    string $type,
    string $title,
    string $detail,
    string $businessId,
    string $assetId,
    ?string $date,
    string $href,
    int $urgency = 0
): array {
    return [
        'priority' => $priority,
        'type' => $type,
        'title' => $title,
        'detail' => $detail,
        'business_id' => $businessId,
        'asset_id' => $assetId,
        'date' => $date,
        'href' => $href,
        'urgency' => $urgency,
    ];
}

function getDashboardAttentionItems(
    PDO $pdo,
    array $aiAnalyses,
    string $today,
    int $limit = DASHBOARD_ATTENTION_LIMIT
): array {
    $items = [];

    foreach ($aiAnalyses as $analysis) {
        $level = (string) ($analysis['level'] ?? '');

        if (!in_array($level, ['Critical', 'High'], true)) {
            continue;
        }

        $assetId = (string) ($analysis['asset_id'] ?? '');
        $items[] = dashboardAttentionItem(
            $level === 'Critical' ? 1 : 3,
            strtolower($level),
            $assetId . ' requires ' . $level . ' attention',
            dashboardAiFactorLabel(
                (string) ($analysis['primary_factor'] ?? '')
            ),
            $assetId,
            $assetId,
            $today,
            BASE_URL . 'modules/ai_insights/index.php?asset=' .
                rawurlencode($assetId),
            (int) ($analysis['score'] ?? 0)
        );
    }

    $lostStatement = $pdo->query(
        "SELECT asset_id, asset_name, updated_at
         FROM assets
         WHERE status = 'Lost'"
    );

    foreach ($lostStatement->fetchAll() as $row) {
        $assetId = (string) $row['asset_id'];
        $items[] = dashboardAttentionItem(
            2,
            'lost',
            $assetId . ' is marked Lost',
            (string) $row['asset_name'],
            $assetId,
            $assetId,
            substr((string) $row['updated_at'], 0, 10),
            BASE_URL . 'modules/asset_registry/index.php'
        );
    }

    $maintenanceStatement = $pdo->prepare(
        "SELECT
            m.maintenance_id,
            m.asset_name_snap,
            m.scheduled_date,
            COALESCE(a.asset_id, '') AS asset_business_id
         FROM maintenance m
         LEFT JOIN assets a ON a.id = m.asset_id
         WHERE m.status = 'Scheduled'
           AND m.scheduled_date < :today"
    );
    $maintenanceStatement->execute(['today' => $today]);

    foreach ($maintenanceStatement->fetchAll() as $row) {
        $maintenanceId = (string) $row['maintenance_id'];
        $assetId = (string) $row['asset_business_id'];
        $items[] = dashboardAttentionItem(
            4,
            'maintenance',
            $maintenanceId . ' is overdue',
            trim($assetId . ' · ' . (string) $row['asset_name_snap'], ' ·'),
            $maintenanceId,
            $assetId,
            (string) $row['scheduled_date'],
            BASE_URL . 'modules/maintenance/index.php'
        );
    }

    $auditStatement = $pdo->prepare(
        "SELECT
            au.audit_id,
            au.asset_name_snap,
            au.audit_date,
            au.status,
            au.result,
            COALESCE(a.asset_id, '') AS asset_business_id
         FROM audits au
         LEFT JOIN assets a ON a.id = au.asset_id
         WHERE (
                au.status = 'Completed'
                AND au.result IN (
                    'Missing',
                    'Damaged',
                    'For Investigation'
                )
            )
            OR (
                au.status <> 'Completed'
                AND au.audit_date < :today
            )"
    );
    $auditStatement->execute(['today' => $today]);

    $auditPriorities = [
        'Missing' => 5,
        'Damaged' => 6,
        'For Investigation' => 7,
    ];

    foreach ($auditStatement->fetchAll() as $row) {
        $auditId = (string) $row['audit_id'];
        $assetId = (string) $row['asset_business_id'];
        $isCompletedConcern = $row['status'] === 'Completed';
        $result = (string) ($row['result'] ?? '');
        $priority = $isCompletedConcern
            ? ($auditPriorities[$result] ?? 7)
            : 8;
        $title = $isCompletedConcern
            ? $auditId . ' reported ' . $result
            : $auditId . ' is overdue';
        $detail = trim(
            $assetId . ' · ' . (string) $row['asset_name_snap'],
            ' ·'
        );

        $items[] = dashboardAttentionItem(
            $priority,
            'audit',
            $title,
            $detail,
            $auditId,
            $assetId,
            (string) $row['audit_date'],
            BASE_URL . 'modules/audit/index.php'
        );
    }

    usort(
        $items,
        static function (array $left, array $right): int {
            $priorityOrder = $left['priority'] <=> $right['priority'];

            if ($priorityOrder !== 0) {
                return $priorityOrder;
            }

            $urgencyOrder = $right['urgency'] <=> $left['urgency'];

            if ($urgencyOrder !== 0) {
                return $urgencyOrder;
            }

            $dateOrder = strcmp(
                (string) $right['date'],
                (string) $left['date']
            );

            if ($dateOrder !== 0) {
                return $dateOrder;
            }

            return strcmp(
                (string) ($left['asset_id'] ?: $left['business_id']),
                (string) ($right['asset_id'] ?: $right['business_id'])
            );
        }
    );

    return array_slice($items, 0, max(0, $limit));
}

function getAssetStatusDistribution(PDO $pdo): array
{
    $statement = $pdo->query(
        "SELECT
            COUNT(*) FILTER (WHERE status = 'Available') AS available,
            COUNT(*) FILTER (WHERE status = 'Assigned') AS assigned,
            COUNT(*) FILTER (
                WHERE status = 'Under Maintenance'
            ) AS under_maintenance,
            COUNT(*) FILTER (WHERE status = 'Lost') AS lost
         FROM assets"
    );
    $counts = $statement->fetch();

    return [
        'Available' => (int) ($counts['available'] ?? 0),
        'Assigned' => (int) ($counts['assigned'] ?? 0),
        'Under Maintenance' =>
            (int) ($counts['under_maintenance'] ?? 0),
        'Lost' => (int) ($counts['lost'] ?? 0),
    ];
}

function getSixMonthPropertyActivity(PDO $pdo, string $today): array
{
    $timezone = new DateTimeZone(DASHBOARD_TIMEZONE);
    $todayDate = new DateTimeImmutable($today, $timezone);
    $rangeStart = $todayDate
        ->modify('first day of this month')
        ->modify('-5 months');

    $statement = $pdo->prepare(
        "SELECT
            to_char(date_trunc('month', event_date), 'YYYY-MM') AS month_key,
            COUNT(*) AS event_count
         FROM property_events
         WHERE event_date >= :range_start
           AND event_date <= :today
         GROUP BY date_trunc('month', event_date)
         ORDER BY date_trunc('month', event_date)"
    );
    $statement->execute([
        'range_start' => $rangeStart->format('Y-m-d'),
        'today' => $today,
    ]);

    $counts = [];

    foreach ($statement->fetchAll() as $row) {
        $counts[(string) $row['month_key']] = (int) $row['event_count'];
    }

    $months = [];

    for ($offset = 0; $offset < 6; $offset++) {
        $month = $rangeStart->modify('+' . $offset . ' months');
        $key = $month->format('Y-m');
        $months[] = [
            'key' => $key,
            'short_label' => $month->format('M'),
            'label' => $month->format('F Y'),
            'count' => $counts[$key] ?? 0,
        ];
    }

    return $months;
}

function getMaintenanceAuditOverview(PDO $pdo): array
{
    $statement = $pdo->query(
        "SELECT
            'maintenance' AS module,
            COUNT(*) FILTER (WHERE status = 'Scheduled') AS scheduled,
            COUNT(*) FILTER (WHERE status = 'In Progress') AS active,
            COUNT(*) FILTER (WHERE status = 'Completed') AS completed
         FROM maintenance
         UNION ALL
         SELECT
            'audit' AS module,
            COUNT(*) FILTER (WHERE status = 'Scheduled') AS scheduled,
            COUNT(*) FILTER (WHERE status = 'Ongoing') AS active,
            COUNT(*) FILTER (WHERE status = 'Completed') AS completed
         FROM audits"
    );

    $overview = [
        'maintenance' => [
            'label' => 'Maintenance',
            'active_label' => 'In Progress',
            'scheduled' => 0,
            'active' => 0,
            'completed' => 0,
        ],
        'audit' => [
            'label' => 'Audits',
            'active_label' => 'Ongoing',
            'scheduled' => 0,
            'active' => 0,
            'completed' => 0,
        ],
    ];

    foreach ($statement->fetchAll() as $row) {
        $module = (string) $row['module'];

        if (!isset($overview[$module])) {
            continue;
        }

        $overview[$module]['scheduled'] = (int) $row['scheduled'];
        $overview[$module]['active'] = (int) $row['active'];
        $overview[$module]['completed'] = (int) $row['completed'];
    }

    return $overview;
}

function dashboardBriefEventDescription(array $event): string
{
    $parts = [];
    $name = trim((string) ($event['record_name_snap'] ?? ''));

    if ($name !== '') {
        $parts[] = $name;
    }

    if ($event['quantity_delta'] !== null) {
        $quantity = (int) $event['quantity_delta'];
        $parts[] = 'Quantity ' . ($quantity > 0 ? '+' : '') . $quantity;
    }

    $fromStatus = trim((string) ($event['from_status'] ?? ''));
    $toStatus = trim((string) ($event['to_status'] ?? ''));

    if ($fromStatus !== '' || $toStatus !== '') {
        $parts[] = ($fromStatus !== '' ? $fromStatus : 'None') .
            ' to ' .
            ($toStatus !== '' ? $toStatus : 'None');
    }

    $outcome = trim((string) ($event['outcome'] ?? ''));

    if ($outcome !== '') {
        $parts[] = $outcome;
    }

    $description = trim((string) ($event['description'] ?? ''));

    if ($description !== '') {
        $parts[] = $description;
    }

    $summary = empty($parts)
        ? 'Recorded property activity'
        : implode(' · ', $parts);

    return strlen($summary) > 150
        ? rtrim(substr($summary, 0, 147)) . '...'
        : $summary;
}

function getRecentPropertyEvents(
    PDO $pdo,
    string $today,
    int $limit = DASHBOARD_RECENT_EVENT_LIMIT
): array {
    $safeLimit = max(1, min(20, $limit));
    $statement = $pdo->prepare(
        "SELECT
            id,
            module,
            event_type,
            business_id,
            record_name_snap,
            event_date,
            quantity_delta,
            from_status,
            to_status,
            outcome,
            description,
            created_at
         FROM property_events
         WHERE event_date <= :today
         ORDER BY event_date DESC, created_at DESC, id DESC
         LIMIT " . $safeLimit
    );
    $statement->execute(['today' => $today]);
    $events = $statement->fetchAll();

    $modulePaths = [
        'Procurement' => 'modules/procurement/index.php',
        'Inventory' => 'modules/inventory/index.php',
        'Asset Registry' => 'modules/asset_registry/index.php',
        'Maintenance' => 'modules/maintenance/index.php',
        'Audit' => 'modules/audit/index.php',
    ];

    foreach ($events as &$event) {
        $event['summary'] = dashboardBriefEventDescription($event);
        $event['href'] = BASE_URL . (
            $modulePaths[$event['module']] ?? 'dashboard.php'
        );
    }
    unset($event);

    return $events;
}

function getDashboardQuickLinks(bool $administrator): array
{
    $links = [
        ['Register Asset', 'Open the Asset Registry', 'fa-boxes-stacked', 'modules/asset_registry/index.php'],
        ['Add Procurement', 'Create a property request', 'fa-cart-plus', 'modules/procurement/index.php'],
        ['Schedule Maintenance', 'Plan Asset servicing', 'fa-screwdriver-wrench', 'modules/maintenance/index.php'],
        ['Schedule Audit', 'Plan physical verification', 'fa-clipboard-check', 'modules/audit/index.php'],
        ['View Reports', 'Review period activity', 'fa-chart-column', 'modules/reports/index.php'],
        ['View AI Insights', 'Review Asset attention', 'fa-brain', 'modules/ai_insights/index.php'],
    ];

    if ($administrator) {
        $links[] = [
            'User Management',
            'Manage system access',
            'fa-users-gear',
            'modules/users/index.php',
        ];
    }

    $links[] = [
        'Account Security',
        'Manage password and MFA',
        'fa-user-shield',
        'modules/account/security.php',
    ];

    return array_map(
        static fn (array $link): array => [
            'label' => $link[0],
            'description' => $link[1],
            'icon' => $link[2],
            'href' => BASE_URL . $link[3],
        ],
        $links
    );
}

function getExternalAnnouncements(): array
{
    return [];
}

function buildDashboardData(
    PDO $pdo,
    bool $administrator,
    ?string $today = null
): array {
    $dashboardToday = $today ?? getDashboardToday();
    $aiSummary = getDashboardAiSummary($pdo, $dashboardToday);

    return [
        'today' => $dashboardToday,
        'kpis' => getDashboardKpis($pdo, $aiSummary),
        'operational' => getOperationalIndicators($pdo),
        'attention' => getDashboardAttentionItems(
            $pdo,
            $aiSummary['analyses'],
            $dashboardToday
        ),
        'asset_status' => getAssetStatusDistribution($pdo),
        'activity_months' => getSixMonthPropertyActivity(
            $pdo,
            $dashboardToday
        ),
        'workflow' => getMaintenanceAuditOverview($pdo),
        'ai' => $aiSummary,
        'recent_events' => getRecentPropertyEvents(
            $pdo,
            $dashboardToday
        ),
        'quick_links' => getDashboardQuickLinks($administrator),
        'external_announcements' => getExternalAnnouncements(),
    ];
}
