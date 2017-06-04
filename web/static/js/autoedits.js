$(function () {
    var newData, editOffset = 0;

    $('.tools-table').on('click', '.tools-toggle', function () {
        if (!newData) {
            // countsByTool must be cloned
            newData = Object.assign({}, window.countsByTool);
        }

        var index = $(this).data('index'),
            tool = $(this).data('tool'),
            $row = $(this).parents('tr');

        // must use .attr instead of .prop as sorting script will clone DOM elements
        if ($(this).attr('data-disabled') === 'true') {
            newData[tool] = window.countsByTool[tool];
            window.toolsChart.data.datasets[0].data[index] = parseInt(newData[tool], 10);
            $(this).attr('data-disabled', 'false');
        } else {
            delete newData[tool];
            window.toolsChart.data.datasets[0].data[index] = null;
            $(this).attr('data-disabled', 'true');
        }

        // gray out row in table
        $(this).parents('tr').toggleClass('excluded');

        // change the hover icon from a 'x' to a '+'
        $(this).find('.glyphicon').toggleClass('glyphicon-remove').toggleClass('glyphicon-plus');

        // update stats
        var total = 0;
        Object.keys(newData).forEach(function (tool) {
            total += parseInt(newData[tool], 10);
        });
        var toolsCount = Object.keys(newData).length;
        $('.tools--tools').text(
            toolsCount.toLocaleString() + " " +
            $.i18n('num-tools', toolsCount)
        );
        $('.tools--count').text(total.toLocaleString());

        window.toolsChart.update();
    });

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
        var username = $('.non-auto-edits-container').data('username');

        $.getJSON(xtBaseUrl + 'api/nonautomated_edits/en.wikipedia.org/' + username + '/all/' + editOffset).then(function (data) {
            $('.non-auto-edits-container').html(data.markup).show();
            $('.non-auto-edits-loading').hide();
            setupNavListeners();

            if (editOffset > 0) {
                $('.prev-edits').show();
            }
            if ($('.contribs-table tbody tr').length < 50) {
                $('.next-edits').hide();
            }
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
