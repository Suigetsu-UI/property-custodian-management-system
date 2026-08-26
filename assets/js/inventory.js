(function () {
    'use strict';

    var modal = window.PCMSModal;
    var addModal = document.getElementById('inventoryModal');
    var viewModal = document.getElementById('viewInventoryModal');
    var editModal = document.getElementById('editInventoryModal');
    var addForm = document.getElementById('addInventoryForm');
    var editForm = document.getElementById('editInventoryForm');
    var addButton = document.getElementById('openInventoryModal');
    var editFromView = document.getElementById('editInventoryFromView');
    var currentRecord = null;

    function populateView(record) {
        viewModal.querySelectorAll('[data-inventory-view]').forEach(function (field) {
            field.textContent = modal.value(record[field.dataset.inventoryView]);
        });
        document.getElementById('viewInventorySubtitle').textContent = [
            record.inventory_id,
            record.asset_name
        ].filter(Boolean).join(' — ');
    }

    function populateEdit(record) {
        document.getElementById('editInventoryRowID').value = record.id || '';
        document.getElementById('editInventoryID').value = record.inventory_id || '';
        document.getElementById('editInventoryName').value = record.asset_name || '';
        document.getElementById('editInventoryQuantity').value = record.quantity ?? 0;
        modal.setSelectValue(document.getElementById('editInventoryCategory'), record.category);
        modal.setSelectValue(document.getElementById('editInventoryCondition'), record.condition);
    }

    document.querySelectorAll('[data-inventory-action]').forEach(function (trigger) {
        trigger.addEventListener('click', function (event) {
            var record = modal.readRowData(trigger);
            if (!record) return;

            if (trigger.dataset.inventoryAction === 'view' && viewModal) {
                populateView(record);
                event.preventDefault();
                currentRecord = record;
                modal.open(viewModal, trigger);
            } else if (trigger.dataset.inventoryAction === 'edit' && editModal) {
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
            var idField = document.getElementById('inventoryID');
            if (!idField || !addForm) return;

            addForm.reset();
            idField.value = '';
            addButton.disabled = true;

            try {
                var response = await fetch('next_inventory_id.php', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                    },
                    cache: 'no-store'
                });
                if (!response.ok) throw new Error('Could not generate Inventory ID.');

                var data = await response.json();
                if (!data || typeof data.inventory_id !== 'string' || !data.inventory_id) {
                    throw new Error('Invalid Inventory ID response.');
                }

                idField.value = data.inventory_id;
                modal.open(addModal, addButton);
            } catch (error) {
                alert('Could not generate an Inventory ID. Please try again.');
            } finally {
                addButton.disabled = false;
            }
        });
    }

    var searchInput = document.getElementById('searchInput');
    var categoryFilter = document.getElementById('categoryFilter');
    var conditionFilter = document.getElementById('conditionFilter');

    function applyFilters() {
        var search = (searchInput ? searchInput.value : '').toLowerCase();
        var category = (categoryFilter ? categoryFilter.value : '').toLowerCase();
        var condition = (conditionFilter ? conditionFilter.value : '').toLowerCase();

        document.querySelectorAll('#inventoryTable tbody tr.inventory-row').forEach(function (row) {
            var matchesSearch = row.cells[0].textContent.toLowerCase().includes(search) ||
                row.cells[1].textContent.toLowerCase().includes(search);
            var matchesCategory = !category || row.cells[2].textContent.toLowerCase().trim() === category;
            var matchesCondition = !condition || row.cells[4].textContent.toLowerCase().trim() === condition;
            row.style.display = matchesSearch && matchesCategory && matchesCondition ? '' : 'none';
        });
    }

    if (searchInput) searchInput.addEventListener('input', applyFilters);
    if (categoryFilter) categoryFilter.addEventListener('change', applyFilters);
    if (conditionFilter) conditionFilter.addEventListener('change', applyFilters);
})();
