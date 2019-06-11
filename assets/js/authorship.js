$(function () {
    if (!$('body.authorship').length) {
        return;
    }

    const $showSelector = $('#show_selector');

    $showSelector.on('change', e => {
        $('.show-option').addClass('hidden')
            .find('input').prop('disabled', true);
        $(`.show-option--${e.target.value}`).removeClass('hidden')
            .find('input').prop('disabled', false);
    });

    window.onload = () => $showSelector.trigger('change');
});
