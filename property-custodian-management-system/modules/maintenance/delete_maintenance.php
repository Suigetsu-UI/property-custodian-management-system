<?php

require_once "../../auth/check_auth.php";

$id = $_GET['id'] ?? null;

if (
    $id !== null &&
    isset($_SESSION['maintenance'][$id])
) {

    unset($_SESSION['maintenance'][$id]);

    // Re-index the array
    $_SESSION['maintenance'] = array_values($_SESSION['maintenance']);
}

header("Location: index.php");
exit;