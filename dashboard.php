<?php

require_once __DIR__ . '/auth/check_auth.php';
require_once __DIR__ . '/includes/dashboard_functions.php';

$dashboard = buildDashboardData(getDbConnection(), isAdministrator());
$dashboardDate = (new DateTimeImmutable(
    $dashboard['today'],
    new DateTimeZone(DASHBOARD_TIMEZONE)
))->format('F j, Y');
$dashboardRole = currentUserRoleLabel();
$highestAttention = $dashboard['ai']['highest'];
$pageStyles = [
    BASE_URL . 'assets/css/dashboard.css?v=' .
        filemtime(__DIR__ . '/assets/css/dashboard.css'),
];

include __DIR__ . '/includes/header.php';

?>

<div class="layout">

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<main class="main-content dashboard-page">

<header class="dashboard-heading">
    <div>
        <p class="dashboard-eyebrow">Executive Property Overview</p>
        <h1>Dashboard</h1>
        <p class="dashboard-subtitle">
            <?= htmlspecialchars($dashboardDate) ?>
            <span aria-hidden="true">&bull;</span>
            <?= htmlspecialchars($dashboardRole) ?>
        </p>
    </div>
    <a class="btn btn-outline" href="<?= BASE_URL ?>modules/reports/index.php">
        <i class="fas fa-file-lines" aria-hidden="true"></i>
        View Reports
    </a>
</header>

<section class="dashboard-kpi-grid" aria-label="Executive property indicators">
<?php
$kpiCards = [
    ['total', 'Total Assets', 'fa-boxes-stacked', 'neutral'],
    ['available', 'Available Assets', 'fa-circle-check', 'success'],
    ['assigned', 'Assigned Assets', 'fa-user-check', 'info'],
    ['under_maintenance', 'Under Maintenance', 'fa-wrench', 'warning'],
    ['lost', 'Lost Assets', 'fa-location-crosshairs', 'danger'],
    ['needs_attention', 'Needs Attention', 'fa-triangle-exclamation', 'accent'],
];
?>
<?php foreach ($kpiCards as [$key, $label, $icon, $tone]): ?>
<?php if ($key === 'needs_attention'): ?>
<a class="dashboard-kpi dashboard-kpi-link" data-tone="<?= htmlspecialchars($tone) ?>" href="<?= BASE_URL ?>modules/ai_insights/index.php?level=attention">
    <span class="dashboard-kpi-icon" aria-hidden="true">
        <i class="fas <?= htmlspecialchars($icon) ?>"></i>
    </span>
    <div>
        <strong><?= (int) $dashboard['kpis'][$key] ?></strong>
        <span><?= htmlspecialchars($label) ?></span>
    </div>
</a>
<?php else: ?>
<article class="dashboard-kpi" data-tone="<?= htmlspecialchars($tone) ?>">
    <span class="dashboard-kpi-icon" aria-hidden="true">
        <i class="fas <?= htmlspecialchars($icon) ?>"></i>
    </span>
    <div>
        <strong><?= (int) $dashboard['kpis'][$key] ?></strong>
        <span><?= htmlspecialchars($label) ?></span>
    </div>
</article>
<?php endif; ?>
<?php endforeach; ?>
</section>

<section class="dashboard-operational-grid" aria-label="Operational indicators">
<?php
$operationalCards = [
    ['open_procurement', 'Open Procurement', 'Pending + Approved', 'fa-cart-shopping'],
    ['active_maintenance', 'Active Maintenance', 'Scheduled + In Progress', 'fa-screwdriver-wrench'],
    ['pending_audits', 'Pending Audits', 'Scheduled + Ongoing', 'fa-clipboard-list'],
    ['inventory_units', 'Inventory Units', 'Current stock quantity', 'fa-warehouse'],
];
?>
<?php foreach ($operationalCards as [$key, $label, $detail, $icon]): ?>
<article class="dashboard-operational-card">
    <i class="fas <?= htmlspecialchars($icon) ?>" aria-hidden="true"></i>
    <div>
        <strong><?= (int) $dashboard['operational'][$key] ?></strong>
        <span><?= htmlspecialchars($label) ?></span>
        <small><?= htmlspecialchars($detail) ?></small>
    </div>
</article>
<?php endforeach; ?>
</section>

<div class="dashboard-section-grid">
<section class="dashboard-panel" aria-labelledby="attentionHeading">
    <header class="dashboard-panel-header">
        <div>
            <p class="dashboard-panel-eyebrow">Priority Queue</p>
            <h2 id="attentionHeading">Requires Attention</h2>
        </div>
        <span class="dashboard-count-badge"><?= count($dashboard['attention']) ?></span>
    </header>

    <?php if (empty($dashboard['attention'])): ?>
    <div class="dashboard-empty-state">
        <i class="fas fa-circle-check" aria-hidden="true"></i>
        <p>No urgent property issues currently require attention.</p>
    </div>
    <?php else: ?>
    <div class="dashboard-attention-list">
        <?php foreach ($dashboard['attention'] as $item): ?>
        <a class="dashboard-attention-item" href="<?= htmlspecialchars($item['href']) ?>">
            <span class="dashboard-attention-marker" data-type="<?= htmlspecialchars($item['type']) ?>" aria-hidden="true"></span>
            <span class="dashboard-attention-copy">
                <strong><?= htmlspecialchars($item['title']) ?></strong>
                <small><?= htmlspecialchars($item['detail']) ?></small>
            </span>
            <?php if (!empty($item['date'])): ?>
            <time datetime="<?= htmlspecialchars($item['date']) ?>">
                <?= htmlspecialchars(date('M j', strtotime($item['date']))) ?>
            </time>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<section class="dashboard-panel dashboard-ai-panel" aria-labelledby="aiSummaryHeading">
    <header class="dashboard-panel-header">
        <div>
            <p class="dashboard-panel-eyebrow">Explainable Decision Support</p>
            <h2 id="aiSummaryHeading">AI Asset Attention</h2>
        </div>
        <i class="fas fa-brain dashboard-panel-icon" aria-hidden="true"></i>
    </header>

    <div class="dashboard-ai-levels" aria-label="AI attention levels">
        <?php foreach (['critical', 'high', 'moderate', 'low'] as $level): ?>
        <div data-level="<?= htmlspecialchars($level) ?>">
            <strong><?= (int) $dashboard['ai'][$level] ?></strong>
            <span><?= htmlspecialchars(ucfirst($level)) ?></span>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($highestAttention): ?>
    <a class="dashboard-ai-highest dashboard-ai-highest-link" href="<?= BASE_URL ?>modules/ai_insights/index.php?asset=<?= rawurlencode($highestAttention['asset_id']) ?>">
        <span>Highest Attention</span>
        <strong><?= htmlspecialchars($highestAttention['asset_id']) ?></strong>
        <p>
            <?= (int) $highestAttention['score'] ?> / 100
            <span aria-hidden="true">&mdash;</span>
            <?= htmlspecialchars($highestAttention['level']) ?>
        </p>
        <small><?= htmlspecialchars(dashboardAiFactorLabel($highestAttention['primary_factor'])) ?></small>
    </a>
    <?php else: ?>
    <div class="dashboard-empty-state compact">
        <p>No registered Assets are available for analysis.</p>
    </div>
    <?php endif; ?>

    <a class="dashboard-panel-link" href="<?= BASE_URL ?>modules/ai_insights/index.php?level=attention">
        Review Attention Queue <i class="fas fa-arrow-right" aria-hidden="true"></i>
    </a>
</section>
</div>

<div class="dashboard-section-grid">
<section class="dashboard-panel" aria-labelledby="assetStatusHeading">
    <header class="dashboard-panel-header">
        <div>
            <p class="dashboard-panel-eyebrow">Current State</p>
            <h2 id="assetStatusHeading">Asset Status</h2>
        </div>
    </header>

    <div class="dashboard-horizontal-chart" data-dashboard-chart="horizontal">
        <?php foreach ($dashboard['asset_status'] as $label => $count): ?>
        <div class="dashboard-horizontal-row">
            <div class="dashboard-chart-label">
                <span><?= htmlspecialchars($label) ?></span>
                <strong><?= (int) $count ?></strong>
            </div>
            <div class="dashboard-chart-track" aria-hidden="true">
                <span class="dashboard-chart-bar" data-value="<?= (int) $count ?>"></span>
            </div>
            <span class="sr-only"><?= htmlspecialchars($label) ?>: <?= (int) $count ?> Assets</span>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="dashboard-panel" aria-labelledby="activityHeading">
    <header class="dashboard-panel-header">
        <div>
            <p class="dashboard-panel-eyebrow">Historical Events Through Today</p>
            <h2 id="activityHeading">Six-Month Property Activity</h2>
        </div>
    </header>

    <div class="dashboard-column-chart" data-dashboard-chart="columns" role="img" aria-label="Property events by month">
        <?php foreach ($dashboard['activity_months'] as $month): ?>
        <div class="dashboard-column-item">
            <strong><?= (int) $month['count'] ?></strong>
            <div class="dashboard-column-track" aria-hidden="true">
                <span class="dashboard-column-bar" data-value="<?= (int) $month['count'] ?>"></span>
            </div>
            <span><?= htmlspecialchars($month['short_label']) ?></span>
            <small class="sr-only"><?= htmlspecialchars($month['label']) ?>: <?= (int) $month['count'] ?> events</small>
        </div>
        <?php endforeach; ?>
    </div>
</section>
</div>

<div class="dashboard-section-grid">
<section class="dashboard-panel" aria-labelledby="workflowHeading">
    <header class="dashboard-panel-header">
        <div>
            <p class="dashboard-panel-eyebrow">Workflow Overview</p>
            <h2 id="workflowHeading">Maintenance &amp; Audit</h2>
        </div>
    </header>

    <div class="dashboard-workflow-chart" data-dashboard-chart="workflow">
        <?php foreach ($dashboard['workflow'] as $workflow): ?>
        <div class="dashboard-workflow-group">
            <h3><?= htmlspecialchars($workflow['label']) ?></h3>
            <?php
            $workflowRows = [
                ['Scheduled', $workflow['scheduled']],
                [$workflow['active_label'], $workflow['active']],
                ['Completed', $workflow['completed']],
            ];
            ?>
            <?php foreach ($workflowRows as [$label, $count]): ?>
            <div class="dashboard-workflow-row">
                <span><?= htmlspecialchars($label) ?></span>
                <div class="dashboard-chart-track" aria-hidden="true">
                    <span class="dashboard-chart-bar" data-value="<?= (int) $count ?>"></span>
                </div>
                <strong><?= (int) $count ?></strong>
                <span class="sr-only"><?= htmlspecialchars($workflow['label']) ?> <?= htmlspecialchars($label) ?>: <?= (int) $count ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="dashboard-panel" aria-labelledby="recentActivityHeading">
    <header class="dashboard-panel-header">
        <div>
            <p class="dashboard-panel-eyebrow">Latest Recorded History</p>
            <h2 id="recentActivityHeading">Recent Property Activity</h2>
        </div>
    </header>

    <?php if (empty($dashboard['recent_events'])): ?>
    <div class="dashboard-empty-state compact">
        <p>No historical property activity has been recorded yet.</p>
    </div>
    <?php else: ?>
    <div class="dashboard-timeline">
        <?php foreach ($dashboard['recent_events'] as $event): ?>
        <a href="<?= htmlspecialchars($event['href']) ?>" class="dashboard-timeline-item">
            <span class="dashboard-timeline-icon" aria-hidden="true">
                <i class="fas <?= match ($event['module']) {
                    'Procurement' => 'fa-cart-shopping',
                    'Inventory' => 'fa-warehouse',
                    'Asset Registry' => 'fa-boxes-stacked',
                    'Maintenance' => 'fa-wrench',
                    'Audit' => 'fa-clipboard-check',
                    default => 'fa-clock-rotate-left',
                } ?>"></i>
            </span>
            <span class="dashboard-timeline-copy">
                <span>
                    <strong><?= htmlspecialchars($event['business_id']) ?></strong>
                    <small><?= htmlspecialchars($event['module']) ?></small>
                </span>
                <b><?= htmlspecialchars($event['event_type']) ?></b>
                <small><?= htmlspecialchars($event['summary']) ?></small>
            </span>
            <time datetime="<?= htmlspecialchars($event['event_date']) ?>">
                <?= htmlspecialchars(date('M j', strtotime($event['event_date']))) ?>
            </time>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
</div>

<div class="dashboard-section-grid">
<section class="dashboard-panel" aria-labelledby="quickLinksHeading">
    <header class="dashboard-panel-header">
        <div>
            <p class="dashboard-panel-eyebrow">Shortcuts</p>
            <h2 id="quickLinksHeading">Quick Links</h2>
        </div>
    </header>

    <div class="dashboard-quick-links">
        <?php foreach ($dashboard['quick_links'] as $link): ?>
        <a href="<?= htmlspecialchars($link['href']) ?>">
            <i class="fas <?= htmlspecialchars($link['icon']) ?>" aria-hidden="true"></i>
            <span>
                <strong><?= htmlspecialchars($link['label']) ?></strong>
                <small><?= htmlspecialchars($link['description']) ?></small>
            </span>
            <i class="fas fa-chevron-right" aria-hidden="true"></i>
        </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="dashboard-panel dashboard-announcement-panel" aria-labelledby="announcementHeading">
    <header class="dashboard-panel-header">
        <div>
            <p class="dashboard-panel-eyebrow">External School Service</p>
            <h2 id="announcementHeading">School Announcements</h2>
        </div>
        <i class="fas fa-bullhorn dashboard-panel-icon" aria-hidden="true"></i>
    </header>

    <?php if (empty($dashboard['external_announcements'])): ?>
    <div class="dashboard-announcement-placeholder">
        <i class="fas fa-link-slash" aria-hidden="true"></i>
        <strong>Announcement service not connected</strong>
        <p>
            Announcements from the integrated school system will appear
            here when external integration is enabled.
        </p>
    </div>
    <?php else: ?>
    <div class="dashboard-announcement-list">
        <?php foreach ($dashboard['external_announcements'] as $announcement): ?>
        <article>
            <strong><?= htmlspecialchars($announcement['title'] ?? '') ?></strong>
            <p><?= htmlspecialchars($announcement['summary'] ?? '') ?></p>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
</div>

</main>
</div>

<script src="<?= BASE_URL ?>assets/js/dashboard.js?v=<?= filemtime(__DIR__ . '/assets/js/dashboard.js') ?>"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>
