(function () {
    'use strict';

    var modal = window.PCMSModal;
    var addModal = document.getElementById('inventoryModal');
    var viewModal = document.getElementById('viewInventoryModal');
    var editModal = document.getElementById('editInventoryModal');
    var addForm = document.getElementById('addInventoryForm');
    var addButton = document.getElementById('openInventoryModal');
    var editFromView = document.getElementById('editInventoryFromView');
    var searchForm = document.getElementById('inventorySearchForm');
    var searchInput = document.getElementById('searchInput');
    var categoryFilter = document.getElementById('categoryFilter');
    var conditionFilter = document.getElementById('conditionFilter');
    var currentRecord = null;
    var searchTimer = null;

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
        document.getElementById('editInventoryBusinessID').value =
            record.inventory_id || '';
        document.getElementById('editInventoryID').value =
            record.inventory_id || '';
        document.getElementById('editInventoryName').value =
            record.asset_name || '';
        document.getElementById('editInventoryQuantity').value =
            record.quantity ?? 0;
        modal.setSelectValue(
            document.getElementById('editInventoryCategory'),
            record.category
        );
        modal.setSelectValue(
            document.getElementById('editInventoryCondition'),
            record.condition
        );
    }

    async function loadInventory(inventoryId) {
        var response = await fetch(
            'view_inventory.php?' + new URLSearchParams({
                inventory_id: inventoryId,
                format: 'json'
            }).toString(),
            {
                cache: 'no-store',
                headers: { 'Accept': 'application/json' }
            }
        );
        var data = await response.json();

        if (!response.ok || !data || data.success !== true || !data.inventory) {
            throw new Error('Inventory details are unavailable.');
        }

        return data.inventory;
    }

    document.addEventListener('click', async function (event) {
        var trigger = event.target.closest('[data-inventory-action]');

        if (!trigger) return;

        var row = trigger.closest('tr[data-inventory-id]');
        var inventoryId = row ? row.dataset.inventoryId : '';

        if (!inventoryId) return;

        event.preventDefault();
        trigger.setAttribute('aria-busy', 'true');

        try {
            var record = await loadInventory(inventoryId);

            currentRecord = record;

            if (trigger.dataset.inventoryAction === 'view' && viewModal) {
                populateView(record);
                modal.open(viewModal, trigger);
            } else if (
                trigger.dataset.inventoryAction === 'edit' &&
                editModal
            ) {
                populateEdit(record);
                modal.open(editModal, trigger);
            }
        } catch (error) {
            alert('Inventory details are temporarily unavailable. Please try again.');
        } finally {
            trigger.removeAttribute('aria-busy');
        }
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
                        'X-CSRF-Token': document.querySelector(
                            'meta[name="csrf-token"]'
                        ).content
                    },
                    cache: 'no-store'
                });
                var data = await response.json();

                if (
                    !response.ok ||
                    !data ||
                    typeof data.inventory_id !== 'string' ||
                    !data.inventory_id
                ) {
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

    function submitFilters() {
        if (searchForm) searchForm.requestSubmit();
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            window.clearTimeout(searchTimer);
            searchTimer = window.setTimeout(submitFilters, 400);
        });
    }
    if (categoryFilter) categoryFilter.addEventListener('change', submitFilters);
    if (conditionFilter) conditionFilter.addEventListener('change', submitFilters);
})();
