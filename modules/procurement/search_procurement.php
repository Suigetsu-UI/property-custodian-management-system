<?php

require_once "../../auth/check_auth.php";

$keyword = strtolower(trim($_GET['search'] ?? ''));
$status = $_GET['status'] ?? '';
$supplier = $_GET['supplier'] ?? '';

$filtered = [];

foreach ($_SESSION['procurement'] ?? [] as $item) {

    $matchKeyword =
        $keyword === '' ||
        strpos(strtolower($item['procurement_id']), $keyword) !== false ||
        strpos(strtolower($item['item_name']), $keyword) !== false ||
        strpos(strtolower($item['category']), $keyword) !== false ||
        strpos(strtolower($item['supplier']), $keyword) !== false ||
        strpos(strtolower($item['status']), $keyword) !== false;

    $matchStatus =
        $status === '' ||
        $item['status'] === $status;

    $matchSupplier =
        $supplier === '' ||
        $item['supplier'] === $supplier;

    if ($matchKeyword && $matchStatus && $matchSupplier) {
        $filtered[] = $item;
    }
}

$_SESSION['filtered_procurement'] = $filtered;

header("Location: index.php");
exit;
