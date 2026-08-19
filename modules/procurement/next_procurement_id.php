<?php

require_once "../../auth/check_auth.php";
require_once __DIR__ . "/../../includes/asset_functions.php";
require_once __DIR__ . "/../../includes/database.php";

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'error' => 'Method not allowed.',
    ]);

    exit;
}

try {
    $pdo = getDbConnection();

    $procurementId = nextBusinessId(
        $pdo,
        'procurement',
        'PRC'
    );

    /*
     * This is temporary control state only.
     * Procurement business records themselves remain PostgreSQL-backed.
     *
     * save_procurement.php will require the submitted ID to have been
     * issued to this authenticated PHP session before accepting a create.
     */
    if (!isset($_SESSION['pending_procurement_ids'])) {
        $_SESSION['pending_procurement_ids'] = [];
    }

    $_SESSION['pending_procurement_ids'][$procurementId] = true;

    echo json_encode([
        'procurement_id' => $procurementId,
    ]);

} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'error' => 'Could not generate a Procurement ID.',
    ]);
}

exit;