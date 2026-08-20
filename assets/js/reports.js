(function () {
    'use strict';

    var modal = window.PCMSModal;
    var generateModal = document.getElementById('reportModal');
    var viewModal = document.getElementById('viewReportModal');
    var generateButton = document.getElementById('openReportModal');
    var generateForm = document.getElementById('generateReportForm');
    var previewBody = document.getElementById('reportPreviewBody');
    var previewTitle = document.getElementById('viewReportTitle');
    var previewSubtitle = document.getElementById('viewReportSubtitle');
    var previewDownload = document.getElementById('reportPreviewDownload');

    function loadingMarkup() {
        return '<div class="pcms-report-loading"><span class="pcms-loading-spinner" aria-hidden="true"></span><strong>Loading report…</strong></div>';
    }

    async function loadReport(url, options, reportType, opener) {
        previewTitle.textContent = reportType || 'Report Preview';
        previewSubtitle.textContent = 'Loading current report data…';
        previewBody.innerHTML = loadingMarkup();
        previewDownload.href = url.replace('view_report.php', 'download_report.php');
        modal.open(viewModal, opener);

        var response = await fetch(url, options || {cache: 'no-store'});
        if (!response.ok) throw new Error('Could not load report.');

        var html = await response.text();
        var parsed = new DOMParser().parseFromString(html, 'text/html');
        var content = parsed.querySelector('.main-content');
        if (!content) throw new Error('Invalid report response.');

        var heading = content.querySelector('h1');
        if (heading) heading.remove();
        var divider = content.querySelector('hr');
        if (divider) divider.remove();

        content.querySelectorAll('a').forEach(function (link) {
            var text = link.textContent.trim().toLowerCase();
            if (text.includes('download')) {
                previewDownload.href = link.getAttribute('href') || previewDownload.href;
                link.remove();
            } else if (text.includes('back')) {
                link.remove();
            }
        });

        previewBody.innerHTML = content.innerHTML;
        previewSubtitle.textContent = (reportType || 'Report') + ' — current system data';
    }

    if (generateButton) generateButton.addEventListener('click', function () {
        generateForm.reset();
        modal.open(generateModal, generateButton);
    });

    document.querySelectorAll('[data-report-action="view"]').forEach(function (trigger) {
        trigger.addEventListener('click', function (event) {
            event.preventDefault();
            loadReport(trigger.href, {cache: 'no-store'}, trigger.dataset.reportType, trigger).catch(function () {
                window.location.href = trigger.href;
            });
        });
    });

    if (generateForm) generateForm.addEventListener('submit', function (event) {
        event.preventDefault();
        var formData = new FormData(generateForm);
        var reportType = formData.get('report_type');
        loadReport(
            generateForm.action,
            {method: 'POST', body: formData, cache: 'no-store'},
            reportType,
            generateButton
        ).catch(function () {
            generateForm.submit();
        });
    });

    var searchInput = document.getElementById('searchInput');
    var reportTypeFilter = document.getElementById('reportType');
    function applyFilters() {
        var search = (searchInput ? searchInput.value : '').toLowerCase();
        var reportType = (reportTypeFilter ? reportTypeFilter.value : '').toLowerCase();
        document.querySelectorAll('#reportTable tbody tr.report-row').forEach(function (row) {
            var type = row.cells[0].textContent.toLowerCase().trim();
            var description = row.cells[1].textContent.toLowerCase();
            var matchesSearch = type.includes(search) || description.includes(search);
            var matchesType = !reportType || type === reportType;
            row.style.display = matchesSearch && matchesType ? '' : 'none';
        });
    }
    if (searchInput) searchInput.addEventListener('input', applyFilters);
    if (reportTypeFilter) reportTypeFilter.addEventListener('change', applyFilters);
})();
