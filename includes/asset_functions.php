<?php

/*
|--------------------------------------------------------------------------
| PostgreSQL Business-ID / Inventory Helpers
|--------------------------------------------------------------------------
| Shared helpers for PostgreSQL-backed business IDs and Inventory stock
| adjustments used by the persistent PCMS business modules.
*/

function nextBusinessId(PDO $pdo, string $entity, string $prefix): string
{
    $allowedSequences = [
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
