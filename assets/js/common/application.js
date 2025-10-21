xtools = {};
xtools.application = {};
xtools.application.vars = {
    sectionOffset: {},
};
xtools.application.chartGridColor = 'rgba(0, 0, 0, 0.1)';

if (window.matchMedia("(prefers-color-scheme: dark)").matches) {
    Chart.defaults.global.defaultFontColor = '#AAA';
    // Can't set a global default with our version of Chart.js, apparently,
    // so each chart initialization must explicitly set the grid line color.
    xtools.application.chartGridColor = '#333';
}

/** global: i18nLang */
/** global: i18nPaths */
$.i18n({
    locale: i18nLang
}).load(i18nPaths);

$(function () {
    // The $() around this code apparently isn't enough for Webpack, need another document-ready check.
    $(document).ready(function () {
        // TODO: move these listeners to a setup function and document how to use it.
        $('.xt-hide').on('click', function () {
            $(this).hide();
            $(this).siblings('.xt-show').show();

            if ($(this).parents('.panel-heading').length) {
                $(this).parents('.panel-heading').siblings('.panel-body').hide();
            } else {
                $(this).parents('.xt-show-hide--parent').next('.xt-show-hide--target').hide();
            }
        });
        $('.xt-show').on('click', function () {
            $(this).hide();
            $(this).siblings('.xt-hide').show();

            if ($(this).parents('.panel-heading').length) {
                $(this).parents('.panel-heading').siblings('.panel-body').show();
            } else {
                $(this).parents('.xt-show-hide--parent').next('.xt-show-hide--target').show();
            }
        });

        setupNavCollapsing();

        xtools.application.setupColumnSorting();
        setupTOC();
        setupStickyHeader();
        setupProjectListener();
        setupAutocompletion();
        displayWaitingNoticeOnSubmission();
        setupLinkLoadingNotices();

        // Allow to add focus to input elements with i.e. ?focus=username
        if ('function' === typeof URL) {
            const focusElement = new URL(window.location.href)
                .searchParams
                .get('focus');
            if (focusElement) {
                $(`[name=${focusElement}]`).focus();
            }
        }
    });

    // Re-init forms, workaround for issues with Safari and Firefox.
    // See displayWaitingNoticeOnSubmission() for more.
    window.onpageshow = function (e) {
        if (e.persisted) {
            displayWaitingNoticeOnSubmission(true);
            setupLinkLoadingNotices(true);
        }
    };
});

/**
 * Script to make interactive toggle table and pie chart.
 * For visual example, see the "Semi-automated edits" section of the AutoEdits tool.
 *
 * Example usage (see autoEdits/result.html.twig and js/autoedits.js for more):
 *     <table class="table table-bordered table-hover table-striped toggle-table">
 *         <thead>...</thead>
 *         <tbody>
 *             {% for tool, values in semi_automated %}
 *             <tr>
 *                 <!-- use the 'linked' class here because the cell contains a link -->
 *                 <td class="sort-entry--tool linked" data-value="{{ tool }}">
 *                     <span class="toggle-table--toggle" data-index="{{ loop.index0 }}" data-key="{{ tool }}">
 *                         <span class="glyphicon glyphicon-remove"></span>
 *                         <span class="color-icon" style="background:{{ chartColor(loop.index0) }}"></span>
 *                     </span>
 *                     {{ wiki.pageLink(...) }}
 *                 </td>
 *                 <td class="sort-entry--count" data-value="{{ values.count }}">
 *                     {{ values.count }}
 *                 </td>
 *             </tr>
 *             {% endfor %}
 *             ...
 *         </tbody>
 *     </table>
 *     <div class="toggle-table--chart">
 *         <canvas id="tool_chart" width="400" height="400"></canvas>
 *     </div>
 *     <script>
 *         window.toolsChart = new Chart($('#tool_chart'), { ... });
 *         window.countsByTool = {{ semi_automated | json_encode() | raw }};
 *         ...
 *
 *         // See autoedits.js for more
 *         xtools.application.setupToggleTable(window.countsByTool, window.toolsChart, 'count', function (newData) {
 *             // update the totals in toggle table based on newData
 *         });
 *     </script>
 *
 * @param  {Object}      dataSource  Object of data that makes up the chart
 * @param  {Chart}       chartObj    Reference to the pie chart associated with the .toggle-table
 * @param  {String|null} [valueKey]  The name of the key within entries of dataSource, where the value is
 *                                   what's shown in the chart. If omitted or null, `dataSource` is assumed
 *                                   to be of the structure: { 'a' => 123, 'b' => 456 }
 * @param  {Function} updateCallback Callback to update the .toggle-table totals. `toggleTableData`
 *                                   is passed in which contains the new data, you just need to
 *                                   format it (maybe need to use i18n, update multiple cells, etc.).
 *                                   The second parameter that is passed back is the 'key' of the toggled
 *                                   item, and the third is the index of the item.
 */
xtools.application.setupToggleTable = function (dataSource, chartObj, valueKey, updateCallback) {
    var toggleTableData;

    $('.toggle-table').on('click', '.toggle-table--toggle', function () {
        if (!toggleTableData) {
            // must be cloned
            toggleTableData = Object.assign({}, dataSource);
        }

        var index = $(this).data('index'),
            key = $(this).data('key');

        // must use .attr instead of .prop as sorting script will clone DOM elements
        if ($(this).attr('data-disabled') === 'true') {
            toggleTableData[key] = dataSource[key];
            if (chartObj) {
                chartObj.data.datasets[0].data[index] = (
                    parseInt(valueKey ? toggleTableData[key][valueKey] : toggleTableData[key], 10)
                );
            }
            $(this).attr('data-disabled', 'false');
        } else {
            delete toggleTableData[key];
            if (chartObj) {
                chartObj.data.datasets[0].data[index] = null;
            }
            $(this).attr('data-disabled', 'true');
        }

        // gray out row in table
        $(this).parents('tr').toggleClass('excluded');

        // change the hover icon from a 'x' to a '+'
        $(this).find('.glyphicon').toggleClass('glyphicon-remove').toggleClass('glyphicon-plus');

        // update stats
        updateCallback(toggleTableData, key, index);

        if (chartObj) {
            chartObj.update();
        }
    });
};

/**
 * If there are more tool links in the nav than will fit in the viewport, move the last entry to the More menu,
 * one at a time, until it all fits. This does not listen for window resize events.
 */
function setupNavCollapsing()
{
    var windowWidth = $(window).width(),
        toolNavWidth = $('.tool-links').outerWidth(),
        navRightWidth = $('.nav-buttons').outerWidth();

    // Ignore if in mobile responsive view
    if (windowWidth < 768) {
        return;
    }

    // Do this first so we account for the space the More menu takes up
    if (toolNavWidth + navRightWidth > windowWidth) {
        $('.tool-links--more').removeClass('hidden');
    }

    // Don't loop more than there are links in the nav.
    // This more just a safeguard against an infinite loop should something go wrong.
    var numLinks = $('.tool-links--entry').length;
    while (numLinks > 0 && toolNavWidth + navRightWidth > windowWidth) {
        // Remove the last tool link that is not the current tool being used
        var $link = $('.tool-links--nav > .tool-links--entry:not(.active)').last().remove();
        $('.tool-links--more .dropdown-menu').append($link);
        toolNavWidth = $('.tool-links').outerWidth();
        numLinks--;
    }
}

/**
 * Sorting of columns.
 *
 *  Example usage:
 *   {% for key in ['username', 'edits', 'minor', 'date'] %}
 *      <th>
 *         <span class="sort-link sort-link--{{ key }}" data-column="{{ key }}">
 *            {{ msg(key) | capitalize }}
 *            <span class="glyphicon glyphicon-sort"></span>
 *         </span>
 *      </th>
 *  {% endfor %}
 *   <th class="sort-link" data-column="username">Username</th>
 *   ...
 *   <td class="sort-entry--username" data-value="{{ username }}">{{ username }}</td>
 *   ...
 *
 * Data type is automatically determined, with support for integer,
 *   floats, and strings, including date strings (e.g. "2016-01-01 12:59")
 */
xtools.application.setupColumnSorting = function () {
    var sortDirection, sortColumn;

    $('.sort-link').on('click', function () {
        sortDirection = sortColumn === $(this).data('column') ? -sortDirection : 1;

        $('.sort-link .glyphicon').removeClass('glyphicon-sort-by-alphabet-alt glyphicon-sort-by-alphabet').addClass('glyphicon-sort');
        var newSortClassName = sortDirection === 1 ? 'glyphicon-sort-by-alphabet-alt' : 'glyphicon-sort-by-alphabet';
        $(this).find('.glyphicon').addClass(newSortClassName).removeClass('glyphicon-sort');

        sortColumn = $(this).data('column');
        var $table = $(this).parents('table');
        var $entries = $table.find('.sort-entry--' + sortColumn).parent();

        if (!$entries.length) {
            return;
        }

        $entries.sort(function (a, b) {
            var before = $(a).find('.sort-entry--' + sortColumn).data('value') || 0,
                after = $(b).find('.sort-entry--' + sortColumn).data('value') || 0;

            // Cast numerical strings into floats for faster sorting.
            if (!isNaN(before)) {
                before = parseFloat(before) || 0;
            }
            if (!isNaN(after)) {
                after = parseFloat(after) || 0;
            }

            if (before < after) {
                return sortDirection;
            } else if (before > after) {
                return -sortDirection;
            } else {
                return 0;
            }
        });

        // Re-fill the rank column, if applicable.
        if ($('.sort-entry--rank').length > 0) {
            $.each($entries, function (index, entry) {
                $(entry).find('.sort-entry--rank').text(index + 1);
            });
        }

        $table.find('tbody').html($entries);
    });
};

/**
 * Floating table of contents.
 *
 * Example usage (see pageInfo/result.html.twig for more):
 *     <p class="text-center xt-heading-subtitle">
 *         ...
 *     </p>
 *     <div class="text-center xt-toc">
 *         {% set sections = ['generalstats', 'usertable', 'yearcounts', 'monthcounts'] %}
 *         {% for section in sections %}
 *             <span>
 *                 <a href="#{{ section }}" data-section="{{ section }}">{{ msg(section) }}</a>
 *             </span>
 *         {% endfor %}
 *     </div>
 *     ...
 *     {% set content %}
 *         ...content for general stats...
 *     {% endset %}
 *     {{ layout.content_block('generalstats', content) }}
 *     ...
 */
function setupTOC()
{
    var $toc = $('.xt-toc');

    if (!$toc || !$toc[0]) {
        return;
    }

    xtools.application.vars.tocHeight = $toc.height();

    // listeners on the section links
    var setupTocListeners = function () {
        $('.xt-toc').find('a').off('click').on('click', function (e) {
            document.activeElement.blur();
            var $newSection = $('#' + $(e.target).data('section'));
            $(window).scrollTop($newSection.offset().top - xtools.application.vars.tocHeight);

            $(this).parents('.xt-toc').find('a').removeClass('bold');

            createTocClone();
            xtools.application.vars.$tocClone.addClass('bold');
        });
    };
    xtools.application.setupTocListeners = setupTocListeners;

    // clone the TOC and add position:fixed
    var createTocClone = function () {
        if (xtools.application.vars.$tocClone) {
            return;
        }
        xtools.application.vars.$tocClone = $toc.clone();
        xtools.application.vars.$tocClone.addClass('fixed');
        $toc.after(xtools.application.vars.$tocClone);
        setupTocListeners();
    };

    // build object containing offsets of each section
    xtools.application.buildSectionOffsets = function () {
        $.each($toc.find('a'), function (index, tocMember) {
            var id = $(tocMember).data('section');
            xtools.application.vars.sectionOffset[id] = $('#' + id).offset().top;
        });
    };

    // rebuild section offsets when sections are shown/hidden
    $('.xt-show, .xt-hide').on('click', xtools.application.buildSectionOffsets);

    xtools.application.buildSectionOffsets();
    setupTocListeners();

    var tocOffsetTop = $toc.offset().top;
    $(window).on('scroll.toc', function (e) {
        var windowOffset = $(e.target).scrollTop();
        var inRange = windowOffset > tocOffsetTop;

        if (inRange) {
            if (!xtools.application.vars.$tocClone) {
                createTocClone();
            }

            // bolden the link for whichever section we're in
            var $activeMember;
            Object.keys(xtools.application.vars.sectionOffset).forEach(function (section) {
                if (windowOffset > xtools.application.vars.sectionOffset[section] - xtools.application.vars.tocHeight - 1) {
                    $activeMember = xtools.application.vars.$tocClone.find('a[data-section="' + section + '"]');
                }
            });
            xtools.application.vars.$tocClone.find('a').removeClass('bold');
            if ($activeMember) {
                $activeMember.addClass('bold');
            }
        } else if (!inRange && xtools.application.vars.$tocClone) {
            // remove the clone once we're out of range
            xtools.application.vars.$tocClone.remove();
            xtools.application.vars.$tocClone = null;
        }
    });
}

/**
 * Make any tables with the class 'table-sticky-header' have sticky headers.
 * E.g. as you scroll the heading row will be fixed at the top for reference.
 */
function setupStickyHeader()
{
    var $header = $('.table-sticky-header');

    if (!$header || !$header[0]) {
        return;
    }

    var $headerRow = $header.find('thead tr').eq(0),
        $headerClone;

    // Make a clone of the header to maintain placement of the original header,
    // making the original header the sticky one. This way event listeners on it
    // (such as column sorting) will still work.
    var cloneHeader = function () {
        if ($headerClone) {
            return;
        }

        $headerClone = $headerRow.clone();
        $headerRow.addClass('sticky-heading');
        $headerRow.before($headerClone);

        // Explicitly set widths of each column, which are lost with position:absolute.
        $headerRow.find('th').each(function (index) {
            $(this).css('width', $headerClone.find('th').eq(index).outerWidth());
        });
        $headerRow.css('width', $headerClone.outerWidth() + 1);
    };

    var headerOffsetTop = $header.offset().top;
    $(window).on('scroll.stickyHeader', function (e) {
        var windowOffset = $(e.target).scrollTop();
        var inRange = windowOffset > headerOffsetTop;

        if (inRange && !$headerClone) {
            cloneHeader();
        } else if (!inRange && $headerClone) {
            // Remove the clone once we're out of range,
            // and make the original un-sticky.
            $headerRow.removeClass('sticky-heading');
            $headerClone.remove();
            $headerClone = null;
        } else if ($headerClone) {
            // The header is position:absolute so it will follow with X scrolling,
            // but for Y we must go by the window scroll position.
            $headerRow.css(
                'top',
                $(window).scrollTop() - $header.offset().top
            );
        }
    });
}

/**
 * Add listener to the project input field to update any namespace selectors and autocompletion fields.
 */
function setupProjectListener()
{
    var $projectInput = $('#project_input');

    // Stop here if there is no project field
    if (!$projectInput) {
        return;
    }

    // If applicable, setup namespace selector with real time updates when changing projects.
    // This will also set `apiPath` so that autocompletion will query the right wiki.
    if ($projectInput.length && $('#namespace_select').length) {
        setupNamespaceSelector();
        // Otherwise, if there's a user or page input field, we still need to update `apiPath`
        // for the user input autocompletion when the project is changed.
    } else if ($('#user_input')[0] || $('#page_input')[0]) {
        // keep track of last valid project
        xtools.application.vars.lastProject = $projectInput.val();

        $projectInput.on('change', function () {
            var newProject = this.value;

            /** global: xtBaseUrl */
            $.get(xtBaseUrl + 'api/project/normalize/' + newProject).done(function (data) {
                // Keep track of project API path for use in page title autocompletion
                xtools.application.vars.apiPath = data.api;
                xtools.application.vars.lastProject = newProject;
                setupAutocompletion();

                // Other pages may listen for this custom event.
                $projectInput.trigger('xtools.projectLoaded', data);
            }).fail(
                revertToValidProject.bind(this, newProject)
            );
        });
    }
}

/**
 * Use the wiki input field to populate the namespace selector.
 * This also updates `apiPath` and calls setupAutocompletion().
 */
function setupNamespaceSelector()
{
    // keep track of last valid project
    xtools.application.vars.lastProject = $('#project_input').val();

    $('#project_input').off('change').on('change', function () {
        // Disable the namespace selector and show a spinner while the data loads.
        $('#namespace_select').prop('disabled', true);

        var newProject = this.value;

        /** global: xtBaseUrl */
        $.get(xtBaseUrl + 'api/project/namespaces/' + newProject).done(function (data) {
            // Clone the 'all' option (even if there isn't one),
            // and replace the current option list with this.
            var $allOption = $('#namespace_select option[value="all"]').eq(0).clone();
            $("#namespace_select").html($allOption);

            // Keep track of project API path for use in page title autocompletion.
            xtools.application.vars.apiPath = data.api;

            // Add all of the new namespace options.
            for (var ns in data.namespaces) {
                if (!data.namespaces.hasOwnProperty(ns)) {
                    continue; // Skip keys from the prototype.
                }

                var nsName = parseInt(ns, 10) === 0 ? $.i18n('mainspace') : data.namespaces[ns];
                $('#namespace_select').append(
                    "<option value=" + ns + ">" + nsName + "</option>"
                );
            }
            // Default to mainspace being selected.
            $("#namespace_select").val(0);
            xtools.application.vars.lastProject = newProject;

            // Re-init autocompletion
            setupAutocompletion();
        }).fail(revertToValidProject.bind(this, newProject)).always(function () {
            $('#namespace_select').prop('disabled', false);
        });
    });

    // If they change the namespace, update autocompletion,
    // which will ensure only pages in the selected namespace
    // show up in the autocompletion
    $('#namespace_select').on('change', setupAutocompletion);
}

/**
 * Called by setupNamespaceSelector or setupProjectListener when the user changes to a project that doesn't exist.
 * This throws a warning message and reverts back to the last valid project.
 * @param {string} newProject - project they attempted to add
 */
function revertToValidProject(newProject)
{
    $('#project_input').val(xtools.application.vars.lastProject);
    $('.site-notice').append(
        "<div class='alert alert-warning alert-dismissible' role='alert'>" +
        $.i18n('invalid-project', "<strong>" + newProject + "</strong>") +
        "<button class='close' data-dismiss='alert' aria-label='Close'>" +
        "<span aria-hidden='true'>&times;</span>" +
        "</button>" +
        "</div>"
    );
}

/**
 * Setup autocompletion of pages if a page input field is present.
 */
function setupAutocompletion()
{
    var $pageInput = $('#page_input'),
        $userInput = $('#user_input'),
        $namespaceInput = $("#namespace_select");

    // Make sure typeahead-compatible fields are present
    if (!$pageInput[0] && !$userInput[0] && !$('#project_input')[0]) {
        return;
    }

    // Destroy any existing instances
    if ($pageInput.data('typeahead')) {
        $pageInput.data('typeahead').destroy();
    }
    if ($userInput.data('typeahead')) {
        $userInput.data('typeahead').destroy();
    }

    // set initial value for the API url, which is put as a data attribute in forms.html.twig
    if (!xtools.application.vars.apiPath) {
        xtools.application.vars.apiPath = $('#page_input').data('api') || $('#user_input').data('api');
    }

    // Defaults for typeahead options. preDispatch and preProcess will be
    // set accordingly for each typeahead instance
    var typeaheadOpts = {
        url: xtools.application.vars.apiPath,
        timeout: 200,
        triggerLength: 1,
        method: 'get',
        preDispatch: null,
        preProcess: null
    };

    if ($pageInput[0]) {
        $pageInput.typeahead({
            ajax: Object.assign(typeaheadOpts, {
                preDispatch: function (query) {
                    // If there is a namespace selector, make sure we search
                    // only within that namespace
                    if ($namespaceInput[0] && $namespaceInput.val() !== '0') {
                        var nsName = $namespaceInput.find('option:selected').text().trim();
                        query = nsName + ':' + query;
                    }
                    return {
                        action: 'query',
                        list: 'prefixsearch',
                        format: 'json',
                        pssearch: query
                    };
                },
                preProcess: function (data) {
                    var nsName = '';
                    // Strip out namespace name if applicable
                    if ($namespaceInput[0] && $namespaceInput.val() !== '0') {
                        nsName = $namespaceInput.find('option:selected').text().trim();
                    }
                    return data.query.prefixsearch.map(function (elem) {
                        return elem.title.replace(new RegExp('^' + nsName + ':'), '');
                    });
                }
            })
        });
    }

    if ($userInput[0]) {
        $userInput.typeahead({
            ajax: Object.assign(typeaheadOpts, {
                preDispatch: function (query) {
                    return {
                        action: 'query',
                        list: 'prefixsearch',
                        format: 'json',
                        pssearch: 'User:' + query
                    };
                },
                preProcess: function (data) {
                    var results = data.query.prefixsearch.map(function (elem) {
                        return elem.title.split('/')[0].substr(elem.title.indexOf(':') + 1);
                    });

                    return results.filter(function (value, index, array) {
                        return array.indexOf(value) === index;
                    });
                }
            })
        });
    }
    let allowAmpersand = (e) => {
        if (e.key == "&") {
            $(e.target).blur().focus();
        }
    };
    $pageInput.on("keydown", allowAmpersand);
    $userInput.on("keydown", allowAmpersand);

}

/*
 * Loading timer id if one is running.
 * Used to prevent concurrent timers.
 */
let loadingTimerId;

/**
 * Create a new loading timer interval.
 * Uses #submit_timer.
 */
function createTimerInterval()
{
    var startTime = Date.now();
    return setInterval(function () {
        var elapsedSeconds = Math.round((Date.now() - startTime) / 1000);
        var minutes = Math.floor(elapsedSeconds / 60);
        var seconds = ('00' + (elapsedSeconds - (minutes * 60))).slice(-2);
        $('#submit_timer').text(minutes + ":" + seconds);
    }, 1000);
}

/**
 * For any form submission, this disables the submit button and replaces its text with
 * a loading message and a counting timer.
 * @param {boolean} [undo] Revert the form back to the initial state.
 *                         This is used on page load to solve an issue with Safari and Firefox
 *                         where after browsing back to the form, the "loading" state persists.
 */
function displayWaitingNoticeOnSubmission(undo)
{
    if (undo) {
        // Re-enable form
        $('.form-control').prop('readonly', false);
        $('.form-submit').prop('disabled', false);
        $('.form-submit').text($.i18n('submit')).prop('disabled', false);
        if (loadingTimerId) {
            clearInterval(loadingTimerId);
            loadingTimerId = null;
        }
    } else {
        $('#content form').on('submit', function () {
            // Remove focus from any active element
            document.activeElement.blur();

            // Disable the form so they can't hit Enter to re-submit
            $('.form-control').prop('readonly', true);

            // Change the submit button text.
            $('.form-submit').prop('disabled', true)
                .html($.i18n('loading') + " <span id='submit_timer'></span>");

            // Add the counter.
            loadingTimerId = createTimerInterval();
        });
    }
}

/*
 * Resets a link out of loading.
 */
function clearLinkTimer()
{
    // clear the timer proper
    clearInterval(loadingTimerId);
    loaingTimerId = null;
    // change the link's label back
    let old = $("#submit_timer").parent()[0];
    $(old).html(old.initialtext);
    $(old).removeClass("link-loading");
}

/**
 * For any links to an XTools query, this replaces the link text with
 * a loading message and a counting timer.
 * @param {boolean} [undo] Revert the links back to their initial state
 *                         This is used on page load to solve an isssue with Safari and Firefox
 *                         where after browsing back, the "loading" state persists.
 */
function setupLinkLoadingNotices(undo)
{
    if (undo) {
        clearLinkTimer();
    } else {
        // Get the list of links:
        $("a").filter(
            (index, el) =>
            el.className == "" && // only plain links, not buttons
            el.href.startsWith(document.location.origin) && // to XTools
            new URL(el.href).pathname.replaceAll(/[^\/]/g, "").length > 1 && // that include parameters (just going to a search form is not costy)
            el.target != "_blank" && // that doesn't open in a new tab
            el.href.split("#")[0] != document.location.href // and that isn't a section link to here.
        ).on("click", (ev) => {
            // And then add a listener
            let el = $(ev.target);
            el.prop("initialtext", el.html());
            el.html($.i18n('loading') + ' <span id=\'submit_timer\'></span>');
            el.addClass("link-loading");
            if (loadingTimerId) {
                clearLinkTimer();
            }
            loadingTimerId = createTimerInterval();
        });
    }
}

/**
 * Handles the multi-select inputs on some index pages.
 */
xtools.application.setupMultiSelectListeners = function () {
    var $inputs = $('.multi-select--body:not(.hidden) .multi-select--option');
    $inputs.on('change', function () {
        // If all sections are selected, select the 'All' checkbox, and vice versa.
        $('.multi-select--all').prop(
            'checked',
            $('.multi-select--body:not(.hidden) .multi-select--option:checked').length === $inputs.length
        );
    });
    // Uncheck/check all when the 'All' checkbox is modified.
    $('.multi-select--all').on('click', function () {
        $inputs.prop('checked', $(this).prop('checked'));
    });
};
