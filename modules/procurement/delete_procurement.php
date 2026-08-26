<?php

require_once "../../auth/check_auth.php";
require_once __DIR__ . "/../../includes/database.php";
require_once __DIR__ . "/../../includes/event_functions.php";

requireValidAccessCsrfPost();

$id = isset($_POST['id']) ? (int) $_POST['id'] : null;

if ($id !== null) {
    $pdo = getDbConnection();

    try {
        $pdo->beginTransaction();

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

        $row = $stmt->fetch();

        if ($row) {
            if ((int) $row['delivered_quantity'] > 0) {
                $pdo->rollBack();

                header("Location: index.php?error=delivered");
                exit;
            }

            recordPropertyEvent($pdo, [
                'module' => 'Procurement',
                'event_type' => 'Deleted',
                'business_id' => $row['procurement_id'],
                'record_name_snap' => $row['item_name'],
                'category_snap' => $row['category'],
                'event_date' => currentPropertyEventDate(),
                'from_status' => $row['status'],
                'performed_by' => currentPropertyEventActor(),
            ]);

            $delete = $pdo->prepare(
                "DELETE FROM procurement
                 WHERE id = :id"
            );

            $delete->execute([
                'id' => $id,
            ]);
        }

        $pdo->commit();

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        header("Location: index.php?error=save_failed");
        exit;
    }
}

header("Location: index.php");
exit;
