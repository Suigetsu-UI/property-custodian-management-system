<?php

require_once "../../auth/check_auth.php";

$id = $_GET['id'] ?? null;

if (
    $id !== null &&
    isset($_SESSION['inventory'][$id])
) {

    unset($_SESSION['inventory'][$id]);

    // Re-index the array
    $_SESSION['inventory'] = array_values($_SESSION['inventory']);
}

header("Location: index.php");
exit;