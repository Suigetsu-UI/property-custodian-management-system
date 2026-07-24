<?php

require_once "../../auth/check_auth.php";

$keyword = strtolower(trim($_GET['search'] ?? ''));
$category = $_GET['category'] ?? '';
$condition = $_GET['condition'] ?? '';

$filtered = [];

foreach ($_SESSION['inventory'] ?? [] as $item) {

$matchKeyword =
        $keyword === '' ||

        strpos(strtolower($item['inventory_id']), $keyword) !== false ||

        strpos(strtolower($item['asset_name']), $keyword) !== false ||

        strpos(strtolower($item['category']), $keyword) !== false ||

        strpos(strtolower($item['condition']), $keyword) !== false;
        
    $matchCategory =
        $category === '' ||
        $item['category'] === $category;

    $matchCondition =
        $condition === '' ||
        $item['condition'] === $condition;

    if ($matchKeyword && $matchCategory && $matchCondition) {
        $filtered[] = $item;
    }
}

$_SESSION['filtered_inventory'] = $filtered;

header("Location: index.php");
exit;