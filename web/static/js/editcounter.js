(function () {
    $(document).ready(function () {

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

    });
} )();
