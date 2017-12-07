$(function () {
    // Don't execute this code if we're not on the Pages tool
    // FIXME: find a way to automate this somehow...
    if (!$('body.pages').length) {
        return;
    }

    setupToggleTable(window.countsByNamespace, window.pieChart, 'count', function (newData) {
        var totals = {
            count: 0,
            deleted: 0,
            redirects: 0,
        };
        Object.keys(newData).forEach(function (ns) {
            totals.count += newData[ns].count;
            totals.deleted += newData[ns].deleted;
            totals.redirects += newData[ns].redirects;
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
            totals.redirects.toLocaleString() + " (" +
            ((totals.redirects / totals.count) * 100).toFixed(1) + "%)"
        );
    });
});
