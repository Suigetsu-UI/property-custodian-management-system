<?php

require_once "../../auth/check_auth.php";

$id = $_GET['id'] ?? null;

if ($id !== null && isset($_SESSION['reports'][$id])) {
    unset($_SESSION['reports'][$id]);
    $_SESSION['reports'] = array_values($_SESSION['reports']);
}

header("Location: index.php");
exit;
