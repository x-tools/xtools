xtools.adminstats = {};

$(function () {
    var $projectInput = $('#project_input'),
        lastProject = $projectInput.val();

    // Don't do anything if this isn't an Admin Stats page.
    if ($('body.adminstats, body.patrollerstats, body.stewardstats').length === 0) {
        return;
    }

    xtools.application.setupMultiSelectListeners();

    $('.group-selector').on('change', function () {
        $('.action-selector').addClass('hidden');
        $('.action-selector--' + $(this).val()).removeClass('hidden');

        // Update title of form.
        $('.xt-page-title--title').text($.i18n('tool-' + $(this).val() + 'stats'));
        $('.xt-page-title--desc').text($.i18n('tool-' + $(this).val() + 'stats-desc'));
        var title = $.i18n('tool-' + $(this).val() + 'stats') + ' - ' + $.i18n('xtools-title');
        document.title = title;
        history.replaceState({}, title, '/' + $(this).val() + 'stats');

        // Change project to Meta if it's Steward Stats.
        if ('steward' === $(this).val()) {
            lastProject = $projectInput.val();
            $projectInput.val('meta.wikimedia.org');
        } else {
            $projectInput.val(lastProject);
        }

        xtools.application.setupMultiSelectListeners();
    });
});
