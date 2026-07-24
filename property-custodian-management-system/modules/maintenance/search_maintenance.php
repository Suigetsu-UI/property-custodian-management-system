<?php

require_once "../../auth/check_auth.php";

$keyword = strtolower(trim($_GET['search'] ?? ''));
$status = $_GET['status'] ?? '';

$filtered = [];

foreach ($_SESSION['maintenance'] ?? [] as $key => $item) {

    $matchKeyword =
        $keyword === '' ||

        strpos(strtolower($item['maintenance_id']), $keyword) !== false ||

        strpos(strtolower($item['asset_name']), $keyword) !== false ||

        strpos(strtolower($item['maintenance_type']), $keyword) !== false;

    $matchStatus =
        $status === '' ||
        $item['status'] === $status;

    if ($matchKeyword && $matchStatus) {

        $filtered[$key] = $item;

    }

}

$_SESSION['filtered_maintenance'] = $filtered;

header("Location: index.php");
exit;