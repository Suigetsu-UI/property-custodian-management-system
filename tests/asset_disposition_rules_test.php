<?php

require_once __DIR__ . '/../services/property_core/src/PropertyCoreDomainException.php';
require_once __DIR__ . '/../services/property_core/src/AssetDispositionRules.php';

$testsRun = 0;
function assertDispositionRule(bool $condition, string $message): void
{
    global $testsRun;
    $testsRun++;
    if (!$condition) {
        throw new RuntimeException('FAILED: ' . $message);
    }
}

$asset = ['status' => 'Available'];
$verifiedAudit = ['result' => 'Verified'];
assertDispositionRule(
    AssetDispositionRules::requestBlockers($asset, false) === [],
    'Review requests must not require final-sale eligibility.'
);
assertDispositionRule(
    AssetDispositionRules::approvalBlockers($asset, false, false, $verifiedAudit) === [],
    'Available Assets with completed Verified Audit evidence may pass readiness checks.'
);
assertDispositionRule(
    count(AssetDispositionRules::approvalBlockers(['status' => 'Assigned'], true, true, null)) === 4,
    'Current Asset, Maintenance, Audit, and condition blockers must be reported together.'
);
assertDispositionRule(
    AssetDispositionRules::requestBlockers(['status' => 'Sold'], false) !== [],
    'Sold Assets must never receive another disposition request.'
);
assertDispositionRule(
    AssetDispositionRules::approvalBlockers($asset, false, false, ['result' => 'Damaged']) !== [],
    'Damaged Audit evidence must block approval.'
);

AssetDispositionRules::assertTransition('Pending Institutional Approval', 'approve');
AssetDispositionRules::assertTransition('Approved for Sale/Bidding', 'complete');
assertDispositionRule(true, 'Valid disposition transitions must pass.');

try {
    AssetDispositionRules::assertTransition('Sold', 'cancel');
    assertDispositionRule(false, 'Sold must be terminal.');
} catch (PropertyCoreDomainException $error) {
    assertDispositionRule($error->getDomainCode() === 'INVALID_DISPOSITION_TRANSITION', 'Sold must reject every later transition.');
}

$approval = AssetDispositionRules::approvalInput([
    'institutional_approval_reference' => 'BOARD-2026-001',
    'institutional_approver_name' => 'Authorized Official',
    'institutional_approver_position' => 'School Director',
    'institutional_approval_date' => '2026-09-08',
], '2026-09-08');
assertDispositionRule($approval['institutional_approval_reference'] === 'BOARD-2026-001', 'External approval attribution must be preserved separately.');

echo "Asset disposition rules passed: {$testsRun}" . PHP_EOL;
