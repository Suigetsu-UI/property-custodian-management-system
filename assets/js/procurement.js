(function () {
    'use strict';

    var modal = window.PCMSModal;
    var addModal = document.getElementById('procurementModal');
    var viewModal = document.getElementById('viewProcurementModal');
    var editModal = document.getElementById('editProcurementModal');
    var addForm = document.getElementById('addProcurementForm');
    var addButton = document.getElementById('openProcurementModal');
    var editFromView = document.getElementById('editProcurementFromView');
    var currentRecord = null;

    function populateView(record) {
        viewModal.querySelectorAll('[data-procurement-view]').forEach(function (field) {
            var name = field.dataset.procurementView;
            var emptyText = ['approved_by', 'approval_date'].includes(name)
                ? 'Not Yet Approved'
                : '—';
            field.textContent = ['request_date', 'approval_date'].includes(name)
                ? modal.date(record[name], emptyText)
                : modal.value(record[name], emptyText);
        });
        document.getElementById('viewProcurementSubtitle').textContent = [record.procurement_id, record.item_name].filter(Boolean).join(' — ');
        modal.setStatus(document.getElementById('viewProcurementStatus'), record.status);
    }

    function populateEdit(record) {
        var values = {
            editProcurementRowID: record.id,
            editProcurementID: record.procurement_id,
            editProcurementItem: record.item_name,
            editProcurementQuantity: record.quantity,
            editProcurementSupplier: record.supplier,
            editProcurementRequestedBy: record.requested_by,
            editProcurementRequestDate: record.request_date,
            editProcurementApprovedBy: record.approved_by,
            editProcurementApprovalDate: record.approval_date,
            editProcurementRemarks: record.remarks
        };
        Object.keys(values).forEach(function (id) {
            document.getElementById(id).value = values[id] == null ? '' : values[id];
        });
        modal.setSelectValue(document.getElementById('editProcurementCategory'), record.category);
        modal.setSelectValue(document.getElementById('editProcurementStatus'), record.status);
    }

    document.querySelectorAll('[data-procurement-action]').forEach(function (trigger) {
        trigger.addEventListener('click', function (event) {
            var record = modal.readRowData(trigger);
            if (!record) return;

            if (trigger.dataset.procurementAction === 'view') {
                populateView(record);
                event.preventDefault();
                currentRecord = record;
                modal.open(viewModal, trigger);
            } else if (trigger.dataset.procurementAction === 'edit') {
                populateEdit(record);
                event.preventDefault();
                currentRecord = record;
                modal.open(editModal, trigger);
            }
        });
    });

    if (editFromView) {
        editFromView.addEventListener('click', function () {
            if (!currentRecord) return;
            populateEdit(currentRecord);
            modal.open(editModal, editFromView);
        });
    }

    if (addButton) {
        addButton.addEventListener('click', async function () {
            var idField = document.getElementById('procurementID');
            if (!idField || !addForm) return;

            addForm.reset();
            idField.value = '';
            addButton.disabled = true;

            try {
                var response = await fetch('next_procurement_id.php', {method: 'POST', cache: 'no-store'});
                if (!response.ok) throw new Error('Could not generate Procurement ID.');
                var data = await response.json();
                if (!data || typeof data.procurement_id !== 'string' || !data.procurement_id) throw new Error('Invalid Procurement ID response.');
                idField.value = data.procurement_id;
                modal.open(addModal, addButton);
            } catch (error) {
                alert('Could not generate a Procurement ID. Please try again.');
            } finally {
                addButton.disabled = false;
            }
        });
    }

    var searchInput = document.getElementById('searchInput');
    var statusFilter = document.getElementById('statusFilter');
    var supplierFilter = document.getElementById('supplierFilter');

    function applyFilters() {
        var search = (searchInput ? searchInput.value : '').toLowerCase();
        var status = (statusFilter ? statusFilter.value : '').toLowerCase();
        var supplier = (supplierFilter ? supplierFilter.value : '').toLowerCase();
        document.querySelectorAll('#procurementTable tbody tr.procurement-row').forEach(function (row) {
            var matchesSearch = row.cells[0].textContent.toLowerCase().includes(search) || row.cells[1].textContent.toLowerCase().includes(search);
            var matchesStatus = !status || row.cells[5].textContent.toLowerCase().trim() === status;
            var matchesSupplier = !supplier || row.cells[4].textContent.toLowerCase().trim() === supplier;
            row.style.display = matchesSearch && matchesStatus && matchesSupplier ? '' : 'none';
        });
    }

    if (searchInput) searchInput.addEventListener('input', applyFilters);
    if (statusFilter) statusFilter.addEventListener('change', applyFilters);
    if (supplierFilter) supplierFilter.addEventListener('change', applyFilters);
})();
