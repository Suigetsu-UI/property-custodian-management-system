(function () {
    'use strict';

    var modal = window.PCMSModal;
    var addModal = document.getElementById('addUserModal');
    var editModal = document.getElementById('editUserModal');
    var resetMfaModal = document.getElementById('resetMfaModal');
    var addForm = document.getElementById('addUserForm');
    var editForm = document.getElementById('editUserForm');
    var resetMfaForm = document.getElementById('resetMfaForm');
    var addButton = document.getElementById('openAddUserModal');
    var searchInput = document.getElementById('userSearchInput');
    var roleFilter = document.getElementById('userRoleFilter');
    var statusFilter = document.getElementById('userStatusFilter');
    var resultCount = document.getElementById('userResultCount');
    var emptyState = document.getElementById('userFilterEmptyState');

    function setPasswordConfirmation(password, confirmation) {
        if (!password || !confirmation) return;

        function validate() {
            confirmation.setCustomValidity(
                password.value === confirmation.value
                    ? ''
                    : 'Passwords must match.'
            );
        }

        password.addEventListener('input', validate);
        confirmation.addEventListener('input', validate);
    }

    setPasswordConfirmation(
        document.getElementById('addUserPassword'),
        document.getElementById('addUserPasswordConfirm')
    );
    setPasswordConfirmation(
        document.getElementById('editUserPassword'),
        document.getElementById('editUserPasswordConfirm')
    );

    if (addButton && addModal && addForm) {
        addButton.addEventListener('click', function () {
            addForm.reset();
            modal.open(addModal, addButton);
        });
    }

    document.querySelectorAll('[data-user-action="edit"]').forEach(function (button) {
        button.addEventListener('click', function () {
            var user = modal.readRowData(button);

            if (!user || !editModal || !editForm) return;

            editForm.reset();
            document.getElementById('editUserID').value = user.id || '';
            document.getElementById('editUserEmployeeID').value = user.employee_id || '';
            document.getElementById('editUserFullName').value = user.full_name || '';
            modal.setSelectValue(document.getElementById('editUserRole'), user.role);
            document.getElementById('editUserStatus').value = user.is_active ? '1' : '0';
            document.getElementById('editUserSelfNotice').hidden = !user.is_self;
            modal.open(editModal, button);
        });
    });

    document.querySelectorAll('[data-user-action="reset-mfa"]').forEach(function (button) {
        button.addEventListener('click', function () {
            var user = modal.readRowData(button);

            if (
                !user ||
                user.is_self ||
                !user.mfa_has_state ||
                !resetMfaModal ||
                !resetMfaForm
            ) return;

            resetMfaForm.reset();
            document.getElementById('resetMfaTargetID').value = user.id || '';
            document.getElementById('resetMfaTargetSessionVersion').value =
                user.session_version || '';
            document.getElementById('resetMfaTargetName').textContent =
                (user.full_name || 'User') + ' (' +
                (user.employee_id || 'Unknown ID') + ')';
            document.getElementById('resetMfaTargetStatus').textContent =
                user.mfa_status || 'Pending';
            modal.open(resetMfaModal, button);
        });
    });

    function applyFilters() {
        var search = (searchInput ? searchInput.value : '').trim().toLowerCase();
        var role = roleFilter ? roleFilter.value.toLowerCase() : '';
        var status = statusFilter ? statusFilter.value.toLowerCase() : '';
        var visibleCount = 0;

        document.querySelectorAll('#userTable tbody tr.user-row').forEach(function (row) {
            var user = modal.readRowData(row.querySelector('[data-user-action]')) || {};
            var searchable = [user.employee_id, user.full_name]
                .filter(Boolean)
                .join(' ')
                .toLowerCase();
            var userRole = String(user.role || '').toLowerCase();
            var userStatus = user.is_active ? 'active' : 'inactive';
            var visible = (!search || searchable.includes(search)) &&
                (!role || userRole === role) &&
                (!status || userStatus === status);

            row.style.display = visible ? '' : 'none';
            if (visible) visibleCount++;
        });

        if (resultCount) {
            resultCount.textContent = 'Showing ' + visibleCount + ' ' +
                (visibleCount === 1 ? 'User' : 'Users');
        }
        if (emptyState) emptyState.hidden = visibleCount !== 0;
    }

    if (searchInput) searchInput.addEventListener('input', applyFilters);
    if (roleFilter) roleFilter.addEventListener('change', applyFilters);
    if (statusFilter) statusFilter.addEventListener('change', applyFilters);

    applyFilters();
})();
