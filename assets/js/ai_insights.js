(function () {
    'use strict';

    var modalApi = window.PCMSModal;
    var analysisModal = document.getElementById('aiAnalysisModal');
    var comparisonModal = document.getElementById('aiComparisonModal');
    var searchInput = document.getElementById('aiInsightSearch');
    var levelFilter = document.getElementById('aiInsightLevelFilter');
    var statusFilter = document.getElementById('aiInsightStatusFilter');
    var concernFilter = document.getElementById('aiInsightConcernFilter');
    var coverageFilter = document.getElementById('aiInsightCoverageFilter');
    var sortControl = document.getElementById('aiInsightSort');
    var clearButton = document.getElementById('clearAiInsightFilters');
    var resultCount = document.getElementById('aiInsightResultCount');
    var emptyState = document.getElementById('aiInsightEmptyState');
    var tableBody = document.getElementById('aiInsightTableBody');
    var comparisonBar = document.getElementById('aiComparisonBar');
    var comparisonCount = document.getElementById('aiComparisonCount');
    var compareButton = document.getElementById('compareAiAssets');
    var scenarioForm = document.getElementById('aiScenarioForm');
    var rows = Array.prototype.slice.call(document.querySelectorAll('.ai-insight-row'));
    var activeAnalysis = null;

    function setText(id, value) {
        var element = document.getElementById(id);
        if (element) element.textContent = value == null ? '' : value;
    }

    function populateList(id, values, ordered) {
        var list = document.getElementById(id);
        if (!list) return;
        list.replaceChildren();
        (values || []).forEach(function (value) {
            var item = document.createElement('li');
            item.textContent = value;
            list.appendChild(item);
        });
        if (!ordered && !values.length) {
            var empty = document.createElement('li');
            empty.textContent = 'No additional evidence recorded.';
            list.appendChild(empty);
        }
    }

    function formatDate(value) {
        return modalApi && value ? modalApi.date(value) : (value || '—');
    }

    function renderCoverage(coverage) {
        var container = document.getElementById('aiCoverageSignals');
        if (!container) return;
        container.replaceChildren();
        var labels = {
            acquisition_date: 'Acquisition date',
            maintenance_history: 'Maintenance history',
            completed_audit: 'Completed Audit',
            historical_events: 'Historical event evidence'
        };
        Object.keys(labels).forEach(function (key) {
            var present = Boolean(coverage.signals && coverage.signals[key]);
            var row = document.createElement('div');
            row.dataset.present = present ? 'true' : 'false';
            var icon = document.createElement('i');
            icon.className = 'fas ' + (present ? 'fa-check' : 'fa-minus');
            icon.setAttribute('aria-hidden', 'true');
            var text = document.createElement('span');
            text.textContent = labels[key];
            row.append(icon, text);
            container.appendChild(row);
        });
    }

    function renderTimeline(timeline) {
        var container = document.getElementById('aiEvidenceTimeline');
        if (!container) return;
        container.replaceChildren();

        if (!timeline || !timeline.length) {
            var empty = document.createElement('p');
            empty.className = 'ai-timeline-empty';
            empty.textContent = 'No dated supporting evidence is available.';
            container.appendChild(empty);
            return;
        }

        timeline.forEach(function (event) {
            var item = document.createElement('article');
            var marker = document.createElement('span');
            var body = document.createElement('div');
            var heading = document.createElement('strong');
            var meta = document.createElement('small');
            marker.className = 'ai-timeline-marker';
            marker.dataset.type = String(event.type || '').toLowerCase().replace(/\s+/g, '-');
            heading.textContent = event.summary;
            meta.textContent = formatDate(event.date) + ' · ' + event.type +
                (event.business_id ? ' · ' + event.business_id : '') +
                (event.recency ? ' · ' + event.recency : '');
            body.append(heading, meta);
            item.append(marker, body);
            container.appendChild(item);
        });
    }

    function resetScenario() {
        if (scenarioForm) scenarioForm.reset();
        var result = document.getElementById('aiScenarioResult');
        var error = document.getElementById('aiScenarioError');
        if (result) result.hidden = true;
        if (error) error.hidden = true;
    }

    function openAnalysis(trigger) {
        if (!modalApi || !analysisModal) return;
        var analysis = modalApi.readRowData(trigger);
        if (!analysis) return;
        activeAnalysis = analysis;
        resetScenario();

        var descriptor = [analysis.brand, analysis.model].filter(Boolean).join(' ');
        var subtitle = analysis.asset_id + ' — ' + analysis.asset_name;
        if (descriptor) subtitle += ' (' + descriptor + ')';

        setText('aiAnalysisTitle', analysis.asset_name);
        setText('aiAnalysisSubtitle', subtitle);
        setText('aiAnalysisScore', analysis.score + ' / 100');
        setText('aiPrimaryConcern', analysis.presentation.concern.label);
        setText('aiCoverageLabel', analysis.presentation.coverage.label);
        setText('aiAnalysisDate', formatDate(analysis.analysis_date));
        setText('aiAnalysisRuleVersion', analysis.rule_version + ' · ' + analysis.interpretation_version);
        setText('aiMaintenancePattern', analysis.presentation.maintenance_label);
        setText('aiMaintenanceExplanation', analysis.presentation.maintenance_explanation);
        setText('aiMaintenancePatternDetail', 'Technical label: ' +
            analysis.maintenance_pattern.classification + ' · ' +
            analysis.maintenance_pattern.unique_corrective_cases +
            ' corrective case' + (analysis.maintenance_pattern.unique_corrective_cases === 1 ? '' : 's'));
        setText('aiAuditTrend', analysis.presentation.audit_label);
        setText('aiAuditExplanation', analysis.presentation.audit_explanation);
        setText('aiAuditTrendDetail', 'Technical label: ' +
            analysis.audit_trend.classification + ' · ' +
            analysis.audit_trend.audits_considered +
            ' completed Audit' + (analysis.audit_trend.audits_considered === 1 ? '' : 's'));
        setText('aiPointsAge', '+' + analysis.components.asset_age);
        setText('aiPointsMaintenance', '+' + analysis.components.corrective_maintenance);
        setText('aiPointsCondition', '+' + analysis.components.audit_condition);
        setText('aiPointsRecurring', '+' + analysis.components.recurring_attention);
        setText('aiBaseScore', analysis.base_score);
        setText('aiRecommendationTitle', analysis.recommendation.title);
        setText('aiRecommendationReason', analysis.recommendation.reason);
        setText('aiScoreSummary', analysis.presentation.score_summary);
        setText('aiCoverageFriendlyLabel', analysis.presentation.coverage.label);
        setText('aiCoverageExplanation', analysis.presentation.coverage.explanation);
        setText('aiCoverageTechnicalLabel', analysis.evidence_coverage.label);
        var scenarioAssetId = document.getElementById('aiScenarioAssetId');
        if (scenarioAssetId) scenarioAssetId.value = analysis.asset_id;

        var levelBadge = document.getElementById('aiAnalysisLevel');
        if (levelBadge) {
            levelBadge.textContent = analysis.level;
            levelBadge.dataset.level = analysis.level.toLowerCase();
        }

        var floor = analysis.priority_floor || {};
        var floorSection = document.getElementById('aiPriorityFloorSection');
        if (floorSection) floorSection.hidden = !floor.required;
        if (floor.required) {
            setText('aiPriorityFloorReason', floor.reason + ' requires a minimum score of ' + floor.minimum + '.');
            setText('aiPriorityFloorResult', floor.applied
                ? 'The priority floor raised the final Attention Score.'
                : 'The base score already met the required minimum.');
        }

        populateList('aiFactorList', analysis.factors || [], false);
        populateList('aiScoreExplanationList', analysis.presentation.score_explanations || [], false);
        populateList('aiDataQualityList', analysis.data_quality_notices || [], false);
        populateList('aiRecommendationSteps', analysis.recommendation.steps || [], true);
        var qualitySection = document.getElementById('aiDataQualitySection');
        if (qualitySection) qualitySection.hidden = !(analysis.data_quality_notices || []).length;
        renderCoverage(analysis.evidence_coverage);
        renderTimeline(analysis.timeline);

        var viewAsset = document.getElementById('aiViewAsset');
        var viewMaintenance = document.getElementById('aiViewMaintenance');
        if (viewAsset) viewAsset.href = '../asset_registry/view_asset.php?id=' + encodeURIComponent(analysis.asset_internal_id);
        if (viewMaintenance) viewMaintenance.href = '../maintenance/index.php?search=' + encodeURIComponent(analysis.asset_id);
        modalApi.open(analysisModal, trigger);
    }

    function selectedRows() {
        return rows.filter(function (row) {
            var checkbox = row.querySelector('[data-ai-compare]');
            return checkbox && checkbox.checked;
        });
    }

    function updateComparisonState(changedCheckbox) {
        var selected = selectedRows();
        if (selected.length > 2 && changedCheckbox) {
            changedCheckbox.checked = false;
            selected = selectedRows();
        }
        if (comparisonBar) comparisonBar.hidden = selected.length === 0;
        if (comparisonCount) comparisonCount.textContent = selected.length === 2
            ? 'Two Assets selected and ready to compare.'
            : selected.length + ' of 2 Assets selected.';
        if (compareButton) compareButton.disabled = selected.length !== 2;
    }

    function comparisonCard(analysis) {
        var card = document.createElement('article');
        var heading = document.createElement('h3');
        heading.textContent = analysis.asset_id + ' — ' + analysis.asset_name;
        card.appendChild(heading);
        [
            ['Attention Score', analysis.score + ' / 100'],
            ['Level', analysis.level],
            ['Main reason for attention', analysis.presentation.concern.label],
            ['Available history', analysis.presentation.coverage.label],
            ['Maintenance history', analysis.presentation.maintenance_label],
            ['Recent Audit history', analysis.presentation.audit_label]
        ].forEach(function (entry) {
            var row = document.createElement('div');
            var label = document.createElement('span');
            var value = document.createElement('strong');
            label.textContent = entry[0];
            value.textContent = entry[1];
            row.append(label, value);
            card.appendChild(row);
        });
        return card;
    }

    function openComparison() {
        if (!modalApi || !comparisonModal) return;
        var selected = selectedRows();
        if (selected.length !== 2) return;
        var analyses = selected.map(function (row) { return modalApi.readRowData(row); });
        var grid = document.getElementById('aiComparisonGrid');
        if (grid) {
            grid.replaceChildren(comparisonCard(analyses[0]), comparisonCard(analyses[1]));
        }
        var first = analyses[0];
        var second = analyses[1];
        var higher = first.score === second.score ? null : (first.score > second.score ? first : second);
        setText('aiComparisonSummary', higher
            ? higher.asset_id + ' currently needs more attention. Its score is higher mainly because of ' + higher.presentation.concern.label.toLowerCase() + '. Review this Asset first, then make the final decision from the underlying records.'
            : 'Both Assets currently have the same attention score. Review their individual records before deciding which one to check first.');
        modalApi.open(comparisonModal, compareButton);
    }

    function sortRows() {
        var mode = sortControl ? sortControl.value : 'score';
        rows.sort(function (left, right) {
            if (mode === 'asset') return left.dataset.assetId.localeCompare(right.dataset.assetId);
            if (mode === 'recent') {
                var recentOrder = String(right.dataset.recent).localeCompare(String(left.dataset.recent));
                if (recentOrder) return recentOrder;
            }
            var scoreOrder = Number(right.dataset.score) - Number(left.dataset.score);
            return scoreOrder || left.dataset.assetId.localeCompare(right.dataset.assetId);
        });
        rows.forEach(function (row) { tableBody.insertBefore(row, emptyState); });
    }

    function applyFilters() {
        var search = searchInput ? searchInput.value.trim().toLowerCase() : '';
        var values = {
            level: levelFilter ? levelFilter.value : '',
            status: statusFilter ? statusFilter.value : '',
            concern: concernFilter ? concernFilter.value : '',
            coverage: coverageFilter ? coverageFilter.value : ''
        };
        var visibleCount = 0;
        sortRows();
        rows.forEach(function (row) {
            var visible = (!search || row.dataset.search.includes(search)) &&
                (!values.level || row.dataset.level === values.level) &&
                (!values.status || row.dataset.status === values.status) &&
                (!values.concern || row.dataset.concern === values.concern) &&
                (!values.coverage || row.dataset.coverage === values.coverage);
            row.hidden = !visible;
            if (visible) visibleCount += 1;
        });
        if (resultCount) resultCount.textContent = 'Showing ' + visibleCount + ' ' + (visibleCount === 1 ? 'Asset' : 'Assets');
        if (emptyState) emptyState.hidden = visibleCount !== 0;
        if (clearButton) clearButton.disabled = !search && !Object.keys(values).some(function (key) { return values[key]; });
    }

    function applyUrlState() {
        var params = new URLSearchParams(window.location.search);
        var level = params.get('level');
        var asset = params.get('asset');
        if (level === 'attention' && levelFilter) levelFilter.value = 'high';
        applyFilters();
        if (level === 'attention') {
            rows.forEach(function (row) {
                row.hidden = !['critical', 'high'].includes(row.dataset.level);
            });
            var visible = rows.filter(function (row) { return !row.hidden; }).length;
            if (resultCount) resultCount.textContent = 'Showing ' + visible + ' ' + (visible === 1 ? 'Asset' : 'Assets');
            if (emptyState) emptyState.hidden = visible !== 0;
        }
        if (asset) {
            var row = rows.find(function (candidate) { return candidate.dataset.assetId === asset; });
            if (row) {
                var trigger = row.querySelector('[data-ai-analysis]');
                if (trigger) openAnalysis(trigger);
            }
        }
    }

    rows.forEach(function (row) {
        var analysisButton = row.querySelector('[data-ai-analysis]');
        var compareCheckbox = row.querySelector('[data-ai-compare]');
        if (analysisButton) analysisButton.addEventListener('click', function () { openAnalysis(analysisButton); });
        if (compareCheckbox) compareCheckbox.addEventListener('change', function () { updateComparisonState(compareCheckbox); });
    });

    [searchInput, levelFilter, statusFilter, concernFilter, coverageFilter, sortControl].forEach(function (control) {
        if (!control) return;
        control.addEventListener(control === searchInput ? 'input' : 'change', applyFilters);
    });

    if (clearButton) clearButton.addEventListener('click', function () {
        if (searchInput) searchInput.value = '';
        [levelFilter, statusFilter, concernFilter, coverageFilter].forEach(function (filter) { if (filter) filter.value = ''; });
        if (sortControl) sortControl.value = 'score';
        window.history.replaceState({}, '', window.location.pathname);
        applyFilters();
        if (searchInput) searchInput.focus();
    });
    if (compareButton) compareButton.addEventListener('click', openComparison);

    if (scenarioForm) scenarioForm.addEventListener('submit', function (event) {
        event.preventDefault();
        if (!activeAnalysis || !analysisModal) return;
        var submit = scenarioForm.querySelector('button[type="submit"]');
        var result = document.getElementById('aiScenarioResult');
        var error = document.getElementById('aiScenarioError');
        var csrf = document.querySelector('meta[name="csrf-token"]');
        if (submit) submit.disabled = true;
        if (result) result.hidden = true;
        if (error) error.hidden = true;

        fetch(analysisModal.dataset.scenarioUrl, {
            method: 'POST',
            body: new FormData(scenarioForm),
            headers: { 'X-CSRF-Token': csrf ? csrf.content : '' },
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json().then(function (body) {
                if (!response.ok) throw new Error(body.error || 'Scenario analysis failed.');
                return body;
            });
        }).then(function (body) {
            setText('aiScenarioLead', body.current.level === body.simulated.level
                ? 'With the conditions you selected, this Asset would remain at ' + body.simulated.level + ' attention.'
                : 'With the conditions you selected, this Asset would move from ' + body.current.level + ' attention to ' + body.simulated.level + ' attention.');
            setText('aiScenarioCurrent', body.current.score + ' — ' + body.current.level);
            setText('aiScenarioSimulated', body.simulated.score + ' — ' + body.simulated.level);
            setText('aiScenarioDifference', (body.difference >= 0 ? '+' : '') + body.difference + ' points');
            setText('aiScenarioRecommendation', body.simulated.recommendation.title + ' ' + body.simulated.recommendation.reason);
            var changeLabels = {
                asset_age: 'The Asset age portion changed',
                corrective_maintenance: 'The added corrective cases changed the maintenance portion',
                audit_condition: 'The selected Audit result or status changed the condition portion',
                recurring_attention: 'The repeated attention history changed',
                primary_concern: 'The main reason for attention changed'
            };
            var concernLabels = {
                'Audit / Condition': 'Asset condition',
                'Recurring Maintenance': 'Repeated maintenance needs',
                'Maintenance': 'Maintenance history',
                'Asset Status': 'Current Asset status',
                'Asset Age': 'Asset age',
                'Multiple Concerns': 'Several issues needing attention',
                'No Significant Concern': 'No major concern'
            };
            var changes = (body.changed_factors || []).map(function (change) {
                var currentValue = change.factor === 'primary_concern'
                    ? (concernLabels[change.current] || change.current)
                    : change.current;
                var simulatedValue = change.factor === 'primary_concern'
                    ? (concernLabels[change.simulated] || change.simulated)
                    : change.simulated;
                return (changeLabels[change.factor] || 'The selected information changed the result') +
                    ' from ' + currentValue + ' to ' + simulatedValue + '.';
            });
            populateList('aiScenarioChanges', changes, false);
            if (result) result.hidden = false;
        }).catch(function (requestError) {
            if (error) {
                error.textContent = requestError.message;
                error.hidden = false;
            }
        }).finally(function () {
            if (submit) submit.disabled = false;
        });
    });

    applyUrlState();
    updateComparisonState();
})();
