<?php

require_once __DIR__ . "/../../auth/check_auth.php";
require_once __DIR__ . "/../../includes/report_functions.php";

$search =
    strtolower(
        trim($_GET['search'] ?? '')
    );

$selectedType =
    trim($_GET['report_type'] ?? '');

$descriptions = [
    'Asset Report' =>
        'Asset Registry events that occurred inside the selected reporting period.',

    'Inventory Report' =>
        'Inventory additions, adjustments, and movements inside the selected reporting period.',

    'Maintenance Report' =>
        'Maintenance lifecycle events that occurred inside the selected reporting period.',

    'Procurement Report' =>
        'Procurement events that occurred inside the selected reporting period.',

    'Audit Report' =>
        'Audit lifecycle and finding events inside the selected reporting period.',

    'Full System Report' =>
        'Combined dated activity from all supported modules inside the selected reporting period.'
];

$reportRows = [];
$defaultDateRange = getDefaultReportDateRange();

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

<div class="pcms-table-scroll pcms-card-table-shell" role="region" aria-label="Available reports" tabindex="0">
<table
    class="asset-table pcms-mobile-card-table"
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
        $report['report_type'],
    'report_period' => $defaultDateRange['report_period'],
    'start_date' => $defaultDateRange['start_date'],
    'end_date' => $defaultDateRange['end_date'],
]);

?>

<a
    href="view_report.php?<?= htmlspecialchars($query) ?>"
    class="btn btn-primary"
    data-report-action="view"
    data-report-type="<?= htmlspecialchars($report['report_type']) ?>"
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
</div>
