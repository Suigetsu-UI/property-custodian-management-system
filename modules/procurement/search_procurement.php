<?php

require_once "../../auth/check_auth.php";

$search =
    trim($_GET['search'] ?? '');

$status =
    trim($_GET['status'] ?? '');

$query = http_build_query(
    array_filter(
        [
            'search' => $search,
            'status' => $status
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