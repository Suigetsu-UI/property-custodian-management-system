const reportModal = document.getElementById("reportModal");
const openReportModal = document.getElementById("openReportModal");
const closeReportModal = document.querySelector("#reportModal .close-modal");
const searchInput = document.getElementById("searchInput");
const reportTypeFilter = document.getElementById("reportType");
const reportStatusFilter = document.getElementById("reportStatus");

if (openReportModal) {
    openReportModal.onclick = function () {
        const idField = document.getElementById("reportID");
        if (idField) {
            idField.value = "RPT-" + String(Math.floor(Math.random() * 999999) + 1).padStart(6, "0");
        }

        if (reportModal) {
            reportModal.style.display = "block";
        }
    };
}

if (closeReportModal) {
    closeReportModal.onclick = function () {
        if (reportModal) {
            reportModal.style.display = "none";
        }
    };
}

window.onclick = function (event) {
    if (event.target === reportModal) {
        reportModal.style.display = "none";
    }
};

function applyReportFilters() {
    const search = (searchInput?.value || "").toLowerCase();
    const reportType = (reportTypeFilter?.value || "").toLowerCase();
    const status = (reportStatusFilter?.value || "").toLowerCase();

    const rows = document.querySelectorAll("#reportTable tbody tr.report-row");

    rows.forEach(function (row) {
        const reportID = row.cells[0].textContent.toLowerCase();
        const reportName = row.cells[1].textContent.toLowerCase();
        const reportTypeValue = row.cells[2].textContent.toLowerCase();
        const reportStatusValue = row.cells[5].textContent.toLowerCase();

        const matchesSearch = reportID.includes(search) || reportName.includes(search);
        const matchesType = reportType === "" || reportTypeValue === reportType;
        const matchesStatus = status === "" || reportStatusValue === status;

        row.style.display = (matchesSearch && matchesType && matchesStatus) ? "" : "none";
    });
}

if (searchInput) {
    searchInput.addEventListener("keyup", applyReportFilters);
}

if (reportTypeFilter) {
    reportTypeFilter.addEventListener("change", applyReportFilters);
}

if (reportStatusFilter) {
    reportStatusFilter.addEventListener("change", applyReportFilters);
}
