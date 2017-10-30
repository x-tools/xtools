$(function () {
    var editOffset = 0;

    if ($('body.autoedits').length) {
        setupToggleTable(window.countsByTool, window.toolsChart, 'count', function (newData) {
            var total = 0;
            Object.keys(newData).forEach(function (tool) {
                total += parseInt(newData[tool].count, 10);
            });
            var toolsCount = Object.keys(newData).length;
            $('.tools--tools').text(
                toolsCount.toLocaleString() + " " +
                $.i18n('num-tools', toolsCount)
            );
            $('.tools--count').text(total.toLocaleString());
        });
    }

    // This file gets loaded on every page, so only try to load
    //   non-automated edits if the container is in the DOM
    if ($('.non-auto-edits-container')[0]) {
        loadNonAutoedits();
    }

    /**
     * Loads non-automated edits from the server and lists them in the DOM.
     * The navigation aids and showing/hiding of loading text is also handled here.
     */
    function loadNonAutoedits()
    {
        $('.non-auto-edits-loading').show();
        $('.non-auto-edits-container').hide();
        var project = $('.non-auto-edits-container').data('project'),
            username = $('.non-auto-edits-container').data('username'),
            start = $('.non-auto-edits-container').data('start'),
            end = $('.non-auto-edits-container').data('end'),
            namespace = $('.non-auto-edits-container').data('namespace');

        /** global: xtBaseUrl */
        $.ajax({
            url: xtBaseUrl + 'api/user/nonautomated_edits/' + project + '/' + username + '/' +
                namespace + '/' + start + '/' + end + '/' + editOffset + '?format=html',
            timeout: 30000
        }).done(function (data) {
            $('.non-auto-edits-container').html(data).show();
            $('.non-auto-edits-loading').hide();
            setupNavListeners();

            if (editOffset > 0) {
                $('.prev-edits').show();
            }
            if ($('.contribs-table tbody tr').length < 50) {
                $('.next-edits').hide();
            }
        }).fail(function (_xhr, _status, message) {
            $('.non-auto-edits-loading').hide();
            $('.non-auto-edits-container').html(
                $.i18n('api-error', 'Non-automated edits API: <code>' + message + '</code>')
            ).show();
        });
    }

    /**
     * Set up listeners for navigating non-automated contributions
     */
    function setupNavListeners()
    {
        $('.prev-edits').on('click', function (e) {
            e.preventDefault();
            editOffset -= 50;
            loadNonAutoedits()
        });

        $('.next-edits').on('click', function (e) {
            e.preventDefault();
            editOffset += 50;
            loadNonAutoedits();
        });
    }
});
