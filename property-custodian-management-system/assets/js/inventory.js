const inventoryModal = document.getElementById("inventoryModal");
const openInventoryModal = document.getElementById("openInventoryModal");
const closeInventoryModal = document.querySelector("#inventoryModal .close-modal");
const searchInput = document.getElementById("searchInput");
const categoryFilter = document.getElementById("categoryFilter");
const conditionFilter = document.getElementById("conditionFilter");

if (openInventoryModal) {
    openInventoryModal.onclick = function () {
        if (inventoryModal) {
            inventoryModal.style.display = "block";
        }
    };
}

if (closeInventoryModal) {
    closeInventoryModal.onclick = function () {
        if (inventoryModal) {
            inventoryModal.style.display = "none";
        }
    };
}

window.onclick = function (event) {
    if (event.target === inventoryModal) {
        inventoryModal.style.display = "none";
    }
};

function generateInventoryID() {
    const randomNumber = Math.floor(Math.random() * 999999) + 1;
    const idField = document.getElementById("inventoryID");

    if (idField) {
        idField.value = "INV-" + String(randomNumber).padStart(6, "0");
    }
}

if (openInventoryModal) {
    openInventoryModal.onclick = function () {
        generateInventoryID();

        if (inventoryModal) {
            inventoryModal.style.display = "block";
        }
    };
}

function applyInventoryFilters() {
    const search = (searchInput?.value || "").toLowerCase();
    const category = (categoryFilter?.value || "").toLowerCase();
    const condition = (conditionFilter?.value || "").toLowerCase();

    const rows = document.querySelectorAll("#inventoryTable tbody tr.inventory-row");

    rows.forEach(function (row) {
        const inventoryID = row.cells[0].textContent.toLowerCase();
        const assetName = row.cells[1].textContent.toLowerCase();
        const assetCategory = row.cells[2].textContent.toLowerCase();
        const assetCondition = row.cells[4].textContent.toLowerCase();

        const matchesSearch = inventoryID.includes(search) || assetName.includes(search);
        const matchesCategory = category === "" || assetCategory === category;
        const matchesCondition = condition === "" || assetCondition === condition;

        row.style.display = (matchesSearch && matchesCategory && matchesCondition) ? "" : "none";
    });
}

if (searchInput) {
    searchInput.addEventListener("keyup", applyInventoryFilters);
}

if (categoryFilter) {
    categoryFilter.addEventListener("change", applyInventoryFilters);
}

if (conditionFilter) {
    conditionFilter.addEventListener("change", applyInventoryFilters);
}