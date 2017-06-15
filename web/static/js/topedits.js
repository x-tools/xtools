$(function () {
    // Don't execute this code if we're not on the Pages tool
    // FIXME: find a way to automate this somehow...
    if (!$('body.topedits').length) {
        return;
    }

    // Disable the article input if they select the 'All' namespace option
    $('#namespace_select').on('change', function () {
        $('#article_input').prop('disabled', $(this).val() === 'all');
    });
});
