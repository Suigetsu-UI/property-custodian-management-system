(function () {
    'use strict';

    var modal = window.PCMSModal;
    var addModal = document.getElementById('maintenanceModal');
    var viewModal = document.getElementById('viewMaintenanceModal');
    var editModal = document.getElementById('editMaintenanceModal');
    var addForm = document.getElementById('addMaintenanceForm');
    var editForm = document.getElementById('editMaintenanceForm');
    var addButton = document.getElementById('openMaintenanceModal');
    var editFromView = document.getElementById('editMaintenanceFromView');
    var assetSelect = document.getElementById('maintenanceAssetSelect');
    var currentRecord = null;

    function fillAssetFields() {
        var option = assetSelect.options[assetSelect.selectedIndex];
        document.getElementById('maintenanceAssetName').value = option ? option.dataset.name || '' : '';
        document.getElementById('maintenanceCategory').value = option ? option.dataset.category || '' : '';
        document.getElementById('maintenanceCustodian').value = option ? option.dataset.custodian || '' : '';
        document.getElementById('maintenanceAssetStatus').value = option ? option.dataset.status || '' : '';
    }

    if (assetSelect) assetSelect.addEventListener('change', fillAssetFields);

    function populateView(record) {
        viewModal.querySelectorAll('[data-maintenance-view]').forEach(function (field) {
            var name = field.dataset.maintenanceView;
            field.textContent = name === 'scheduled_date'
                ? modal.date(record[name])
                : modal.value(record[name], name === 'current_custodian' ? 'Not Assigned' : '—');
        });
        document.getElementById('viewMaintenanceSubtitle').textContent = [record.maintenance_id, record.current_asset_name].filter(Boolean).join(' — ');
        modal.setStatus(document.getElementById('viewMaintenanceStatus'), record.status);
    }

    function populateEdit(record) {
        editForm.action = 'edit_maintenance.php?id=' + encodeURIComponent(record.id);
        document.getElementById('editMaintenanceAssetID').value = record.asset_id || '';
        document.getElementById('editMaintenanceID').value = record.maintenance_id || '';
        document.getElementById('editMaintenanceAsset').value = [record.asset_business_id, record.current_asset_name].filter(Boolean).join(' — ');
        document.getElementById('editMaintenanceDate').value = record.scheduled_date || '';
        modal.setSelectValue(document.getElementById('editMaintenanceType'), record.maintenance_type);
        modal.setSelectValue(document.getElementById('editMaintenanceStatus'), record.status);
    }

    document.querySelectorAll('[data-maintenance-action]').forEach(function (trigger) {
        trigger.addEventListener('click', function (event) {
            var record = modal.readRowData(trigger);
            if (!record) return;
            if (trigger.dataset.maintenanceAction === 'view') {
                populateView(record);
                event.preventDefault();
                currentRecord = record;
                modal.open(viewModal, trigger);
            } else if (trigger.dataset.maintenanceAction === 'edit') {
                populateEdit(record);
                event.preventDefault();
                currentRecord = record;
                modal.open(editModal, trigger);
            }
        });
    });

    if (editFromView) editFromView.addEventListener('click', function () {
        if (!currentRecord) return;
        populateEdit(currentRecord);
        modal.open(editModal, editFromView);
    });

    if (addButton) addButton.addEventListener('click', async function () {
        var idField = document.getElementById('maintenanceID');
        if (!idField || !addForm) return;
        addForm.reset();
        fillAssetFields();
        idField.value = '';
        addButton.disabled = true;
        try {
            var response = await fetch('next_maintenance_id.php', {method: 'POST', cache: 'no-store'});
            if (!response.ok) throw new Error('Could not generate Maintenance ID.');
            var data = await response.json();
            if (!data || typeof data.maintenance_id !== 'string' || !data.maintenance_id) throw new Error('Invalid Maintenance ID response.');
            idField.value = data.maintenance_id;
            modal.open(addModal, addButton);
        } catch (error) {
            alert('Could not generate a Maintenance ID. Please try again.');
        } finally {
            addButton.disabled = false;
        }
    });

    var searchInput = document.getElementById('searchInput');
    var statusFilter = document.getElementById('statusFilter');
    function applyFilters() {
        var search = (searchInput ? searchInput.value : '').toLowerCase();
        var status = (statusFilter ? statusFilter.value : '').toLowerCase();
        document.querySelectorAll('#maintenanceTable tbody tr.maintenance-row').forEach(function (row) {
            var matchesSearch = [0, 1, 2].some(function (index) { return row.cells[index].textContent.toLowerCase().includes(search); });
            var matchesStatus = !status || row.cells[4].textContent.toLowerCase().trim() === status;
            row.style.display = matchesSearch && matchesStatus ? '' : 'none';
        });
    }
    if (searchInput) searchInput.addEventListener('input', applyFilters);
    if (statusFilter) statusFilter.addEventListener('change', applyFilters);
})();
