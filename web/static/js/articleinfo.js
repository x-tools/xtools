(function () {
    var colors = [
        'rgba(171, 212, 235, 1)',
        'rgba(178, 223, 138, 1)',
        'rgba(251, 154, 153, 1)',
        'rgba(253, 191, 111, 1)',
        'rgba(202, 178, 214, 1)',
        'rgba(207, 182, 128, 1)',
        'rgba(141, 211, 199, 1)',
        'rgba(252, 205, 229, 1)',
        'rgba(255, 247, 161, 1)',
        'rgba(217, 217, 217, 1)',
    ];

    $(document).ready(function () {
        if (!$('body').hasClass('articleinfo')) {
            return;
        }

        // GENERAL: User/IPs chart
        var usersCount = general.revision_count - general.anon_count,
            usersPercentage = 100 - general.anon_percentage;
        buildPieChart('users_ips', {
            data: [usersCount, general.anon_count],
            labels: general.labels.users_ips,
            legendLabels: [
                general.labels.users_ips[0] + ": " + usersCount + " (" + usersPercentage + "%)",
                general.labels.users_ips[1] + ": " + general.anon_count + " (" + general.anon_percentage + "%)",
            ]
        });

        // GENERAL: Minor/major edits chart
        var majorCount = general.revision_count - general.minor_count,
            majorPercentage = 100 - general.minor_percentage;
        buildPieChart('minor_major', {
            data: [general.minor_count, majorCount],
            labels: general.labels.minor_major,
            legendLabels: [
                general.labels.minor_major[0] + ": " + majorCount + " (" + majorPercentage + "%)",
                general.labels.minor_major[1] + ": " + general.minor_count + " (" + general.minor_percentage + "%)",
            ]
        });

        // GENERAL: Top 10% / bottom 90% chart
        var bottomTenCount = general.revision_count - general.top_ten_count,
            bottomTenPercentage = 100 - general.top_ten_percentage;
        buildPieChart('top_bottom', {
            data: [general.top_ten_count, bottomTenCount],
            labels: general.labels.top_bottom,
            legendLabels: [
                general.labels.top_bottom[0] + ": " + general.top_ten_count + " (" + general.top_ten_percentage + "%)",
                general.labels.top_bottom[1] + ": " + bottomTenCount + " (" + bottomTenPercentage + "%)",
            ]
        });

        // TOP EDITORS: Top 10 by number of edits chart
        var topEditors = Object.keys(editors).slice(0, 9),
            topByEditsData = [],
            topByEditsLegendLabels = [];
            topEditCountSum = 0;
        topEditors.forEach(function (editor) {
            var editCount = editors[editor].all;
            topEditCountSum += editCount;
            var percentage = ((editCount / general.revision_count) * 100).toFixed(1);
            topByEditsData.push(editCount);
            topByEditsLegendLabels.push(
                editor + " · " + editCount + " (" + percentage + "%)"
            );
        });
        topByEditsData.push(general.revision_count - topEditCountSum);
        topByEditsLegendLabels.push(i18n.others);
        buildPieChart('top_by_edits', {
            data: topByEditsData,
            labels: topEditors.concat([i18n.others]),
            legendLabels: topByEditsLegendLabels,
        });

        // TOP EDITORS: Top 10 by added text chart
        var topByAddedData = [],
            topByAddedLegendLabels = [];
            topByAddedSum = 0;
        topEditors.forEach(function (editor) {
            var added = Math.abs(editors[editor].added);
            topByAddedSum += added;
            // var percentage = ((editCount / general.revision_count) * 100).toFixed(1);
            topByAddedData.push(added);
            topByAddedLegendLabels.push(
                editor + " · " + added // + " (" + percentage + "%)"
            );
        });
        // topByAddedData.push(general.revision_count - topByAddedSum);
        // topByAddedLegendLabels.push(i18n.others);
        buildPieChart('top_by_added', {
            data: topByAddedData,
            labels: topEditors, // .concat([i18n.others]),
            legendLabels: topByAddedLegendLabels,
        });

        // YEAR COUNTS: chart of all/minor/anon edits and page size year to year
        var yearCountLabels = Object.keys(year_count);
        var yearDatasets = [{
            type: 'bar',
            label: i18n.all,
            backgroundColor: colors[0],
            data: [],
            yAxisID: 'edits',
        },
        {
            type: 'bar',
            label: i18n.minor,
            backgroundColor: colors[1],
            data: [],
            yAxisID: 'edits',
        },
        {
            type: 'bar',
            label: i18n.anon,
            backgroundColor: colors[2],
            data: [],
            yAxisID: 'edits',
        },
        {
            type: 'line',
            label: i18n.size,
            borderColor: colors[3],
            backgroundColor: colors[3],
            fill: false,
            data: [],
            yAxisID: 'size',
        }];

        // restructure data the way Chart.js wants it
        yearCountLabels.forEach(function (yearLabel) {
            yearDatasets[0].data.push(year_count[yearLabel].all);
            yearDatasets[1].data.push(year_count[yearLabel].minor);
            yearDatasets[2].data.push(year_count[yearLabel].anon);
            yearDatasets[3].data.push(year_count[yearLabel].size);
        });
        buildMultiBarChart('year_count', {
            datasets: yearDatasets,
            labels: yearCountLabels,
            yAxesLabel: i18n.edits,
            yAxesLabelRight: i18n.size,
        });
    });

    function buildMultiBarChart(id, data)
    {
        var ctx = $('#' + id),
        var chartData = {
            labels: data.labels,
            datasets: data.datasets
        };

        var chartObj = new Chart(ctx, {
            type: 'bar',
            data: chartData,
            options: {
                legend: {
                    display: false,
                },
                tooltips: {
                    mode: 'label'
                },
                barValueSpacing: 20,
                scales: {
                    yAxes: [{
                        id: 'edits',
                        type: 'linear',
                        position: 'left',
                        gridLines:{
                            display: false
                        },
                        scaleLabel: {
                            display: true,
                            labelString: data.yAxesLabel
                        }
                    }, {
                        id: 'size',
                        type: 'linear',
                        position: 'right',
                        gridLines:{
                            display: false
                        },
                        scaleLabel: {
                            display: true,
                            labelString: data.yAxesLabelRight
                        }
                    }],
                },
                legendCallback: function () {
                    var markup = "<div class='legend-body'>";
                    var legendLabels = data.datasets.map(function (dataset) {
                        return dataset.label;
                    });
                    legendLabels.forEach(function (legendLabel, i) {
                        markup += "<div><span class='color-icon' style='background:" +
                            colors[i] + "'></span>" + legendLabel + "</div>";
                    });

                    return markup + '</div>';
                },
            }
        });
        $('#' + id + '_legend').html(chartObj.generateLegend());
    }

    function buildPieChart(id, data)
    {
        var ctx = $('#' + id);
        var chartData = {
            labels: data.labels,
            datasets: [{
                data: data.data,
            }],
        };

        var numEntries = data.labels.length;

        chartData.datasets[0].backgroundColor = colors.slice(0, numEntries);
        chartData.datasets[0].hoverBackgroundColor = colors.slice(0, numEntries);

        var chartObj = new Chart(ctx, {
            type: 'pie',
            data: chartData,
            options: Object.assign({
                responsive: true,
                legend: {
                    display: false,
                },
                legendCallback: function () {
                    var markup = "<div class='legend-body'>";
                    data.legendLabels.forEach(function (legendLabel, i) {
                        var value = data.data[i],
                            legendLabel = data.legendLabels[i];
                        markup += "<div><span class='color-icon' style='background:" +
                            colors[i] + "'></span>" + legendLabel + "</div>";
                    });

                    return markup + '</div>';
                },
            }, (data.options || {})),
        });
        $('#' + id + '_legend').html(chartObj.generateLegend());
    }
})();
