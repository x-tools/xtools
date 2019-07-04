xtools.articleinfo = {};

$(function () {
    if (!$('body.articleinfo').length) {
        return;
    }

    var setupToggleTable = function () {
        xtools.application.setupToggleTable(
            window.textshares,
            window.textsharesChart,
            'percentage',
            $.noop
        );
    };

    var $textsharesContainer = $('.textshares-container');

    if ($textsharesContainer[0]) {
        /** global: xtBaseUrl */
        var url = xtBaseUrl + 'authorship/'
            + $textsharesContainer.data('project') + '/'
            + $textsharesContainer.data('article') + '/'
            + (xtools.articleinfo.endDate ? xtools.articleinfo.endDate + '/' : '');
        // Remove extraneous forward slash that would cause a 301 redirect, and request over HTTP instead of HTTPS.
        url = `${url.replace(/\/$/, '')}?htmlonly=yes`;

        $.ajax({
            url: url,
            timeout: 30000
        }).done(function (data) {
            $textsharesContainer.replaceWith(data);
            xtools.application.buildSectionOffsets();
            xtools.application.setupTocListeners();
            xtools.application.setupColumnSorting();
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
