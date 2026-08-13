<?php

require_once "../../auth/check_auth.php";

$id = $_GET['id'] ?? null;

if ($id !== null && isset($_SESSION['procurement'][$id])) {

    $deliveredQuantity = (int) ($_SESSION['procurement'][$id]['delivered_quantity'] ?? 0);

    if ($deliveredQuantity > 0) {

        header("Location: index.php?error=delivered");
        exit;

    }

    unset($_SESSION['procurement'][$id]);

    $_SESSION['procurement'] = array_values($_SESSION['procurement']);

}

header("Location: index.php");
exit;