xtools.autoedits = {};

$(function () {
    if (!$('body.autoedits').length) {
        return;
    }

    xtools.application.setupToggleTable(window.countsByTool, window.toolsChart, 'count', function (newData) {
        var total = 0;
        Object.keys(newData).forEach(function (tool) {
            total += parseInt(newData[tool].count, 10);
        });
        var toolsCount = Object.keys(newData).length;
        /** global: i18nLang */
        $('.tools--tools').text(
            toolsCount.toLocaleString(i18nLang) + " " +
            $.i18n('num-tools', toolsCount)
        );
        $('.tools--count').text(total.toLocaleString(i18nLang));
    });

    if ($('.contributions-container').length) {
        // Load the contributions browser, or set up the listeners if it is already present.
        var initFunc = $('.contributions-table').length ? 'setupContributionsNavListeners' : 'loadContributions';
        xtools.application[initFunc](
            function (params) {
                return params.target + '-contributions/' + params.project + '/' + params.username + '/' +
                    params.namespace + '/' + params.start + '/' + params.end;
            },
            $('.contributions-container').data('target')
        );
    }
});
