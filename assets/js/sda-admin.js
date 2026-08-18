/* SDA Token Ledger — Admin JS */
jQuery(function ($) {

    // Auto-fill BID from selected project in the Quick Issue form
    $('#qi-pid').on('change', function () {
        var bid = $(this).find(':selected').data('bid') || '';
        $('#qi-bid').val(bid);
    });

    // Confirm DAO approval
    $('form[data-confirm]').on('submit', function () {
        return confirm($(this).data('confirm'));
    });

    // Copy-to-clipboard for address codes
    $(document).on('click', '.sda-address', function () {
        var text = $(this).attr('title') || $(this).text();
        if (!text || text.length < 5) return;
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function () {
                var $el = $(this);
                $el.addClass('sda-copied');
                setTimeout(function () { $el.removeClass('sda-copied'); }, 1500);
            }.bind(this));
        }
    });

    // Toggle all SDG checkboxes
    $('#sda-sdg-toggle-all').on('change', function () {
        var checked = $(this).prop('checked');
        $('input[name="sda_settings[active_sdgs][]"]').prop('checked', checked);
    });

    // Highlight row on hover (already done by CSS, this adds keyboard focus)
    $('.sda-table tbody tr').on('focus', function () {
        $(this).siblings().removeClass('sda-focus');
        $(this).addClass('sda-focus');
    });

});
