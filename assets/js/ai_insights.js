(function () {
    'use strict';

    var modalApi = window.PCMSModal;
    var analysisModal = document.getElementById('aiAnalysisModal');
    var searchInput = document.getElementById('aiInsightSearch');
    var levelFilter = document.getElementById('aiInsightLevelFilter');
    var clearButton = document.getElementById('clearAiInsightFilters');
    var resultCount = document.getElementById('aiInsightResultCount');
    var emptyState = document.getElementById('aiInsightEmptyState');
    var rows = Array.prototype.slice.call(
        document.querySelectorAll('.ai-insight-row')
    );

    function setText(id, value) {
        var element = document.getElementById(id);
        if (element) element.textContent = value;
    }

    function populateList(id, values) {
        var list = document.getElementById(id);
        if (!list) return;

        list.replaceChildren();

        (values || []).forEach(function (value) {
            var item = document.createElement('li');
            item.textContent = value;
            list.appendChild(item);
        });
    }

    function formatDate(value) {
        return modalApi ? modalApi.date(value) : value;
    }

    function openAnalysis(trigger) {
        if (!modalApi || !analysisModal) return;

        var analysis = modalApi.readRowData(trigger);
        if (!analysis) return;

        var assetDescriptor = [analysis.brand, analysis.model]
            .filter(Boolean)
            .join(' ');
        var subtitle = analysis.asset_id + ' - ' + analysis.asset_name;
        if (assetDescriptor) subtitle += ' (' + assetDescriptor + ')';

        setText('aiAnalysisTitle', analysis.asset_name);
        setText('aiAnalysisSubtitle', subtitle);
        setText('aiAnalysisScore', analysis.score + ' / 100');
        setText('aiAnalysisDate', formatDate(analysis.analysis_date));
        setText('aiAnalysisRuleVersion', analysis.rule_version);
        setText('aiPointsAge', '+' + analysis.components.asset_age);
        setText(
            'aiPointsMaintenance',
            '+' + analysis.components.corrective_maintenance
        );
        setText(
            'aiPointsCondition',
            '+' + analysis.components.audit_condition
        );
        setText(
            'aiPointsRecurring',
            '+' + analysis.components.recurring_attention
        );
        setText('aiBaseScore', analysis.base_score);
        setText('aiSuggestedAction', analysis.suggested_action);

        var levelBadge = document.getElementById('aiAnalysisLevel');
        if (levelBadge) {
            levelBadge.textContent = analysis.level;
            levelBadge.dataset.level = analysis.level.toLowerCase();
        }

        var floorSection = document.getElementById(
            'aiPriorityFloorSection'
        );
        var floor = analysis.priority_floor || {};

        if (floorSection) {
            floorSection.hidden = !floor.required;
        }

        if (floor.required) {
            setText(
                'aiPriorityFloorReason',
                floor.reason + ' requires a minimum score of ' +
                    floor.minimum + '.'
            );
            setText(
                'aiPriorityFloorResult',
                floor.applied
                    ? 'The priority floor raised the final Attention Score.'
                    : 'The base score already met the required minimum.'
            );
        }

        populateList('aiFactorList', analysis.factors);
        populateList(
            'aiDataQualityList',
            analysis.data_quality_notices
        );

        var qualitySection = document.getElementById(
            'aiDataQualitySection'
        );
        if (qualitySection) {
            qualitySection.hidden =
                !analysis.data_quality_notices.length;
        }

        var viewAsset = document.getElementById('aiViewAsset');
        var viewMaintenance = document.getElementById(
            'aiViewMaintenance'
        );

        if (viewAsset) {
            viewAsset.href = '../asset_registry/view_asset.php?id=' +
                encodeURIComponent(analysis.asset_internal_id);
        }

        if (viewMaintenance) {
            viewMaintenance.href = '../maintenance/index.php?search=' +
                encodeURIComponent(analysis.asset_name);
        }

        modalApi.open(analysisModal, trigger);
    }

    function applyFilters() {
        var search = searchInput
            ? searchInput.value.trim().toLowerCase()
            : '';
        var level = levelFilter ? levelFilter.value : '';
        var visibleCount = 0;

        rows.forEach(function (row) {
            var matchesSearch = !search ||
                row.dataset.search.includes(search);
            var matchesLevel = !level || row.dataset.level === level;
            var visible = matchesSearch && matchesLevel;

            row.hidden = !visible;
            if (visible) visibleCount += 1;
        });

        if (resultCount) {
            resultCount.textContent = 'Showing ' + visibleCount + ' ' +
                (visibleCount === 1 ? 'Asset' : 'Assets');
        }

        if (emptyState) {
            emptyState.hidden = visibleCount !== 0;
        }

        if (clearButton) {
            clearButton.disabled = !search && !level;
        }
    }

    document.querySelectorAll('[data-ai-analysis]').forEach(
        function (trigger) {
            trigger.addEventListener('click', function () {
                openAnalysis(trigger);
            });
        }
    );

    if (searchInput) {
        searchInput.addEventListener('input', applyFilters);
    }

    if (levelFilter) {
        levelFilter.addEventListener('change', applyFilters);
    }

    if (clearButton) {
        clearButton.addEventListener('click', function () {
            if (searchInput) searchInput.value = '';
            if (levelFilter) levelFilter.value = '';
            applyFilters();
            if (searchInput) searchInput.focus();
        });
    }

    applyFilters();
})();
