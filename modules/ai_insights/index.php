<?php

require_once '../../auth/check_auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/ai_v2_recommendations.php';

$analysisDate = getAiInsightToday();
$evidence = loadAiV2Evidence(getDbConnection(), $analysisDate);
$assetAnalyses = buildAiV2Portfolio($evidence, $analysisDate);
$portfolio = summarizeAiV2Portfolio($assetAnalyses);
$commonConcernPresentation = getAiConcernPresentation(
    $portfolio['most_common_concern'],
    ['evidence_summary' => ['condition_code' => 'normal']]
);
$analysisDateLabel = (new DateTimeImmutable(
    $analysisDate,
    new DateTimeZone('Asia/Manila')
))->format('F j, Y');

include '../../includes/header.php';
?>

<div class="layout">
<?php include '../../includes/sidebar.php'; ?>

<main class="main-content">

<div class="ai-page-heading">
    <div>
        <p class="section-heading">Explainable Decision Support</p>
        <h1>AI-Assisted Asset Insights</h1>
        <p class="ai-analysis-date">
            Analysis Date: <?= htmlspecialchars($analysisDateLabel) ?>
            <span aria-hidden="true">&bull;</span>
            Score: <?= htmlspecialchars(AI_INSIGHT_RULE_VERSION) ?>
            <span aria-hidden="true">&bull;</span>
            Interpretation: <?= htmlspecialchars(AI_V2_INTERPRETATION_VERSION) ?>
        </p>
    </div>
</div>

<div class="ai-advisory-notice" role="note">
    <i class="fas fa-circle-info" aria-hidden="true"></i>
    <p>
        Based on the available records, PCMS highlights Assets that may need
        attention and explains why. These suggestions do not change system
        records; every final decision remains with an authorized person.
    </p>
</div>

<section class="ai-summary-grid ai-summary-grid--portfolio" aria-label="AI portfolio summary">
<?php
$summaryCards = [
    ['total', 'Assets Analyzed', $portfolio['total'], 'fa-boxes-stacked'],
    ['critical', 'Critical', $portfolio['levels']['Critical'], 'fa-triangle-exclamation'],
    ['high', 'High', $portfolio['levels']['High'], 'fa-circle-exclamation'],
    ['moderate', 'Moderate', $portfolio['levels']['Moderate'], 'fa-magnifying-glass'],
    ['low', 'Low', $portfolio['levels']['Low'], 'fa-circle-check'],
    ['strong', 'Complete History', $portfolio['coverage']['Strong'], 'fa-layer-group'],
    ['limited', 'Limited History', $portfolio['coverage']['Limited'], 'fa-file-circle-question'],
    ['recurring', 'Repeated Maintenance', $portfolio['recurring'], 'fa-rotate'],
];
?>
<?php foreach ($summaryCards as [$tone, $label, $value, $icon]): ?>
<article class="ai-summary-card" data-level="<?= htmlspecialchars($tone) ?>">
    <div class="ai-summary-icon" aria-hidden="true"><i class="fas <?= htmlspecialchars($icon) ?>"></i></div>
    <div><strong><?= (int) $value ?></strong><span><?= htmlspecialchars($label) ?></span></div>
</article>
<?php endforeach; ?>
</section>

<section class="ai-portfolio-context" aria-label="Portfolio interpretation">
    <div>
        <span>What is drawing the most attention?</span>
        <strong><?= htmlspecialchars($commonConcernPresentation['label']) ?></strong>
        <small><?= htmlspecialchars($commonConcernPresentation['explanation']) ?></small>
    </div>
    <div>
        <span>Highest attention right now</span>
        <strong>
            <?= $portfolio['highest']
                ? htmlspecialchars($portfolio['highest']['asset_id'] . ' — ' .
                    $portfolio['highest']['score'] . ' / 100')
                : 'No Assets analyzed' ?>
        </strong>
    </div>
</section>

<div class="search-toolbar ai-insight-toolbar">
    <input type="search" id="aiInsightSearch"
        placeholder="Search Asset, custodian, status, or reason..."
        aria-label="Search analyzed Assets" autocomplete="off">
    <select id="aiInsightLevelFilter" aria-label="Filter by attention level">
        <option value="">All Attention Levels</option>
        <option value="critical">Critical</option><option value="high">High</option>
        <option value="moderate">Moderate</option><option value="low">Low</option>
    </select>
    <select id="aiInsightStatusFilter" aria-label="Filter by current Asset status">
        <option value="">All Asset Statuses</option>
        <?php foreach (['Available', 'Assigned', 'Under Maintenance', 'Lost'] as $status): ?>
        <option value="<?= htmlspecialchars(strtolower(str_replace(' ', '-', $status))) ?>"><?= htmlspecialchars($status) ?></option>
        <?php endforeach; ?>
    </select>
    <select id="aiInsightConcernFilter" aria-label="Filter by main reason for attention">
        <option value="">All Reasons for Attention</option>
        <?php foreach (['Audit / Condition', 'Maintenance', 'Recurring Maintenance', 'Asset Status', 'Asset Age', 'Multiple Concerns', 'No Significant Concern'] as $concern): ?>
        <?php $concernPresentation = getAiConcernPresentation($concern, ['evidence_summary' => ['condition_code' => 'normal']]); ?>
        <option value="<?= htmlspecialchars(strtolower(str_replace([' ', '/'], ['-', ''], $concern))) ?>"><?= htmlspecialchars($concernPresentation['label']) ?></option>
        <?php endforeach; ?>
    </select>
    <select id="aiInsightCoverageFilter" aria-label="Filter by available Asset history">
        <option value="">All Available History</option>
        <option value="strong">Good amount of information</option>
        <option value="moderate">Some useful information</option>
        <option value="limited">Only a small amount of history</option>
    </select>
    <select id="aiInsightSort" aria-label="Sort analyzed Assets">
        <option value="score">Highest Attention</option>
        <option value="recent">Most Recent Evidence</option>
        <option value="asset">Asset ID</option>
    </select>
</div>

<div class="asset-filter-summary ai-filter-summary">
    <span id="aiInsightResultCount">Showing <?= count($assetAnalyses) ?> <?= count($assetAnalyses) === 1 ? 'Asset' : 'Assets' ?></span>
    <button type="button" class="btn btn-outline" id="clearAiInsightFilters">Clear Filters</button>
</div>

<div class="ai-comparison-bar" id="aiComparisonBar" hidden>
    <span id="aiComparisonCount">Select exactly two Assets to compare.</span>
    <button type="button" class="btn btn-primary" id="compareAiAssets" disabled>Compare Selected</button>
</div>

<div class="pcms-table-scroll pcms-card-table-shell" role="region" aria-label="AI Asset attention records" tabindex="0">
<table class="asset-table ai-insight-table pcms-mobile-card-table" id="aiInsightTable">
<thead><tr>
    <th><span class="sr-only">Compare</span></th><th>Asset</th><th>Status</th>
    <th>Score</th><th>Level</th><th>Main Reason</th>
    <th>Available History</th><th>Maintenance History</th><th>Action</th>
</tr></thead>
<tbody id="aiInsightTableBody">

<?php foreach ($assetAnalyses as $analysis): ?>
<?php
$analysisPayload = htmlspecialchars(json_encode(
    $analysis,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT |
    JSON_INVALID_UTF8_SUBSTITUTE
), ENT_QUOTES, 'UTF-8');
$assetLabel = trim(implode(' ', array_filter([$analysis['brand'] ?? null, $analysis['model'] ?? null])));
$levelKey = strtolower($analysis['level']);
$statusKey = strtolower(str_replace(' ', '-', $analysis['current_status']));
$concernKey = strtolower(str_replace([' ', '/'], ['-', ''], $analysis['primary_concern']));
$coverageKey = strtolower($analysis['evidence_coverage']['label']);
?>
<tr class="ai-insight-row"
    data-asset-id="<?= htmlspecialchars($analysis['asset_id']) ?>"
    data-level="<?= htmlspecialchars($levelKey) ?>"
    data-status="<?= htmlspecialchars($statusKey) ?>"
    data-concern="<?= htmlspecialchars($concernKey) ?>"
    data-coverage="<?= htmlspecialchars($coverageKey) ?>"
    data-score="<?= (int) $analysis['score'] ?>"
    data-recent="<?= htmlspecialchars($analysis['most_recent_concern_date']) ?>"
    data-search="<?= htmlspecialchars(strtolower(implode(' ', [
        $analysis['asset_id'], $analysis['asset_name'], $analysis['brand'] ?? '',
        $analysis['model'] ?? '', $analysis['custodian'] ?? '',
        $analysis['current_status'], $analysis['primary_concern'],
        $analysis['maintenance_pattern']['classification'],
    ]))) ?>"
    data-record="<?= $analysisPayload ?>">
    <td><input type="checkbox" class="ai-compare-checkbox" data-ai-compare aria-label="Compare <?= htmlspecialchars($analysis['asset_id']) ?>"></td>
    <td><strong><?= htmlspecialchars($analysis['asset_id']) ?> — <?= htmlspecialchars($analysis['asset_name']) ?></strong>
        <small><?= htmlspecialchars($assetLabel !== '' ? $assetLabel : ($analysis['custodian'] ?: 'Not assigned')) ?></small></td>
    <td><span class="pcms-status-badge" data-status="<?= htmlspecialchars($statusKey) ?>"><?= htmlspecialchars($analysis['current_status']) ?></span></td>
    <td><strong class="ai-score-value"><?= (int) $analysis['score'] ?> / 100</strong></td>
    <td><span class="ai-level-badge" data-level="<?= htmlspecialchars($levelKey) ?>"><?= htmlspecialchars($analysis['level']) ?></span></td>
    <td><?= htmlspecialchars($analysis['presentation']['concern']['label']) ?></td>
    <td><span class="ai-coverage-badge" data-coverage="<?= htmlspecialchars($coverageKey) ?>"><?= htmlspecialchars($analysis['presentation']['coverage']['label']) ?></span></td>
    <td><?= htmlspecialchars($analysis['presentation']['maintenance_label']) ?></td>
    <td><button type="button" class="btn btn-primary" data-ai-analysis>View Analysis</button></td>
</tr>
<?php endforeach; ?>

<tr id="aiInsightEmptyState" <?= empty($assetAnalyses) ? '' : 'hidden' ?>>
    <td colspan="9" class="ai-empty-state">No Assets match the current filters.</td>
</tr>
</tbody>
</table>
</div>

<?php include 'analysis_modals.php'; ?>

</main>
</div>

<script src="<?= BASE_URL ?>assets/js/ai_insights.js?v=<?= filemtime(__DIR__ . '/../../assets/js/ai_insights.js') ?>"></script>
<?php include '../../includes/footer.php'; ?>
