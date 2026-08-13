const maintenanceModal = document.getElementById("maintenanceModal");
const openMaintenanceModal = document.getElementById("openMaintenanceModal");
const closeMaintenanceModal = document.querySelector("#maintenanceModal .close-modal");
const searchInput = document.getElementById("searchInput");
const statusFilter = document.getElementById("statusFilter");

if (openMaintenanceModal) {
    openMaintenanceModal.onclick = function () {
        const idField = document.getElementById("maintenanceID");
        if (idField) {
            idField.value = "MNT-" + String(Math.floor(Math.random() * 999999) + 1).padStart(6, "0");
        }

        if (maintenanceModal) {
            maintenanceModal.style.display = "block";
        }
    };
}

if (closeMaintenanceModal) {
    closeMaintenanceModal.onclick = function () {
        if (maintenanceModal) {
            maintenanceModal.style.display = "none";
        }
    };
}

window.onclick = function (event) {
    if (event.target === maintenanceModal) {
        maintenanceModal.style.display = "none";
    }
};

function applyMaintenanceFilters() {
    const search = (searchInput?.value || "").toLowerCase();
    const status = (statusFilter?.value || "").toLowerCase();

    const rows = document.querySelectorAll("#maintenanceTable tbody tr.maintenance-row");

    rows.forEach(function (row) {
        const id = row.cells[0].textContent.toLowerCase();
        const assetName = row.cells[1].textContent.toLowerCase();
        const type = row.cells[2].textContent.toLowerCase();
        const statusValue = row.cells[4].textContent.toLowerCase();

        const matchesSearch = id.includes(search) || assetName.includes(search) || type.includes(search);
        const matchesStatus = status === "" || statusValue === status;

        row.style.display = (matchesSearch && matchesStatus) ? "" : "none";
    });
}

if (searchInput) {
    searchInput.addEventListener("keyup", applyMaintenanceFilters);
}

if (statusFilter) {
    statusFilter.addEventListener("change", applyMaintenanceFilters);
}



const maintenanceAssetSelect = document.getElementById("maintenanceAssetSelect");
const maintenanceAssetName = document.getElementById("maintenanceAssetName");
const maintenanceCategory = document.getElementById("maintenanceCategory");
const maintenanceCustodian = document.getElementById("maintenanceCustodian");
const maintenanceAssetStatus = document.getElementById("maintenanceAssetStatus");

if (maintenanceAssetSelect && maintenanceAssetName && maintenanceCategory && maintenanceCustodian && maintenanceAssetStatus) {
    maintenanceAssetSelect.addEventListener("change", function () {
        const selectedOption = maintenanceAssetSelect.options[maintenanceAssetSelect.selectedIndex];
        maintenanceAssetName.value = selectedOption ? (selectedOption.getAttribute("data-name") || "") : "";
        maintenanceCategory.value = selectedOption ? (selectedOption.getAttribute("data-category") || "") : "";
        maintenanceCustodian.value = selectedOption ? (selectedOption.getAttribute("data-custodian") || "") : "";
        maintenanceAssetStatus.value = selectedOption ? (selectedOption.getAttribute("data-status") || "") : "";
    });
}