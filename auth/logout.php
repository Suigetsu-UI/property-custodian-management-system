<?php

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/access_control.php';

startPcmsSession();
requireValidAccessCsrfPost();

/*
|--------------------------------------------------------------------------
| Remove all session data
|--------------------------------------------------------------------------
*/

destroyPcmsSession();

/*
|--------------------------------------------------------------------------
| Prevent browser cache
|--------------------------------------------------------------------------
*/

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

/*
|--------------------------------------------------------------------------
| Back to Login
|--------------------------------------------------------------------------
*/

header("Location: login.php");

exit();

?>
