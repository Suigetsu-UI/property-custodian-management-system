<?php

require_once __DIR__ . "/database.php";

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
    $pdo = getDbConnection();

    $stmt = $pdo->query(
        "SELECT
            COUNT(*) AS total,
            COUNT(*) FILTER (
                WHERE status = 'Pending'
            ) AS pending,
            COUNT(*) FILTER (
                WHERE status = 'Approved'
            ) AS approved,
            COUNT(*) FILTER (
                WHERE status = 'Delivered'
            ) AS delivered,
            COUNT(*) FILTER (
                WHERE status = 'Rejected'
            ) AS rejected
         FROM procurement"
    );

    $counts = $stmt->fetch();

    $summary = [
        'total' => (int) ($counts['total'] ?? 0),
        'pending' => (int) ($counts['pending'] ?? 0),
        'approved' => (int) ($counts['approved'] ?? 0),
        'delivered' => (int) ($counts['delivered'] ?? 0),
        'rejected' => (int) ($counts['rejected'] ?? 0),
        'by_supplier' => [],
        'recent' => []
    ];

    $supplierStmt = $pdo->query(
        "SELECT
            CASE
                WHEN supplier IS NULL
                     OR trim(supplier) = ''
                THEN 'Unknown'
                ELSE supplier
            END AS supplier_name,
            COUNT(*) AS record_count
         FROM procurement
         GROUP BY supplier_name
         ORDER BY supplier_name ASC"
    );

    foreach ($supplierStmt->fetchAll() as $row) {
        $summary['by_supplier'][$row['supplier_name']] =
            (int) $row['record_count'];
    }

    $recentStmt = $pdo->query(
        "SELECT
            procurement_id,
            item_name,
            quantity,
            supplier,
            status
         FROM procurement
         ORDER BY id DESC
         LIMIT 5"
    );

    $summary['recent'] =
        $recentStmt->fetchAll();

    return $summary;
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

    $recentStmt = $pdo->query(
        "SELECT
            procurement_id,
            item_name,
            category,
            quantity,
            status
         FROM procurement
         WHERE status = 'Delivered'
         ORDER BY id DESC
         LIMIT 5"
    );

    $summary['recently_delivered'] =
        $recentStmt->fetchAll();

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