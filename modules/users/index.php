<?php

require_once "../../auth/check_auth.php";
require_once __DIR__ . "/../../includes/access_control.php";
require_once __DIR__ . "/../../includes/database.php";

requireAdministrator();

$pdo = getDbConnection();
$stmt = $pdo->query(
    "SELECT id, employee_id, full_name, role, is_active, created_at,
            session_version, mfa_enabled, mfa_secret_enc,
            mfa_enrolled_at
     FROM users
     ORDER BY lower(full_name), lower(employee_id)"
);
$users = $stmt->fetchAll();
$currentUserID = (int) ($_SESSION['user']['id'] ?? 0);
$csrfToken = getAccessCsrfToken();

$messages = [
    'created' => ['success', 'The user account was created successfully.'],
    'updated' => ['success', 'The user account and access settings were updated.'],
    'duplicate' => ['error', 'That Employee ID is already assigned to another account.'],
    'invalid' => ['error', 'Please check the entered account details and try again.'],
    'self_access' => ['error', 'You cannot demote or deactivate your own administrator account.'],
    'last_admin' => ['error', 'At least one active System Administrator must remain.'],
    'save_failed' => ['error', 'The user account could not be saved. Please try again.'],
    'mfa_reset' => ['success', 'MFA was reset and the user\'s existing sessions were revoked.'],
    'mfa_verification' => ['error', 'The security verification could not be completed.'],
    'mfa_blocked' => ['error', 'Too many verification attempts. Please wait 15 minutes and sign in again.'],
    'mfa_self' => ['error', 'You cannot reset MFA for your own Administrator account.'],
    'mfa_unavailable' => ['error', 'MFA reset is unavailable because the user account state changed.'],
    'mfa_reset_failed' => ['error', 'MFA could not be reset. Please try again.'],
];
$messageKey = (string) ($_GET['message'] ?? $_GET['error'] ?? '');
$message = $messages[$messageKey] ?? null;

include "../../includes/header.php";

?>

<div class="layout">

<?php include "../../includes/sidebar.php"; ?>

<main class="main-content">

<h1>User Management</h1>

<hr>

<br>

<?php if ($message): ?>

<div class="<?= $message[0] === 'success' ? 'success-message' : 'error-message' ?>" role="alert">
    <?= htmlspecialchars($message[1]) ?>
</div>

<br>

<?php endif; ?>

<div class="search-toolbar">
    <input type="text" id="userSearchInput" placeholder="Search users..." aria-label="Search users">
    <select id="userRoleFilter" aria-label="Filter users by role">
        <option value="">All Roles</option>
        <?php foreach (getAllowedUserRoles() as $role): ?>
        <option value="<?= htmlspecialchars($role) ?>"><?= htmlspecialchars(userRoleLabel($role)) ?></option>
        <?php endforeach; ?>
    </select>
    <select id="userStatusFilter" aria-label="Filter users by status">
        <option value="">All Status</option>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
    </select>
    <button type="button" id="openAddUserModal" class="btn btn-primary">
        Add User
    </button>
</div>

<div class="asset-filter-summary">
    <span id="userResultCount" aria-live="polite">Showing <?= count($users) ?> <?= count($users) === 1 ? 'User' : 'Users' ?></span>
</div>

<table class="asset-table" id="userTable">

<thead>
<tr>
    <th>Employee ID</th>
    <th>Full Name</th>
    <th>Role</th>
    <th>Status</th>
    <th>MFA Status</th>
    <th>Created</th>
    <th>Actions</th>
</tr>
</thead>

<tbody>

<?php foreach ($users as $user): ?>

<?php
$mfaEnabled = isUserAccountActive($user['mfa_enabled']);
$mfaHasState = $mfaEnabled ||
    trim((string) ($user['mfa_secret_enc'] ?? '')) !== '';
$mfaStatus = $mfaEnabled
    ? 'Enabled'
    : ($mfaHasState ? 'Pending' : 'Not Enrolled');
$isSelf = (int) $user['id'] === $currentUserID;
$userRecord = [
    'id' => (int) $user['id'],
    'employee_id' => $user['employee_id'],
    'full_name' => $user['full_name'],
    'role' => $user['role'],
    'is_active' => isUserAccountActive($user['is_active']),
    'is_self' => $isSelf,
    'session_version' => (int) $user['session_version'],
    'mfa_enabled' => $mfaEnabled,
    'mfa_has_state' => $mfaHasState,
    'mfa_status' => $mfaStatus,
];
$userPayload = htmlspecialchars(
    json_encode(
        $userRecord,
        JSON_HEX_TAG |
        JSON_HEX_AMP |
        JSON_HEX_APOS |
        JSON_HEX_QUOT |
        JSON_INVALID_UTF8_SUBSTITUTE
    ),
    ENT_QUOTES,
    'UTF-8'
);
$status = isUserAccountActive($user['is_active']) ? 'Active' : 'Inactive';
?>

<tr class="user-row" data-record="<?= $userPayload ?>">
    <td><strong><?= htmlspecialchars($user['employee_id']) ?></strong></td>
    <td>
        <?= htmlspecialchars($user['full_name']) ?>
        <?php if ($isSelf): ?>
        <br><small>Current account</small>
        <?php endif; ?>
    </td>
    <td><?= htmlspecialchars(userRoleLabel($user['role'])) ?></td>
    <td><span class="pcms-status-badge" data-status="<?= strtolower($status) ?>"><?= $status ?></span></td>
    <td>
        <span
            class="pcms-status-badge"
            data-status="<?= $mfaEnabled ? 'active' : ($mfaHasState ? 'pending' : 'inactive') ?>"
        >
            <?= htmlspecialchars($mfaStatus) ?>
        </span>
    </td>
    <td><?= htmlspecialchars((new DateTimeImmutable($user['created_at']))->format('M j, Y')) ?></td>
    <td>
        <button type="button" class="btn btn-warning" data-user-action="edit">
            Edit Access
        </button>
        <?php if ($mfaHasState && !$isSelf): ?>
        <button type="button" class="btn btn-danger" data-user-action="reset-mfa">
            Reset MFA
        </button>
        <?php elseif ($mfaHasState && $isSelf): ?>
        <small class="user-mfa-self-notice">Self-reset is not permitted.</small>
        <?php endif; ?>
    </td>
</tr>

<?php endforeach; ?>

<tr id="userFilterEmptyState" <?= empty($users) ? '' : 'hidden' ?>>
    <td colspan="7" style="text-align:center;padding:40px;">
        No user accounts match the current filters.
    </td>
</tr>

</tbody>

</table>

<?php include __DIR__ . '/user_modals.php'; ?>

</main>

</div>

<script src="<?= BASE_URL ?>assets/js/users.js?v=<?= filemtime(__DIR__ . '/../../assets/js/users.js') ?>"></script>

<?php include "../../includes/footer.php"; ?>
