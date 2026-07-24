<?php

require_once "../../auth/check_auth.php";

$id = $_GET['id'] ?? null;

if ($id !== null && isset($_SESSION['audits'][$id])) {
    unset($_SESSION['audits'][$id]);
    $_SESSION['audits'] = array_values($_SESSION['audits']);
}

header("Location: index.php");
exit;
