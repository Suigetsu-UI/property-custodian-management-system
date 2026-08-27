<?php

require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../includes/access_control.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/ai_v2_scenario.php';

header('Content-Type: application/json; charset=UTF-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['error' => 'Method not allowed.']);
    exit();
}

if (!isValidAccessCsrfToken(getSubmittedAccessCsrfToken())) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid security token. Refresh the page and try again.']);
    exit();
}

$assetId = trim((string) ($_POST['asset_id'] ?? ''));

if (!preg_match('/^AST-[A-Z0-9-]{1,16}$/', $assetId)) {
    http_response_code(422);
    echo json_encode(['error' => 'A valid Asset ID is required.']);
    exit();
}

try {
    $analysisDate = getAiInsightToday();
    $evidence = loadAiV2Evidence(getDbConnection(), $analysisDate);
    $assetEvidence = findAiV2AssetEvidence($evidence, $assetId);

    if ($assetEvidence === null) {
        http_response_code(404);
        echo json_encode(['error' => 'The selected Asset was not found.']);
        exit();
    }

    $result = simulateAiV2Scenario(
        $assetEvidence['asset'],
        $assetEvidence['maintenance'],
        $assetEvidence['audits'],
        $assetEvidence['events'],
        $analysisDate,
        $_POST
    );

    echo json_encode($result, JSON_INVALID_UTF8_SUBSTITUTE);
} catch (InvalidArgumentException $exception) {
    http_response_code(422);
    echo json_encode(['error' => $exception->getMessage()]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'The scenario could not be analyzed. Please try again.']);
}
