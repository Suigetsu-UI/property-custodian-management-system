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

$id = filter_var(
    $_POST['id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
$fullName = trim((string) ($_POST['full_name'] ?? ''));
$role = trim((string) ($_POST['role'] ?? ''));
$isActiveInput = (string) ($_POST['is_active'] ?? '');
$password = (string) ($_POST['password'] ?? '');
$passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

if (
    $id === false ||
    strlen($fullName) < 2 ||
    strlen($fullName) > 150 ||
    !in_array($role, getAllowedUserRoles(), true) ||
    !in_array($isActiveInput, ['0', '1'], true) ||
    ($password !== '' && (strlen($password) < 8 || strlen($password) > 72)) ||
    !hash_equals($password, $passwordConfirm)
) {
    header('Location: index.php?error=invalid');
    exit;
}

$isActive = $isActiveInput === '1';
$pdo = null;

try {
    $pdo = getDbConnection();
    $pdo->beginTransaction();

    $userStmt = $pdo->prepare(
        "SELECT id, employee_id, role, is_active
         FROM users
         WHERE id = :id
         FOR UPDATE"
    );
    $userStmt->execute(['id' => $id]);
    $user = $userStmt->fetch();

    if (!$user) {
        $pdo->rollBack();
        header('Location: index.php?error=invalid');
        exit;
    }

    $isCurrentAccount = $user['employee_id'] ===
        ($_SESSION['user']['employee_id'] ?? '');

    if ($isCurrentAccount && ($role !== 'Administrator' || !$isActive)) {
        $pdo->rollBack();
        header('Location: index.php?error=self_access');
        exit;
    }

    if (
        $user['role'] === 'Administrator' &&
        isUserAccountActive($user['is_active']) &&
        ($role !== 'Administrator' || !$isActive)
    ) {
        $adminRows = $pdo->query(
            "SELECT id, is_active
             FROM users
             WHERE role = 'Administrator'
             FOR UPDATE"
        )->fetchAll();
        $otherActiveAdministratorExists = false;

        foreach ($adminRows as $adminRow) {
            if (
                (int) $adminRow['id'] !== (int) $id &&
                isUserAccountActive($adminRow['is_active'])
            ) {
                $otherActiveAdministratorExists = true;
                break;
            }
        }

        if (!$otherActiveAdministratorExists) {
            $pdo->rollBack();
            header('Location: index.php?error=last_admin');
            exit;
        }
    }

    $sql =
        "UPDATE users
         SET full_name = :full_name,
             role = :role,
             is_active = :is_active";
    $params = [
        'id' => $id,
        'full_name' => $fullName,
        'role' => $role,
        'is_active' => $isActive ? 'true' : 'false',
    ];

    if ($password !== '') {
        $sql .= ', password_hash = :password_hash';
        $params['password_hash'] = password_hash(
            $password,
            PASSWORD_DEFAULT
        );
    }

    $sql .= ' WHERE id = :id';
    $updateStmt = $pdo->prepare($sql);
    $updateStmt->execute($params);
    $pdo->commit();

    if ($isCurrentAccount) {
        $_SESSION['user']['name'] = $fullName;
        $_SESSION['user']['role'] = $role;
    }
} catch (Throwable $error) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    header('Location: index.php?error=save_failed');
    exit;
}

header('Location: index.php?message=updated');
exit;
