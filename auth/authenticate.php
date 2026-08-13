<?php

session_start();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/../includes/database.php';

$employeeID = trim($_POST["employee_id"]);
$password = trim($_POST["password"]);

$authenticatedUser = null;

try {
    $pdo = getDbConnection();

    $stmt = $pdo->prepare(
        "SELECT employee_id, full_name, role, password_hash
         FROM users
         WHERE employee_id = :employee_id"
    );

    $stmt->execute([
        'employee_id' => $employeeID
    ]);

    $row = $stmt->fetch();

    if ($row && password_verify($password, $row['password_hash'])) {
        $authenticatedUser = $row;
    }
} catch (Throwable $e) {
    // Fail closed. Do not expose database or credential details.
    $authenticatedUser = null;
}

if ($authenticatedUser !== null) {
    $_SESSION["user"] = [
        "employee_id" => $authenticatedUser["employee_id"],
        "name" => $authenticatedUser["full_name"],
        "role" => $authenticatedUser["role"]
    ];

    header("Location: ../dashboard.php");
    exit();
}

header("Location: login.php?error=1");
exit();