<div id="addUserModal" class="modal pcms-modal pcms-modal--md" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="addUserTitle">
<div class="modal-content pcms-modal-dialog">
<form id="addUserForm" class="pcms-modal-form" method="POST" action="save_user.php">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
<header class="pcms-modal-header">
    <div><span class="pcms-modal-eyebrow">Access Control</span><h2 id="addUserTitle">Add User Account</h2><p>Create an account and assign its system role.</p></div>
    <button type="button" class="close-modal" data-modal-close aria-label="Close Add User"><span aria-hidden="true">&times;</span></button>
</header>
<div class="pcms-modal-body"><div class="pcms-form-grid">
    <div class="form-row"><label for="addUserEmployeeID">Employee ID</label><input type="text" id="addUserEmployeeID" name="employee_id" maxlength="50" pattern="[A-Za-z0-9._-]{2,50}" autocomplete="off" required data-modal-autofocus></div>
    <div class="form-row"><label for="addUserFullName">Full Name</label><input type="text" id="addUserFullName" name="full_name" maxlength="150" required></div>
    <div class="form-row pcms-form-span-2"><label for="addUserRole">Role</label><select id="addUserRole" name="role" required><?php foreach (getAllowedUserRoles() as $role): ?><option value="<?= htmlspecialchars($role) ?>" <?= $role === 'Property Custodian' ? 'selected' : '' ?>><?= htmlspecialchars(userRoleLabel($role)) ?></option><?php endforeach; ?></select></div>
    <div class="form-row"><label for="addUserPassword">Password</label><input type="password" id="addUserPassword" name="password" minlength="8" maxlength="72" autocomplete="new-password" required></div>
    <div class="form-row"><label for="addUserPasswordConfirm">Confirm Password</label><input type="password" id="addUserPasswordConfirm" name="password_confirm" minlength="8" maxlength="72" autocomplete="new-password" required></div>
</div></div>
<footer class="pcms-modal-footer"><button type="button" class="btn btn-outline" data-modal-close>Cancel</button><button type="submit" class="btn btn-primary">Create User</button></footer>
</form>
</div>
</div>

<div id="editUserModal" class="modal pcms-modal pcms-modal--md" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="editUserTitle">
<div class="modal-content pcms-modal-dialog">
<form id="editUserForm" class="pcms-modal-form" method="POST" action="update_user.php">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
<input type="hidden" id="editUserID" name="id">
<header class="pcms-modal-header">
    <div><span class="pcms-modal-eyebrow">Access Control</span><h2 id="editUserTitle">Edit User Access</h2><p>Update the account role, status, name, or password.</p></div>
    <button type="button" class="close-modal" data-modal-close aria-label="Close Edit User"><span aria-hidden="true">&times;</span></button>
</header>
<div class="pcms-modal-body">
<div id="editUserSelfNotice" class="pcms-form-notice" hidden>Your current administrator account must remain active and cannot be demoted.</div>
<div class="pcms-form-grid">
    <div class="form-row"><label for="editUserEmployeeID">Employee ID</label><input type="text" id="editUserEmployeeID" readonly></div>
    <div class="form-row"><label for="editUserFullName">Full Name</label><input type="text" id="editUserFullName" name="full_name" maxlength="150" required></div>
    <div class="form-row"><label for="editUserRole">Role</label><select id="editUserRole" name="role" required><?php foreach (getAllowedUserRoles() as $role): ?><option value="<?= htmlspecialchars($role) ?>"><?= htmlspecialchars(userRoleLabel($role)) ?></option><?php endforeach; ?></select></div>
    <div class="form-row"><label for="editUserStatus">Account Status</label><select id="editUserStatus" name="is_active" required><option value="1">Active</option><option value="0">Inactive</option></select></div>
    <div class="form-row"><label for="editUserPassword">New Password</label><input type="password" id="editUserPassword" name="password" minlength="8" maxlength="72" autocomplete="new-password" placeholder="Leave blank to keep current"></div>
    <div class="form-row"><label for="editUserPasswordConfirm">Confirm New Password</label><input type="password" id="editUserPasswordConfirm" name="password_confirm" minlength="8" maxlength="72" autocomplete="new-password"></div>
</div>
</div>
<footer class="pcms-modal-footer"><button type="button" class="btn btn-outline" data-modal-close>Cancel</button><button type="submit" class="btn btn-warning">Update Access</button></footer>
</form>
</div>
</div>
