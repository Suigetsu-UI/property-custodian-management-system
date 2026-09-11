<?php

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/config.php';

$pcmsSessionValid = startPcmsSession();

/*
|--------------------------------------------------------------------------
| Check if User is Logged In
|--------------------------------------------------------------------------
*/

if (!$pcmsSessionValid || !isset($_SESSION['user'])) {

    header(
        'Location: ' . BASE_URL . 'auth/login.php?error=' .
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
$verification = resolvePcmsSessionUser(
    static function () use ($sessionEmployeeID): ?array {
        if ($sessionEmployeeID === '') {
            return null;
        }

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

        $user = $stmt->fetch();
        return is_array($user) ? $user : null;
    }
);

if (!$verification['available']) {
    http_response_code(503);
    header('Cache-Control: no-store');
    header('Retry-After: 5');
    exit(
        'PCMS is temporarily unable to verify your session. ' .
        'Your account remains signed in. Please retry in a moment.'
    );
}

$verifiedUser = $verification['user'];

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
        'Location: ' . BASE_URL . 'auth/login.php?error=' .
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
