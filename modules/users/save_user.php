<?php

require_once "../../auth/check_auth.php";
require_once __DIR__ . "/../../includes/access_control.php";
require_once __DIR__ . "/../../includes/database.php";

requireAdministrator();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

if (!isValidAccessCsrfToken($_POST['csrf_token'] ?? null)) {
    header('Location: index.php?error=invalid');
    exit;
}

$employeeID = trim((string) ($_POST['employee_id'] ?? ''));
$fullName = trim((string) ($_POST['full_name'] ?? ''));
$role = trim((string) ($_POST['role'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

if (
    !preg_match('/^[A-Za-z0-9._-]{2,50}$/', $employeeID) ||
    strlen($fullName) < 2 ||
    strlen($fullName) > 150 ||
    !in_array($role, getAllowedUserRoles(), true) ||
    strlen($password) < 8 ||
    strlen($password) > 72 ||
    !hash_equals($password, $passwordConfirm)
) {
    header('Location: index.php?error=invalid');
    exit;
}

try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare(
        "INSERT INTO users (
            employee_id,
            full_name,
            password_hash,
            role,
            is_active
         ) VALUES (
            :employee_id,
            :full_name,
            :password_hash,
            :role,
            TRUE
         )"
    );
    $stmt->execute([
        'employee_id' => $employeeID,
        'full_name' => $fullName,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => $role,
    ]);
} catch (PDOException $error) {
    header(
        'Location: index.php?error=' .
        ($error->getCode() === '23505' ? 'duplicate' : 'save_failed')
    );
    exit;
} catch (Throwable $error) {
    header('Location: index.php?error=save_failed');
    exit;
}

header('Location: index.php?message=created');
exit;
