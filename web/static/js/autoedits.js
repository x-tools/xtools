(function () {
    $(document).ready(function () {
        var newData;

        $('.tools-table').on('click', '.tools-toggle', function () {
            if (!newData) {
                // countsByTool must be cloned
                newData = Object.assign({}, window.countsByTool);
            }

            var index = $(this).data('index'),
                tool = $(this).data('tool'),
                $row = $(this).parents('tr');

            // must use .attr instead of .prop as sorting script will clone DOM elements
            if ($(this).attr('data-disabled') === 'true') {
                newData[tool] = window.countsByTool[tool];
                window.toolsChart.data.datasets[0].data[index] = parseInt(newData[tool], 10);
                $(this).attr('data-disabled', 'false');
            } else {
                delete newData[tool];
                window.toolsChart.data.datasets[0].data[index] = null;
                $(this).attr('data-disabled', 'true');
            }

            // gray out row in table
            $(this).parents('tr').toggleClass('excluded');

            // change the hover icon from a 'x' to a '+'
            $(this).find('.glyphicon').toggleClass('glyphicon-remove').toggleClass('glyphicon-plus');

            // update stats
            var total = 0;
            Object.keys(newData).forEach(function (tool) {
                total += parseInt(newData[tool], 10);
            });
            var toolsCount = Object.keys(newData).length;
            $('.tools--tools').text(
                toolsCount.toLocaleString() + " " +
                $.i18n('num-tools', toolsCount)
            );
            $('.tools--count').text(total.toLocaleString());

            window.toolsChart.update();
        });
    });
})();
