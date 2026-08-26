<?php

require_once '../../auth/check_auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/ai_insight_functions.php';

$analysisDate = getAiInsightToday();
$pdo = getDbConnection();
$evidence = loadAiInsightEvidence($pdo, $analysisDate);
$assetAnalyses = buildAiInsightAnalyses(
    $evidence,
    $analysisDate
);
$levelSummary = summarizeAiInsightLevels($assetAnalyses);
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
        <h1>AI-Assisted Asset Attention</h1>
        <p class="ai-analysis-date">
            Analysis Date: <?= htmlspecialchars($analysisDateLabel) ?>
            <span aria-hidden="true">&bull;</span>
            Rule Version: <?= htmlspecialchars(AI_INSIGHT_RULE_VERSION) ?>
        </p>
    </div>
</div>

<div class="ai-advisory-notice" role="note">
    <i class="fas fa-circle-info" aria-hidden="true"></i>
    <p>
        Recommendations are generated from recorded Asset, Maintenance,
        Audit, and historical-event information. They are advisory only
        and do not modify system records.
    </p>
</div>

<section class="ai-summary-grid" aria-label="Attention level summary">

<?php foreach (['Critical', 'High', 'Moderate', 'Low'] as $level): ?>

<article
    class="ai-summary-card"
    data-level="<?= strtolower($level) ?>"
>
    <div class="ai-summary-icon" aria-hidden="true">
        <i class="fas <?= match ($level) {
            'Critical' => 'fa-triangle-exclamation',
            'High' => 'fa-circle-exclamation',
            'Moderate' => 'fa-magnifying-glass',
            default => 'fa-circle-check',
        } ?>"></i>
    </div>
    <div>
        <strong><?= (int) $levelSummary[$level] ?></strong>
        <span><?= htmlspecialchars($level) ?> Attention</span>
    </div>
</article>

<?php endforeach; ?>

</section>

<div class="search-toolbar ai-insight-toolbar">
    <input
        type="search"
        id="aiInsightSearch"
        placeholder="Search by Asset ID, Asset, Custodian, or status..."
        aria-label="Search analyzed Assets"
        autocomplete="off"
    >

    <select
        id="aiInsightLevelFilter"
        aria-label="Filter by attention level"
    >
        <option value="">All Attention Levels</option>
        <option value="critical">Critical</option>
        <option value="high">High</option>
        <option value="moderate">Moderate</option>
        <option value="low">Low</option>
    </select>
</div>

<div class="asset-filter-summary ai-filter-summary">
    <span id="aiInsightResultCount">
        Showing <?= count($assetAnalyses) ?>
        <?= count($assetAnalyses) === 1 ? 'Asset' : 'Assets' ?>
    </span>
    <button
        type="button"
        class="btn btn-outline"
        id="clearAiInsightFilters"
    >
        Clear Filters
    </button>
</div>

<div class="pcms-table-scroll">

<table class="asset-table ai-insight-table" id="aiInsightTable">
<thead>
<tr>
    <th>Asset ID</th>
    <th>Asset</th>
    <th>Custodian</th>
    <th>Current Status</th>
    <th>Attention Score</th>
    <th>Level</th>
    <th>Primary Factor</th>
    <th>Action</th>
</tr>
</thead>
<tbody>

<?php foreach ($assetAnalyses as $analysis): ?>

<?php
$analysisPayload = htmlspecialchars(
    json_encode(
        $analysis,
        JSON_HEX_TAG |
        JSON_HEX_AMP |
        JSON_HEX_APOS |
        JSON_HEX_QUOT |
        JSON_INVALID_UTF8_SUBSTITUTE
    ),
    ENT_QUOTES,
    'UTF-8'
);
$assetLabel = trim(
    implode(' ', array_filter([
        $analysis['brand'] ?? null,
        $analysis['model'] ?? null,
    ]))
);
?>

<tr
    class="ai-insight-row"
    data-level="<?= strtolower(htmlspecialchars($analysis['level'])) ?>"
    data-search="<?= htmlspecialchars(strtolower(implode(' ', [
        $analysis['asset_id'],
        $analysis['asset_name'],
        $analysis['brand'] ?? '',
        $analysis['model'] ?? '',
        $analysis['custodian'] ?? '',
        $analysis['current_status'],
        $analysis['primary_factor'],
    ]))) ?>"
    data-record="<?= $analysisPayload ?>"
>
    <td><?= htmlspecialchars($analysis['asset_id']) ?></td>
    <td>
        <strong><?= htmlspecialchars($analysis['asset_name']) ?></strong>
        <?php if ($assetLabel !== ''): ?>
        <small><?= htmlspecialchars($assetLabel) ?></small>
        <?php endif; ?>
    </td>
    <td>
        <?= !empty($analysis['custodian'])
            ? htmlspecialchars($analysis['custodian'])
            : 'Not Assigned' ?>
    </td>
    <td>
        <span
            class="pcms-status-badge"
            data-status="<?= strtolower(str_replace(' ', '-', htmlspecialchars($analysis['current_status']))) ?>"
        >
            <?= htmlspecialchars($analysis['current_status']) ?>
        </span>
    </td>
    <td>
        <strong class="ai-score-value">
            <?= (int) $analysis['score'] ?> / 100
        </strong>
    </td>
    <td>
        <span
            class="ai-level-badge"
            data-level="<?= strtolower(htmlspecialchars($analysis['level'])) ?>"
        >
            <?= htmlspecialchars($analysis['level']) ?>
        </span>
    </td>
    <td><?= htmlspecialchars($analysis['primary_factor']) ?></td>
    <td>
        <button
            type="button"
            class="btn btn-primary"
            data-ai-analysis
        >
            View Analysis
        </button>
    </td>
</tr>

<?php endforeach; ?>

<tr id="aiInsightEmptyState" <?= empty($assetAnalyses) ? '' : 'hidden' ?>>
    <td colspan="8" class="ai-empty-state">
        No Assets match the current search and attention-level filter.
    </td>
</tr>

</tbody>
</table>

</div>

<?php include 'analysis_modals.php'; ?>

</main>
</div>

<script src="<?= BASE_URL ?>assets/js/ai_insights.js?v=<?= filemtime(__DIR__ . '/../../assets/js/ai_insights.js') ?>"></script>

<?php include '../../includes/footer.php'; ?>

