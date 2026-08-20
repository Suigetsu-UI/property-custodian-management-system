<?php

require_once "../../auth/check_auth.php";
require_once __DIR__ . "/../../includes/database.php";

$idRaw = $_GET['id'] ?? null;

$id = filter_var(
    $idRaw,
    FILTER_VALIDATE_INT,
    [
        'options' => [
            'min_range' => 1
        ]
    ]
);

if ($id === false) {
    header("Location: index.php");
    exit;
}

$pdo = getDbConnection();

$stmt = $pdo->prepare(
    "SELECT *
     FROM inventory
     WHERE id = :id"
);

$stmt->execute([
    'id' => $id
]);

$inventory = $stmt->fetch();

if (!$inventory) {
    header("Location: index.php");
    exit;
}

/*
 * Header is intentionally included only after the
 * not-found redirect decision.
 */
include "../../includes/header.php";

?>

<div class="layout">

    <?php include "../../includes/sidebar.php"; ?>

    <div class="main-content">

        <h1>Edit Inventory</h1>

        <hr>

        <?php include "inventory_form.php"; ?>

    </div>

</div>

<?php include "../../includes/footer.php"; ?>