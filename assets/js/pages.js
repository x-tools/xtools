xtools.pages = {};

$(function () {
    // Don't execute this code if we're not on the Pages tool
    // FIXME: find a way to automate this somehow...
    if (!$('body.pages').length) {
        return;
    }

    var deletionSummaries = {};

    xtools.application.setupToggleTable(window.countsByNamespace, window.pieChart, 'count', function (newData) {
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

    $('.deleted-page').on('mouseover', function (e) {
        var page = $(this).data('page'),
            startTime = $(this).data('datetime').toString().slice(0, -2);

        var showSummary = function (summary) {
            $(e.target).find('.tooltip-body').html(summary);
        };

        if (deletionSummaries[page] !== undefined) {
            return showSummary(deletionSummaries[page]);
        }

        var logEventsQuery = function (action) {
            return $.ajax({
                url: wikiApi,
                data: {
                    action: 'query',
                    list: 'logevents',
                    letitle: page,
                    lestart: startTime,
                    letype: 'delete',
                    leaction: action || 'delete/delete',
                    lelimit: 1,
                    format: 'json'
                },
                dataType: 'jsonp'
            })
        };

        var showParserApiFailure = function () {
            return showSummary("<span class='text-danger'>" + $.i18n('api-error', 'Parser API') + "</span>");
        };

        var showLoggingApiFailure = function () {
            return showSummary("<span class='text-danger'>" + $.i18n('api-error', 'Logging API') + "</span>");
        };

        var showParsedWikitext = function (event) {
            return $.ajax({
                url: xtBaseUrl + 'api/project/parser/' + wikiDomain + '?wikitext=' + encodeURIComponent(event.comment)
            }).done(function (markup) {
                // Get timestamp in YYYY-MM-DD HH:MM format.
                var timestamp = new Date(event.timestamp)
                    .toISOString()
                    .slice(0, 16)
                    .replace('T', ' ');

                // Add timestamp and link to admin.
                var summary = timestamp + " (<a target='_blank' href='https://" + wikiDomain +
                    "/wiki/User:" + event.user + "'>" + event.user + '</a>): <i>' + markup + '</i>';

                deletionSummaries[page] = summary;
                showSummary(summary);
            }).fail(showParserApiFailure);
        };

        logEventsQuery().done(function (resp) {
            var event = resp.query.logevents[0];

            if (!event) {
                // Try again but look for redirect deletions.
                return logEventsQuery('delete/delete_redir').done(function (resp) {
                    event = resp.query.logevents[0];

                    if (!event) {
                        return showParserApiFailure();
                    }

                    showParsedWikitext(event);
                }).fail(showLoggingApiFailure);
            }

            showParsedWikitext(event);
        }).fail(showLoggingApiFailure);
    });
});
