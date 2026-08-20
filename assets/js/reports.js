const reportModal =
    document.getElementById("reportModal");

const openReportModal =
    document.getElementById("openReportModal");

const closeReportModal =
    document.querySelector(
        "#reportModal .close-modal"
    );

const searchInput =
    document.getElementById("searchInput");

const reportTypeFilter =
    document.getElementById("reportType");


if (openReportModal) {
    openReportModal.onclick =
        function () {

        if (reportModal) {
            reportModal.style.display =
                "block";
        }
    };
}


if (closeReportModal) {
    closeReportModal.onclick =
        function () {

        if (reportModal) {
            reportModal.style.display =
                "none";
        }
    };
}


window.onclick = function (event) {
    if (event.target === reportModal) {
        reportModal.style.display =
            "none";
    }
};


function applyReportFilters() {

    const search =
        (searchInput?.value || "")
            .toLowerCase();

    const reportType =
        (reportTypeFilter?.value || "")
            .toLowerCase();

    const rows =
        document.querySelectorAll(
            "#reportTable tbody tr.report-row"
        );

    rows.forEach(function (row) {

        const typeValue =
            row.cells[0]
                .textContent
                .toLowerCase();

        const description =
            row.cells[1]
                .textContent
                .toLowerCase();

        const matchesSearch =
            typeValue.includes(search) ||
            description.includes(search);

        const matchesType =
            reportType === "" ||
            typeValue === reportType;

        row.style.display =
            (
                matchesSearch &&
                matchesType
            )
                ? ""
                : "none";
    });
}


if (searchInput) {
    searchInput.addEventListener(
        "keyup",
        applyReportFilters
    );
}


if (reportTypeFilter) {
    reportTypeFilter.addEventListener(
        "change",
        applyReportFilters
    );
}