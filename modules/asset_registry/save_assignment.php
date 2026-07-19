<?php

session_start();

if (!isset($_GET['id'])) {
    header("Location:index.php");
    exit();
}

$id = (int)$_GET['id'];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $_SESSION['assets'][$id]['employee_id']
        = trim($_POST['employee_id']);

    $_SESSION['assets'][$id]['custodian']
        = trim($_POST['custodian']);

    $_SESSION['assets'][$id]['department']
        = trim($_POST['department']);

    $_SESSION['assets'][$id]['date_assigned']
        = trim($_POST['date_assigned']);

}

header("Location:index.php");

exit();