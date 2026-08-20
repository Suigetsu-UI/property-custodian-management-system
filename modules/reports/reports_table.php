<?php

require_once __DIR__ . "/../../includes/report_functions.php";

$search =
    strtolower(
        trim($_GET['search'] ?? '')
    );

$selectedType =
    trim($_GET['report_type'] ?? '');

$descriptions = [
    'Asset Report' =>
        'Current Asset Registry status, categories, locations, and custodians.',

    'Inventory Report' =>
        'Current stock, low-stock items, out-of-stock items, and delivered Procurement activity.',

    'Maintenance Report' =>
        'Current Maintenance workload and recent Maintenance activity.',

    'Procurement Report' =>
        'Procurement request totals, statuses, suppliers, and recent activity.',

    'Audit Report' =>
        'Audit totals, completion status, and recent Audit findings.',

    'Full System Report' =>
        'Combined operational summary across all Property Custodian modules.'
];

$reportRows = [];

foreach (getAllowedReportTypes() as $reportType) {

    if (
        $selectedType !== '' &&
        $reportType !== $selectedType
    ) {
        continue;
    }

    $description =
        $descriptions[$reportType] ?? '';

    if (
        $search !== '' &&
        strpos(
            strtolower(
                $reportType . ' ' . $description
            ),
            $search
        ) === false
    ) {
        continue;
    }

    $reportRows[] = [
        'report_type' => $reportType,
        'description' => $description
    ];
}

?>

<table
    class="asset-table"
    id="reportTable"
>

<thead>

<tr>

<th>Report Type</th>
<th>Description</th>
<th>Actions</th>

</tr>

</thead>

<tbody>

<?php foreach ($reportRows as $report): ?>

<tr class="report-row">

<td>
<?= htmlspecialchars($report['report_type']) ?>
</td>

<td>
<?= htmlspecialchars($report['description']) ?>
</td>

<td>

<?php

$query = http_build_query([
    'report_type' =>
        $report['report_type']
]);

?>

<a
    href="view_report.php?<?= htmlspecialchars($query) ?>"
    class="btn btn-primary"
>
View
</a>

<a
    href="download_report.php?<?= htmlspecialchars($query) ?>"
    class="btn btn-warning"
>
Download
</a>

</td>

</tr>

<?php endforeach; ?>

<?php if (empty($reportRows)): ?>

<tr>

<td
    colspan="3"
    style="text-align:center; padding:40px;"
>
No reports found.
</td>

</tr>

<?php endif; ?>

</tbody>

</table>