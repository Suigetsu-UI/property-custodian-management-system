<?php

require_once "../../auth/check_auth.php";

$search =
    trim($_GET['search'] ?? '');

$status =
    trim($_GET['status'] ?? '');

$result =
    trim($_GET['result'] ?? '');

$query = http_build_query(
    array_filter(
        [
            'search' => $search,
            'status' => $status,
            'result' => $result
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