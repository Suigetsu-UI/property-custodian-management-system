(function () {
    'use strict';

    var modal = window.PCMSModal;
    var addModal = document.getElementById('auditModal');
    var viewModal = document.getElementById('viewAuditModal');
    var editModal = document.getElementById('editAuditModal');
    var addForm = document.getElementById('addAuditForm');
    var editForm = document.getElementById('editAuditForm');
    var addButton = document.getElementById('openAuditModal');
    var editFromView = document.getElementById('editAuditFromView');
    var assetSelect = document.getElementById('auditAssetSelect');
    var currentRecord = null;

    function fillAssetFields() {
        var option = assetSelect.options[assetSelect.selectedIndex];
        document.getElementById('auditAssetName').value = option ? option.dataset.name || '' : '';
        document.getElementById('auditCategory').value = option ? option.dataset.category || '' : '';
        document.getElementById('auditCustodian').value = option ? option.dataset.custodian || '' : '';
        document.getElementById('auditAssetStatus').value = option ? option.dataset.status || '' : '';
    }
    if (assetSelect) assetSelect.addEventListener('change', fillAssetFields);

    function populateView(record) {
        viewModal.querySelectorAll('[data-audit-view]').forEach(function (field) {
            var name = field.dataset.auditView;
            field.textContent = name === 'audit_date'
                ? modal.date(record[name])
                : modal.value(record[name], name === 'custodian_snap' ? 'Not Assigned' : '—');
        });
        document.getElementById('viewAuditSubtitle').textContent = [record.audit_id, record.asset_name_snap].filter(Boolean).join(' — ');
        modal.setStatus(document.getElementById('viewAuditStatus'), record.status);
    }

    function populateEdit(record) {
        document.getElementById('editAuditRowID').value = record.id || '';
        document.getElementById('editAuditAssetID').value = record.asset_id || '';
        document.getElementById('editAuditID').value = record.audit_id || '';
        document.getElementById('editAuditAsset').value = [record.asset_business_id, record.current_asset_name].filter(Boolean).join(' — ');
        document.getElementById('editAuditAuditor').value = record.auditor || '';
        document.getElementById('editAuditDate').value = record.audit_date || '';
        document.getElementById('editAuditRemarks').value = record.remarks || '';
        modal.setSelectValue(document.getElementById('editAuditResult'), record.result);
        modal.setSelectValue(document.getElementById('editAuditStatus'), record.status);
    }

    document.querySelectorAll('[data-audit-action]').forEach(function (trigger) {
        trigger.addEventListener('click', function (event) {
            var record = modal.readRowData(trigger);
            if (!record) return;
            if (trigger.dataset.auditAction === 'view') {
                populateView(record);
                event.preventDefault();
                currentRecord = record;
                modal.open(viewModal, trigger);
            } else if (trigger.dataset.auditAction === 'edit') {
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
        var idField = document.getElementById('auditID');
        if (!idField || !addForm) return;
        addForm.reset();
        fillAssetFields();
        idField.value = '';
        addButton.disabled = true;
        try {
            var response = await fetch('next_audit_id.php', {
                method: 'POST',
                headers: {'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content},
                cache: 'no-store'
            });
            if (!response.ok) throw new Error('Could not generate Audit ID.');
            var data = await response.json();
            if (!data || typeof data.audit_id !== 'string' || !data.audit_id) throw new Error('Invalid Audit ID response.');
            idField.value = data.audit_id;
            modal.open(addModal, addButton);
        } catch (error) {
            alert('Could not generate an Audit ID. Please try again.');
        } finally {
            addButton.disabled = false;
        }
    });

    var searchInput = document.getElementById('searchInput');
    var statusFilter = document.getElementById('statusFilter');
    var resultFilter = document.getElementById('resultFilter');
    function applyFilters() {
        var search = (searchInput ? searchInput.value : '').toLowerCase();
        var status = (statusFilter ? statusFilter.value : '').toLowerCase();
        var result = (resultFilter ? resultFilter.value : '').toLowerCase();
        document.querySelectorAll('#auditTable tbody tr.audit-row').forEach(function (row) {
            var matchesSearch = [0, 1, 2].some(function (index) { return row.cells[index].textContent.toLowerCase().includes(search); });
            var matchesStatus = !status || row.cells[4].textContent.toLowerCase().trim() === status;
            var matchesResult = !result || row.cells[5].textContent.toLowerCase().trim() === result;
            row.style.display = matchesSearch && matchesStatus && matchesResult ? '' : 'none';
        });
    }
    if (searchInput) searchInput.addEventListener('input', applyFilters);
    if (statusFilter) statusFilter.addEventListener('change', applyFilters);
    if (resultFilter) resultFilter.addEventListener('change', applyFilters);
})();
