$(function () {
    // Don't do anything if this isn't a Edit Counter page.
    if ($("body.ec").length === 0) {
        return;
    }

    // Set up charts.
    $(".chart-wrapper").each(function () {
        var chartType = $(this).data("chart-type");
        if ( chartType === undefined ) {
            return false;
        }
        var data = $(this).data("chart-data");
        var labels = $(this).data("chart-labels");
        var $ctx = $("canvas", $(this));

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
    setupToggleTable(window.namespaceTotals, window.namespaceChart, null, function (newData) {
        var total = 0;
        Object.keys(newData).forEach(function (namespace) {
            total += parseInt(newData[namespace], 10);
        });
        var namespaceCount = Object.keys(newData).length;
        $('.namespaces--namespaces').text(
            namespaceCount.toLocaleString() + " " +
            $.i18n('num-namespaces', namespaceCount)
        );
        $('.namespaces--count').text(total.toLocaleString());
    });
});

/**
 * Load top edits HTML via AJAX, to not slow down the initial page load.
 * Only load if container is present, which is missing in subroutes, e.g. ec-namespacetotals, etc.
 * @return {Deferred} jQuery Deferred promise.
 */
// function loadTopEdits()
// {
//     var dfd = $.Deferred();
//     var $topEditsContainer = $("#topedits-container");

//     if ($topEditsContainer[0]) {
//         /** global: xtBaseUrl */
//         var url = xtBaseUrl + 'topedits/'
//             + $topEditsContainer.data('project') + '/'
//             + $topEditsContainer.data('username') + '/all?htmlonly=yes';
//         $.ajax({
//             url: url,
//             timeout: 30000
//         }).done(function (data) {
//             $topEditsContainer.replaceWith(data);
//         }).fail(function (_xhr, _status, message) {
//             $topEditsContainer.replaceWith(
//                 $.i18n('api-error', 'TopEdits API: <code>' + message + '</code>')
//             );
//         }).always(function () {
//             dfd.resolve();
//         });
//     }

//     return dfd;
// };

/**
 * Load recent global edits' HTML via AJAX, to not slow down the initial page load.
 * Only load if container is present, which is missing in subroutes, e.g. ec-namespacetotals, etc.
 * @return {Deferred} jQuery Deferred promise.
 */
function loadLatestGlobal()
{
    var dfd = $.Deferred();
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
        }).fail(function (_xhr, _status, message) {
            $latestGlobalContainer.replaceWith(
                $.i18n('api-error', 'Global contributions API: <code>' + message + '</code>')
            );
        }).always(function () {
            dfd.resolve();
        });
    }

    return dfd;
}

/**
 * Set up the monthcounts or yearcounts chart.
 * @param {String} id 'year' or 'month'.
 * @param {Array} datasets Datasets grouped by mainspace.
 * @param {Array} labels The bare labels for the y-axis (years or months).
 * @param {Number} maxTotal Maximum value of year/month totals.
 */
window.setupMonthYearChart = function (id, datasets, labels, maxTotal) {
    /**
     * Namespaces that have been excluded from view via clickable
     * labels above the chart.
     * @type {Array}
     */
    var excludedNamespaces = [];

    /**
     * Number of digits of the max month/year total. We want to keep this consistent
     * for aesthetic reasons, even if the updated totals are fewer digits in size.
     * @type {Number}
     */
    var maxDigits = maxTotal.toString().length;

    /** @type {Array} Labels for each namespace. */
    var namespaces = datasets.map(function (dataset) {
        return dataset.label;
    });

    /**
     * Build the labels for the y-axis of the year/monthcount charts,
     * which include the year/month and the total number of edits across
     * all namespaces in that year/month.
     */
    function getYAxisLabels()
    {
        var labelsAndTotals = {};
        datasets.forEach(function (namespace) {
            if (excludedNamespaces.indexOf(namespace.label) !== -1) {
                return;
            }

            namespace.data.forEach(function (count, index) {
                if (!labelsAndTotals[labels[index]]) {
                    labelsAndTotals[labels[index]] = 0;
                }
                labelsAndTotals[labels[index]] += count;
            });
        });

        // Format labels with totals next to them. This is a bit hacky,
        // but it works! We use tabs (\t) to make the labels/totals
        // for each namespace line up perfectly.
        // The caveat is that we can't localize the numbers because
        // the commas are not monospaced :(
        return Object.keys(labelsAndTotals).map(function (year) {
            var digitCount = labelsAndTotals[year].toString().length;
            var numTabs = (maxDigits - digitCount) * 2;

            // +5 for a bit of extra spacing.
            return year + Array(numTabs + 5).join("\t") +
                labelsAndTotals[year];
        });
    }

    window[id + 'countsChart'] = new Chart($('#' + id + 'counts-canvas'), {
        type: 'horizontalBar',
        data: {
            labels: getYAxisLabels(),
            datasets: datasets
        },
        options: {
            tooltips: {
                intersect: true,
                callbacks: {
                    label: function (tooltip) {
                        return tooltip.xLabel.toLocaleString();
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
                    stacked: true
                }]
            },
            legend: {
                // Happens when the user enables/disables a namespace via the
                // labels above the chart.
                onClick: function (e, legendItem) {
                    // Update totals, skipping over namespaces that have been excluded.
                    if (legendItem.hidden) {
                        excludedNamespaces = excludedNamespaces.filter(function (namespace) {
                            return namespace !== legendItem.text;
                        });
                    } else {
                        excludedNamespaces.push(legendItem.text);
                    }

                    // Update labels with the new totals.
                    window[id + 'countsChart'].config.data.labels = getYAxisLabels();

                    // Yield to default onClick event, which re-renders the chart.
                    Chart.defaults.global.legend.onClick.call(this, e, legendItem);
                }
            }
        }
    });
}
