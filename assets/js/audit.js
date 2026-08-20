const auditModal =
    document.getElementById("auditModal");

const openAuditModal =
    document.getElementById("openAuditModal");

const closeAuditModal =
    document.querySelector(
        "#auditModal .close-modal"
    );

const searchInput =
    document.getElementById("searchInput");

const statusFilter =
    document.getElementById("statusFilter");

const resultFilter =
    document.getElementById("resultFilter");

const auditAssetSelect =
    document.getElementById("auditAssetSelect");

const auditAssetName =
    document.getElementById("auditAssetName");

const auditCategory =
    document.getElementById("auditCategory");

const auditCustodian =
    document.getElementById("auditCustodian");

const auditAssetStatus =
    document.getElementById("auditAssetStatus");


if (openAuditModal) {
    openAuditModal.onclick =
        async function () {

        const idField =
            document.getElementById("auditID");

        if (!idField) {
            alert(
                "Could not prepare the Audit form."
            );

            return;
        }

        idField.value = "";

        openAuditModal.disabled = true;

        try {
            const response = await fetch(
                "next_audit_id.php",
                {
                    method: "POST",
                    cache: "no-store"
                }
            );

            if (!response.ok) {
                throw new Error(
                    "Could not generate Audit ID."
                );
            }

            const data =
                await response.json();

            if (
                !data ||
                typeof data.audit_id !== "string" ||
                data.audit_id === ""
            ) {
                throw new Error(
                    "Invalid Audit ID response."
                );
            }

            idField.value =
                data.audit_id;

            if (auditModal) {
                auditModal.style.display =
                    "block";
            }

        } catch (error) {
            alert(
                "Could not generate an Audit ID. Please try again."
            );

        } finally {
            openAuditModal.disabled =
                false;
        }
    };
}


if (closeAuditModal) {
    closeAuditModal.onclick =
        function () {

        if (auditModal) {
            auditModal.style.display =
                "none";
        }
    };
}


window.onclick = function (event) {
    if (event.target === auditModal) {
        auditModal.style.display =
            "none";
    }
};


function applyAuditFilters() {
    const search =
        (searchInput?.value || "")
            .toLowerCase();

    const status =
        (statusFilter?.value || "")
            .toLowerCase();

    const result =
        (resultFilter?.value || "")
            .toLowerCase();

    const rows =
        document.querySelectorAll(
            "#auditTable tbody tr.audit-row"
        );

    rows.forEach(function (row) {
        const auditID =
            row.cells[0]
                .textContent
                .toLowerCase();

        const assetName =
            row.cells[1]
                .textContent
                .toLowerCase();

        const auditor =
            row.cells[2]
                .textContent
                .toLowerCase();

        const auditStatus =
            row.cells[4]
                .textContent
                .toLowerCase();

        const auditResult =
            row.cells[5]
                .textContent
                .toLowerCase();

        const matchesSearch =
            auditID.includes(search) ||
            assetName.includes(search) ||
            auditor.includes(search);

        const matchesStatus =
            status === "" ||
            auditStatus === status;

        const matchesResult =
            result === "" ||
            auditResult === result;

        row.style.display =
            (
                matchesSearch &&
                matchesStatus &&
                matchesResult
            )
                ? ""
                : "none";
    });
}


if (searchInput) {
    searchInput.addEventListener(
        "keyup",
        applyAuditFilters
    );
}


if (statusFilter) {
    statusFilter.addEventListener(
        "change",
        applyAuditFilters
    );
}


if (resultFilter) {
    resultFilter.addEventListener(
        "change",
        applyAuditFilters
    );
}


if (
    auditAssetSelect &&
    auditAssetName &&
    auditCategory &&
    auditCustodian &&
    auditAssetStatus
) {
    auditAssetSelect.addEventListener(
        "change",
        function () {

        const selectedOption =
            auditAssetSelect.options[
                auditAssetSelect.selectedIndex
            ];

        auditAssetName.value =
            selectedOption
                ? (
                    selectedOption.getAttribute(
                        "data-name"
                    ) || ""
                )
                : "";

        auditCategory.value =
            selectedOption
                ? (
                    selectedOption.getAttribute(
                        "data-category"
                    ) || ""
                )
                : "";

        auditCustodian.value =
            selectedOption
                ? (
                    selectedOption.getAttribute(
                        "data-custodian"
                    ) || ""
                )
                : "";

        auditAssetStatus.value =
            selectedOption
                ? (
                    selectedOption.getAttribute(
                        "data-status"
                    ) || ""
                )
                : "";
    });
}