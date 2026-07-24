<?php

require_once "../../auth/check_auth.php";

$keyword = strtolower(trim($_GET['search'] ?? ''));
$reportType = $_GET['report_type'] ?? '';
$status = $_GET['status'] ?? '';

$filtered = [];

foreach ($_SESSION['reports'] ?? [] as $item) {

    $matchKeyword =
        $keyword === '' ||
        strpos(strtolower($item['report_id']), $keyword) !== false ||
        strpos(strtolower($item['report_name']), $keyword) !== false ||
        strpos(strtolower($item['report_type']), $keyword) !== false ||
        strpos(strtolower($item['generated_by']), $keyword) !== false ||
        strpos(strtolower($item['status']), $keyword) !== false;

    $matchType = $reportType === '' || $item['report_type'] === $reportType;
    $matchStatus = $status === '' || $item['status'] === $status;

    if ($matchKeyword && $matchType && $matchStatus) {
        $filtered[] = $item;
    }
}

$_SESSION['filtered_reports'] = $filtered;

header("Location: index.php");
exit;
