(function () {
    var $tocClone, tocHeight, sectionOffset = {}, apiPath, lastProject;

    // Load translations with 'en.json' as a fallback
    var messagesToLoad = {};

    /** global: i18nLang */
    /** global: i18nPath */
    messagesToLoad[i18nLang] = i18nPath;

    /** global: i18nEnPath */
    if (i18nLang !== 'en') {
        messagesToLoad.en = i18nEnPath;
    }

    $.i18n({
        locale: i18nLang
    }).load(messagesToLoad);

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
        setupColumnSorting();
        setupTOC();
        setupStickyHeader();
        setupProjectListener();
        setupAutocompletion();
        displayWaitingNoticeOnSubmission();

        // Re-init forms, workaround for issues with Safari and Firefox.
        // See displayWaitingNoticeOnSubmission() for more.
        window.onpageshow = function (e) {
            if (e.persisted) {
                displayWaitingNoticeOnSubmission(true);
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
     *         window.setupToggleTable(window.countsByTool, window.toolsChart, 'count', function (newData) {
     *             // update the totals in toggle table based on newData
     *         });
     *     </script>
     *
     * @param  {Object}   dataSource     Object of data that makes up the chart
     * @param  {Chart}    chartObj       Reference to the pie chart associated with the .toggle-table
     * @param  {String}   [valueKey]     The name of the key within entries of dataSource,
     *                                   where the value is what's shown in the chart.
     *                                   If omitted or null, `dataSource` is assumed to be of the structure:
     *                                   { 'a' => 123, 'b' => 456 }
     * @param  {Function} updateCallback Callback to update the .toggle-table totals. `toggleTableData`
     *                                   is passed in which contains the new data, you just need to
     *                                   format it (maybe need to use i18n, update multiple cells, etc.).
     *                                   The second parameter that is passed back is the 'key' of the toggled
     *                                   item, and the third is the index of the item.
     */
    window.setupToggleTable = function (dataSource, chartObj, valueKey, updateCallback) {
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
                var oldValue = parseInt(valueKey ? toggleTableData[key][valueKey] : toggleTableData[key], 10);
                chartObj.data.datasets[0].data[index] = oldValue;
                $(this).attr('data-disabled', 'false');
            } else {
                delete toggleTableData[key];
                chartObj.data.datasets[0].data[index] = null;
                $(this).attr('data-disabled', 'true');
            }

            // gray out row in table
            $(this).parents('tr').toggleClass('excluded');

            // change the hover icon from a 'x' to a '+'
            $(this).find('.glyphicon').toggleClass('glyphicon-remove').toggleClass('glyphicon-plus');

            // update stats
            updateCallback(toggleTableData, key, index);

            chartObj.update();
        });
    }

    /**
     * If there are more tool links in the nav than will fit in the viewport,
     *   move the last entry to the More menu, one at a time, until it all fits.
     * This does not listen for window resize events.
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
     * Sorting of columns
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
    window.setupColumnSorting = function () {
        var sortDirection, sortColumn;

        $('.sort-link').on('click', function () {
            sortDirection = sortColumn === $(this).data('column') ? -sortDirection : 1;

            $('.sort-link .glyphicon').removeClass('glyphicon-sort-by-alphabet-alt glyphicon-sort-by-alphabet').addClass('glyphicon-sort');
            var newSortClassName = sortDirection === 1 ? 'glyphicon-sort-by-alphabet-alt' : 'glyphicon-sort-by-alphabet';
            $(this).find('.glyphicon').addClass(newSortClassName).removeClass('glyphicon-sort');

            sortColumn = $(this).data('column');
            var $table = $(this).parents('table');
            var entries = $table.find('.sort-entry--' + sortColumn).parent();

            if (!entries.length) {
                return; }

            entries.sort(function (a, b) {
                var before = $(a).find('.sort-entry--' + sortColumn).data('value'),
                    after = $(b).find('.sort-entry--' + sortColumn).data('value');

                // test data type, assumed to be string if can't be parsed as float
                if (!isNaN(parseFloat(before, 10))) {
                    before = parseFloat(before, 10);
                    after = parseFloat(after, 10);
                }

                if (before < after) {
                    return sortDirection;
                } else if (before > after) {
                    return -sortDirection;
                } else {
                    return 0;
                }
            });

            $table.find('tbody').html($(entries));
        });
    }

    /**
     * Floating table of contents
     *
     * Example usage (see articleInfo/result.html.twig for more):
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

        tocHeight = $toc.height();

        // listeners on the section links
        var setupTocListeners = function () {
            $('.xt-toc').find('a').off('click').on('click', function (e) {
                document.activeElement.blur();
                var $newSection = $('#' + $(e.target).data('section'));
                $(window).scrollTop($newSection.offset().top - tocHeight);

                $(this).parents('.xt-toc').find('a').removeClass('bold');

                createTocClone();
                $tocClone.addClass('bold');
            });
        };
        window.setupTocListeners = setupTocListeners;

        // clone the TOC and add position:fixed
        var createTocClone = function () {
            if ($tocClone) {
                return;
            }
            $tocClone = $toc.clone();
            $tocClone.addClass('fixed');
            $toc.after($tocClone);
            setupTocListeners();
        };

        // build object containing offsets of each section
        window.buildSectionOffsets = function () {
            $.each($toc.find('a'), function (index, tocMember) {
                var id = $(tocMember).data('section');
                sectionOffset[id] = $('#' + id).offset().top;
            });
        }

        // rebuild section offsets when sections are shown/hidden
        $('.xt-show, .xt-hide').on('click', buildSectionOffsets);

        buildSectionOffsets();
        setupTocListeners();

        var tocOffsetTop = $toc.offset().top;
        $(window).on('scroll.toc', function (e) {
            var windowOffset = $(e.target).scrollTop();
            var inRange = windowOffset > tocOffsetTop;

            if (inRange) {
                if (!$tocClone) {
                    createTocClone();
                }

                // bolden the link for whichever section we're in
                var $activeMember;
                Object.keys(sectionOffset).forEach(function (section) {
                    if (windowOffset > sectionOffset[section] - tocHeight - 1) {
                        $activeMember = $tocClone.find('a[data-section="' + section + '"]');
                    }
                });
                $tocClone.find('a').removeClass('bold');
                if ($activeMember) {
                    $activeMember.addClass('bold');
                }
            } else if (!inRange && $tocClone) {
                // remove the clone once we're out of range
                $tocClone.remove();
                $tocClone = null;
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

        var headerHeight = $header.height(),
            $headerRow = $header.find('thead tr').eq(0),
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
     * Add listener to the project input field to update any
     * namespace selectors and autocompletion fields.
     */
    function setupProjectListener()
    {
        // Stop here if there is no project field
        if (!$("#project_input")) {
            return;
        }

        // If applicable, setup namespace selector with real time updates when changing projects.
        // This will also set `apiPath` so that autocompletion will query the right wiki.
        if ($('#project_input').length && $('#namespace_select').length) {
            setupNamespaceSelector();
        // Otherwise, if there's a user or page input field, we still need to update `apiPath`
        // for the user input autocompletion when the project is changed.
        } else if ($('#user_input')[0] || $('#article_input')[0]) {
            // keep track of last valid project
            lastProject = $('#project_input').val();

            $('#project_input').on('change', function () {
                var newProject = this.value;

                // Show the spinner.
                $(this).addClass('show-loader');

                /** global: xtBaseUrl */
                $.get(xtBaseUrl + 'api/project/normalize/' + newProject).done(function (data) {
                    // Keep track of project API path for use in page title autocompletion
                    apiPath = data.api;
                    lastProject = newProject;
                    setupAutocompletion();
                }).fail(
                    revertToValidProject.bind(this, newProject)
                ).always(function () {
                    $('#project_input').removeClass('show-loader');
                });
            });
        }
    }

    /**
     * Use the wiki input field to populate the namespace selector.
     * This also updates `apiPath` and calls setupAutocompletion()
     */
    function setupNamespaceSelector()
    {
        // keep track of last valid project
        lastProject = $('#project_input').val();

        $('#project_input').on('change', function () {
            // Disable the namespace selector and show a spinner while the data loads.
            $('#namespace_select').prop('disabled', true);
            $(this).addClass('show-loader');

            var newProject = this.value;

            /** global: xtBaseUrl */
            $.get(xtBaseUrl + 'api/project/namespaces/' + newProject).done(function (data) {
                // Clone the 'all' option (even if there isn't one),
                // and replace the current option list with this.
                var $allOption = $('#namespace_select option[value="all"]').eq(0).clone();
                $("#namespace_select").html($allOption);

                // Keep track of project API path for use in page title autocompletion
                apiPath = data.api;

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
                lastProject = newProject;

                // Re-init autocompletion
                setupAutocompletion();
            }).fail(revertToValidProject.bind(this, newProject)).always(function () {
                $('#namespace_select').prop('disabled', false);
                $('#project_input').removeClass('show-loader');
            });
        });

        // If they change the namespace, update autocompletion,
        // which will ensure only pages in the selected namespace
        // show up in the autocompletion
        $('#namespace_select').on('change', setupAutocompletion);
    }

    /**
     * Called by setupNamespaceSelector or setupProjectListener
     *   when the user changes to a project that doesn't exist.
     * This throws a warning message and reverts back to the
     *   last valid project.
     * @param {string} newProject - project they attempted to add
     */
    function revertToValidProject(newProject)
    {
        $('#project_input').val(lastProject);
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
        var $articleInput = $('#article_input'),
            $userInput = $('#user_input'),
            $namespaceInput = $("#namespace_select");

        // Make sure typeahead-compatible fields are present
        if (!$articleInput[0] && !$userInput[0] && !$('#project_input')[0]) {
            return;
        }

        // Destroy any existing instances
        if ($articleInput.data('typeahead')) {
            $articleInput.data('typeahead').destroy();
        }
        if ($userInput.data('typeahead')) {
            $userInput.data('typeahead').destroy();
        }

        // set initial value for the API url, which is put as a data attribute in forms.html.twig
        if (!apiPath) {
            apiPath = $('#article_input').data('api') || $('#user_input').data('api');
        }

        // Defaults for typeahead options. preDispatch and preProcess will be
        // set accordingly for each typeahead instance
        var typeaheadOpts = {
            url: apiPath,
            timeout: 200,
            triggerLength: 1,
            method: 'get',
            loadingClass: 'show-loader',
            preDispatch: null,
            preProcess: null,
        };

        if ($articleInput[0]) {
            $articleInput.typeahead({
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
                    },
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
                    },
                })
            });
        }
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
                var startTime = Date.now();
                setInterval(function () {
                    var elapsedSeconds = Math.round((Date.now() - startTime) / 1000);
                    var minutes = Math.floor(elapsedSeconds / 60);
                    var seconds = ('00' + (elapsedSeconds - (minutes * 60))).slice(-2);
                    $('#submit_timer').text(minutes + ":" + seconds);
                }, 1000);
            });
        }
    }

})();
