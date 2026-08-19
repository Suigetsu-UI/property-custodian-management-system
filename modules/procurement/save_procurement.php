<?php

require_once "../../auth/check_auth.php";
require_once __DIR__ . "/../../includes/asset_functions.php";
require_once __DIR__ . "/../../includes/database.php";

$id = isset($_POST['id']) && $_POST['id'] !== ''
    ? (int) $_POST['id']
    : null;

$itemName = trim($_POST['item_name'] ?? '');
$category = trim($_POST['category'] ?? '');
$quantity = (int) ($_POST['quantity'] ?? 0);
$supplier = trim($_POST['supplier'] ?? '');
$requestedBy = trim($_POST['requested_by'] ?? '');
$requestDate = trim($_POST['request_date'] ?? '');
$newStatus = trim($_POST['status'] ?? '');
$approvedBy = trim($_POST['approved_by'] ?? '');
$approvalDate = trim($_POST['approval_date'] ?? '');
$remarks = trim($_POST['remarks'] ?? '');

/*
|--------------------------------------------------------------------------
| Normalize optional DATE values
|--------------------------------------------------------------------------
| PostgreSQL DATE columns do not accept an empty string as a date.
*/
$requestDate = $requestDate !== '' ? $requestDate : null;
$approvalDate = $approvalDate !== '' ? $approvalDate : null;

/*
|--------------------------------------------------------------------------
| Settled Procurement transition matrix
|--------------------------------------------------------------------------
*/
$allowedTransitions = [
    'Pending' => [
        'Pending',
        'Approved',
        'Rejected',
        'Delivered',
    ],

    'Approved' => [
        'Pending',
        'Approved',
        'Rejected',
        'Delivered',
    ],

    'Rejected' => [
        'Pending',
        'Approved',
        'Rejected',
    ],

    'Delivered' => [
        'Delivered',
    ],
];

/*
 * Reject an invalid submitted status before touching the database.
 */
if (!array_key_exists($newStatus, $allowedTransitions)) {
    header("Location: index.php?error=invalid_status");
    exit;
}

/*
 * Quantity must remain positive, matching the database CHECK constraint
 * and the existing form's min="1" behavior.
 */
if (
    $quantity <= 0 ||
    $itemName === '' ||
    $category === '' ||
    $supplier === ''
) {
    header("Location: index.php?error=save_failed");
    exit;
}

$pdo = null;
$createdProcurementId = null;

try {
    $pdo = getDbConnection();
    $pdo->beginTransaction();

    if ($id !== null) {

        /*
        |--------------------------------------------------------------------------
        | EDIT
        |--------------------------------------------------------------------------
        | Procurement row is always locked before any Inventory rows.
        */
        $stmt = $pdo->prepare(
            "SELECT
                procurement_id,
                item_name,
                category,
                status,
                delivered_quantity
             FROM procurement
             WHERE id = :id
             FOR UPDATE"
        );

        $stmt->execute([
            'id' => $id,
        ]);

        $existing = $stmt->fetch();

        if (!$existing) {
            $pdo->rollBack();

            header("Location: index.php");
            exit;
        }

        $previousStatus = $existing['status'];

        if (
            !isset($allowedTransitions[$previousStatus]) ||
            !in_array(
                $newStatus,
                $allowedTransitions[$previousStatus],
                true
            )
        ) {
            $pdo->rollBack();

            header("Location: index.php?error=invalid_status");
            exit;
        }

        $oldItemName = $existing['item_name'];
        $oldCategory = $existing['category'];
        $oldDeliveredQuantity =
            (int) $existing['delivered_quantity'];

        $newDeliveredQuantity = $oldDeliveredQuantity;

        /*
         * Only Delivered may affect Inventory.
         *
         * transferInventoryStockPdo() handles both:
         *
         * 1. Same logical item/category:
         *      new quantity - old delivered quantity
         *
         * 2. Changed item/category:
         *      remove old delivered quantity from old Inventory
         *      then add the new quantity to new Inventory
         *
         * All Inventory work remains inside this same transaction.
         */
        if ($newStatus === 'Delivered') {
            transferInventoryStockPdo(
                $pdo,
                $oldItemName,
                $oldCategory,
                $oldDeliveredQuantity,
                $itemName,
                $category,
                $quantity
            );

            $newDeliveredQuantity = $quantity;
        }

        /*
         * procurement_id is intentionally immutable.
         */
        $update = $pdo->prepare(
            "UPDATE procurement
             SET
                item_name = :item_name,
                category = :category,
                quantity = :quantity,
                supplier = :supplier,
                requested_by = :requested_by,
                request_date = :request_date,
                status = :status,
                approved_by = :approved_by,
                approval_date = :approval_date,
                remarks = :remarks,
                delivered_quantity = :delivered_quantity,
                updated_at = now()
             WHERE id = :id"
        );

        $update->execute([
            'item_name' => $itemName,
            'category' => $category,
            'quantity' => $quantity,
            'supplier' => $supplier,
            'requested_by' => $requestedBy,
            'request_date' => $requestDate,
            'status' => $newStatus,
            'approved_by' => $approvedBy,
            'approval_date' => $approvalDate,
            'remarks' => $remarks,
            'delivered_quantity' => $newDeliveredQuantity,
            'id' => $id,
        ]);

    } else {

        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        | The visible Procurement ID must have been issued by
        | next_procurement_id.php to this authenticated PHP session.
        */
        $submittedId = trim(
            $_POST['procurement_id'] ?? ''
        );

        $validFormat =
            preg_match('/^PRC-\d{6}$/', $submittedId) === 1;

        $issuedToSession =
            isset(
                $_SESSION['pending_procurement_ids'][$submittedId]
            );

        if (!$validFormat || !$issuedToSession) {
            $pdo->rollBack();

            header("Location: index.php?error=invalid_id");
            exit;
        }

        /*
         * Friendly duplicate protection.
         *
         * The UNIQUE database constraint remains the final structural
         * protection against duplicates.
         */
        $check = $pdo->prepare(
            "SELECT 1
             FROM procurement
             WHERE procurement_id = :procurement_id"
        );

        $check->execute([
            'procurement_id' => $submittedId,
        ]);

        if ($check->fetch()) {
            $pdo->rollBack();

            unset(
                $_SESSION['pending_procurement_ids'][$submittedId]
            );

            header("Location: index.php?error=duplicate_id");
            exit;
        }

        $deliveredQuantity = 0;

        /*
         * Create-as-Delivered is allowed.
         *
         * Previous delivered quantity is zero, so the entire requested
         * quantity is added to Inventory.
         */
        if ($newStatus === 'Delivered') {
            adjustInventoryStockPdo(
                $pdo,
                $itemName,
                $category,
                $quantity
            );

            $deliveredQuantity = $quantity;
        }

        $insert = $pdo->prepare(
            "INSERT INTO procurement (
                procurement_id,
                item_name,
                category,
                quantity,
                supplier,
                requested_by,
                request_date,
                status,
                approved_by,
                approval_date,
                remarks,
                delivered_quantity
             )
             VALUES (
                :procurement_id,
                :item_name,
                :category,
                :quantity,
                :supplier,
                :requested_by,
                :request_date,
                :status,
                :approved_by,
                :approval_date,
                :remarks,
                :delivered_quantity
             )"
        );

        $insert->execute([
            'procurement_id' => $submittedId,
            'item_name' => $itemName,
            'category' => $category,
            'quantity' => $quantity,
            'supplier' => $supplier,
            'requested_by' => $requestedBy,
            'request_date' => $requestDate,
            'status' => $newStatus,
            'approved_by' => $approvedBy,
            'approval_date' => $approvalDate,
            'remarks' => $remarks,
            'delivered_quantity' => $deliveredQuantity,
        ]);

        /*
         * Retire this temporary session-issued ID only after the database
         * transaction successfully commits below.
         */
        $createdProcurementId = $submittedId;
    }

    $pdo->commit();

    if ($createdProcurementId !== null) {
        unset(
            $_SESSION['pending_procurement_ids'][$createdProcurementId]
        );

        if (
            isset($_SESSION['pending_procurement_ids']) &&
            empty($_SESSION['pending_procurement_ids'])
        ) {
            unset($_SESSION['pending_procurement_ids']);
        }
    }

} catch (RuntimeException $e) {
    if (
        $pdo instanceof PDO &&
        $pdo->inTransaction()
    ) {
        $pdo->rollBack();
    }

    if ($e->getMessage() === 'INVENTORY_NEGATIVE') {
        header(
            "Location: index.php?error=inventory_negative"
        );
        exit;
    }

    header("Location: index.php?error=save_failed");
    exit;

} catch (Throwable $e) {
    if (
        $pdo instanceof PDO &&
        $pdo->inTransaction()
    ) {
        $pdo->rollBack();
    }

    header("Location: index.php?error=save_failed");
    exit;
}

header("Location: index.php");
exit;