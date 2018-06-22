$(function () {
    if (!$('body.categoryedits').length) {
        return;
    }

    var $select2Input = $('#category_selector'),
        nsName = $select2Input.data('ns');

    var params = {
        ajax: {
            url: $select2Input.data('api'),
            dataType: 'jsonp',
            jsonpCallback: 'categorySuggestionCallback',
            delay: 200,
            data: function (search) {
                return {
                    action: 'query',
                    list: 'prefixsearch',
                    format: 'json',
                    pssearch: search.term || '',
                    psnamespace: 14,
                    cirrusUseCompletionSuggester: 'yes'
                };
            },
            processResults: function (data) {
                var query = data ? data.query : {},
                    results = [];

                if (query && query.prefixsearch.length) {
                    results = query.prefixsearch.map(function (elem) {
                        var title = elem.title.replace(new RegExp('^' + nsName + ':'), '');
                        return {
                            id: title.score(),
                            text: title
                        };
                    });
                }

                return {results: results}
            }
        },
        placeholder: $.i18n('category-search'),
        maximumSelectionLength: 10,
        minimumInputLength: 1
    };

    $select2Input.select2(params);

    $('form').on('submit', function () {
        $('#category_input').val(
            $select2Input.val().join('|')
        );
    });

    setupToggleTable(window.countsByCategory, window.categoryChart, null, function (newData) {
        var total = 0;
        Object.keys(newData).forEach(function (category) {
            total += parseInt(newData[category], 10);
        });
        var categoriesCount = Object.keys(newData).length;
        /** global: i18nLang */
        $('.category--category').text(
            categoriesCount.toLocaleString(i18nLang) + " " +
            $.i18n('num-categories', categoriesCount)
        );
        $('.category--count').text(total.toLocaleString(i18nLang));
    });

    // Load the contributions browser, or set up the listeners if it is already present.
    var initFunc = $('.contributions-table').length ? 'setupContributionsNavListeners' : 'loadContributions';
    window[initFunc](
        function (params) {
            return 'categoryedits-contributions/' + params.project + '/' + params.username + '/' +
                    params.categories + '/' + params.start + '/' + params.end;
        },
        'Category'
    );
});
