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
        new Chart($ctx, {
            type: chartType,
            data: {
                labels: labels,
                datasets: [ { data: data } ]
            }
        });
    });

    // Load recent global edits' HTML via Ajax, to not slow down the initial page load.
    // Only load if container is present, which is missing subroutes, e.g. ec-namespacetotals, etc.
    var $latestGlobalContainer = $("#latestglobal-container");
    if ($latestGlobalContainer[0]) {
        var url = xtBaseUrl + 'ec-latestglobal/'
            + $latestGlobalContainer.data("project") + '/'
            + $latestGlobalContainer.data("username") + '?htmlonly=yes';
        $.ajax({
            url: url,
            timeout: 30000
        }).done(function (data) {
            $latestGlobalContainer.replaceWith(data);
        }).fail(function (_xhr, _status, message) {
            $latestGlobalContainer.replaceWith(
                $.i18n('api-error', 'Global contributions API: <code>' + message + '</code>')
            );
        });
    }

    // Set up namespace toggle chart
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
