<?php defined('ABSPATH') || exit; ?>

<div class="wrap pifc-wrap">

    <h1 class="pifc-page-title">
        <span class="dashicons dashicons-upload"></span>
        <?php esc_html_e('Bulk Fulfill Orders', 'printful-for-fluentcart'); ?>
    </h1>

    <div class="pifc-card">
        <h2><?php esc_html_e('Unfulfilled Printful Orders', 'printful-for-fluentcart'); ?></h2>
        <p>
            <?php esc_html_e(
                'Shows paid FluentCart orders that contain Printful-linked products but have not yet been sent to Printful. '
                . 'Select the orders you want to fulfill and click the button below.',
                'printful-for-fluentcart'
            ); ?>
        </p>

        <p>
            <button type="button" id="pifc-load-unfulfilled" class="button button-secondary">
                <?php esc_html_e('Load Unfulfilled Orders', 'printful-for-fluentcart'); ?>
            </button>
            <button type="button" id="pifc-select-all" class="button" style="display:none">
                <?php esc_html_e('Select All', 'printful-for-fluentcart'); ?>
            </button>
            <button type="button" id="pifc-deselect-all" class="button" style="display:none">
                <?php esc_html_e('Deselect All', 'printful-for-fluentcart'); ?>
            </button>
        </p>

        <div id="pifc-unfulfilled-wrap" style="display:none">
            <div class="pifc-orders-table-wrap">
                <table class="pifc-orders-table">
                    <thead>
                        <tr>
                            <th style="width:30px"><input type="checkbox" id="pifc-check-all"></th>
                            <th><?php esc_html_e('Order',    'printful-for-fluentcart'); ?></th>
                            <th><?php esc_html_e('Customer', 'printful-for-fluentcart'); ?></th>
                            <th><?php esc_html_e('Total',    'printful-for-fluentcart'); ?></th>
                            <th><?php esc_html_e('Date',     'printful-for-fluentcart'); ?></th>
                            <th><?php esc_html_e('Status',   'printful-for-fluentcart'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="pifc-unfulfilled-tbody"></tbody>
                </table>
            </div>

            <p id="pifc-no-orders" style="display:none">
                <?php esc_html_e('No unfulfilled Printful orders found. All paid orders have already been sent to Printful.', 'printful-for-fluentcart'); ?>
            </p>

            <div id="pifc-bulk-actions" style="margin-top:12px;display:none">
                <button type="button" id="pifc-bulk-fulfill-btn" class="button button-primary">
                    <?php esc_html_e('Send Selected to Printful', 'printful-for-fluentcart'); ?>
                </button>
                <span id="pifc-bulk-selected-count"></span>
            </div>
        </div>

        <div id="pifc-bulk-log" class="pifc-sync-log" style="display:none;margin-top:12px"></div>
    </div>

</div>

<script type="text/javascript">
jQuery(function ($) {

    var orders = [];

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function updateCount() {
        var n = $('.pifc-order-check:checked').length;
        $('#pifc-bulk-selected-count').text(n ? ' (' + n + ' selected)' : '');
        $('#pifc-bulk-actions').toggle(n > 0);
    }

    function appendLog(msg, isErr) {
        var $p = $('<p>').text(msg);
        if (isErr) $p.addClass('pifc-log-error');
        $('#pifc-bulk-log').append($p).show().scrollTop($('#pifc-bulk-log')[0].scrollHeight);
    }

    $('#pifc-load-unfulfilled').on('click', function () {
        var $btn = $(this).prop('disabled', true).text('<?php echo esc_js(__('Loading…', 'printful-for-fluentcart')); ?>');
        $('#pifc-unfulfilled-wrap').hide();
        $('#pifc-bulk-log').empty().hide();

        $.post(pifcAdmin.ajaxUrl, {
            action: 'pifc_get_unfulfilled_orders',
            nonce:  pifcAdmin.nonce
        })
        .done(function (res) {
            if (!res.success) { alert(res.data.message); return; }
            orders = res.data.orders || [];

            if (!orders.length) {
                $('#pifc-unfulfilled-tbody').empty();
                $('#pifc-no-orders').show();
                $('#pifc-unfulfilled-wrap').show();
                $('#pifc-select-all,#pifc-deselect-all').hide();
                return;
            }

            $('#pifc-no-orders').hide();
            var rows = '';
            $.each(orders, function (i, o) {
                rows += '<tr>' +
                    '<td><input type="checkbox" class="pifc-order-check" value="' + o.id + '"></td>' +
                    '<td>#' + escHtml(o.id) + '</td>' +
                    '<td>' + escHtml(o.customer_name) + '</td>' +
                    '<td>' + escHtml(o.currency + ' ' + o.total) + '</td>' +
                    '<td>' + escHtml(o.date) + '</td>' +
                    '<td>' + escHtml(o.order_status) + '</td>' +
                    '</tr>';
            });
            $('#pifc-unfulfilled-tbody').html(rows);
            $('#pifc-unfulfilled-wrap').show();
            $('#pifc-select-all,#pifc-deselect-all').show();

            $(document).on('change', '.pifc-order-check, #pifc-check-all', updateCount);
        })
        .fail(function () { alert('<?php echo esc_js(__('Request failed.', 'printful-for-fluentcart')); ?>'); })
        .always(function () { $btn.prop('disabled', false).text('<?php echo esc_js(__('Load Unfulfilled Orders', 'printful-for-fluentcart')); ?>'); });
    });

    $('#pifc-check-all').on('change', function () {
        $('.pifc-order-check').prop('checked', $(this).is(':checked'));
        updateCount();
    });

    $('#pifc-select-all').on('click', function () {
        $('.pifc-order-check, #pifc-check-all').prop('checked', true); updateCount();
    });
    $('#pifc-deselect-all').on('click', function () {
        $('.pifc-order-check, #pifc-check-all').prop('checked', false); updateCount();
    });

    $('#pifc-bulk-fulfill-btn').on('click', function () {
        var ids = $('.pifc-order-check:checked').map(function () { return $(this).val(); }).get().join(',');
        if (!ids) { alert('<?php echo esc_js(__('No orders selected.', 'printful-for-fluentcart')); ?>'); return; }
        if (!confirm('<?php echo esc_js(__('Send selected orders to Printful for fulfillment?', 'printful-for-fluentcart')); ?>')) return;

        var $btn = $(this).prop('disabled', true).text(pifcAdmin.i18n.fulfilling);
        $('#pifc-bulk-log').empty().hide();

        $.post(pifcAdmin.ajaxUrl, {
            action:    'pifc_bulk_fulfill',
            nonce:     pifcAdmin.nonce,
            order_ids: ids
        })
        .done(function (res) {
            appendLog(res.data.message, !res.success);
            if (res.data && res.data.results) {
                $.each(res.data.results.failed || [], function (i, f) {
                    appendLog('⚠ Order #' + f.id + ': ' + f.reason, true);
                });
                $.each(res.data.results.fulfilled || [], function (i, f) {
                    appendLog('✓ Order #' + f.id + ' → Printful #' + f.printful_id);
                });
            }
            // Reload the table
            $('#pifc-load-unfulfilled').trigger('click');
        })
        .fail(function () { appendLog('<?php echo esc_js(__('Request failed.', 'printful-for-fluentcart')); ?>', true); })
        .always(function () { $btn.prop('disabled', false).text('<?php echo esc_js(__('Send Selected to Printful', 'printful-for-fluentcart')); ?>'); });
    });
});
</script>
