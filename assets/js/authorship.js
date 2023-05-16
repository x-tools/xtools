$(function () {
    if (!$('body.authorship').length) {
        return;
    }

    // For the form page.
    const $showSelector = $('#show_selector');
    $showSelector.on('change', e => {
        $('.show-option').addClass('hidden')
            .find('input').prop('disabled', true);
        $(`.show-option--${e.target.value}`).removeClass('hidden')
            .find('input').prop('disabled', false);
    });
    window.onload = () => $showSelector.trigger('change');

    if ($('#authorship_chart').length) {
        setupChart();
    }
});

function setupChart()
{
    const $chart = $('#authorship_chart'),
        percentages = Object.keys($chart.data('list')).slice(0, 10).map(author => {
            return $chart.data('list')[author].percentage;
        });

    // Add the "Others" slice if applicable.
    if ($chart.data('others')) {
        percentages.push($chart.data('others').percentage);
    }

    const authorshipChart = new Chart($chart, {
        type: 'pie',
        data: {
            labels: $chart.data('labels'),
            datasets: [{
                data: percentages,
                backgroundColor: $chart.data('colors'),
                borderColor: $chart.data('colors'),
                borderWidth: 1
            }]
        },
        options: {
            aspectRatio: 1,
            legend: {
                display: false
            },
            tooltips: {
                callbacks: {
                    label: function (tooltipItem, chartData) {
                        const label = chartData.labels[tooltipItem.index],
                            value = chartData.datasets[0].data[tooltipItem.index] / 100;
                        return label + ': ' + value.toLocaleString(i18nLang, {
                            style: 'percent',
                            maximumFractionDigits: 1
                        });
                    }
                }
            }
        }
    });
}
