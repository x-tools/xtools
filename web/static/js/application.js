(function () {
    var sortDirection, sortColumn, $tocClone, tocHeight, sectionOffset = {},
        toggleTableData;

    // Load translations with 'en.json' as a fallback
    var messagesToLoad = {};
    messagesToLoad[i18nLang] = assetPath + 'static/i18n/' + i18nLang + '.json';
    if (i18nLang !== 'en') {
        messagesToLoad.en = assetPath + 'static/i18n/en.json';
    }
    $.i18n({
        locale: i18nLang
    }).load(messagesToLoad);

    $(document).ready(function () {
        $('.xt-hide').on('click', function () {
            $(this).hide();
            $(this).siblings('.xt-show').show();
            $(this).parents('.panel-heading').siblings('.panel-body').hide();
        });
        $('.xt-show').on('click', function () {
            $(this).hide();
            $(this).siblings('.xt-hide').show();
            $(this).parents('.panel-heading').siblings('.panel-body').show();
        });

        setupColumnSorting();

        setupTOC();

        // if applicable, setup namespace selector with real time updates when changing projects
        if ($('#project_input').length && $('#namespace_select').length) {
            setupNamespaceSelector();
        }
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
     *                     {{ wiki_link(...) }}
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
     *                                   format it (maybe need to use i18n, update multiple cells, etc.)
     */
    window.setupToggleTable = function (dataSource, chartObj, valueKey, updateCallback) {
        $('.toggle-table').on('click', '.toggle-table--toggle', function () {
            if (!toggleTableData) {
                // must be cloned
                toggleTableData = Object.assign({}, dataSource);
            }

            var index = $(this).data('index'),
                key = $(this).data('key'),
                $row = $(this).parents('tr');

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
            updateCallback(toggleTableData);

            chartObj.update();
        });
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
    function setupColumnSorting()
    {
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
        var buildSectionOffsets = function () {
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
        $(window).on('scroll', function (e) {
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
     * Use the wiki input field to populate the namespace selector
     */
    function setupNamespaceSelector()
    {
        // keep track of last valid project
        var lastProject = $('#project_input').val();

        $('#project_input').on('change', function () {
            // Disable the namespace selector and show a spinner while the data loads.
            $('#namespace_select').prop('disabled', true);
            $loader = $('span.loader');
            $('label[for="namespace_select"]').append($loader);
            $loader.removeClass('hidden');

            var newProject = this.value;

            $.get(xtBaseUrl + 'api/namespaces/' + newProject).done(function (namespaces) {
                // Clone the 'all' option (even if there isn't one),
                // and replace the current option list with this.
                var $allOption = $('#namespace_select option[value="all"]').eq(0).clone();
                $("#namespace_select").html($allOption);
                // Add all of the new namespace options.
                for (var ns in namespaces) {
                    $('#namespace_select').append(
                        "<option value=" + ns + ">" + namespaces[ns] + "</option>"
                    );
                }
                // Default to mainspace being selected.
                $("#namespace_select").val(0);
                lastProject = newProject;
            }).fail(function () {
                // revert back to last valid project
                $('#project_input').val(lastProject);
                // FIXME: i18n
                $('.site-notice').append(
                    "<div class='alert alert-warning alert-dismissible' role='alert'>" +
                        $.i18n('invalid-project', "<strong>" + newProject + "</strong>") +
                        "<button class='close' data-dismiss='alert' aria-label='Close'>" +
                            "<span aria-hidden='true'>&times;</span>" +
                        "</button>" +
                    "</div>"
                );
            }).always(function () {
                $('#namespace_select').prop('disabled', false);
                $loader.addClass('hidden');
            });
        });
    }
})();
