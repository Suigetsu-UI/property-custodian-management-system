<?php

require_once "../../auth/check_auth.php";

$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$condition = trim($_GET['condition'] ?? '');

$query = http_build_query(
    array_filter(
        [
            'search' => $search,
            'category' => $category,
            'condition' => $condition
        ],
        static function ($value) {
            return $value !== '';
        }
    )
);

header(
    "Location: index.php" .
    ($query !== '' ? '?' . $query : '')
);

exit;