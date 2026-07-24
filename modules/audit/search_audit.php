<?php

require_once "../../auth/check_auth.php";

$keyword = strtolower(trim($_GET['search'] ?? ''));
$status = $_GET['status'] ?? '';
$result = $_GET['result'] ?? '';

$filtered = [];

foreach ($_SESSION['audits'] ?? [] as $item) {

    $matchKeyword =
        $keyword === '' ||
        strpos(strtolower($item['audit_id']), $keyword) !== false ||
        strpos(strtolower($item['asset_name']), $keyword) !== false ||
        strpos(strtolower($item['auditor']), $keyword) !== false ||
        strpos(strtolower($item['status']), $keyword) !== false ||
        strpos(strtolower($item['result']), $keyword) !== false;

    $matchStatus = $status === '' || $item['status'] === $status;
    $matchResult = $result === '' || $item['result'] === $result;

    if ($matchKeyword && $matchStatus && $matchResult) {
        $filtered[] = $item;
    }
}

$_SESSION['filtered_audits'] = $filtered;

header("Location: index.php");
exit;
