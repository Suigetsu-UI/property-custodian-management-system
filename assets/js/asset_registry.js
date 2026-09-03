(function () {
    'use strict';

    var modal = window.PCMSModal;
    var registerModal = document.getElementById('assetModal');
    var registerForm = document.getElementById('registerAssetForm');
    var openRegister = document.getElementById('openAssetModal');
    var viewModal = document.getElementById('viewAssetModal');
    var editModal = document.getElementById('editAssetModal');
    var assignModal = document.getElementById('assignCustodianModal');
    var editFromView = document.getElementById('editAssetFromView');
    var filterForm = document.getElementById('assetFilterForm');
    var searchInput = document.getElementById('searchInput');
    var currentAsset = null;
    var filterTimer = null;

    function money(value) {
        if (value === null || value === undefined || value === '') return '—';
        var amount = Number(value);
        if (!Number.isFinite(amount)) return String(value);
        return new Intl.NumberFormat('en-PH', {
            style: 'currency', currency: 'PHP'
        }).format(amount);
    }

    function populateView(asset) {
        viewModal.querySelectorAll('[data-view-field]').forEach(function (field) {
            var name = field.dataset.viewField;
            var value = asset[name];

            if (name === 'purchase_cost') {
                field.textContent = money(value);
            } else if (name === 'acquisition_date' || name === 'date_assigned') {
                field.textContent = modal.date(value, 'Not Assigned');
            } else if (['employee_id', 'custodian', 'department'].includes(name)) {
                field.textContent = modal.value(value, 'Not Assigned');
            } else {
                field.textContent = modal.value(value);
            }
        });
        document.getElementById('viewAssetSubtitle').textContent = [
            asset.asset_id, asset.asset_name
        ].filter(Boolean).join(' — ');
        modal.setStatus(document.getElementById('viewAssetStatus'), asset.status);
    }

    function populateEdit(asset) {
        document.getElementById('editAssetBusinessID').value = asset.asset_id || '';
        document.getElementById('editAssetID').value = asset.asset_id || '';
        document.getElementById('editAssetName').value = asset.asset_name || '';
        modal.setSelectValue(
            document.getElementById('editAssetCategory'),
            asset.category
        );
        document.getElementById('editAssetBrand').value = asset.brand || '';
        document.getElementById('editAssetModel').value = asset.model || '';
        document.getElementById('editAssetSerial').value = asset.serial_number || '';
        document.getElementById('editAssetSupplier').value = asset.supplier || '';
        document.getElementById('editAssetLocation').value = asset.location || '';
        document.getElementById('editAssetRemarks').value = asset.remarks || '';
    }

    function populateAssignment(asset) {
        document.getElementById('assignAssetBusinessID').value = asset.asset_id || '';
        document.getElementById('assignAssetLabel').textContent = [
            asset.asset_id, asset.asset_name
        ].filter(Boolean).join(' — ');
        document.getElementById('assignEmployeeID').value = '';
        document.getElementById('assignCustodianName').value = '';
        document.getElementById('assignDate').value = '';
    }

    async function loadAsset(assetId) {
        var response = await fetch(
            'view_asset.php?' + new URLSearchParams({
                asset_id: assetId,
                format: 'json'
            }).toString(),
            { cache: 'no-store', headers: { 'Accept': 'application/json' } }
        );
        var data = await response.json();

        if (!response.ok || !data || !data.asset) {
            throw new Error('Asset details are unavailable.');
        }
        return data.asset;
    }

    document.addEventListener('click', async function (event) {
        var trigger = event.target.closest('[data-asset-action]');
        if (!trigger) return;

        var row = trigger.closest('tr[data-asset-id]');
        var assetId = row ? row.dataset.assetId : '';
        if (!assetId) return;

        event.preventDefault();
        trigger.setAttribute('aria-busy', 'true');

        try {
            var asset = await loadAsset(assetId);
            currentAsset = asset;

            if (trigger.dataset.assetAction === 'view') {
                populateView(asset);
                modal.open(viewModal, trigger);
            } else if (trigger.dataset.assetAction === 'edit') {
                populateEdit(asset);
                modal.open(editModal, trigger);
            } else if (trigger.dataset.assetAction === 'assign') {
                if (asset.status !== 'Available') {
                    throw new Error('Asset state changed.');
                }
                populateAssignment(asset);
                modal.open(assignModal, trigger);
            }
        } catch (error) {
            alert('Asset details are temporarily unavailable. Please refresh and try again.');
        } finally {
            trigger.removeAttribute('aria-busy');
        }
    });

    if (editFromView) {
        editFromView.addEventListener('click', function () {
            if (!currentAsset) return;
            populateEdit(currentAsset);
            modal.open(editModal, editFromView);
        });
    }

    var inventorySearch = document.getElementById('inventoryItemSearch');
    var inventoryId = document.getElementById('inventoryItemID');
    var inventorySuggestions = document.getElementById('inventoryItemSuggestions');
    var assetName = document.getElementById('assetNameField');
    var assetCategory = document.getElementById('assetCategoryField');
    var inventoryTimer = null;
    var inventoryRequest = null;

    function clearInventorySelection(clearSearch) {
        if (inventoryId) inventoryId.value = '';
        if (assetName) assetName.value = '';
        if (assetCategory) assetCategory.value = '';
        if (clearSearch && inventorySearch) inventorySearch.value = '';
    }

    function closeInventorySuggestions() {
        if (!inventorySuggestions || !inventorySearch) return;
        inventorySuggestions.hidden = true;
        inventorySearch.setAttribute('aria-expanded', 'false');
    }

    function renderInventoryOptions(options) {
        inventorySuggestions.replaceChildren();

        if (!Array.isArray(options) || options.length === 0) {
            var empty = document.createElement('div');
            empty.className = 'pcms-typeahead-empty';
            empty.textContent = 'No available Inventory items found.';
            inventorySuggestions.appendChild(empty);
        } else {
            options.forEach(function (option) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'pcms-typeahead-option';
                button.setAttribute('role', 'option');
                button.textContent = [
                    option.inventory_id,
                    option.asset_name,
                    option.category,
                    'Qty ' + String(option.quantity ?? 0)
                ].filter(Boolean).join(' — ');
                button.addEventListener('click', function () {
                    inventoryId.value = option.inventory_id || '';
                    inventorySearch.value = [
                        option.inventory_id, option.asset_name
                    ].filter(Boolean).join(' — ');
                    assetName.value = option.asset_name || '';
                    assetCategory.value = option.category || '';
                    closeInventorySuggestions();
                });
                inventorySuggestions.appendChild(button);
            });
        }

        inventorySuggestions.hidden = false;
        inventorySearch.setAttribute('aria-expanded', 'true');
    }

    async function searchInventory(query) {
        if (inventoryRequest) inventoryRequest.abort();
        inventoryRequest = new AbortController();

        try {
            var response = await fetch(
                'inventory_options.php?' + new URLSearchParams({ q: query }),
                {
                    cache: 'no-store',
                    headers: { 'Accept': 'application/json' },
                    signal: inventoryRequest.signal
                }
            );
            var data = await response.json();
            if (!response.ok || !data) throw new Error('Options unavailable.');
            renderInventoryOptions(data.options || []);
        } catch (error) {
            if (error.name !== 'AbortError') renderInventoryOptions([]);
        }
    }

    if (inventorySearch) {
        inventorySearch.addEventListener('input', function () {
            clearInventorySelection(false);
            window.clearTimeout(inventoryTimer);
            var query = inventorySearch.value.trim();
            if (query.length < 2) {
                closeInventorySuggestions();
                return;
            }
            inventoryTimer = window.setTimeout(function () {
                searchInventory(query);
            }, 250);
        });
        inventorySearch.addEventListener('blur', function () {
            window.setTimeout(closeInventorySuggestions, 150);
        });
    }

    if (registerForm) {
        registerForm.addEventListener('submit', function (event) {
            if (!inventoryId || !inventoryId.value) {
                event.preventDefault();
                alert('Select an available Inventory item from the search results.');
                if (inventorySearch) inventorySearch.focus();
            }
        });
    }

    if (openRegister) {
        openRegister.addEventListener('click', async function () {
            var idField = document.getElementById('assetID');
            if (!idField || !registerForm) return;

            registerForm.reset();
            clearInventorySelection(true);
            closeInventorySuggestions();
            idField.value = '';
            openRegister.disabled = true;

            try {
                var response = await fetch('next_asset_id.php', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-Token': document.querySelector(
                            'meta[name="csrf-token"]'
                        ).content
                    },
                    cache: 'no-store'
                });
                var data = await response.json();
                if (!response.ok || !data || !data.asset_id) {
                    throw new Error('Invalid Asset ID response.');
                }
                idField.value = data.asset_id;
                modal.open(registerModal, openRegister);
            } catch (error) {
                alert('Could not generate an Asset ID. Please try again.');
            } finally {
                openRegister.disabled = false;
            }
        });
    }

    var today = new Date().toISOString().split('T')[0];
    var acquisitionDate = document.getElementById('acquisitionDate');
    var assignmentDate = document.getElementById('assignDate');
    if (acquisitionDate) acquisitionDate.max = today;
    if (assignmentDate) assignmentDate.max = today;

    function submitFilters() {
        if (filterForm) filterForm.requestSubmit();
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            window.clearTimeout(filterTimer);
            filterTimer = window.setTimeout(submitFilters, 400);
        });
    }
    ['categoryFilter', 'statusFilter', 'locationFilter'].forEach(function (id) {
        var field = document.getElementById(id);
        if (field) field.addEventListener('change', submitFilters);
    });
})();
