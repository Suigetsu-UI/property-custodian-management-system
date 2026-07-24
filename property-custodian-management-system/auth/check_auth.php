<?php

/*
|--------------------------------------------------------------------------
| Start Session
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Check if User is Logged In
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user'])) {

    header("Location: /property-custodian-management-system/auth/login.php");
    exit();

}

?>