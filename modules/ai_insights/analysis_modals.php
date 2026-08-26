<div
    id="aiAnalysisModal"
    class="modal pcms-modal pcms-modal--lg"
    role="dialog"
    aria-modal="true"
    aria-hidden="true"
    aria-labelledby="aiAnalysisTitle"
>
<div class="modal-content pcms-modal-dialog">

<header class="pcms-modal-header">
    <div>
        <span class="pcms-modal-eyebrow">AI Asset Analysis</span>
        <div class="pcms-modal-title-row">
            <h2 id="aiAnalysisTitle">Asset Attention Analysis</h2>
            <span
                id="aiAnalysisLevel"
                class="ai-level-badge"
                data-level="low"
            >Low</span>
        </div>
        <p id="aiAnalysisSubtitle">Recorded evidence and advisory result</p>
    </div>
    <button
        type="button"
        class="close-modal"
        data-modal-close
        aria-label="Close Asset Attention Analysis"
    >
        <span aria-hidden="true">&times;</span>
    </button>
</header>

<div class="pcms-modal-body ai-analysis-body">

<section class="ai-score-overview" aria-label="Attention result">
    <div>
        <span>Attention Score</span>
        <strong id="aiAnalysisScore">0 / 100</strong>
    </div>
    <div>
        <span>Analysis Date</span>
        <strong id="aiAnalysisDate">&mdash;</strong>
    </div>
    <div>
        <span>Rule Version</span>
        <strong id="aiAnalysisRuleVersion">&mdash;</strong>
    </div>
</section>

<section class="pcms-detail-section">
    <h3>Point Breakdown</h3>
    <div class="ai-point-breakdown">
        <div><span>Asset Age</span><strong id="aiPointsAge">+0</strong></div>
        <div><span>Corrective Maintenance</span><strong id="aiPointsMaintenance">+0</strong></div>
        <div><span>Audit / Current Condition</span><strong id="aiPointsCondition">+0</strong></div>
        <div><span>Recurring Attention States</span><strong id="aiPointsRecurring">+0</strong></div>
        <div class="ai-point-total"><span>Base Score</span><strong id="aiBaseScore">0</strong></div>
    </div>
</section>

<section
    class="pcms-detail-section ai-priority-floor"
    id="aiPriorityFloorSection"
    hidden
>
    <h3>Priority Floor</h3>
    <p id="aiPriorityFloorReason"></p>
    <p id="aiPriorityFloorResult"></p>
</section>

<section class="pcms-detail-section">
    <h3>Detected Factors</h3>
    <ul class="ai-factor-list" id="aiFactorList"></ul>
</section>

<section
    class="pcms-detail-section ai-data-quality"
    id="aiDataQualitySection"
    hidden
>
    <h3>Data Quality Notices</h3>
    <ul class="ai-factor-list" id="aiDataQualityList"></ul>
</section>

<section class="pcms-detail-section ai-suggested-action">
    <h3>Suggested Human Action</h3>
    <p id="aiSuggestedAction"></p>
</section>

<div class="ai-read-only-confirmation" role="note">
    <i class="fas fa-shield-halved" aria-hidden="true"></i>
    <span>
        No Asset, Maintenance, Audit, Inventory, or Procurement record
        was changed. Final action remains with the Property Custodian.
    </span>
</div>

</div>

<footer class="pcms-modal-footer">
    <button type="button" class="btn btn-outline" data-modal-close>
        Close
    </button>
    <a id="aiViewMaintenance" href="../maintenance/index.php" class="btn btn-warning">
        View Maintenance History
    </a>
    <a id="aiViewAsset" href="../asset_registry/index.php" class="btn btn-primary">
        View Asset
    </a>
</footer>

</div>
</div>

