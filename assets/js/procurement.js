(function () {
    'use strict';

    var modal = window.PCMSModal;
    var addModal = document.getElementById('procurementModal');
    var viewModal = document.getElementById('viewProcurementModal');
    var editModal = document.getElementById('editProcurementModal');
    var addForm = document.getElementById('addProcurementForm');
    var addButton = document.getElementById('openProcurementModal');
    var editFromView = document.getElementById('editProcurementFromView');
    var addStatus = document.getElementById('procurementStatus');
    var addDeliveryDate = document.getElementById('procurementDeliveryDate');
    var editStatus = document.getElementById('editProcurementStatus');
    var editDeliveryDate = document.getElementById('editProcurementDeliveryDate');
    var currentRecord = null;

    function syncDeliveryDateRequirement(statusSelect, dateInput) {
        if (!statusSelect || !dateInput) return;
        dateInput.required = statusSelect.value === 'Delivered';
    }

    function populateView(record) {
        viewModal.querySelectorAll('[data-procurement-view]').forEach(function (field) {
            var name = field.dataset.procurementView;
            var emptyText;
            if (['approved_by', 'approval_date'].includes(name)) {
                emptyText = 'Not Yet Approved';
            } else if (name === 'delivery_date') {
                emptyText = 'Not Yet Delivered';
            } else {
                emptyText = '—';
            }
            field.textContent = ['request_date', 'approval_date', 'delivery_date'].includes(name)
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
            editProcurementDeliveryDate: record.delivery_date,
            editProcurementRemarks: record.remarks
        };
        Object.keys(values).forEach(function (id) {
            document.getElementById(id).value = values[id] == null ? '' : values[id];
        });
        modal.setSelectValue(document.getElementById('editProcurementCategory'), record.category);
        modal.setSelectValue(document.getElementById('editProcurementStatus'), record.status);
        syncDeliveryDateRequirement(editStatus, editDeliveryDate);
    }

    if (addStatus) {
        addStatus.addEventListener('change', function () {
            syncDeliveryDateRequirement(addStatus, addDeliveryDate);
        });
    }

    if (editStatus) {
        editStatus.addEventListener('change', function () {
            syncDeliveryDateRequirement(editStatus, editDeliveryDate);
        });
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
            syncDeliveryDateRequirement(addStatus, addDeliveryDate);
            idField.value = '';
            addButton.disabled = true;

            try {
                var response = await fetch('next_procurement_id.php', {
                    method: 'POST',
                    headers: {'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content},
                    cache: 'no-store'
                });
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

    var filterForm = document.getElementById('procurementFilterForm');
    var statusFilter = document.getElementById('statusFilter');
    var supplierFilter = document.getElementById('supplierFilter');

    function submitFilters() {
        if (filterForm) filterForm.submit();
    }

    if (statusFilter) statusFilter.addEventListener('change', submitFilters);
    if (supplierFilter) supplierFilter.addEventListener('change', submitFilters);
})();
