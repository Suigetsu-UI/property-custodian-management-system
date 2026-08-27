<div id="aiAnalysisModal" class="modal pcms-modal pcms-modal--lg"
    role="dialog" aria-modal="true" aria-hidden="true"
    aria-labelledby="aiAnalysisTitle"
    data-scenario-url="<?= BASE_URL ?>modules/ai_insights/analyze_scenario.php">
<div class="modal-content pcms-modal-dialog">

<header class="pcms-modal-header">
    <div>
        <span class="pcms-modal-eyebrow">Asset Review</span>
        <div class="pcms-modal-title-row">
            <h2 id="aiAnalysisTitle">Asset Attention Analysis</h2>
            <span id="aiAnalysisLevel" class="ai-level-badge" data-level="low">Low</span>
        </div>
        <p id="aiAnalysisSubtitle">Recorded evidence and advisory result</p>
    </div>
    <button type="button" class="close-modal" data-modal-close aria-label="Close Asset Attention Analysis"><span aria-hidden="true">&times;</span></button>
</header>

<div class="pcms-modal-body ai-analysis-body">

<section class="ai-score-overview" aria-label="Attention result">
    <div><span>Attention Score</span><strong id="aiAnalysisScore">0 / 100</strong></div>
    <div><span>Main reason for attention</span><strong id="aiPrimaryConcern">&mdash;</strong></div>
    <div><span>Available history</span><strong id="aiCoverageLabel">&mdash;</strong></div>
    <div><span>Analysis Versions</span><strong id="aiAnalysisRuleVersion">&mdash;</strong></div>
</section>

<section class="pcms-detail-section">
    <h3>What do the records show?</h3>
    <div class="ai-interpretation-grid">
        <div><span>Maintenance history</span><strong id="aiMaintenancePattern">&mdash;</strong><small id="aiMaintenanceExplanation"></small><em id="aiMaintenancePatternDetail"></em></div>
        <div><span>Recent Audit history</span><strong id="aiAuditTrend">&mdash;</strong><small id="aiAuditExplanation"></small><em id="aiAuditTrendDetail"></em></div>
        <div><span>Analysis Date</span><strong id="aiAnalysisDate">&mdash;</strong><small>Asia/Manila</small></div>
    </div>
</section>

<section class="pcms-detail-section ai-human-score">
    <h3>Why does this Asset need attention?</h3>
    <ul class="ai-factor-list" id="aiScoreExplanationList"></ul>
    <p id="aiScoreSummary"></p>
    <details class="ai-technical-score">
        <summary>View technical score breakdown</summary>
        <div class="ai-point-breakdown">
            <div><span>Asset Age</span><strong id="aiPointsAge">+0</strong></div>
            <div><span>Corrective Maintenance</span><strong id="aiPointsMaintenance">+0</strong></div>
            <div><span>Audit / Current Condition</span><strong id="aiPointsCondition">+0</strong></div>
            <div><span>Recurring Attention States</span><strong id="aiPointsRecurring">+0</strong></div>
            <div class="ai-point-total"><span>Base Score</span><strong id="aiBaseScore">0</strong></div>
        </div>
        <div class="ai-priority-floor" id="aiPriorityFloorSection" hidden>
            <h4>Technical priority floor</h4><p id="aiPriorityFloorReason"></p><p id="aiPriorityFloorResult"></p>
        </div>
        <h4>Technical factors</h4>
        <ul class="ai-factor-list" id="aiFactorList"></ul>
    </details>
</section>

<details class="pcms-detail-section ai-explanation" open>
    <summary>What records are available?</summary>
    <div class="ai-coverage-introduction">
        <strong id="aiCoverageFriendlyLabel">&mdash;</strong>
        <p id="aiCoverageExplanation"></p>
        <small>Technical label: Evidence Coverage — <span id="aiCoverageTechnicalLabel">&mdash;</span></small>
    </div>
    <div class="ai-coverage-signals" id="aiCoverageSignals"></div>
    <div class="ai-data-quality" id="aiDataQualitySection" hidden>
        <h4>Data Quality Notices</h4><ul class="ai-factor-list" id="aiDataQualityList"></ul>
    </div>
    <div class="ai-timeline" id="aiEvidenceTimeline"></div>
</details>

<section class="pcms-detail-section ai-suggested-action">
    <p class="pcms-modal-eyebrow">What should you do next?</p>
    <h3 id="aiRecommendationTitle">Continue routine monitoring</h3>
    <p id="aiRecommendationReason"></p>
    <ol class="ai-recommendation-steps" id="aiRecommendationSteps"></ol>
</section>

<section class="pcms-detail-section ai-scenario-section" aria-labelledby="aiScenarioHeading">
    <div class="ai-scenario-heading">
        <div><p class="pcms-modal-eyebrow">What-If</p><h3 id="aiScenarioHeading">What could change?</h3></div>
        <span>No records changed</span>
    </div>
    <form id="aiScenarioForm" class="ai-scenario-form">
        <input type="hidden" id="aiScenarioAssetId" name="asset_id">
        <label>Simulated latest completed Audit
            <select name="audit_result">
                <option value="unchanged">No change</option><option>Verified</option>
                <option>For Investigation</option><option>Damaged</option><option>Missing</option>
            </select>
        </label>
        <label>Additional corrective cases
            <select name="additional_corrective_cases">
                <option value="0">0</option><option value="1">1</option>
                <option value="2">2</option><option value="3">3</option>
            </select>
        </label>
        <label>Simulated current status
            <select name="asset_status">
                <option value="unchanged">No change</option><option>Available</option>
                <option>Assigned</option><option>Under Maintenance</option><option>Lost</option>
            </select>
        </label>
        <button type="submit" class="btn btn-primary">Analyze Scenario</button>
    </form>
    <div class="ai-scenario-result" id="aiScenarioResult" hidden aria-live="polite">
        <p class="ai-scenario-lead" id="aiScenarioLead"></p>
        <div><span>Current score</span><strong id="aiScenarioCurrent">&mdash;</strong></div>
        <div><span>Simulated score</span><strong id="aiScenarioSimulated">&mdash;</strong></div>
        <div><span>Change</span><strong id="aiScenarioDifference">&mdash;</strong></div>
        <p id="aiScenarioRecommendation"></p>
        <strong class="ai-scenario-why">Why did it change?</strong>
        <ul id="aiScenarioChanges"></ul>
        <small>This is only a scenario. No PCMS records were changed.</small>
    </div>
    <p class="ai-scenario-error" id="aiScenarioError" hidden role="alert"></p>
</section>

<div class="ai-read-only-confirmation" role="note">
    <i class="fas fa-shield-halved" aria-hidden="true"></i>
    <span>AI assists the Property Custodian. It does not approve, assign, audit, maintain, delete, or otherwise change a PCMS record.</span>
</div>
</div>

<footer class="pcms-modal-footer">
    <button type="button" class="btn btn-outline" data-modal-close>Close</button>
    <a id="aiViewMaintenance" href="../maintenance/index.php" class="btn btn-warning">View Maintenance</a>
    <a id="aiViewAsset" href="../asset_registry/index.php" class="btn btn-primary">View Asset</a>
</footer>
</div>
</div>

<div id="aiComparisonModal" class="modal pcms-modal pcms-modal--lg"
    role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="aiComparisonTitle">
<div class="modal-content pcms-modal-dialog">
    <header class="pcms-modal-header">
        <div><span class="pcms-modal-eyebrow">Side-by-Side Review</span><h2 id="aiComparisonTitle">Which Asset needs more attention?</h2><p>Both Assets are reviewed using the same recorded information and rules.</p></div>
        <button type="button" class="close-modal" data-modal-close aria-label="Close Asset comparison"><span aria-hidden="true">&times;</span></button>
    </header>
    <div class="pcms-modal-body">
        <div class="ai-comparison-grid" id="aiComparisonGrid"></div>
        <p class="ai-comparison-summary" id="aiComparisonSummary"></p>
        <div class="ai-read-only-confirmation"><i class="fas fa-scale-balanced" aria-hidden="true"></i><span>This does not automatically mean an Asset should be replaced. Use the comparison to decide which Asset to review first.</span></div>
    </div>
    <footer class="pcms-modal-footer"><button type="button" class="btn btn-outline" data-modal-close>Close</button></footer>
</div>
</div>
