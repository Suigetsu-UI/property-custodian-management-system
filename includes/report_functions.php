<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function generateProcurementSummary()
{
    $records = $_SESSION['procurement'] ?? [];

    $summary = [
        'total' => count($records),
        'pending' => 0,
        'approved' => 0,
        'delivered' => 0,
        'rejected' => 0,
        'by_supplier' => [],
        'recent' => []
    ];

    foreach ($records as $item) {

        switch ($item['status'] ?? '') {
            case 'Pending': $summary['pending']++; break;
            case 'Approved': $summary['approved']++; break;
            case 'Delivered': $summary['delivered']++; break;
            case 'Rejected': $summary['rejected']++; break;
        }

        $supplier = $item['supplier'] ?? 'Unknown';
        $summary['by_supplier'][$supplier] = ($summary['by_supplier'][$supplier] ?? 0) + 1;
    }

    $summary['recent'] = array_slice(array_reverse($records), 0, 5);

    return $summary;
}

function generateInventorySummary()
{
    $items = $_SESSION['inventory'] ?? [];
    $procurement = $_SESSION['procurement'] ?? [];

    $summary = [
        'total_items' => count($items),
        'total_stock' => 0,
        'low_stock' => 0,
        'out_of_stock' => 0,
        'ready_for_registration' => 0,
        'recently_delivered' => []
    ];

    foreach ($items as $item) {

        $qty = (int) ($item['quantity'] ?? 0);
        $summary['total_stock'] += $qty;

        if ($qty === 0) {
            $summary['out_of_stock']++;
        } elseif ($qty <= 3) {
            $summary['low_stock']++;
        }

        if ($qty > 0) {
            $summary['ready_for_registration']++;
        }
    }

    $delivered = array_values(array_filter($procurement, function ($p) {
        return ($p['status'] ?? '') === 'Delivered';
    }));

    $summary['recently_delivered'] = array_slice(array_reverse($delivered), 0, 5);

    return $summary;
}

function generateAssetSummary()
{
    $assets = $_SESSION['assets'] ?? [];

    $summary = [
        'total' => count($assets),
        'available' => 0,
        'assigned' => 0,
        'under_maintenance' => 0,
        'lost' => 0,
        'by_category' => [],
        'by_location' => [],
        'by_custodian' => []
    ];

    foreach ($assets as $asset) {

        switch ($asset['status'] ?? 'Available') {
            case 'Available': $summary['available']++; break;
            case 'Assigned': $summary['assigned']++; break;
            case 'Under Maintenance': $summary['under_maintenance']++; break;
            case 'Lost': $summary['lost']++; break;
        }

        $category = $asset['category'] ?? 'Uncategorized';
        $summary['by_category'][$category] = ($summary['by_category'][$category] ?? 0) + 1;

        $location = !empty($asset['location']) ? $asset['location'] : 'Unspecified';
        $summary['by_location'][$location] = ($summary['by_location'][$location] ?? 0) + 1;

        if (!empty($asset['custodian'])) {
            $summary['by_custodian'][$asset['custodian']] = ($summary['by_custodian'][$asset['custodian']] ?? 0) + 1;
        }
    }

    return $summary;
}

function generateMaintenanceSummary()
{
    $records = $_SESSION['maintenance'] ?? [];

    $summary = [
        'total' => count($records),
        'scheduled' => 0,
        'in_progress' => 0,
        'completed' => 0,
        'currently_under_maintenance' => 0,
        'recent' => []
    ];

    foreach ($records as $item) {

        switch ($item['status'] ?? '') {
            case 'Scheduled':
                $summary['scheduled']++;
                $summary['currently_under_maintenance']++;
                break;
            case 'In Progress':
                $summary['in_progress']++;
                $summary['currently_under_maintenance']++;
                break;
            case 'Completed':
                $summary['completed']++;
                break;
        }
    }

    $summary['recent'] = array_slice(array_reverse($records), 0, 5);

    return $summary;
}

function generateAuditSummary()
{
    $records = $_SESSION['audits'] ?? [];

    $summary = [
        'total' => count($records),
        'completed' => 0,
        'pending' => 0,
        'recent' => []
    ];

    foreach ($records as $item) {

        if (($item['status'] ?? '') === 'Completed') {
            $summary['completed']++;
        } else {
            $summary['pending']++;
        }
    }

    $summary['recent'] = array_slice(array_reverse($records), 0, 5);

    return $summary;
}

function generateFullSystemSummary()
{
    return [
        'procurement' => generateProcurementSummary(),
        'inventory' => generateInventorySummary(),
        'assets' => generateAssetSummary(),
        'maintenance' => generateMaintenanceSummary(),
        'audit' => generateAuditSummary()
    ];
}