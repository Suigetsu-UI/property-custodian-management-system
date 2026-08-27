(function () {
    'use strict';

    function numericValue(element) {
        var value = Number(element.getAttribute('data-value'));

        return Number.isFinite(value) && value > 0 ? value : 0;
    }

    function renderRelativeBars(container, selector) {
        var bars = Array.prototype.slice.call(
            container.querySelectorAll(selector)
        );
        var maximum = bars.reduce(function (currentMaximum, bar) {
            return Math.max(currentMaximum, numericValue(bar));
        }, 0);

        bars.forEach(function (bar) {
            var ratio = maximum === 0 ? 0 : numericValue(bar) / maximum;
            bar.style.setProperty('--dashboard-ratio', ratio.toFixed(4));
        });
    }

    document.querySelectorAll('[data-dashboard-chart="horizontal"]')
        .forEach(function (chart) {
            renderRelativeBars(chart, '.dashboard-chart-bar');
        });

    document.querySelectorAll('[data-dashboard-chart="columns"]')
        .forEach(function (chart) {
            renderRelativeBars(chart, '.dashboard-column-bar');
        });

    document.querySelectorAll('[data-dashboard-chart="workflow"]')
        .forEach(function (chart) {
            renderRelativeBars(chart, '.dashboard-chart-bar');
        });
})();
