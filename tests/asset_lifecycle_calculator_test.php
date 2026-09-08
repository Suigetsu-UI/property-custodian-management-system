<?php

require_once __DIR__ . '/../services/property_core/src/AssetLifecycleCalculator.php';

$testsRun = 0;

function assertAssetLifecycle(bool $condition, string $message): void
{
    global $testsRun;
    $testsRun++;

    if (!$condition) {
        throw new RuntimeException('FAILED: ' . $message);
    }
}

$asOf = new DateTimeImmutable('2026-09-08', new DateTimeZone('Asia/Manila'));

assertAssetLifecycle(
    AssetLifecycleCalculator::ageInMonths('2022-02-08', $asOf) === 55,
    'Asset age must use complete calendar months.'
);
assertAssetLifecycle(
    AssetLifecycleCalculator::ageInMonths(null, $asOf) === null,
    'Missing acquisition dates must remain unknown.'
);
assertAssetLifecycle(
    AssetLifecycleCalculator::classify(47, 60, 80) === 'Active',
    'Assets below the configured threshold must remain Active.'
);
assertAssetLifecycle(
    AssetLifecycleCalculator::classify(48, 60, 80) === 'Aging',
    'Assets at the configured threshold must enter Aging.'
);
assertAssetLifecycle(
    AssetLifecycleCalculator::classify(60, 60, 80) === 'Retirement Review',
    'Assets at useful life must enter Retirement Review.'
);
assertAssetLifecycle(
    AssetLifecycleCalculator::classify(48, null, 80) === 'Useful Life Not Configured',
    'Unconfigured categories must never receive an assumed classification.'
);
assertAssetLifecycle(
    AssetLifecycleCalculator::classify(null, 60, 80) === 'Age Not Recorded',
    'Assets without an acquisition date must be identified explicitly.'
);
assertAssetLifecycle(
    AssetLifecycleCalculator::durationLabel(55) === '4y 7m',
    'Duration labels must remain compact and factual.'
);

echo "Asset lifecycle calculator tests passed: {$testsRun}" . PHP_EOL;
