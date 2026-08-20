const assetModal =
    document.getElementById("assetModal");

const openAssetModal =
    document.getElementById("openAssetModal");

const closeAssetModal =
    document.querySelector("#assetModal .close-modal");

const searchInput =
    document.getElementById("searchInput");

const categoryFilter =
    document.getElementById("categoryFilter");

const statusFilter =
    document.getElementById("statusFilter");

const inventoryItemSelect =
    document.getElementById("inventoryItemSelect");

const assetNameField =
    document.getElementById("assetNameField");

const assetCategoryField =
    document.getElementById("assetCategoryField");

const acquisitionDate =
    document.getElementById("acquisitionDate");


if (openAssetModal) {
    openAssetModal.onclick = async function () {
        const idField =
            document.getElementById("assetID");

        if (!idField) {
            alert(
                "Could not prepare the Asset registration form."
            );
            return;
        }

        /*
         * Never reuse an abandoned AST ID.
         */
        idField.value = "";

        openAssetModal.disabled = true;

        try {
            const response = await fetch(
                "next_asset_id.php",
                {
                    method: "POST",
                    cache: "no-store"
                }
            );

            if (!response.ok) {
                throw new Error(
                    "Could not generate Asset ID."
                );
            }

            const data = await response.json();

            if (
                !data ||
                typeof data.asset_id !== "string" ||
                data.asset_id === ""
            ) {
                throw new Error(
                    "Invalid Asset ID response."
                );
            }

            idField.value = data.asset_id;

            if (assetModal) {
                assetModal.style.display = "block";
            }

        } catch (error) {
            alert(
                "Could not generate an Asset ID. Please try again."
            );

        } finally {
            openAssetModal.disabled = false;
        }
    };
}


if (closeAssetModal) {
    closeAssetModal.onclick = function () {
        if (assetModal) {
            assetModal.style.display = "none";
        }
    };
}


window.onclick = function (event) {
    if (event.target === assetModal) {
        assetModal.style.display = "none";
    }
};


if (acquisitionDate) {
    acquisitionDate.max =
        new Date()
            .toISOString()
            .split("T")[0];
}


function applyAssetFilters() {
    const search =
        (searchInput?.value || "")
            .toLowerCase();

    const category =
        (categoryFilter?.value || "")
            .toLowerCase();

    const status =
        (statusFilter?.value || "")
            .toLowerCase();

    const rows =
        document.querySelectorAll(
            "#assetTable tbody tr.asset-row"
        );

    rows.forEach(function (row) {
        const assetID =
            row.cells[0]
                .textContent
                .toLowerCase();

        const assetName =
            row.cells[1]
                .textContent
                .toLowerCase();

        const assetCategory =
            row.cells[2]
                .textContent
                .toLowerCase();

        const assetStatus =
            row.cells[4]
                .textContent
                .toLowerCase();

        const matchesSearch =
            assetID.includes(search) ||
            assetName.includes(search);

        const matchesCategory =
            category === "" ||
            assetCategory === category;

        const matchesStatus =
            status === "" ||
            assetStatus === status;

        row.style.display =
            (
                matchesSearch &&
                matchesCategory &&
                matchesStatus
            )
                ? ""
                : "none";
    });
}


if (searchInput) {
    searchInput.addEventListener(
        "keyup",
        applyAssetFilters
    );
}


if (categoryFilter) {
    categoryFilter.addEventListener(
        "change",
        applyAssetFilters
    );
}


if (statusFilter) {
    statusFilter.addEventListener(
        "change",
        applyAssetFilters
    );
}


if (
    inventoryItemSelect &&
    assetNameField &&
    assetCategoryField
) {
    inventoryItemSelect.addEventListener(
        "change",
        function () {
            const selectedOption =
                inventoryItemSelect.options[
                    inventoryItemSelect.selectedIndex
                ];

            assetNameField.value =
                selectedOption
                    ? (
                        selectedOption.getAttribute(
                            "data-name"
                        ) || ""
                    )
                    : "";

            assetCategoryField.value =
                selectedOption
                    ? (
                        selectedOption.getAttribute(
                            "data-category"
                        ) || ""
                    )
                    : "";
        }
    );
}