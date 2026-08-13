<?php

session_start();

if (!isset($_GET['id'])) {
    header("Location:index.php");
    exit();
}

$id = (int)$_GET['id'];

if (!isset($_SESSION['assets'][$id])) {
    header("Location:index.php");
    exit();
}

if (($_SESSION['assets'][$id]['status'] ?? 'Available') === 'Under Maintenance') {
    header("Location:index.php?error=maintenance");
    exit();
}

if (($_SESSION['assets'][$id]['status'] ?? 'Available') === 'Lost') {
    header("Location:index.php?error=lost");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $employeeId = trim($_POST['employee_id'] ?? '');
    $custodian = trim($_POST['custodian'] ?? '');

    $_SESSION['assets'][$id]['employee_id']
        = $employeeId;

    $_SESSION['assets'][$id]['custodian']
        = $custodian;

    $_SESSION['assets'][$id]['department']
        = trim($_POST['department'] ?? '');

    $_SESSION['assets'][$id]['date_assigned']
        = trim($_POST['date_assigned'] ?? '');

    $_SESSION['assets'][$id]['status']
        = ($employeeId !== '' && $custodian !== '') ? 'Assigned' : 'Available';

}

header("Location:index.php");

exit();