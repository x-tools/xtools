$(function () {
    // Don't execute this code if we're not on the Pages tool
    // FIXME: find a way to automate this somehow...
    if (!$('body.pages').length) {
        return;
    }

    setupToggleTable(window.countsByNamespace, window.pieChart, 'total', function (newData) {
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
    });
});
