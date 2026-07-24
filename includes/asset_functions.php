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