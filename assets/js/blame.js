xtools.blame = {};

$(function () {
    if (!$('body.blame').length) {
        return;
    }

    if ($('.diff-empty').length === $('.diff tr').length - 1) {
        $('.diff-empty').eq(0)
            .text(`(${$.i18n('diff-empty').toLowerCase()})`)
            .addClass('text-muted text-center')
            .prop('width', '20%');
    }

    $('.diff-addedline').each(function () {
        // Escape query to make regex-safe.
        const escapedQuery = xtools.blame.query.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&');

        const highlightMatch = selector => {
            const regex = new RegExp(`(${escapedQuery})`, 'gi');
            $(selector).html(
                $(selector).html().replace(regex, `<strong>$1</strong>`)
            );
        };

        if ($(this).find('.diffchange-inline').length) {
            $('.diffchange-inline').each(function () {
                highlightMatch(this);
            });
        } else {
            highlightMatch(this);
        }
    });

    // Handles the "Show" dropdown, show/hiding the associated input field accordingly.
    const $showSelector = $('#show_selector');
    $showSelector.on('change', e => {
        $('.show-option').addClass('hidden')
            .find('input').prop('disabled', true);
        $(`.show-option--${e.target.value}`).removeClass('hidden')
            .find('input').prop('disabled', false);
    });
    window.onload = () => $showSelector.trigger('change');
});
