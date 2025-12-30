xtools.topedits = {};

$(function () {
	// Don't execute this code if we're not on the TopEdits tool.
	// FIXME: find a way to automate this somehow...
	if (!$('body.topedits').length) {
		return;
	}

	// Disable the page input if they select the 'All' namespace option
	$('#namespace_select').on('change', function () {
		$('#page_input').prop('disabled', $(this).val() === 'all');
	});
});
