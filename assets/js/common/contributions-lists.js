Object.assign(xtools.application.vars, {
	initialOffset: '',
	offset: '',
	prevOffsets: [],
	initialLoad: false,
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

	var $contributionsContainer = $('.contributions-container'),
		$contributionsLoading = $('.contributions-loading'),
		params = $contributionsContainer.data(),
		endpoint = endpointFunc(params),
		limit = parseInt(params.limit, 10) || 50,
		urlParams = new URLSearchParams(window.location.search),
		newUrl = xtBaseUrl + endpoint + '/' + xtools.application.vars.offset,
		oldToolPath = location.pathname.split('/')[1],
		newToolPath = newUrl.split('/')[1];

	// Gray out contributions list.
	$contributionsContainer.addClass('contributions-container--loading')

	// Show the 'Loading...' text. CSS will hide the "Previous" / "Next" links to prevent jumping.
	$contributionsLoading.show();

	urlParams.set('limit', limit.toString());
	urlParams.append('htmlonly', 'yes');

	/** global: xtBaseUrl */
	$.ajax({
		// Make sure to include any URL parameters, such as tool=Huggle (for AutoEdits).
		url: newUrl + '?' + urlParams.toString(),
		timeout: 60000
	}).always(function () {
		$contributionsContainer.removeClass('contributions-container--loading');
		$contributionsLoading.hide();
	}).done(function (data) {
		$contributionsContainer.html(data).show();
		xtools.application.setupContributionsNavListeners(endpointFunc, apiTitle);

		// Set an initial offset if we don't have one already so that we know when we're on the first page of contribs.
		if (!xtools.application.vars.initialOffset) {
			xtools.application.vars.initialOffset = $('.contribs-row-date').first().data('value');

			// In this case we know we are loading contribs for this first time via AJAX (such as at /autoedits),
			// hence we'll set the initialLoad flag to true, so we know not to unnecessarily pollute the URL
			// after we get back the data (see below).
			xtools.application.vars.initialLoad = true;
		}

		if (oldToolPath !== newToolPath) {
			// Happens when a subrequest is made to a different controller action.
			// For instance, /autoedits embeds /nonautoedits-contributions.
			var regexp = new RegExp(` ^ / ${newToolPath} / (.*) / `);
			newUrl = newUrl.replace(regexp, ` / ${oldToolPath} / $1 / `);
		}

		// Do not run on the initial page load. This is to retain a clean URL:
		// (i.e. /autoedits/enwiki/Example, rather than /autoedits/enwiki/Example/0///2015-07-02T15:50:48?limit=50)
		// When user paginates (requests made NOT on the initial page load), we do want to update the URL.
		if (!xtools.application.vars.initialLoad) {
			// Update URL so we can have permalinks.
			// 'htmlonly' should be removed as it's an internal param.
			urlParams.delete('htmlonly');
			window.history.replaceState(
				null,
				document.title,
				newUrl + '?' + urlParams.toString()
			);

			// Also scroll to the top of the contribs container.
			$contributionsContainer.parents('.panel')[0].scrollIntoView();
		} else {
			// So that pagination through the contribs will update the URL and scroll into view.
			xtools.application.vars.initialLoad = false;
		}

		if (xtools.application.vars.offset < xtools.application.vars.initialOffset) {
			$('.contributions--prev').show();
		} else {
			$('.contributions--prev').hide();
		}
		if ($('.contributions-table tbody tr').length < limit) {
			$('.next-edits').hide();
		}
	}).fail(function (_xhr, _status, message) {
		$contributionsLoading.hide();
		$contributionsContainer.html(
			$.i18n('api-error', $.i18n(apiTitle) + ' API: <code>' + message + '</code>')
		).show();
	});
};

/**
 * Set up listeners for navigating contribution lists.
 */
xtools.application.setupContributionsNavListeners = function (endpointFunc, apiTitle) {
	setInitialOffset();

	// Previous arrow.
	$('.contributions--prev').off('click').one('click', function (e) {
		e.preventDefault();
		xtools.application.vars.offset = xtools.application.vars.prevOffsets.pop()
			|| xtools.application.vars.initialOffset;
		xtools.application.loadContributions(endpointFunc, apiTitle)
	});

	// Next arrow.
	$('.contributions--next').off('click').one('click', function (e) {
		e.preventDefault();
		if (xtools.application.vars.offset) {
			xtools.application.vars.prevOffsets.push(xtools.application.vars.offset);
		}
		xtools.application.vars.offset = $('.contribs-row-date').last().data('value');
		xtools.application.loadContributions(endpointFunc, apiTitle);
	});

	// The 'Limit:' dropdown.
	$('#contributions_limit').on('change', function (e) {
		var limit = parseInt(e.target.value, 10);
		$('.contributions-container').data('limit', limit);
		let capitalize = (str) => str[0].toUpperCase() + str.slice(1);
		$('.contributions--prev-text').text(
			capitalize($.i18n('pager-newer-n', limit))
		);
		$('.contributions--next-text').text(
			capitalize($.i18n('pager-older-n', limit))
		);
	});
};
