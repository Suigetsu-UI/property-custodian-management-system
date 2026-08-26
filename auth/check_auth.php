<?php

require_once __DIR__ . '/../includes/session.php';

$pcmsSessionValid = startPcmsSession();

/*
|--------------------------------------------------------------------------
| Check if User is Logged In
|--------------------------------------------------------------------------
*/

if (!$pcmsSessionValid || !isset($_SESSION['user'])) {

    header(
        "Location: /property-custodian-management-system/auth/login.php?error=" .
        ($pcmsSessionValid ? 'session' : 'expired')
    );
    exit();

}

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/access_control.php';

$sessionEmployeeID = trim((string) (
    $_SESSION['user']['employee_id'] ?? ''
));
$sessionVersion = (int) (
    $_SESSION['user']['session_version'] ?? 0
);
$verifiedUser = null;

if ($sessionEmployeeID !== '') {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            "SELECT id, employee_id, full_name, role, is_active,
                    session_version
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

if (
    !$verifiedUser ||
    !isUserAccountActive($verifiedUser['is_active']) ||
    $sessionVersion < 1 ||
    !hash_equals(
        (string) $verifiedUser['session_version'],
        (string) $sessionVersion
    )
) {
    destroyPcmsSession();

    header(
        "Location: /property-custodian-management-system/auth/login.php?error=" .
        (
            $verifiedUser &&
            !isUserAccountActive($verifiedUser['is_active'])
                ? 'inactive'
                : 'session'
        )
    );
    exit();
}

$_SESSION['user'] = [
    'id' => (int) $verifiedUser['id'],
    'employee_id' => $verifiedUser['employee_id'],
    'name' => $verifiedUser['full_name'],
    'role' => $verifiedUser['role'],
    'session_version' => (int) $verifiedUser['session_version'],
];

?>
