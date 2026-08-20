<?php

require_once "../../auth/check_auth.php";

$search =
    trim($_GET['search'] ?? '');

$reportType =
    trim($_GET['report_type'] ?? '');

$query = http_build_query(
    array_filter(
        [
            'search' => $search,
            'report_type' => $reportType
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