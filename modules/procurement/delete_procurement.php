<?php

require_once "../../auth/check_auth.php";

$id = $_GET['id'] ?? null;

if ($id !== null && isset($_SESSION['procurement'][$id])) {

    unset($_SESSION['procurement'][$id]);

    $_SESSION['procurement'] = array_values($_SESSION['procurement']);

}

header("Location: index.php");
exit;