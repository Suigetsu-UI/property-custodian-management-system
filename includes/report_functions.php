<?php

require_once __DIR__ . "/database.php";
require_once __DIR__ . "/procurement_gateway.php";

function getAllowedReportTypes(): array
{
    return [
        'Asset Report',
        'Inventory Report',
        'Maintenance Report',
        'Procurement Report',
        'Audit Report',
        'Full System Report'
    ];
}

function isAllowedReportType(string $reportType): bool
{
    return in_array(
        $reportType,
        getAllowedReportTypes(),
        true
    );
}

function generateProcurementSummary(): array
{
    return getProcurementSummarySafely();
}

function generateInventorySummary(): array
{
    $pdo = getDbConnection();

    $stmt = $pdo->query(
        "SELECT
            COUNT(*) AS total_items,
            COALESCE(SUM(quantity), 0) AS total_stock,
            COUNT(*) FILTER (
                WHERE quantity BETWEEN 1 AND 3
            ) AS low_stock,
            COUNT(*) FILTER (
                WHERE quantity = 0
            ) AS out_of_stock,
            COUNT(*) FILTER (
                WHERE quantity > 0
            ) AS ready_for_registration
         FROM inventory"
    );

    $counts = $stmt->fetch();

    $summary = [
        'total_items' =>
            (int) ($counts['total_items'] ?? 0),

        'total_stock' =>
            (int) ($counts['total_stock'] ?? 0),

        'low_stock' =>
            (int) ($counts['low_stock'] ?? 0),

        'out_of_stock' =>
            (int) ($counts['out_of_stock'] ?? 0),

        'ready_for_registration' =>
            (int) ($counts['ready_for_registration'] ?? 0),

        'recently_delivered' => []
    ];

    $procurement = getProcurementSummarySafely();
    $summary['procurement_available'] =
        (bool) ($procurement['available'] ?? false);
    $summary['recently_delivered'] =
        $procurement['recently_delivered'] ?? [];

    return $summary;
}

function generateAssetSummary(): array
{
    $pdo = getDbConnection();

    $stmt = $pdo->query(
        "SELECT
            COUNT(*) AS total,
            COUNT(*) FILTER (
                WHERE status = 'Available'
            ) AS available,
            COUNT(*) FILTER (
                WHERE status = 'Assigned'
            ) AS assigned,
            COUNT(*) FILTER (
                WHERE status = 'Under Maintenance'
            ) AS under_maintenance,
            COUNT(*) FILTER (
                WHERE status = 'Lost'
            ) AS lost
         FROM assets"
    );

    $counts = $stmt->fetch();

    $summary = [
        'total' =>
            (int) ($counts['total'] ?? 0),

        'available' =>
            (int) ($counts['available'] ?? 0),

        'assigned' =>
            (int) ($counts['assigned'] ?? 0),

        'under_maintenance' =>
            (int) ($counts['under_maintenance'] ?? 0),

        'lost' =>
            (int) ($counts['lost'] ?? 0),

        'by_category' => [],
        'by_location' => [],
        'by_custodian' => []
    ];

    $categoryStmt = $pdo->query(
        "SELECT
            CASE
                WHEN category IS NULL
                     OR trim(category) = ''
                THEN 'Uncategorized'
                ELSE category
            END AS category_name,
            COUNT(*) AS record_count
         FROM assets
         GROUP BY category_name
         ORDER BY category_name ASC"
    );

    foreach ($categoryStmt->fetchAll() as $row) {
        $summary['by_category'][$row['category_name']] =
            (int) $row['record_count'];
    }

    $locationStmt = $pdo->query(
        "SELECT
            CASE
                WHEN location IS NULL
                     OR trim(location) = ''
                THEN 'Unspecified'
                ELSE location
            END AS location_name,
            COUNT(*) AS record_count
         FROM assets
         GROUP BY location_name
         ORDER BY location_name ASC"
    );

    foreach ($locationStmt->fetchAll() as $row) {
        $summary['by_location'][$row['location_name']] =
            (int) $row['record_count'];
    }

    $custodianStmt = $pdo->query(
        "SELECT
            custodian,
            COUNT(*) AS record_count
         FROM assets
         WHERE custodian IS NOT NULL
           AND trim(custodian) <> ''
         GROUP BY custodian
         ORDER BY custodian ASC"
    );

    foreach ($custodianStmt->fetchAll() as $row) {
        $summary['by_custodian'][$row['custodian']] =
            (int) $row['record_count'];
    }

    return $summary;
}

function generateMaintenanceSummary(): array
{
    $pdo = getDbConnection();

    $stmt = $pdo->query(
        "SELECT
            COUNT(*) AS total,
            COUNT(*) FILTER (
                WHERE status = 'Scheduled'
            ) AS scheduled,
            COUNT(*) FILTER (
                WHERE status = 'In Progress'
            ) AS in_progress,
            COUNT(*) FILTER (
                WHERE status = 'Completed'
            ) AS completed,
            COUNT(*) FILTER (
                WHERE status <> 'Completed'
            ) AS currently_under_maintenance
         FROM maintenance"
    );

    $counts = $stmt->fetch();

    $summary = [
        'total' =>
            (int) ($counts['total'] ?? 0),

        'scheduled' =>
            (int) ($counts['scheduled'] ?? 0),

        'in_progress' =>
            (int) ($counts['in_progress'] ?? 0),

        'completed' =>
            (int) ($counts['completed'] ?? 0),

        'currently_under_maintenance' =>
            (int) ($counts['currently_under_maintenance'] ?? 0),

        'recent' => []
    ];

    $recentStmt = $pdo->query(
        "SELECT
            maintenance_id,
            asset_name_snap AS asset_name,
            maintenance_type,
            scheduled_date,
            status
         FROM maintenance
         ORDER BY id DESC
         LIMIT 5"
    );

    $summary['recent'] =
        $recentStmt->fetchAll();

    return $summary;
}

function generateAuditSummary(): array
{
    $pdo = getDbConnection();

    $stmt = $pdo->query(
        "SELECT
            COUNT(*) AS total,
            COUNT(*) FILTER (
                WHERE status = 'Completed'
            ) AS completed,
            COUNT(*) FILTER (
                WHERE status <> 'Completed'
            ) AS pending
         FROM audits"
    );

    $counts = $stmt->fetch();

    $summary = [
        'total' =>
            (int) ($counts['total'] ?? 0),

        'completed' =>
            (int) ($counts['completed'] ?? 0),

        'pending' =>
            (int) ($counts['pending'] ?? 0),

        'recent' => []
    ];

    $recentStmt = $pdo->query(
        "SELECT
            audit_id,
            asset_name_snap AS asset_name,
            auditor,
            audit_date,
            status,
            result
         FROM audits
         ORDER BY id DESC
         LIMIT 5"
    );

    $summary['recent'] =
        $recentStmt->fetchAll();

    return $summary;
}

function generateFullSystemSummary(): array
{
    return [
        'procurement' =>
            generateProcurementSummary(),

        'inventory' =>
            generateInventorySummary(),

        'assets' =>
            generateAssetSummary(),

        'maintenance' =>
            generateMaintenanceSummary(),

        'audit' =>
            generateAuditSummary()
    ];
}

function getReportTimezone(): DateTimeZone
{
    static $timezone = null;

    if (!$timezone instanceof DateTimeZone) {
        $timezone = new DateTimeZone('Asia/Manila');
    }

    return $timezone;
}

function getReportNow(): DateTimeImmutable
{
    return new DateTimeImmutable('now', getReportTimezone());
}

function getReportToday(): string
{
    return getReportNow()->format('Y-m-d');
}

function getAllowedReportPeriods(): array
{
    return [
        'Daily',
        'Weekly',
        'Monthly',
        'Quarterly',
        'Annual',
        'Custom Date Range',
    ];
}

function parseReportDate(string $value): ?DateTimeImmutable
{
    $date = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $value,
        getReportTimezone()
    );

    return $date !== false && $date->format('Y-m-d') === $value
        ? $date
        : null;
}

function formatReportPeriodLabel(
    string $period,
    string $startDate,
    string $endDate
): string {
    $start = parseReportDate($startDate);
    $end = parseReportDate($endDate);

    if (!$start || !$end) {
        return $startDate . ' to ' . $endDate;
    }

    return match ($period) {
        'Daily' => $start->format('F j, Y'),
        'Weekly' => $start->format('F j, Y') .
            ' to ' . $end->format('F j, Y'),
        'Monthly' => $start->format('F Y'),
        'Quarterly' => 'Q' .
            (string) ((int) floor(((int) $start->format('n') - 1) / 3) + 1) .
            ' ' . $start->format('Y'),
        'Annual' => $start->format('Y'),
        default => $start->format('F j, Y') .
            ' to ' . $end->format('F j, Y'),
    };
}

function resolveReportPeriod(array $input): ?array
{
    $period = trim((string) ($input['report_period'] ?? 'Monthly'));

    if (!in_array($period, getAllowedReportPeriods(), true)) {
        return null;
    }

    $now = getReportNow();
    $year = filter_var(
        $input['period_year'] ?? $now->format('Y'),
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 2000, 'max_range' => 2100]]
    );

    $start = null;
    $end = null;

    if ($period === 'Daily') {
        $start = parseReportDate(
            trim((string) ($input['period_date'] ?? getReportToday()))
        );
        $end = $start;

    } elseif ($period === 'Weekly') {
        $selectedDate = parseReportDate(
            trim((string) ($input['week_of'] ?? getReportToday()))
        );

        if ($selectedDate) {
            $start = $selectedDate->modify('monday this week');
            $end = $start->modify('+6 days');
        }

    } elseif ($period === 'Monthly') {
        $month = filter_var(
            $input['period_month'] ?? $now->format('n'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 12]]
        );

        if ($year !== false && $month !== false) {
            $start = DateTimeImmutable::createFromFormat(
                '!Y-n-j',
                $year . '-' . $month . '-1',
                getReportTimezone()
            );
            $end = $start->modify('last day of this month');
        }

    } elseif ($period === 'Quarterly') {
        $quarter = filter_var(
            $input['period_quarter'] ?? 1,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 4]]
        );

        if ($year !== false && $quarter !== false) {
            $firstMonth = (($quarter - 1) * 3) + 1;
            $start = DateTimeImmutable::createFromFormat(
                '!Y-n-j',
                $year . '-' . $firstMonth . '-1',
                getReportTimezone()
            );
            $end = $start->modify('+3 months -1 day');
        }

    } elseif ($period === 'Annual') {
        if ($year !== false) {
            $start = DateTimeImmutable::createFromFormat(
                '!Y-n-j',
                $year . '-1-1',
                getReportTimezone()
            );
            $end = DateTimeImmutable::createFromFormat(
                '!Y-n-j',
                $year . '-12-31',
                getReportTimezone()
            );
        }

    } else {
        $customRange = normalizeReportDateRange(
            $input['start_date'] ?? null,
            $input['end_date'] ?? null
        );

        if ($customRange) {
            $start = parseReportDate($customRange['start_date']);
            $end = parseReportDate($customRange['end_date']);
        }
    }

    if (!$start || !$end || $start > $end) {
        return null;
    }

    $startDate = $start->format('Y-m-d');
    $endDate = $end->format('Y-m-d');

    return [
        'report_period' => $period,
        'period_label' => formatReportPeriodLabel(
            $period,
            $startDate,
            $endDate
        ),
        'start_date' => $startDate,
        'end_date' => $endDate,
    ];
}

function getDefaultReportDateRange(): array
{
    return resolveReportPeriod([
        'report_period' => 'Monthly',
    ]);
}

function normalizeReportDateRange(
    ?string $startDate,
    ?string $endDate
): ?array {
    $startDate = trim((string) $startDate);
    $endDate = trim((string) $endDate);

    if ($startDate === '' && $endDate === '') {
        return getDefaultReportDateRange();
    }

    if (
        !parseReportDate($startDate) ||
        !parseReportDate($endDate) ||
        $startDate > $endDate
    ) {
        return null;
    }

    return [
        'start_date' => $startDate,
        'end_date' => $endDate,
    ];
}

function buildReportDateBounds(
    string $startDate,
    string $endDate
): ?array {
    $dateRange = normalizeReportDateRange($startDate, $endDate);

    if ($dateRange === null) {
        return null;
    }

    $end = parseReportDate($dateRange['end_date']);

    if (!$end) {
        return null;
    }

    return [
        'start_date' => $dateRange['start_date'],
        'end_date' => $dateRange['end_date'],
        'end_exclusive' => $end->modify('+1 day')->format('Y-m-d'),
    ];
}

function buildPeriodReportMetrics(
    string $reportType,
    array $events
): array {
    $countEvents = static function (
        ?string $module = null,
        ?string $eventType = null,
        ?string $outcome = null
    ) use ($events): int {
        $count = 0;

        foreach ($events as $event) {
            if ($module !== null && $event['module'] !== $module) {
                continue;
            }

            if ($eventType !== null && $event['event_type'] !== $eventType) {
                continue;
            }

            if ($outcome !== null && $event['outcome'] !== $outcome) {
                continue;
            }

            $count++;
        }

        return $count;
    };

    $sumQuantity = static function (
        string $module,
        ?string $eventType = null,
        ?string $direction = null
    ) use ($events): int {
        $total = 0;

        foreach ($events as $event) {
            if ($event['module'] !== $module) {
                continue;
            }

            if ($eventType !== null && $event['event_type'] !== $eventType) {
                continue;
            }

            $quantity = $event['quantity_delta'];

            if ($quantity === null) {
                continue;
            }

            $quantity = (int) $quantity;

            if ($direction === 'positive' && $quantity <= 0) {
                continue;
            }

            if ($direction === 'negative' && $quantity >= 0) {
                continue;
            }

            $total += $quantity;
        }

        return $total;
    };

    return match ($reportType) {
        'Procurement Report' => [
            'Requests during period' => $countEvents('Procurement', 'Requested'),
            'Approved during period' => $countEvents('Procurement', 'Approved'),
            'Rejected during period' => $countEvents('Procurement', 'Rejected'),
            'Delivered during period' => $countEvents('Procurement', 'Delivered'),
            'Procurement records updated' => $countEvents('Procurement', 'Updated'),
            'Delivered quantity' => $sumQuantity('Procurement', 'Delivered'),
        ],
        'Inventory Report' => [
            'Inventory events' => $countEvents('Inventory'),
            'Stock increased' => $sumQuantity('Inventory', null, 'positive'),
            'Stock decreased' => abs($sumQuantity('Inventory', null, 'negative')),
            'Net Inventory Stock Change' => $sumQuantity('Inventory'),
        ],
        'Asset Report' => [
            'Assets registered' => $countEvents('Asset Registry', 'Registered'),
            'Assets assigned' => $countEvents('Asset Registry', 'Assigned'),
            'Assets returned' => $countEvents('Asset Registry', 'Returned'),
            'Assets updated' => $countEvents('Asset Registry', 'Updated'),
            'Asset status changes' => $countEvents('Asset Registry', 'Status Changed'),
            'Assets deleted' => $countEvents('Asset Registry', 'Deleted'),
        ],
        'Maintenance Report' => [
            'Maintenance scheduled' => $countEvents('Maintenance', 'Scheduled'),
            'Maintenance started' => $countEvents('Maintenance', 'Started'),
            'Maintenance completed' => $countEvents('Maintenance', 'Completed'),
            'Maintenance updated' => $countEvents('Maintenance', 'Updated'),
            'Maintenance deleted' => $countEvents('Maintenance', 'Deleted'),
        ],
'Audit Report' => [
    'Audits scheduled' =>
        $countEvents(
            'Audit',
            'Scheduled'
        ),

    'Audits started' =>
        $countEvents(
            'Audit',
            'Started'
        ),

    'Audits completed' =>
        $countEvents(
            'Audit',
            'Completed'
        ),

    'Verified findings' =>
        $countEvents(
            'Audit',
            'Completed',
            'Verified'
        ),

    'Missing findings' =>
        $countEvents(
            'Audit',
            'Completed',
            'Missing'
        ),

    'Damaged findings' =>
        $countEvents(
            'Audit',
            'Completed',
            'Damaged'
        ),

    'For Investigation findings' =>
        $countEvents(
            'Audit',
            'Completed',
            'For Investigation'
        ),
],
        'Full System Report' => [
            'Total business events' => count($events),
            'Procurement events' => $countEvents('Procurement'),
            'Inventory events' => $countEvents('Inventory'),
            'Asset Registry events' => $countEvents('Asset Registry'),
            'Maintenance events' => $countEvents('Maintenance'),
            'Audit events' => $countEvents('Audit'),
            'Procurement delivered quantity' => $sumQuantity('Procurement', 'Delivered'),
            'Net Inventory Stock Change' => $sumQuantity('Inventory'),
        ],
        default => [],
    };
}

function getReportEventModules(string $reportType): array
{
    return match ($reportType) {
        'Asset Report' => ['Asset Registry'],
        'Inventory Report' => ['Inventory'],
        'Maintenance Report' => ['Maintenance'],
        'Procurement Report' => ['Procurement'],
        'Audit Report' => ['Audit'],
        'Full System Report' => [
            'Procurement',
            'Inventory',
            'Asset Registry',
            'Maintenance',
            'Audit',
        ],
        default => [],
    };
}

function generateHistoricalActivityReport(
    string $reportType,
    string $startDate,
    string $endDate,
    ?PDO $pdo = null
): array {
    if (!isAllowedReportType($reportType)) {
        throw new InvalidArgumentException('INVALID_REPORT_TYPE');
    }

    $dateRange = buildReportDateBounds($startDate, $endDate);

    if ($dateRange === null) {
        throw new InvalidArgumentException('INVALID_REPORT_DATE_RANGE');
    }

    $modules = getReportEventModules($reportType);

    if (empty($modules)) {
        throw new InvalidArgumentException('INVALID_REPORT_MODULES');
    }

    $modulePlaceholders = [];
    $parameters = [
        'start_date' => $dateRange['start_date'],
        'end_exclusive' => $dateRange['end_exclusive'],
    ];

    foreach ($modules as $index => $module) {
        $parameterName = 'module_' . $index;
        $modulePlaceholders[] = ':' . $parameterName;
        $parameters[$parameterName] = $module;
    }

    $pdo ??= getDbConnection();

    $stmt = $pdo->prepare(
        "SELECT
            id,
            module,
            event_type,
            business_id,
            related_business_id,
            record_name_snap,
            category_snap,
            event_date,
            quantity_delta,
            from_status,
            to_status,
            outcome,
            performed_by,
            description,
            created_at
         FROM property_events
         WHERE event_date >= :start_date
           AND event_date < :end_exclusive
           AND module IN (" . implode(', ', $modulePlaceholders) . ")
         ORDER BY event_date DESC, created_at DESC, id DESC"
    );

    $stmt->execute($parameters);
    $events = $stmt->fetchAll();

    $moduleTotals = [];
    $eventTypeTotals = [];
    $netInventoryStockChange = 0;

    foreach ($events as &$event) {
        $module = (string) $event['module'];
        $eventType = (string) $event['event_type'];

        $moduleTotals[$module] = ($moduleTotals[$module] ?? 0) + 1;
        $eventTypeTotals[$eventType] = ($eventTypeTotals[$eventType] ?? 0) + 1;

        if ($event['quantity_delta'] !== null) {
            $event['quantity_delta'] = (int) $event['quantity_delta'];

            if ($module === 'Inventory') {
                $netInventoryStockChange += $event['quantity_delta'];
            }
        }
    }

    unset($event);

    ksort($moduleTotals, SORT_NATURAL | SORT_FLAG_CASE);
    arsort($eventTypeTotals, SORT_NUMERIC);

    return [
        'start_date' => $dateRange['start_date'],
        'end_date' => $dateRange['end_date'],
        'total_events' => count($events),
        'net_inventory_stock_change' => $netInventoryStockChange,
        'period_metrics' => buildPeriodReportMetrics(
            $reportType,
            $events
        ),
        'module_totals' => $moduleTotals,
        'event_type_totals' => $eventTypeTotals,
        'events' => $events,
    ];
}

function describePropertyEvent(array $event): string
{
    $parts = [];

    if ($event['quantity_delta'] !== null) {
        $quantity = (int) $event['quantity_delta'];
        $parts[] = 'Quantity ' . ($quantity > 0 ? '+' : '') . $quantity;
    }

    $fromStatus = trim((string) ($event['from_status'] ?? ''));
    $toStatus = trim((string) ($event['to_status'] ?? ''));

    if ($fromStatus !== '' || $toStatus !== '') {
        $parts[] = ($fromStatus !== '' ? $fromStatus : 'None') .
            ' → ' .
            ($toStatus !== '' ? $toStatus : 'None');
    }

    $outcome = trim((string) ($event['outcome'] ?? ''));

    if ($outcome !== '') {
        $parts[] = 'Outcome: ' . $outcome;
    }

    $description = trim((string) ($event['description'] ?? ''));

    if ($description !== '') {
        $parts[] = $description;
    }

    return empty($parts) ? '—' : implode(' · ', $parts);
}
