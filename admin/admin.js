jQuery(function ($) {

    $(document).on('click', '.upt-delete-row', function () {
        const $btn = $(this);
        const id   = $btn.data('id');
        if (!confirm('Delete this log entry?')) return;

        $btn.closest('tr').addClass('upt-row-deleting');

        $.post(UPT.ajax_url, {
            action: 'upt_delete_row',
            nonce:  UPT.nonce,
            id:     id,
        }).done(function (res) {
            if (res.success) {
                $btn.closest('tr').fadeOut(300, function () { $(this).remove(); });
            }
        });
    });

    $('#upt-clear-all').on('click', function () {
        if (!confirm('Delete ALL log entries? This cannot be undone.')) return;

        $.post(UPT.ajax_url, {
            action: 'upt_clear_logs',
            nonce:  UPT.nonce,
        }).done(function (res) {
            if (res.success) location.reload();
        });
    });

});
