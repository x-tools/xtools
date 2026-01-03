xtools.categoryedits = {};

$(function () {
	if (!$('body.categoryedits').length) {
		return;
	}

	$(document).ready(function () {
		xtools.categoryedits.$select2Input = $('#category_selector');

		setupCategoryInput();

		$('#project_input').on('xtools.projectLoaded', function (_e, data) {
			/** global: xtBaseUrl */
			$.get(xtBaseUrl + 'api/project/namespaces/' + data.project).done(function (data) {
				setupCategoryInput(data.api, data.namespaces[14]);
			});
		});

		$('form').on('submit', function () {
			$('#category_input').val( // Hidden input field
				xtools.categoryedits.$select2Input.val().join('|')
			);
		});

		xtools.application.setupToggleTable(window.countsByCategory, window.categoryChart, 'editCount', function (newData) {
			var totalEdits = 0,
				totalPages = 0;
			Object.keys(newData).forEach(function (category) {
				totalEdits += parseInt(newData[category].editCount, 10);
				totalPages += parseInt(newData[category].pageCount, 10);
			});
			var categoriesCount = Object.keys(newData).length;
			/** global: i18nLang */
			$('.category--category').text(
				categoriesCount.toLocaleString(i18nLang) + " " +
				$.i18n('num-categories', categoriesCount)
			);
			$('.category--count').text(totalEdits.toLocaleString(i18nLang));
			$('.category--percent-of-edit-count').text(
				((totalEdits / xtools.categoryedits.userEditCount).toLocaleString(i18nLang) * 100) + '%'
			);
			$('.category--pages').text(totalPages.toLocaleString(i18nLang));
		});

		if ($('.contributions-container').length) {
			loadCategoryEdits();
		}
	});
});

/**
 * Load category edits HTML via AJAX, to not slow down the initial page load. Only load if container is present,
 * which is missing on index pages and in subroutes, e.g. categoryedits-contributions, etc.
 */
function loadCategoryEdits()
{
	// Load the contributions browser, or set up the listeners if it is already present.
	var initFunc = $('.contributions-table').length ? 'setupContributionsNavListeners' : 'loadContributions';
	xtools.application[initFunc](
		function (params) {
			return 'categoryedits-contributions/' + params.project + '/' + params.username + '/' +
				params.categories + '/' + params.start + '/' + params.end;
		},
		'Category'
	);
}

/**
 * Setups the Select2 control to search for pages in the Category namespace.
 * @param {String} [api] Fully qualified API endpoint.
 * @param {String} [ns] Name of the Category namespace.
 */
function setupCategoryInput(api, ns)
{
	// First destroy any existing Select2 inputs.
	if (xtools.categoryedits.$select2Input.data('select2')) {
		xtools.categoryedits.$select2Input.off('change');
		xtools.categoryedits.$select2Input.select2('val', null);
		xtools.categoryedits.$select2Input.select2('data', null);
		xtools.categoryedits.$select2Input.select2('destroy');
	}

	var nsName = ns || xtools.categoryedits.$select2Input.data('ns');

	var params = {
		ajax: {
			url: api || xtools.categoryedits.$select2Input.data('api'),
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
							id: title.replace(/ /g, '_'),
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

	xtools.categoryedits.$select2Input.select2(params);
}
