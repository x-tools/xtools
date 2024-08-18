xtools.editcounter = {};

/**
 * Namespaces that have been excluded from view via namespace toggle table.
 * @type {Array}
 */
xtools.editcounter.excludedNamespaces = [];

/**
 * Chart labels for the month/yearcount charts.
 * @type {Object} Keys are the chart IDs, values are arrays of strings.
 */
xtools.editcounter.chartLabels = {};

/**
 * Number of digits of the max month/year total. We want to keep this consistent
 * for aesthetic reasons, even if the updated totals are fewer digits in size.
 * @type {Object} Keys are the chart IDs, values are integers.
 */
xtools.editcounter.maxDigits = {};

$(function () {
    // Don't do anything if this isn't a Edit Counter page.
    if ($('body.editcounter').length === 0) {
        return;
    }

    xtools.application.setupMultiSelectListeners();

    // Set up charts.
    $('.chart-wrapper').each(function () {
        var chartType = $(this).data('chart-type');
        if (chartType === undefined) {
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

    // Set up namespace toggle chart.
    xtools.application.setupToggleTable(window.namespaceTotals, window.namespaceChart, null, toggleNamespace);
});

/**
 * Callback for setupToggleTable(). This will show/hide a given namespace from
 * all charts, and update totals and percentages.
 * @param {Object} newData New namespaces and totals, as returned by setupToggleTable.
 * @param {String} key Namespace ID of the toggled namespace.
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

    /** global: i18nLang */
    $('.namespaces--namespaces').text(
        namespaceCount.toLocaleString(i18nLang) + ' ' +
        $.i18n('num-namespaces', namespaceCount)
    );
    $('.namespaces--count').text(total.toLocaleString(i18nLang));

    // Now that we have the total, loop through once more time to update percentages.
    counts.forEach(function (count) {
        // Calculate percentage, rounded to tenths.
        var percentage = getPercentage(count, total);

        // Update text with new value and percentage.
        $('.namespaces-table .sort-entry--count[data-value='+count+']').text(
            count.toLocaleString(i18nLang) + ' (' + percentage + ')'
        );
    });

    // Loop through month and year charts, toggling the dataset for the newly excluded namespace.
    ['year', 'month'].forEach(function (id) {
        var chartObj = window[id + 'countsChart'],
            nsName = window.namespaces[key] || $.i18n('mainspace');

        // Year and month sections can be selectively hidden.
        if (!chartObj) {
            return;
        }

        // Figure out the index of the namespace we're toggling within this chart object.
        var datasetIndex = 0;
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
            xtools.editcounter.excludedNamespaces.push(nsName);
        } else {
            xtools.editcounter.excludedNamespaces = xtools.editcounter.excludedNamespaces.filter(function (namespace) {
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
 * Build the labels for the y-axis of the year/monthcount charts, which include the year/month and the total number of
 * edits across all namespaces in that year/month.
 * @param {String} id ID prefix of the chart, either 'month' or 'year'.
 * @param {Array} datasets Datasets making up the chart.
 * @return {Array} Labels for each year/month.
 */
function getYAxisLabels(id, datasets)
{
    var labelsAndTotals = getMonthYearTotals(id, datasets);

    // Format labels with totals next to them. This is a bit hacky, but it works! We use tabs (\t) to make the
    // labels/totals for each namespace line up perfectly. The caveat is that we can't localize the numbers because
    // the commas are not monospaced :(
    return Object.keys(labelsAndTotals).map(function (year) {
        var digitCount = labelsAndTotals[year].toString().length;
        var numTabs = (xtools.editcounter.maxDigits[id] - digitCount) * 2;

        // +5 for a bit of extra spacing.
        /** global: i18nLang */
        return year + Array(numTabs + 5).join("\t") +
            labelsAndTotals[year].toLocaleString(i18nLang, {useGrouping: false});
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
        if (xtools.editcounter.excludedNamespaces.indexOf(namespace.label) !== -1) {
            return;
        }

        namespace.data.forEach(function (count, index) {
            if (!labelsAndTotals[xtools.editcounter.chartLabels[id][index]]) {
                labelsAndTotals[xtools.editcounter.chartLabels[id][index]] = 0;
            }
            labelsAndTotals[xtools.editcounter.chartLabels[id][index]] += count;
        });
    });

    return labelsAndTotals;
}

/**
 * Calculate and format a percentage, rounded to the tenths place.
 * @param {Number} numerator
 * @param {Number} denominator
 * @return {Number}
 */
function getPercentage(numerator, denominator)
{
    /** global: i18nLang */
    return (numerator / denominator).toLocaleString(i18nLang, {style: 'percent'});
}

/**
 * Set up the monthcounts or yearcounts chart. This is set on the window
 * because it is called in the yearcounts/monthcounts view.
 * @param {String} id 'year' or 'month'.
 * @param {Array} datasets Datasets grouped by mainspace.
 * @param {Array} labels The bare labels for the y-axis (years or months).
 * @param {Number} maxTotal Maximum value of year/month totals.
 * @param {Boolean} showLegend Whether to show the legend above the chart.
 */
xtools.editcounter.setupMonthYearChart = function (id, datasets, labels, maxTotal, showLegend) {
    /** @type {Array} Labels for each namespace. */
    var namespaces = datasets.map(function (dataset) {
        return dataset.label;
    });

    xtools.editcounter.maxDigits[id] = maxTotal.toString().length;
    xtools.editcounter.chartLabels[id] = labels;

    /** global: i18nRTL */
    /** global: i18nLang */
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

                        return tooltip.xLabel.toLocaleString(i18nLang) + ' ' +
                            '(' + percentage + ')';
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
                        reverse: i18nRTL,
                        callback: function (value) {
                            if (Math.floor(value) === value) {
                                return value.toLocaleString(i18nLang);
                            }
                        }
                    },
                    gridLines: {
                        color: xtools.application.chartGridColor
                    }
                }],
                yAxes: [{
                    stacked: true,
                    barThickness: 18,
                    position: i18nRTL ? 'right' : 'left',
                    gridLines: {
                        color: xtools.application.chartGridColor
                    }
                }]
            },
            legend: {
                display: showLegend
            }
        }
    });
};

/**
 * Builds the timecard chart and adds a listener for the 'local time' option.
 * @param {Array} timeCardDatasets
 * @param {Object} days
 */
xtools.editcounter.setupTimecard = function (timeCardDatasets, days) {
    var useLocalTimezone = false,
        timezoneOffset = new Date().getTimezoneOffset() / 60;
    window.chart = new Chart($("#timecard-bubble-chart"), {
        type: 'bubble',
        data: {
            datasets: timeCardDatasets
        },
        options: {
            responsive: true,
            // maintainAspectRatio: false,
            legend: {
                display: false
            },
            layout: {
                padding: {
                    right: 0
                }
            },
            elements: {
                point: {
                    radius: function (context) {
                        var index = context.dataIndex;
                        var data = context.dataset.data[index];
                        // var size = context.chart.width;
                        // var base = data.value / 100;
                        // return (size / 50) * base;
                        return data.scale;
                    },
                    hitRadius: 8
                }
            },
            scales: {
                yAxes: [{
                    ticks: {
                        min: 0,
                        max: 8,
                        stepSize: 1,
                        padding: 25,
                        callback: function (value, index) {
                            return days[index];
                        }
                    },
                    position: i18nRTL ? 'right' : 'left',
                    gridLines: {
                        color: xtools.application.chartGridColor
                    }
                }, {
                    ticks: {
                        min: 0,
                        max: 8,
                        stepSize: 1,
                        padding: 25,
                        callback: function (value, index) {
                            if (index === 0 || index > 7) {
                                return '';
                            }
                            return timeCardDatasets[index - 1].data.reduce(function (a, b) {
                                return a + parseInt(b.value, 10);
                            }, 0);
                        }
                    },
                    position: i18nRTL ? 'left' : 'right'
                }],
                xAxes: [{
                    ticks: {
                        beginAtZero: true,
                        min: 0,
                        max: 24,
                        stepSize: 1,
                        reverse: i18nRTL,
                        padding: 0,
                        callback: function (value) {
                            if (value % 2 === 0) {
                                return value + ":00";
                            } else {
                                return '';
                            }
                        }
                    },
                    gridLines: {
                        color: xtools.application.chartGridColor
                    }
                }]
            },
            tooltips: {
                displayColors: false,
                callbacks: {
                    title: function (items) {
                        return days[7 - items[0].yLabel + 1] + ' ' + parseInt(items[0].xLabel) + ':' + String(60*(items[0].xLabel%1)).padStart(2, '0');
                    },
                    label: function (item) {
                        var numEdits = [timeCardDatasets[item.datasetIndex].data[item.index].value];
                        return`${numEdits} ${$.i18n('num-edits', [numEdits])}`;
                    }
                }
            }
        }
    });

    $(function () {
        $('.use-local-time')
            .prop('checked', false)
            .on('click', function () {
                var offset = $(this).is(':checked') ? timezoneOffset : -timezoneOffset;
                chart.data.datasets = chart.data.datasets.map(function (day) {
                    day.data = day.data.map(function (datum) {
                        var newHour = (parseFloat(datum.hour) - offset);
                        var newDay = parseInt(datum.day_of_week, 10);
                        if (newHour < 0) {
                            newHour = 24 + newHour;
                            newDay = newDay - 1;
                            if (newDay < 1) {
                                newDay = 7 + newDay;
                            }
                        } else if (newHour >= 24) {
                            newHour = newHour - 24;
                            newDay = newDay + 1;
                            if (newDay > 7) {
                                newDay = newDay - 7;
                            }
                        }
                        datum.hour = newHour.toString();
                        datum.x = newHour.toString();
                        datum.day_of_week = newDay.toString();
                        datum.y = (8-newDay).toString();
                        return datum;
                    });
                    return day;
                });
                useLocalTimezone = true;
                chart.update();
            });
    });
}
