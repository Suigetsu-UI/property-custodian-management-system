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

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/access_control.php';

$sessionEmployeeID = trim((string) (
    $_SESSION['user']['employee_id'] ?? ''
));
$verifiedUser = null;

if ($sessionEmployeeID !== '') {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            "SELECT id, employee_id, full_name, role, is_active
             FROM users
             WHERE employee_id = :employee_id"
        );
        $stmt->execute([
            'employee_id' => $sessionEmployeeID,
        ]);
        $verifiedUser = $stmt->fetch();
    } catch (Throwable $error) {
        $verifiedUser = null;
    }
}

if (!$verifiedUser || !isUserAccountActive($verifiedUser['is_active'])) {
    $_SESSION = [];
    session_destroy();

    header(
        "Location: /property-custodian-management-system/auth/login.php?error=" .
        ($verifiedUser ? 'inactive' : 'session')
    );
    exit();
}

$_SESSION['user'] = [
    'id' => (int) $verifiedUser['id'],
    'employee_id' => $verifiedUser['employee_id'],
    'name' => $verifiedUser['full_name'],
    'role' => $verifiedUser['role'],
];

?>
