<?php

require_once "../../auth/check_auth.php";
require_once __DIR__ . "/../../includes/database.php";
require_once __DIR__ . "/../../includes/event_functions.php";

requireValidAccessCsrfPost();

$idRaw = trim((string) ($_POST['id'] ?? ''));
$isEdit = $idRaw !== '';

$id = null;

if ($isEdit) {

    $validatedId = filter_var(
        $idRaw,
        FILTER_VALIDATE_INT,
        [
            'options' => [
                'min_range' => 1
            ]
        ]
    );

    if ($validatedId === false) {
        header("Location: index.php?error=save_failed");
        exit;
    }

    $id = (int) $validatedId;
}

$assetName = trim($_POST['asset_name'] ?? '');
$category = trim($_POST['category'] ?? '');
$condition = trim($_POST['condition'] ?? '');

$quantityRaw = $_POST['quantity'] ?? null;

/*
 * Validate the submitted value before converting it.
 *
 * This prevents malformed values such as "hello" from becoming 0
 * through a normal integer cast.
 */
$quantity = filter_var(
    $quantityRaw,
    FILTER_VALIDATE_INT
);

if (
    $assetName === '' ||
    $category === '' ||
    $condition === '' ||
    $quantity === false
) {
    header("Location: index.php?error=save_failed");
    exit;
}

$quantity = (int) $quantity;

/*
 * Settled quantity rules:
 *
 * Create: quantity must be greater than 0.
 * Edit:   quantity may be 0 or greater.
 */
if (
    (!$isEdit && $quantity <= 0) ||
    ($isEdit && $quantity < 0)
) {
    header("Location: index.php?error=save_failed");
    exit;
}

$submittedId = null;

/*
 * Validate the sequence-issued visible Inventory ID before opening
 * the database transaction.
 */
if (!$isEdit) {

    $submittedId = trim($_POST['inventory_id'] ?? '');

    $validFormat =
        preg_match('/^INV-\d{6}$/', $submittedId) === 1;

    $issuedToSession =
        isset($_SESSION['pending_inventory_ids'][$submittedId]);

    if (!$validFormat || !$issuedToSession) {
        header("Location: index.php?error=invalid_id");
        exit;
    }
}

$pdo = null;
$createdInventoryId = null;

try {

    $pdo = getDbConnection();

    $pdo->beginTransaction();

    if ($isEdit) {

        /*
         * EDIT
         *
         * Lock the row first so validation and the resulting UPDATE
         * operate against one stable record.
         */
        $stmt = $pdo->prepare(
            "SELECT
                inventory_id,
                asset_name,
                category,
                quantity,
                condition
             FROM inventory
             WHERE id = :id
             FOR UPDATE"
        );

        $stmt->execute([
            'id' => $id
        ]);

        $existing = $stmt->fetch();

        if (!$existing) {
            $pdo->rollBack();

            header("Location: index.php");
            exit;
        }

        /*
         * Prevent another Inventory row from acquiring the same
         * case-insensitive logical identity.
         */
        $duplicate = $pdo->prepare(
            "SELECT 1
             FROM inventory
             WHERE lower(asset_name) = lower(:asset_name)
               AND lower(category) = lower(:category)
               AND id <> :id
             LIMIT 1"
        );

        $duplicate->execute([
            'asset_name' => $assetName,
            'category' => $category,
            'id' => $id
        ]);

        if ($duplicate->fetch()) {

            $pdo->rollBack();

            header("Location: index.php?error=duplicate_item");
            exit;
        }

        /*
         * inventory_id is intentionally immutable.
         */
        $update = $pdo->prepare(
            "UPDATE inventory
             SET
                 asset_name = :asset_name,
                 category = :category,
                 quantity = :quantity,
                 condition = :condition,
                 updated_at = now()
             WHERE id = :id"
        );

        $update->execute([
            'asset_name' => $assetName,
            'category' => $category,
            'quantity' => $quantity,
            'condition' => $condition,
            'id' => $id
        ]);

        $quantityDelta = $quantity - (int) $existing['quantity'];

        if ($quantityDelta !== 0) {
            recordPropertyEvent($pdo, [
                'module' => 'Inventory',
                'event_type' => 'Adjusted',
                'business_id' => $existing['inventory_id'],
                'record_name_snap' => $assetName,
                'category_snap' => $category,
                'event_date' => currentPropertyEventDate(),
                'quantity_delta' => $quantityDelta,
                'performed_by' => currentPropertyEventActor(),
                'description' => 'Manual Inventory quantity adjustment.',
            ]);
        }

    } else {

        /*
         * CREATE
         *
         * Manual Add must not merge into an existing Inventory row.
         * Procurement Delivery remains responsible for automatic stock
         * increases against an existing logical identity.
         */
        $duplicate = $pdo->prepare(
            "SELECT 1
             FROM inventory
             WHERE lower(asset_name) = lower(:asset_name)
               AND lower(category) = lower(:category)
             LIMIT 1"
        );

        $duplicate->execute([
            'asset_name' => $assetName,
            'category' => $category
        ]);

        if ($duplicate->fetch()) {

            $pdo->rollBack();

            /*
             * Keep the issued INV ID available so the same form can be
             * corrected and legitimately retried.
             */
            header("Location: index.php?error=duplicate_item");
            exit;
        }

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
                :condition
             )"
        );

        $insert->execute([
            'inventory_id' => $submittedId,
            'asset_name' => $assetName,
            'category' => $category,
            'quantity' => $quantity,
            'condition' => $condition
        ]);

        recordPropertyEvent($pdo, [
            'module' => 'Inventory',
            'event_type' => 'Added',
            'business_id' => $submittedId,
            'record_name_snap' => $assetName,
            'category_snap' => $category,
            'event_date' => currentPropertyEventDate(),
            'quantity_delta' => $quantity,
            'performed_by' => currentPropertyEventActor(),
        ]);

        /*
         * Remember what was created, but do NOT remove the session-issued
         * ID yet. Cleanup happens only after commit succeeds.
         */
        $createdInventoryId = $submittedId;
    }

    $pdo->commit();

    /*
     * Database commit succeeded.
     * Only now retire the temporary issued-ID validation state.
     */
    if ($createdInventoryId !== null) {

        unset(
            $_SESSION['pending_inventory_ids'][$createdInventoryId]
        );

        if (
            isset($_SESSION['pending_inventory_ids']) &&
            empty($_SESSION['pending_inventory_ids'])
        ) {
            unset($_SESSION['pending_inventory_ids']);
        }
    }

} catch (PDOException $e) {

    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    /*
     * The database unique index remains the final concurrency backstop
     * for lower(asset_name), lower(category).
     */
    if ($e->getCode() === '23505') {
        header("Location: index.php?error=duplicate_item");
        exit;
    }

    header("Location: index.php?error=save_failed");
    exit;

} catch (Throwable $e) {

    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    header("Location: index.php?error=save_failed");
    exit;
}

header("Location: index.php");
exit;
