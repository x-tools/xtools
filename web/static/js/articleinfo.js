$(function () {
    if (!$('body.articleinfo').length) {
        return;
    }

    var setupToggleTable = function () {
        window.setupToggleTable(
            window.textshares,
            window.textsharesChart,
            'percentage',
            $.noop
        );
    };

    var $textsharesContainer = $('.textshares-container');

    if ($textsharesContainer[0]) {
        /** global: xtBaseUrl */
        var url = xtBaseUrl + 'articleinfo-authorship/'
            + $textsharesContainer.data('project') + '/'
            + $textsharesContainer.data('article') + '?htmlonly=yes';

        $.ajax({
            url: url,
            timeout: 30000
        }).done(function (data) {
            $textsharesContainer.replaceWith(data);
            buildSectionOffsets();
            setupTocListeners();
            setupColumnSorting();
            setupToggleTable();
        }).fail(function (_xhr, _status, message) {
            $textsharesContainer.replaceWith(
                $.i18n('api-error', 'Authorship API: <code>' + message + '</code>')
            );
        });
    } else if ($('.textshares-table').length) {
        setupToggleTable();
    }
});
