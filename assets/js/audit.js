const auditModal = document.getElementById("auditModal");
const openAuditModal = document.getElementById("openAuditModal");
const closeAuditModal = document.querySelector("#auditModal .close-modal");
const searchInput = document.getElementById("searchInput");
const statusFilter = document.getElementById("statusFilter");
const resultFilter = document.getElementById("resultFilter");

if (openAuditModal) {
    openAuditModal.onclick = function () {
        const idField = document.getElementById("auditID");
        if (idField) {
            idField.value = "AUD-" + String(Math.floor(Math.random() * 999999) + 1).padStart(6, "0");
        }

        if (auditModal) {
            auditModal.style.display = "block";
        }
    };
}

if (closeAuditModal) {
    closeAuditModal.onclick = function () {
        if (auditModal) {
            auditModal.style.display = "none";
        }
    };
}

window.onclick = function (event) {
    if (event.target === auditModal) {
        auditModal.style.display = "none";
    }
};

function applyAuditFilters() {
    const search = (searchInput?.value || "").toLowerCase();
    const status = (statusFilter?.value || "").toLowerCase();
    const result = (resultFilter?.value || "").toLowerCase();

    const rows = document.querySelectorAll("#auditTable tbody tr.audit-row");

    rows.forEach(function (row) {
        const auditID = row.cells[0].textContent.toLowerCase();
        const assetName = row.cells[1].textContent.toLowerCase();
        const auditor = row.cells[2].textContent.toLowerCase();
        const auditStatus = row.cells[4].textContent.toLowerCase();
        const auditResult = row.cells[5].textContent.toLowerCase();

        const matchesSearch = auditID.includes(search) || assetName.includes(search) || auditor.includes(search);
        const matchesStatus = status === "" || auditStatus === status;
        const matchesResult = result === "" || auditResult === result;

        row.style.display = (matchesSearch && matchesStatus && matchesResult) ? "" : "none";
    });
}

if (searchInput) {
    searchInput.addEventListener("keyup", applyAuditFilters);
}

if (statusFilter) {
    statusFilter.addEventListener("change", applyAuditFilters);
}

if (resultFilter) {
    resultFilter.addEventListener("change", applyAuditFilters);
}
