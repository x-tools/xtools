xtools.autoedits = {};

$(function () {
	if (!$('body.autoedits').length) {
		return;
	}

	var $contributionsContainer = $('.contributions-container'),
		$toolSelector = $('#tool_selector');

	// For the form page.
	if ($toolSelector.length) {
		xtools.autoedits.fetchTools = function (project) {
			$toolSelector.prop('disabled', true);
			$.get('/api/project/automated_tools/' + project).done(function (tools) {
				if (tools.error) {
					$toolSelector.prop('disabled', false);
					return; // Abort, project was invalid.
				}

				// These aren't tools, just metadata in the API response.
				delete tools.project;
				delete tools.elapsed_time;

				$toolSelector.html(
					'<option value="none">' + $.i18n('none') + '</option>' +
					'<option value="all">' + $.i18n('all') + '</option>'
				);
				Object.keys(tools).forEach(function (tool) {
					$toolSelector.append(
						'<option value="' + tool + '">' + (tools[tool].label || tool) + '</option>'
					);
				});

				$toolSelector.prop('disabled', false);
			});
		};

		$(document).ready(function () {
			$('#project_input').on('change.autoedits', function () {
				xtools.autoedits.fetchTools($('#project_input').val());
			});
		});

		xtools.autoedits.fetchTools($('#project_input').val());

		// All the other code below only applies to result pages.
		return;
	}

	// For result pages only...

	xtools.application.setupToggleTable(window.countsByTool, window.toolsChart, 'count', function (newData) {
		var total = 0;
		Object.keys(newData).forEach(function (tool) {
			total += parseInt(newData[tool].count, 10);
		});
		var toolsCount = Object.keys(newData).length;
		/** global: i18nLang */
		$('.tools--tools').text(
			toolsCount.toLocaleString(i18nLang) + " " +
			$.i18n('num-tools', toolsCount)
		);
		$('.tools--count').text(total.toLocaleString(i18nLang));
	});

	if ($contributionsContainer.length) {
		// Load the contributions browser, or set up the listeners if it is already present.
		var initFunc = $('.contributions-table').length ? 'setupContributionsNavListeners' : 'loadContributions';
		xtools.application[initFunc](
			function (params) {
				return `${params.target} - contributions / ${params.project} / ${params.username}` +
					` / ${params.namespace} / ${params.start} / ${params.end}`;
			},
			$contributionsContainer.data('target')
		);
	}
});
