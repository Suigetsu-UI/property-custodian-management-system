<?php

session_start();

if (!isset($_GET['id'])) {

    header("Location:index.php");
    exit();

}

$id = (int) $_GET['id'];

if (isset($_SESSION['assets'][$id])) {

    unset($_SESSION['assets'][$id]);

    // Re-index the array
    $_SESSION['assets'] = array_values($_SESSION['assets']);

}

header("Location:index.php");

exit();