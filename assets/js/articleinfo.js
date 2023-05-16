xtools.articleinfo = {};

$(function () {
    if (!$('body.articleinfo').length) {
        return;
    }

    const setupToggleTable = function () {
        xtools.application.setupToggleTable(
            window.textshares,
            window.textsharesChart,
            'percentage',
            $.noop
        );
    };

    const $textsharesContainer = $('.textshares-container');

    if ($textsharesContainer[0]) {
        /** global: xtBaseUrl */
        let url = xtBaseUrl + 'authorship/'
            + $textsharesContainer.data('project') + '/'
            + $textsharesContainer.data('article') + '/'
            + ($textsharesContainer.data('end-date') ? $textsharesContainer.data('end-date') + '/' : '');
        // Remove extraneous forward slash that would cause a 301 redirect, and request over HTTP instead of HTTPS.
        url = `${url.replace(/\/$/, '')}?htmlonly=yes`;

        $.ajax({
            url: url,
            timeout: 30000
        }).done(function (data) {
            $textsharesContainer.replaceWith(data);
            xtools.application.buildSectionOffsets();
            xtools.application.setupTocListeners();
            xtools.application.setupColumnSorting();
            setupToggleTable();
        }).fail(function (_xhr, _status, message) {
            $textsharesContainer.replaceWith(
                $.i18n('api-error', 'Authorship API: <code>' + message + '</code>')
            );
        });
    } else if ($('.textshares-table').length) {
        setupToggleTable();
    }

    // Setup the charts.
    const $chart = $('#year_count'),
        datasets = $chart.data('datasets');
    new Chart($chart, {
        type: 'bar',
        data: {
            labels: $chart.data('year-labels'),
            datasets,
        },
        options: {
            responsive: true,
            legend: {
                display: false,
            },
            tooltips: {
                mode: 'label',
                callbacks: {
                    label: function (tooltipItem) {
                        return datasets[tooltipItem.datasetIndex].label + ': '
                            + (Number(tooltipItem.yLabel)).toLocaleString(i18nLang);
                    }
                }
            },
            barValueSpacing: 20,
            scales: {
                yAxes: [{
                    id: 'edits',
                    type: 'linear',
                    position: 'left',
                    scaleLabel: {
                        display: true,
                        labelString: $.i18n('edits').capitalize(),
                    },
                    ticks: {
                        beginAtZero: true,
                        callback: function (value) {
                            if (Math.floor(value) === value) {
                                return value.toLocaleString(i18nLang);
                            }
                        }
                    },
                    gridLines: {
                        color: xtools.application.chartGridColor
                    }
                }, {
                    id: 'size',
                    type: 'linear',
                    position: 'right',
                    scaleLabel: {
                        display: true,
                        labelString: $.i18n('size').capitalize(),
                    },
                    ticks: {
                        beginAtZero: true,
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
                xAxes: [{
                    gridLines: {
                        color: xtools.application.chartGridColor
                    }
                }]
            },
        },
    });
});
