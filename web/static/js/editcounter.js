(function () {
    $(document).ready(function () {

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
        var $latestGlobalContainer = $("#latestglobal-container");
        var url = xtBaseUrl + 'ec-latestglobal/'
            + $latestGlobalContainer.data("project") + '/'
            + $latestGlobalContainer.data("username") + '?htmlonly=yes';
        $.ajax({
            url: url,
            timeout: 30000
        }).done(function (data) {
            $latestGlobalContainer.replaceWith(data);
        }).fail(function (data) {
        });

    });
} )();
