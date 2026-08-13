<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['assets'])) {
    $_SESSION['assets'] = [];
}

function generateAssetID()
{
    return "AST-" . str_pad(count($_SESSION['assets']) + 1, 6, "0", STR_PAD_LEFT);
}


if (!isset($_SESSION['inventory'])) {
    $_SESSION['inventory'] = [];
}

function generateInventoryID()
{
    return "INV-" . str_pad(count($_SESSION['inventory']) + 1, 6, "0", STR_PAD_LEFT);
}

if (!isset($_SESSION['maintenance'])) {
    $_SESSION['maintenance'] = [];
}

function generateMaintenanceID()
{
    $highest = 0;

    foreach ($_SESSION['maintenance'] as $item) {
        if (!empty($item['maintenance_id'])) {
            $number = (int) preg_replace('/[^0-9]/', '', $item['maintenance_id']);
            if ($number > $highest) {
                $highest = $number;
            }
        }
    }

    return "MNT-" . str_pad($highest + 1, 6, "0", STR_PAD_LEFT);
}

if (!isset($_SESSION['procurement'])) {
    $_SESSION['procurement'] = [];
}

function generateProcurementID()
{
    return "PRC-" . str_pad(count($_SESSION['procurement']) + 1, 6, "0", STR_PAD_LEFT);
}

if (!isset($_SESSION['audits'])) {
    $_SESSION['audits'] = [];
}

function generateAuditID()
{
    return "AUD-" . str_pad(count($_SESSION['audits']) + 1, 6, "0", STR_PAD_LEFT);
}

if (!isset($_SESSION['reports'])) {
    $_SESSION['reports'] = [];
}

function generateReportID()
{
    return "RPT-" . str_pad(count($_SESSION['reports']) + 1, 6, "0", STR_PAD_LEFT);
}

function getAssetByID($assetId)
{
    foreach ($_SESSION['assets'] as $asset) {
        if (isset($asset['asset_id']) && $asset['asset_id'] === $assetId) {
            return $asset;
        }
    }

    return null;
}

function getInventoryByID($inventoryId)
{
    foreach ($_SESSION['inventory'] as $item) {
        if (isset($item['inventory_id']) && $item['inventory_id'] === $inventoryId) {
            return $item;
        }
    }

    return null;
}

function decrementInventoryQuantity($inventoryId)
{
    foreach ($_SESSION['inventory'] as $index => $item) {
        if (isset($item['inventory_id']) && $item['inventory_id'] === $inventoryId) {
            $currentQty = (int) ($item['quantity'] ?? 0);
            $_SESSION['inventory'][$index]['quantity'] = max(0, $currentQty - 1);
            return true;
        }
    }

    return false;
}

function incrementInventoryQuantity($inventoryId)
{
    if (empty($inventoryId)) {
        return false;
    }

    foreach ($_SESSION['inventory'] as $index => $item) {
        if (isset($item['inventory_id']) && $item['inventory_id'] === $inventoryId) {
            $currentQty = (int) ($item['quantity'] ?? 0);
            $_SESSION['inventory'][$index]['quantity'] = $currentQty + 1;
            return true;
        }
    }

    return false;
}


function isInventoryLinkedToAsset($inventoryId)
{
    if (empty($inventoryId)) {
        return false;
    }

    foreach ($_SESSION['assets'] ?? [] as $asset) {
        if (isset($asset['inventory_id']) && $asset['inventory_id'] === $inventoryId) {
            return true;
        }
    }

    return false;
}

function updateAssetStatusFromMaintenance($assetId, $maintenanceStatus)
{
    if (empty($assetId)) {
        return;
    }

    foreach ($_SESSION['assets'] as $index => $asset) {
        if (isset($asset['asset_id']) && $asset['asset_id'] === $assetId) {

            if ($maintenanceStatus === 'Completed') {

                $_SESSION['assets'][$index]['status'] =
                    !empty($asset['custodian']) ? 'Assigned' : 'Available';

            } else {

                $_SESSION['assets'][$index]['status'] = 'Under Maintenance';

            }

            return;

        }
    }
}



function hasActiveMaintenance($assetId)
{
    if (empty($assetId)) {
        return false;
    }

    foreach ($_SESSION['maintenance'] ?? [] as $item) {
        if (
            isset($item['asset_id']) &&
            $item['asset_id'] === $assetId &&
            ($item['status'] ?? '') !== 'Completed'
        ) {
            return true;
        }
    }

    return false;
}


function updateAssetStatusFromAudit($assetId, $auditResult)
{
    if (empty($assetId)) {
        return;
    }

    // Respect Maintenance module ownership of "Under Maintenance"
    if (hasActiveMaintenance($assetId) && in_array($auditResult, ['Verified', 'For Investigation'], true)) {
        return;
    }

    foreach ($_SESSION['assets'] as $index => $asset) {
        if (isset($asset['asset_id']) && $asset['asset_id'] === $assetId) {

            switch ($auditResult) {

                case 'Missing':
                    $_SESSION['assets'][$index]['status'] = 'Lost';
                    break;

                case 'Damaged':
                    $_SESSION['assets'][$index]['status'] = 'Under Maintenance';
                    break;

                case 'Verified':
                    $_SESSION['assets'][$index]['status'] =
                        !empty($asset['custodian']) ? 'Assigned' : 'Available';
                    break;

                case 'For Investigation':
                default:
                    // Keep current status unchanged
                    break;
            }

            return;
        }
    }
}

function syncAssetStatusAfterAuditRemoval($assetId)
{
    if (empty($assetId)) {
        return;
    }

    // Maintenance module still owns "Under Maintenance" if it's active
    if (hasActiveMaintenance($assetId)) {
        return;
    }

    $remainingResult = null;

    foreach ($_SESSION['audits'] ?? [] as $audit) {
        if (isset($audit['asset_id']) && $audit['asset_id'] === $assetId) {
            $remainingResult = $audit['result'] ?? $remainingResult;
        }
    }

    if ($remainingResult === 'Missing') {
        updateAssetStatusFromAudit($assetId, 'Missing');
        return;
    }

    if ($remainingResult === 'Damaged') {
        updateAssetStatusFromAudit($assetId, 'Damaged');
        return;
    }

    // No remaining Missing/Damaged findings — restore by custodian
    updateAssetStatusFromAudit($assetId, 'Verified');
}

function findInventoryIndexByNameCategory($assetName, $category)
{
    foreach ($_SESSION['inventory'] ?? [] as $index => $item) {
        if (
            isset($item['asset_name'], $item['category']) &&
            strcasecmp($item['asset_name'], $assetName) === 0 &&
            strcasecmp($item['category'], $category) === 0
        ) {
            return $index;
        }
    }

    return null;
}

function adjustInventoryStock($assetName, $category, $quantityDelta)
{
    if ((int) $quantityDelta === 0) {
        return;
    }

    if (!isset($_SESSION['inventory'])) {
        $_SESSION['inventory'] = [];
    }

    $index = findInventoryIndexByNameCategory($assetName, $category);

    if ($index !== null) {

        $currentQty = (int) ($_SESSION['inventory'][$index]['quantity'] ?? 0);
        $_SESSION['inventory'][$index]['quantity'] = max(0, $currentQty + $quantityDelta);

    } elseif ($quantityDelta > 0) {

        $_SESSION['inventory'][] = [
            'inventory_id' => generateInventoryID(),
            'asset_name' => $assetName,
            'category' => $category,
            'quantity' => $quantityDelta,
            'condition' => 'Good'
        ];

    }
}