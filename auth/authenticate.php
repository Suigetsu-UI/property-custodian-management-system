<?php

session_start();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    header("Location: login.php");
    exit();

}

$employeeID = trim($_POST["employee_id"]);
$password = trim($_POST["password"]);

/*
|--------------------------------------------------------------------------
| Temporary Login
|--------------------------------------------------------------------------
| This will later be replaced by a PostgreSQL/Supabase query.
*/

if ($employeeID === "admin" && $password === "admin123") {

    $_SESSION["user"] = [

        "employee_id" => "admin",

        "name" => "System Administrator",

        "role" => "Administrator"

    ];

    header("Location: ../dashboard.php");

    exit();

}

header("Location: login.php?error=1");

exit();

?>