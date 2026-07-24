<?php

session_start();

if (!isset($_GET['id'])) {
    header("Location:index.php");
    exit();
}

$id = (int) $_GET['id'];

if (isset($_SESSION['assets'][$id])) {

    if (($_SESSION['assets'][$id]['status'] ?? 'Available') === 'Under Maintenance') {
        header("Location:index.php?error=maintenance");
        exit();
    }

    unset($_SESSION['assets'][$id]['employee_id']);
    unset($_SESSION['assets'][$id]['custodian']);
    unset($_SESSION['assets'][$id]['department']);
    unset($_SESSION['assets'][$id]['date_assigned']);

    $_SESSION['assets'][$id]['status'] = 'Available';

}

header("Location:index.php");

exit();