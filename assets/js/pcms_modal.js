(function (window, document) {
    'use strict';

    if (window.PCMSModal) return;

    var activeModal = null;
    var returnFocusTo = null;
    var focusableSelector = [
        'a[href]',
        'button:not([disabled])',
        'input:not([disabled]):not([type="hidden"])',
        'select:not([disabled])',
        'textarea:not([disabled])',
        '[tabindex]:not([tabindex="-1"])'
    ].join(',');

    function getFocusable(modal) {
        return Array.prototype.slice.call(
            modal.querySelectorAll(focusableSelector)
        ).filter(function (element) {
            return element.offsetParent !== null;
        });
    }

    function close(modal, restoreFocus) {
        modal = modal || activeModal;
        if (!modal) return;

        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');

        if (activeModal === modal) activeModal = null;

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

    function open(modal, opener) {
        if (!modal) return;

        if (!activeModal && opener) returnFocusTo = opener;

        if (activeModal && activeModal !== modal) {
            close(activeModal, false);
        }

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('pcms-modal-open');
        activeModal = modal;

        window.requestAnimationFrame(function () {
            var preferred = modal.querySelector('[data-modal-autofocus]');
            var focusable = getFocusable(modal);
            var target = preferred || focusable[0];
            if (target) target.focus();
        });
    }

    function readRowData(trigger) {
        var row = trigger.closest('tr[data-record]');
        if (!row || !row.dataset.record) return null;

        try {
            return JSON.parse(row.dataset.record);
        } catch (error) {
            return null;
        }
    }

    function value(value, emptyText) {
        if (value === null || value === undefined || String(value).trim() === '') {
            return emptyText || '—';
        }
        return String(value);
    }

    function date(valueToFormat, emptyText) {
        if (!valueToFormat) return emptyText || '—';

        var parts = String(valueToFormat).split('-');
        if (parts.length !== 3) return String(valueToFormat);

        var parsed = new Date(
            Number(parts[0]),
            Number(parts[1]) - 1,
            Number(parts[2])
        );

        if (Number.isNaN(parsed.getTime())) return String(valueToFormat);

        return parsed.toLocaleDateString('en-PH', {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });
    }

    function setSelectValue(select, selectedValue) {
        if (!select) return;

        var dynamic = select.querySelector('option[data-dynamic-option]');
        if (dynamic) dynamic.remove();

        var stringValue = selectedValue == null ? '' : String(selectedValue);
        var exists = Array.prototype.some.call(select.options, function (option) {
            return option.value === stringValue;
        });

        if (!exists && stringValue) {
            var option = document.createElement('option');
            option.value = stringValue;
            option.textContent = stringValue;
            option.dataset.dynamicOption = 'true';
            select.appendChild(option);
        }

        select.value = stringValue;
    }

    function setStatus(badge, status) {
        if (!badge) return;

        var statusValue = value(status, 'Available');
        badge.textContent = statusValue;
        badge.dataset.status = statusValue.toLowerCase().replace(/\s+/g, '-');
    }

    document.addEventListener('click', function (event) {
        var closeButton = event.target.closest('[data-modal-close]');
        if (!closeButton) return;

        var modal = closeButton.closest('.pcms-modal');
        if (modal) close(modal);
    });

    document.addEventListener('mousedown', function (event) {
        if (event.target.matches('.pcms-modal.is-open')) close(event.target);
    });

    document.addEventListener('keydown', function (event) {
        if (!activeModal) return;

        if (event.key === 'Escape') {
            event.preventDefault();
            close(activeModal);
            return;
        }

        if (event.key !== 'Tab') return;

        var focusable = getFocusable(activeModal);
        if (!focusable.length) {
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

    window.PCMSModal = {
        open: open,
        close: close,
        readRowData: readRowData,
        value: value,
        date: date,
        setSelectValue: setSelectValue,
        setStatus: setStatus
    };
})(window, document);
