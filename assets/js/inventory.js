const inventoryModal = document.getElementById("inventoryModal");
const openInventoryModal = document.getElementById("openInventoryModal");
const closeInventoryModal = document.querySelector(
    "#inventoryModal .close-modal"
);

const searchInput = document.getElementById("searchInput");
const categoryFilter = document.getElementById("categoryFilter");
const conditionFilter = document.getElementById("conditionFilter");

if (openInventoryModal) {
    openInventoryModal.onclick = async function () {
        const idField = document.getElementById("inventoryID");

        /*
         * Never reuse an abandoned Add ID.
         * Each Add initiation receives a fresh sequence value.
         */
        if (idField) {
            idField.value = "";
        }

        openInventoryModal.disabled = true;

        try {
            const response = await fetch(
                "next_inventory_id.php",
                {
                    method: "POST",
                    cache: "no-store"
                }
            );

            if (!response.ok) {
                throw new Error(
                    "Could not generate Inventory ID."
                );
            }

            const data = await response.json();

            if (
                !data ||
                typeof data.inventory_id !== "string" ||
                data.inventory_id === ""
            ) {
                throw new Error(
                    "Invalid Inventory ID response."
                );
            }

            if (idField) {
                idField.value = data.inventory_id;
            }

            if (inventoryModal) {
                inventoryModal.style.display = "block";
            }

        } catch (error) {
            alert(
                "Could not generate an Inventory ID. Please try again."
            );

        } finally {
            openInventoryModal.disabled = false;
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

function applyInventoryFilters() {
    const search =
        (searchInput?.value || "").toLowerCase();

    const category =
        (categoryFilter?.value || "").toLowerCase();

    const condition =
        (conditionFilter?.value || "").toLowerCase();

    const rows = document.querySelectorAll(
        "#inventoryTable tbody tr.inventory-row"
    );

    rows.forEach(function (row) {
        const inventoryID =
            row.cells[0].textContent.toLowerCase();

        const assetName =
            row.cells[1].textContent.toLowerCase();

        const assetCategory =
            row.cells[2].textContent.toLowerCase();

        const assetCondition =
            row.cells[4].textContent.toLowerCase();

        const matchesSearch =
            inventoryID.includes(search) ||
            assetName.includes(search);

        const matchesCategory =
            category === "" ||
            assetCategory === category;

        const matchesCondition =
            condition === "" ||
            assetCondition === condition;

        row.style.display =
            (
                matchesSearch &&
                matchesCategory &&
                matchesCondition
            )
                ? ""
                : "none";
    });
}

if (searchInput) {
    searchInput.addEventListener(
        "keyup",
        applyInventoryFilters
    );
}

if (categoryFilter) {
    categoryFilter.addEventListener(
        "change",
        applyInventoryFilters
    );
}

if (conditionFilter) {
    conditionFilter.addEventListener(
        "change",
        applyInventoryFilters
    );
}