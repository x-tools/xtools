$(document).ready(function() {
  var sortDirection, sortColumn;

  $('.xt-hide').on('click', function() {
    $(this).hide();
    $(this).siblings('.xt-show').show();
    $(this).parents('.panel-heading').siblings('.panel-body').hide();
  });
  $('.xt-show').on('click', function() {
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
  $('.sort-link').on('click', function() {
    sortDirection = sortColumn === $(this).data('column') ? -sortDirection : 1;

    $('.sort-link .glyphicon').removeClass('glyphicon-sort-by-alphabet-alt glyphicon-sort-by-alphabet').addClass('glyphicon-sort');
    var newSortClassName = sortDirection === 1 ? 'glyphicon-sort-by-alphabet-alt' : 'glyphicon-sort-by-alphabet';
    $(this).find('.glyphicon').addClass(newSortClassName).removeClass('glyphicon-sort');

    sortColumn = $(this).data('column');
    var $table = $(this).parents('table');
    var entries = $table.find('.sort-entry--' + sortColumn).parent();

    if (!entries.length) return;

    entries.sort(function(a, b) {
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
});
