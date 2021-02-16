Object.assign(xtools.application.vars, {
    initialOffset: '',
    offset: '',
    prevOffsets: [],
});

/**
 * Set the initial offset for contributions lists, based on what was
 * supplied in the contributions container.
 */
function setInitialOffset()
{
    if (!xtools.application.vars.offset) {
        // The initialOffset should be what was given via the .contributions-container.
        // This is used to determine if we're back on the first page or not.
        xtools.application.vars.initialOffset = $('.contributions-container').data('offset');
        // The offset will from here represent which page we're on, and is compared with
        // intitialEditOffset to know if we're on the first page.
        xtools.application.vars.offset = xtools.application.vars.initialOffset;
    }
}

/**
 * Loads configured type of contributions from the server and lists them in the DOM.
 * The navigation aids and showing/hiding of loading text is also handled here.
 * @param {function} endpointFunc The callback that takes the params set on .contributions-container
 *     and returns a string that is the endpoint to fetch from (without the offset appended).
 * @param {String} apiTitle The name of the API (could be i18n key), used in error reporting.
 */
xtools.application.loadContributions = function (endpointFunc, apiTitle) {
    setInitialOffset();

    $('.contributions-loading').show();
    $('.contributions-container').hide();

    var params = $('.contributions-container').data(),
        endpoint = endpointFunc(params),
        pageSize = parseInt(params.pagesize, 10) || 50;

    /** global: xtBaseUrl */
    $.ajax({
        url: xtBaseUrl + endpoint + '/' + xtools.application.vars.offset +
            // Make sure to include any URL parameters, such as tool=Huggle (for AutoEdits).
            '?htmlonly=yes&pagesize=' + pageSize + '&' + window.location.search.replace(/^\?/, ''),
        timeout: 60000
    }).done(function (data) {
        $('.contributions-container').html(data).show();
        $('.contributions-loading').hide();
        xtools.application.setupContributionsNavListeners(endpointFunc, apiTitle);

        if (xtools.application.vars.offset > xtools.application.vars.initialOffset) {
            $('.contributions--prev').show();
        }
        if ($('.contributions-table tbody tr').length < pageSize) {
            $('.next-edits').hide();
        }
    }).fail(function (_xhr, _status, message) {
        $('.contributions-loading').hide();
        $('.contributions-container').html(
            $.i18n('api-error', $.i18n(apiTitle) + ' API: <code>' + message + '</code>')
        ).show();
    });
};

/**
 * Set up listeners for navigating contribution lists.
 */
xtools.application.setupContributionsNavListeners = function (endpointFunc, apiTitle) {
    setInitialOffset();

    $('.contributions--prev').off('click').one('click', function (e) {
        e.preventDefault();
        xtools.application.vars.offset = xtools.application.vars.prevOffsets.pop()
            || xtools.application.vars.initialOffset;
        xtools.application.loadContributions(endpointFunc, apiTitle)
    });

    $('.contributions--next').off('click').one('click', function (e) {
        e.preventDefault();
        if (xtools.application.vars.offset) {
            xtools.application.vars.prevOffsets.push(xtools.application.vars.offset);
        }
        xtools.application.vars.offset = $('.contribs-row-date').last().data('value');
        xtools.application.loadContributions(endpointFunc, apiTitle);
    });
};
