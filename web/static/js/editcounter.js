/**
 * Namespaces that have been excluded from view via namespace toggle table.
 * @type {Array}
 */
window.excludedNamespaces = [];

/**
 * Chart labels for the month/yearcount charts.
 * @type {Object} Keys are the chart IDs, values are arrays of strings.
 */
window.chartLabels = {};

/**
 * Number of digits of the max month/year total. We want to keep this consistent
 * for aesthetic reasons, even if the updated totals are fewer digits in size.
 * @type {Object} Keys are the chart IDs, values are integers.
 */
window.maxDigits = {};

$(function () {
    // Don't do anything if this isn't a Edit Counter page.
    if ($("body.ec").length === 0) {
        return;
    }

    // Set up charts.
    $('.chart-wrapper').each(function () {
        var chartType = $(this).data('chart-type');
        if ( chartType === undefined ) {
            return false;
        }
        var data = $(this).data('chart-data');
        var labels = $(this).data('chart-labels');
        var $ctx = $('canvas', $(this));

        /** global: Chart */
        new Chart($ctx, {
            type: chartType,
            data: {
                labels: labels,
                datasets: [ { data: data } ]
            }
        });

        return undefined;
    });

    loadLatestGlobal();

    // Set up namespace toggle chart.
    setupToggleTable(window.namespaceTotals, window.namespaceChart, null, toggleNamespace);
});

/**
 * Callback for setupToggleTable(). This will show/hide a given namespace from
 * all charts, and update totals and percentages.
 * @param  {Object} newData New namespaces and totals, as returned by setupToggleTable.
 * @param  {String} key     Namespace ID of the toggled namespace.
 */
function toggleNamespace(newData, key)
{
    var total = 0, counts = [];
    Object.keys(newData).forEach(function (namespace) {
        var count = parseInt(newData[namespace], 10);
        counts.push(count);
        total += count;
    });
    var namespaceCount = Object.keys(newData).length;

    $('.namespaces--namespaces').text(
        namespaceCount.toLocaleString() + ' ' +
        $.i18n('num-namespaces', namespaceCount)
    );
    $('.namespaces--count').text(total.toLocaleString());

    // Now that we have the total, loop through once more time to update percentages.
    counts.forEach(function (count) {
        // Calculate percentage, rounded to tenths.
        var percentage = getPercentage(count, total);

        // Update text with new value and percentage.
        $('.namespaces-table .sort-entry--count[data-value='+count+']').text(
            count.toLocaleString() + ' (' + percentage + '%)'
        );
    });

    // Loop through month and year charts, toggling the dataset for the newly excluded namespace.
    ['year', 'month'].forEach(function (id) {
        var chartObj = window[id + 'countsChart'],
            nsName = window.namespaces[key] || $.i18n('mainspace');

        // Figure out the index of the namespace we're toggling within this chart object.
        var datasetIndex;
        chartObj.data.datasets.forEach(function (dataset, i) {
            if (dataset.label === nsName) {
                datasetIndex = i;
            }
        });

        // Fetch the metadata and toggle the hidden property.
        var meta = chartObj.getDatasetMeta(datasetIndex);
        meta.hidden = meta.hidden === null ? !chartObj.data.datasets[datasetIndex].hidden : null;

        // Add this namespace to the list of excluded namespaces.
        if (meta.hidden) {
            window.excludedNamespaces.push(nsName);
        } else {
            window.excludedNamespaces = window.excludedNamespaces.filter(function (namespace) {
                return namespace !== nsName;
            });
        }

        // Update y-axis labels with the new totals.
        window[id + 'countsChart'].config.data.labels = getYAxisLabels(id, chartObj.data.datasets);

        // Refresh chart.
        chartObj.update();
    });
}

/**
 * Load recent global edits' HTML via AJAX, to not slow down the initial page load.
 * Only load if container is present, which is missing in subroutes, e.g. ec-namespacetotals, etc.
 */
function loadLatestGlobal()
{
    var $latestGlobalContainer = $("#latestglobal-container");

    if ($latestGlobalContainer[0]) {
        /** global: xtBaseUrl */
        var url = xtBaseUrl + 'ec-latestglobal/'
            + $latestGlobalContainer.data('project') + '/'
            + $latestGlobalContainer.data('username') + '?htmlonly=yes';
        $.ajax({
            url: url,
            timeout: 30000
        }).done(function (data) {
            $latestGlobalContainer.replaceWith(data);
            setupColumnSorting();
        }).fail(function (_xhr, _status, message) {
            $latestGlobalContainer.replaceWith(
                $.i18n('api-error', 'Global contributions API: <code>' + message + '</code>')
            );
        });
    }
}

/**
 * Build the labels for the y-axis of the year/monthcount charts,
 * which include the year/month and the total number of edits across
 * all namespaces in that year/month.
 * @param {String} id ID prefix of the chart, either 'month' or 'year'.
 * @param {Array} datasets Datasets making up the chart.
 * @return {Array} Labels for each year/month.
 */
function getYAxisLabels(id, datasets)
{
    var labelsAndTotals = getMonthYearTotals(id, datasets);

    // Format labels with totals next to them. This is a bit hacky,
    // but it works! We use tabs (\t) to make the labels/totals
    // for each namespace line up perfectly.
    // The caveat is that we can't localize the numbers because
    // the commas are not monospaced :(
    return Object.keys(labelsAndTotals).map(function (year) {
        var digitCount = labelsAndTotals[year].toString().length;
        var numTabs = (window.maxDigits[id] - digitCount) * 2;

        // +5 for a bit of extra spacing.
        return year + Array(numTabs + 5).join("\t") +
            labelsAndTotals[year];
    });
}

/**
 * Get the total number of edits for the given dataset (year or month).
 * @param {String} id ID prefix of the chart, either 'month' or 'year'.
 * @param {Array} datasets Datasets making up the chart.
 * @return {Object} Labels for each year/month as keys, totals as the values.
 */
function getMonthYearTotals(id, datasets)
{
    var labelsAndTotals = {};
    datasets.forEach(function (namespace) {
        if (window.excludedNamespaces.indexOf(namespace.label) !== -1) {
            return;
        }

        namespace.data.forEach(function (count, index) {
            if (!labelsAndTotals[window.chartLabels[id][index]]) {
                labelsAndTotals[window.chartLabels[id][index]] = 0;
            }
            labelsAndTotals[window.chartLabels[id][index]] += count;
        });
    });

    return labelsAndTotals;
}

/**
 * Calculate and format a percentage, rounded to the tenths place.
 * @param  {Number} numerator
 * @param  {Number} denominator
 * @return {Number}
 */
function getPercentage(numerator, denominator)
{
    return +(Math.round(
        ((numerator / denominator) * 100) + 'e+1'
    ) + 'e-1');
}

/**
 * Set up the monthcounts or yearcounts chart. This is set on the window
 * because it is called in the yearcounts/monthcounts view.
 * @param {String} id 'year' or 'month'.
 * @param {Array} datasets Datasets grouped by mainspace.
 * @param {Array} labels The bare labels for the y-axis (years or months).
 * @param {Number} maxTotal Maximum value of year/month totals.
 */
window.setupMonthYearChart = function (id, datasets, labels, maxTotal) {
    /** @type {Array} Labels for each namespace. */
    var namespaces = datasets.map(function (dataset) {
        return dataset.label;
    });

    window.maxDigits[id] = maxTotal.toString().length
    window.chartLabels[id] = labels;

    window[id + 'countsChart'] = new Chart($('#' + id + 'counts-canvas'), {
        type: 'horizontalBar',
        data: {
            labels: getYAxisLabels(id, datasets),
            datasets: datasets
        },
        options: {
            tooltips: {
                mode: 'nearest',
                intersect: true,
                callbacks: {
                    label: function (tooltip) {
                        var labelsAndTotals = getMonthYearTotals(id, datasets),
                            totals = Object.keys(labelsAndTotals).map(function (label) {
                                return labelsAndTotals[label];
                            }),
                            total = totals[tooltip.index],
                            percentage = getPercentage(tooltip.xLabel, total);

                        return tooltip.xLabel.toLocaleString() + ' ' +
                            '(' + percentage + '%)';
                    },
                    title: function (tooltip) {
                        var yLabel = tooltip[0].yLabel.replace(/\t.*/, '');
                        return yLabel + ' - ' + namespaces[tooltip[0].datasetIndex];
                    }
                }
            },
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                xAxes: [{
                    stacked: true,
                    ticks: {
                        beginAtZero: true,
                        callback: function (value) {
                            if (Math.floor(value) === value) {
                                return value.toLocaleString();
                            }
                        }
                    }
                }],
                yAxes: [{
                    stacked: true,
                    barThickness: 18,
                }]
            },
            legend: {
                display: false
            }
        }
    });
}
