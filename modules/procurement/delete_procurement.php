<?php

require_once "../../auth/check_auth.php";
require_once __DIR__ . "/../../includes/database.php";

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

if ($id !== null) {
    $pdo = getDbConnection();

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            "SELECT delivered_quantity
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