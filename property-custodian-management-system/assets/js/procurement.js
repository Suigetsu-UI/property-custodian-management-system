const procurementModal = document.getElementById("procurementModal");
const openProcurementModal = document.getElementById("openProcurementModal");
const closeProcurementModal = document.querySelector("#procurementModal .close-modal");
const searchInput = document.getElementById("searchInput");
const statusFilter = document.getElementById("statusFilter");
const supplierFilter = document.getElementById("supplierFilter");

if (openProcurementModal) {
    openProcurementModal.onclick = function () {
        const idField = document.getElementById("procurementID");
        if (idField) {
            idField.value = "PRC-" + String(Math.floor(Math.random() * 999999) + 1).padStart(6, "0");
        }

        if (procurementModal) {
            procurementModal.style.display = "block";
        }
    };
}

if (closeProcurementModal) {
    closeProcurementModal.onclick = function () {
        if (procurementModal) {
            procurementModal.style.display = "none";
        }
    };
}

window.onclick = function (event) {
    if (event.target === procurementModal) {
        procurementModal.style.display = "none";
    }
};

function applyProcurementFilters() {
    const search = (searchInput?.value || "").toLowerCase();
    const status = (statusFilter?.value || "").toLowerCase();
    const supplier = (supplierFilter?.value || "").toLowerCase();

    const rows = document.querySelectorAll("#procurementTable tbody tr.procurement-row");

    rows.forEach(function (row) {
        const id = row.cells[0].textContent.toLowerCase();
        const itemName = row.cells[1].textContent.toLowerCase();
        const rowSupplier = row.cells[4].textContent.toLowerCase();
        const rowStatus = row.cells[5].textContent.toLowerCase();

        const matchesSearch = id.includes(search) || itemName.includes(search);
        const matchesStatus = status === "" || rowStatus === status;
        const matchesSupplier = supplier === "" || rowSupplier === supplier;

        row.style.display = (matchesSearch && matchesStatus && matchesSupplier) ? "" : "none";
    });
}

if (searchInput) {
    searchInput.addEventListener("keyup", applyProcurementFilters);
}

if (statusFilter) {
    statusFilter.addEventListener("change", applyProcurementFilters);
}

if (supplierFilter) {
    supplierFilter.addEventListener("change", applyProcurementFilters);
}
