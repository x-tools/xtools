(function () {
    var sortDirection, sortColumn, $tocClone, tocHeight, sectionOffset = {};

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

        // Sorting of columns
        //
        //  Example usage:
        //   {% for key in ['username', 'edits', 'minor', 'date'] %}
        //      <th>
        //         <span class="sort-link sort-link--{{ key }}" data-column="{{ key }}">
        //            {{ msg(key) | capitalize }}
        //            <span class="glyphicon glyphicon-sort"></span>
        //         </span>
        //      </th>
        //  {% endfor %}
        //   <th class="sort-link" data-column="username">Username</th>
        //   ...
        //   <td class="sort-entry--username" data-value="{{ username }}">{{ username }}</td>
        //   ...
        //
        // Data type is automatically determined, with support for integer,
        //   floats, and strings, including date strings (e.g. "2016-01-01 12:59")
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

        setupTOC();
    });

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
                e.preventDefault();
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
})();