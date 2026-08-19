<?php

require_once "../../auth/check_auth.php";
require_once __DIR__ . "/../../includes/database.php";

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

$pdo = getDbConnection();
$procurement = null;

if ($id !== null) {
    $stmt = $pdo->prepare(
        "SELECT *
         FROM procurement
         WHERE id = :id"
    );

    $stmt->execute([
        'id' => $id,
    ]);

    $procurement = $stmt->fetch() ?: null;
}

if (!$procurement) {
    header("Location: index.php");
    exit;
}

include "../../includes/header.php";

?>

<div class="layout">

<?php include "../../includes/sidebar.php"; ?>

<div class="main-content">

<h1>Edit Procurement</h1>

<hr>

<?php include "procurement_form.php"; ?>

</div>

</div>

<?php include "../../includes/footer.php"; ?>