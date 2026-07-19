<?php

session_start();

if(!isset($_GET['id'])){

header("Location:index.php");
exit();

}

$id=(int)$_GET['id'];

if($_SERVER["REQUEST_METHOD"]=="POST"){

$_SESSION['assets'][$id]['asset_name']
=$_POST['asset_name'];

$_SESSION['assets'][$id]['category']
=$_POST['category'];

$_SESSION['assets'][$id]['brand']
=$_POST['brand'];

$_SESSION['assets'][$id]['model']
=$_POST['model'];

$_SESSION['assets'][$id]['serial_number']
=$_POST['serial_number'];

$_SESSION['assets'][$id]['supplier']
=$_POST['supplier'];

$_SESSION['assets'][$id]['location']
=$_POST['location'];

$_SESSION['assets'][$id]['remarks']
=$_POST['remarks'];

}

header("Location:index.php");

exit();