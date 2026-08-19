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

/*
|--------------------------------------------------------------------------
| PostgreSQL Business-ID / Inventory Helpers
|--------------------------------------------------------------------------
| Added during the phased Supabase migration.
|
| Existing session-based helpers above remain in place temporarily for
| modules that have not yet been migrated.
*/

function nextBusinessId(PDO $pdo, string $entity, string $prefix): string
{
    $allowedSequences = [
        'procurement' => [
            'sequence' => 'procurement_id_seq',
            'prefix' => 'PRC',
        ],
        'inventory' => [
            'sequence' => 'inventory_id_seq',
            'prefix' => 'INV',
        ],
        'asset' => [
            'sequence' => 'asset_id_seq',
            'prefix' => 'AST',
        ],
        'maintenance' => [
            'sequence' => 'maintenance_id_seq',
            'prefix' => 'MNT',
        ],
        'audit' => [
            'sequence' => 'audit_id_seq',
            'prefix' => 'AUD',
        ],
    ];

    if (!isset($allowedSequences[$entity])) {
        throw new InvalidArgumentException('Unknown business-ID sequence key.');
    }

    if ($allowedSequences[$entity]['prefix'] !== $prefix) {
        throw new InvalidArgumentException('Invalid business-ID prefix.');
    }

    $sequenceName = $allowedSequences[$entity]['sequence'];

    $stmt = $pdo->query(
        "SELECT nextval('" . $sequenceName . "') AS n"
    );

    $row = $stmt->fetch();

    if (!$row || !isset($row['n'])) {
        throw new RuntimeException('BUSINESS_ID_GENERATION_FAILED');
    }

    $number = (int) $row['n'];

    return $prefix . '-' . str_pad(
        (string) $number,
        6,
        '0',
        STR_PAD_LEFT
    );
}

function adjustInventoryStockPdo(
    PDO $pdo,
    string $assetName,
    string $category,
    int $delta
): void {
    if ($delta === 0) {
        return;
    }

    $select = $pdo->prepare(
        "SELECT id, quantity
         FROM inventory
         WHERE lower(asset_name) = lower(:asset_name)
           AND lower(category) = lower(:category)
         FOR UPDATE"
    );

    $select->execute([
        'asset_name' => $assetName,
        'category' => $category,
    ]);

    $row = $select->fetch();

    if ($row) {
        $newQuantity = (int) $row['quantity'] + $delta;

        if ($newQuantity < 0) {
            throw new RuntimeException('INVENTORY_NEGATIVE');
        }

        $update = $pdo->prepare(
            "UPDATE inventory
             SET quantity = :quantity,
                 updated_at = now()
             WHERE id = :id"
        );

        $update->execute([
            'quantity' => $newQuantity,
            'id' => $row['id'],
        ]);

        return;
    }

    if ($delta < 0) {
        // A negative adjustment must never create an Inventory row.
        throw new RuntimeException('INVENTORY_NEGATIVE');
    }

    /*
     * No matching row existed when we checked.
     *
     * Another transaction could create the same logical Inventory row
     * between that SELECT and this INSERT. ON CONFLICT DO NOTHING safely
     * handles that race; if another row won, we re-read it under lock and
     * add this transaction's positive quantity to it.
     */
    $inventoryId = nextBusinessId($pdo, 'inventory', 'INV');

    $insert = $pdo->prepare(
        "INSERT INTO inventory (
            inventory_id,
            asset_name,
            category,
            quantity,
            condition
         )
         VALUES (
            :inventory_id,
            :asset_name,
            :category,
            :quantity,
            'Good'
         )
         ON CONFLICT DO NOTHING
         RETURNING id"
    );

    $insert->execute([
        'inventory_id' => $inventoryId,
        'asset_name' => $assetName,
        'category' => $category,
        'quantity' => $delta,
    ]);

    $inserted = $insert->fetch();

    if ($inserted) {
        return;
    }

    /*
     * A concurrent transaction may have inserted the logical row first.
     * Re-read it under lock and apply our positive adjustment.
     */
    $select->execute([
        'asset_name' => $assetName,
        'category' => $category,
    ]);

    $row = $select->fetch();

    if (!$row) {
        throw new RuntimeException('INVENTORY_UPDATE_FAILED');
    }

    $newQuantity = (int) $row['quantity'] + $delta;

    $update = $pdo->prepare(
        "UPDATE inventory
         SET quantity = :quantity,
             updated_at = now()
         WHERE id = :id"
    );

    $update->execute([
        'quantity' => $newQuantity,
        'id' => $row['id'],
    ]);
}

function transferInventoryStockPdo(
    PDO $pdo,
    string $oldAssetName,
    string $oldCategory,
    int $oldDeliveredQuantity,
    string $newAssetName,
    string $newCategory,
    int $newDeliveredQuantity
): void {
    if ($oldDeliveredQuantity < 0 || $newDeliveredQuantity < 0) {
        throw new InvalidArgumentException(
            'Inventory transfer quantities cannot be negative.'
        );
    }

    $sameLogicalItem =
        strcasecmp($oldAssetName, $newAssetName) === 0 &&
        strcasecmp($oldCategory, $newCategory) === 0;

    if ($sameLogicalItem) {
        $delta = $newDeliveredQuantity - $oldDeliveredQuantity;

        adjustInventoryStockPdo(
            $pdo,
            $newAssetName,
            $newCategory,
            $delta
        );

        return;
    }

    /*
     * Lock existing old/new Inventory rows in a canonical order that does
     * not depend on transfer direction. This avoids A->B and B->A taking
     * the same pair of row locks in opposite order.
     */
    $itemsToLock = [
        [
            'role' => 'old',
            'asset_name' => $oldAssetName,
            'category' => $oldCategory,
        ],
        [
            'role' => 'new',
            'asset_name' => $newAssetName,
            'category' => $newCategory,
        ],
    ];

    usort(
        $itemsToLock,
        function (array $left, array $right): int {
            $nameComparison = strcasecmp(
                $left['asset_name'],
                $right['asset_name']
            );

            if ($nameComparison !== 0) {
                return $nameComparison;
            }

            return strcasecmp(
                $left['category'],
                $right['category']
            );
        }
    );

    $select = $pdo->prepare(
        "SELECT id, quantity
         FROM inventory
         WHERE lower(asset_name) = lower(:asset_name)
           AND lower(category) = lower(:category)
         FOR UPDATE"
    );

    $lockedRows = [
        'old' => null,
        'new' => null,
    ];

    foreach ($itemsToLock as $item) {
        $select->execute([
            'asset_name' => $item['asset_name'],
            'category' => $item['category'],
        ]);

        $lockedRows[$item['role']] = $select->fetch() ?: null;
    }

    /*
     * Business operation order remains old subtraction first.
     */
    if ($oldDeliveredQuantity > 0) {
        $oldRow = $lockedRows['old'];

        if (!$oldRow) {
            throw new RuntimeException('INVENTORY_NEGATIVE');
        }

        $remainingOldQuantity =
            (int) $oldRow['quantity'] - $oldDeliveredQuantity;

        if ($remainingOldQuantity < 0) {
            throw new RuntimeException('INVENTORY_NEGATIVE');
        }

        $updateOld = $pdo->prepare(
            "UPDATE inventory
             SET quantity = :quantity,
                 updated_at = now()
             WHERE id = :id"
        );

        $updateOld->execute([
            'quantity' => $remainingOldQuantity,
            'id' => $oldRow['id'],
        ]);
    }

    /*
     * Then add the new delivered quantity.
     */
    if ($newDeliveredQuantity === 0) {
        return;
    }

    $newRow = $lockedRows['new'];

    if ($newRow) {
        $updatedNewQuantity =
            (int) $newRow['quantity'] + $newDeliveredQuantity;

        $updateNew = $pdo->prepare(
            "UPDATE inventory
             SET quantity = :quantity,
                 updated_at = now()
             WHERE id = :id"
        );

        $updateNew->execute([
            'quantity' => $updatedNewQuantity,
            'id' => $newRow['id'],
        ]);

        return;
    }

    /*
     * The new logical Inventory row did not exist when locks were taken.
     * Use the normal positive-adjustment helper so concurrent creation of
     * that same row is handled safely.
     */
    adjustInventoryStockPdo(
        $pdo,
        $newAssetName,
        $newCategory,
        $newDeliveredQuantity
    );
}