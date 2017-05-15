(function () {
    $(document).ready(function () {
        var newData;

        $('.namespaces-table').on('click', '.namespace-toggle', function () {
            if (!newData) {
                // countsByNamespace must be cloned
                newData = Object.assign({}, window.countsByNamespace);
            }

            var index = $(this).data('index') - 1,
                ns = $(this).data('ns'),
                $row = $(this).parents('tr');

            // must use .attr instead of .prop as sorting script will clone DOM elements
            if ($(this).attr('data-disabled') === 'true') {
                newData[ns] = window.countsByNamespace[ns];
                window.pieChart.data.datasets[0].data[index] = newData[ns].total;
                $(this).attr('data-disabled', 'false');
            } else {
                delete newData[ns];
                window.pieChart.data.datasets[0].data[index] = null;
                $(this).attr('data-disabled', 'true');
            }

            // gray out row in table
            $(this).parents('tr').toggleClass('excluded');

            // change the hover icon from a 'x' to a '+'
            $(this).find('.glyphicon').toggleClass('glyphicon-remove').toggleClass('glyphicon-plus');

            // update stats
            var totals = {
                count: 0,
                deleted: 0,
                redirect: 0,
            };
            Object.keys(newData).forEach(function (ns) {
                totals.count += newData[ns].total;
                totals.deleted += newData[ns].deleted;
                totals.redirect += newData[ns].redirect;
            });
            $('.namespaces--namespaces').text(
                Object.keys(newData).length.toLocaleString() + " " +
                $.i18n(
                    'num-namespaces',
                    Object.keys(newData).length,
                )
            );
            $('.namespaces--pages').text(totals.count.toLocaleString());
            $('.namespaces--deleted').text(
                totals.deleted.toLocaleString() + " (" +
                ((totals.deleted / totals.count) * 100).toFixed(1) + "%)"
            );
            $('.namespaces--redirects').text(
                totals.redirect.toLocaleString() + " (" +
                ((totals.redirect / totals.count) * 100).toFixed(1) + "%)"
            );

            window.pieChart.update();
        });
    });
})();
