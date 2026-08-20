(function () {
    'use strict';

    var assetModal = document.getElementById('assetModal');
    var viewAssetModal = document.getElementById('viewAssetModal');
    var editAssetModal = document.getElementById('editAssetModal');
    var assignCustodianModal = document.getElementById('assignCustodianModal');
    var openAssetModal = document.getElementById('openAssetModal');
    var editAssetFromView = document.getElementById('editAssetFromView');
    var searchInput = document.getElementById('searchInput');
    var categoryFilter = document.getElementById('categoryFilter');
    var statusFilter = document.getElementById('statusFilter');
    var inventoryItemSelect = document.getElementById('inventoryItemSelect');
    var assetNameField = document.getElementById('assetNameField');
    var assetCategoryField = document.getElementById('assetCategoryField');
    var acquisitionDate = document.getElementById('acquisitionDate');
    var editAssetForm = document.getElementById('editAssetForm');
    var assignCustodianForm = document.getElementById('assignCustodianForm');
    var activeModal = null;
    var returnFocusTo = null;
    var currentAsset = null;

    var focusableSelector = [
        'a[href]',
        'button:not([disabled])',
        'input:not([disabled]):not([type="hidden"])',
        'select:not([disabled])',
        'textarea:not([disabled])',
        '[tabindex]:not([tabindex="-1"])'
    ].join(',');

    function focusableElements(modal) {
        return Array.prototype.slice.call(
            modal.querySelectorAll(focusableSelector)
        ).filter(function (element) {
            return element.offsetParent !== null;
        });
    }

    function closeModal(modal, restoreFocus) {
        if (!modal) return;

        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');

        if (activeModal === modal) {
            activeModal = null;
        }

        if (!document.querySelector('.pcms-modal.is-open')) {
            document.body.classList.remove('pcms-modal-open');
        }

        if (
            restoreFocus !== false &&
            returnFocusTo &&
            document.documentElement.contains(returnFocusTo)
        ) {
            returnFocusTo.focus();
            returnFocusTo = null;
        }
    }

    function openModal(modal, opener) {
        if (!modal) return;

        if (!activeModal && opener) {
            returnFocusTo = opener;
        }

        if (activeModal && activeModal !== modal) {
            closeModal(activeModal, false);
        }

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('pcms-modal-open');
        activeModal = modal;

        window.requestAnimationFrame(function () {
            var preferred = modal.querySelector('[data-modal-autofocus]');
            var focusable = focusableElements(modal);
            var target = preferred || focusable[0];

            if (target) target.focus();
        });
    }

    function readAsset(trigger) {
        var row = trigger.closest('tr[data-asset]');

        if (!row || !row.dataset.asset) return null;

        try {
            return JSON.parse(row.dataset.asset);
        } catch (error) {
            return null;
        }
    }

    function displayValue(value, emptyText) {
        if (value === null || value === undefined || String(value).trim() === '') {
            return emptyText || '—';
        }

        return String(value);
    }

    function displayDate(value, emptyText) {
        if (!value) return emptyText || '—';

        var parts = String(value).split('-');

        if (parts.length !== 3) return String(value);

        var date = new Date(
            Number(parts[0]),
            Number(parts[1]) - 1,
            Number(parts[2])
        );

        if (Number.isNaN(date.getTime())) return String(value);

        return date.toLocaleDateString('en-PH', {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });
    }

    function displayCost(value) {
        if (value === null || value === undefined || value === '') return '—';

        var amount = Number(value);

        if (!Number.isFinite(amount)) return String(value);

        return new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: 'PHP'
        }).format(amount);
    }

    function populateViewModal(asset) {
        var custodyFields = ['employee_id', 'custodian', 'department', 'date_assigned'];

        viewAssetModal.querySelectorAll('[data-view-field]').forEach(function (field) {
            var name = field.dataset.viewField;
            var value = asset[name];

            if (name === 'purchase_cost') {
                field.textContent = displayCost(value);
            } else if (name === 'acquisition_date') {
                field.textContent = displayDate(value);
            } else if (name === 'date_assigned') {
                field.textContent = displayDate(value, 'Not Assigned');
            } else {
                field.textContent = displayValue(
                    value,
                    custodyFields.indexOf(name) !== -1 ? 'Not Assigned' : '—'
                );
            }
        });

        var subtitle = document.getElementById('viewAssetSubtitle');
        var badge = document.getElementById('viewAssetStatus');

        subtitle.textContent = [asset.asset_id, asset.asset_name]
            .filter(Boolean)
            .join(' — ');

        badge.textContent = displayValue(asset.status, 'Available');
        badge.dataset.status = String(asset.status || 'Available')
            .toLowerCase()
            .replace(/\s+/g, '-');
    }

    function setSelectValue(select, value) {
        var dynamicOption = select.querySelector('option[data-dynamic-option]');

        if (dynamicOption) dynamicOption.remove();

        var hasValue = Array.prototype.some.call(select.options, function (option) {
            return option.value === value;
        });

        if (!hasValue && value) {
            var option = document.createElement('option');
            option.value = value;
            option.textContent = value;
            option.dataset.dynamicOption = 'true';
            select.appendChild(option);
        }

        select.value = value || '';
    }

    function populateEditModal(asset) {
        editAssetForm.action = 'update_asset.php?id=' + encodeURIComponent(asset.id);
        document.getElementById('editAssetID').value = asset.asset_id || '';
        document.getElementById('editAssetName').value = asset.asset_name || '';
        setSelectValue(
            document.getElementById('editAssetCategory'),
            asset.category || ''
        );
        document.getElementById('editAssetBrand').value = asset.brand || '';
        document.getElementById('editAssetModel').value = asset.model || '';
        document.getElementById('editAssetSerial').value = asset.serial_number || '';
        document.getElementById('editAssetSupplier').value = asset.supplier || '';
        document.getElementById('editAssetLocation').value = asset.location || '';
        document.getElementById('editAssetRemarks').value = asset.remarks || '';
    }

    function populateAssignModal(asset) {
        assignCustodianForm.reset();
        assignCustodianForm.action = 'save_assignment.php?id=' + encodeURIComponent(asset.id);
        document.getElementById('assignAssetLabel').textContent = [
            asset.asset_id,
            asset.asset_name
        ].filter(Boolean).join(' — ');
    }

    document.querySelectorAll('[data-modal-close]').forEach(function (button) {
        button.addEventListener('click', function () {
            closeModal(button.closest('.pcms-modal'));
        });
    });

    document.querySelectorAll('.pcms-modal').forEach(function (modal) {
        modal.addEventListener('mousedown', function (event) {
            if (event.target === modal) closeModal(modal);
        });
    });

    document.addEventListener('keydown', function (event) {
        if (!activeModal) return;

        if (event.key === 'Escape') {
            event.preventDefault();
            closeModal(activeModal);
            return;
        }

        if (event.key !== 'Tab') return;

        var focusable = focusableElements(activeModal);

        if (focusable.length === 0) {
            event.preventDefault();
            return;
        }

        var first = focusable[0];
        var last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    document.querySelectorAll('[data-asset-action]').forEach(function (trigger) {
        trigger.addEventListener('click', function (event) {
            var asset = readAsset(trigger);

            if (!asset) return;

            var action = trigger.dataset.assetAction;
            var modal = null;

            if (action === 'view' && viewAssetModal) {
                populateViewModal(asset);
                modal = viewAssetModal;
            } else if (action === 'edit' && editAssetModal && editAssetForm) {
                populateEditModal(asset);
                modal = editAssetModal;
            } else if (
                action === 'assign' &&
                assignCustodianModal &&
                assignCustodianForm
            ) {
                populateAssignModal(asset);
                modal = assignCustodianModal;
            }

            if (!modal) return;

            event.preventDefault();
            currentAsset = asset;
            openModal(modal, trigger);
        });
    });

    if (editAssetFromView) {
        editAssetFromView.addEventListener('click', function () {
            if (!currentAsset || !editAssetModal || !editAssetForm) return;

            populateEditModal(currentAsset);
            openModal(editAssetModal, editAssetFromView);
        });
    }

    if (openAssetModal) {
        openAssetModal.addEventListener('click', async function () {
            var idField = document.getElementById('assetID');

            if (!idField) {
                alert('Could not prepare the Asset registration form.');
                return;
            }

            idField.value = '';
            openAssetModal.disabled = true;

            try {
                var response = await fetch('next_asset_id.php', {
                    method: 'POST',
                    cache: 'no-store'
                });

                if (!response.ok) {
                    throw new Error('Could not generate Asset ID.');
                }

                var data = await response.json();

                if (!data || typeof data.asset_id !== 'string' || data.asset_id === '') {
                    throw new Error('Invalid Asset ID response.');
                }

                idField.value = data.asset_id;
                openModal(assetModal, openAssetModal);
            } catch (error) {
                alert('Could not generate an Asset ID. Please try again.');
            } finally {
                openAssetModal.disabled = false;
            }
        });
    }

    var today = new Date().toISOString().split('T')[0];

    if (acquisitionDate) acquisitionDate.max = today;

    if (inventoryItemSelect && assetNameField && assetCategoryField) {
        inventoryItemSelect.addEventListener('change', function () {
            var selectedOption = inventoryItemSelect.options[
                inventoryItemSelect.selectedIndex
            ];

            assetNameField.value = selectedOption
                ? selectedOption.getAttribute('data-name') || ''
                : '';

            assetCategoryField.value = selectedOption
                ? selectedOption.getAttribute('data-category') || ''
                : '';
        });
    }

    function applyAssetFilters() {
        var search = (searchInput ? searchInput.value : '').toLowerCase();
        var category = (categoryFilter ? categoryFilter.value : '').toLowerCase();
        var status = (statusFilter ? statusFilter.value : '').toLowerCase();
        var rows = document.querySelectorAll('#assetTable tbody tr.asset-row');

        rows.forEach(function (row) {
            var assetID = row.cells[0].textContent.toLowerCase();
            var assetName = row.cells[1].textContent.toLowerCase();
            var assetCategory = row.cells[2].textContent.toLowerCase().trim();
            var assetStatus = row.cells[4].textContent.toLowerCase().trim();
            var matchesSearch = assetID.includes(search) || assetName.includes(search);
            var matchesCategory = category === '' || assetCategory === category;
            var matchesStatus = status === '' || assetStatus === status;

            row.style.display = matchesSearch && matchesCategory && matchesStatus
                ? ''
                : 'none';
        });
    }

    if (searchInput) searchInput.addEventListener('input', applyAssetFilters);
    if (categoryFilter) categoryFilter.addEventListener('change', applyAssetFilters);
    if (statusFilter) statusFilter.addEventListener('change', applyAssetFilters);
})();
