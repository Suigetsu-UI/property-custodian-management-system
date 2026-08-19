const procurementModal = document.getElementById("procurementModal");
const openProcurementModal = document.getElementById("openProcurementModal");
const closeProcurementModal = document.querySelector("#procurementModal .close-modal");
const searchInput = document.getElementById("searchInput");
const statusFilter = document.getElementById("statusFilter");
const supplierFilter = document.getElementById("supplierFilter");

if (openProcurementModal) {
    openProcurementModal.onclick = async function () {
        const idField = document.getElementById("procurementID");

        if (!idField || !procurementModal) {
            return;
        }

        /*
         * Never reuse an ID left in the field from an earlier abandoned
         * Add attempt. Each new Add initiation requests a fresh sequence
         * value from the server.
         */
        idField.value = "";
        openProcurementModal.disabled = true;

        try {
            const response = await fetch("next_procurement_id.php", {
                method: "POST",
                cache: "no-store"
            });

            if (!response.ok) {
                throw new Error("Could not generate Procurement ID.");
            }

            const data = await response.json();

            if (
                !data ||
                typeof data.procurement_id !== "string" ||
                data.procurement_id === ""
            ) {
                throw new Error("Invalid Procurement ID response.");
            }

            idField.value = data.procurement_id;
            procurementModal.style.display = "block";

        } catch (error) {
            alert("Could not generate a Procurement ID. Please try again.");

        } finally {
            openProcurementModal.disabled = false;
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
