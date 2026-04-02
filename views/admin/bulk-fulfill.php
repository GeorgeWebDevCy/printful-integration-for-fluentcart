<?php defined('ABSPATH') || exit; ?>

<div class="wrap pifc-wrap">
    <?php
    $pifc_current_page = 'bulk-fulfill';
    $pifc_page_title = __('Bulk Fulfill Orders', 'printful-for-fluentcart');
    $pifc_page_icon = 'dashicons-upload';
    $pifc_page_subtitle = __('Load ready-to-send FluentCart orders and push them to Printful in batches.', 'printful-for-fluentcart');
    include __DIR__ . '/partials/header.php';
    ?>

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
            <span class="spinner pifc-inline-spinner" id="pifc-load-unfulfilled-spinner"></span>
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
                            <th><?php esc_html_e('Order', 'printful-for-fluentcart'); ?></th>
                            <th><?php esc_html_e('Customer', 'printful-for-fluentcart'); ?></th>
                            <th><?php esc_html_e('Total', 'printful-for-fluentcart'); ?></th>
                            <th><?php esc_html_e('Date', 'printful-for-fluentcart'); ?></th>
                            <th><?php esc_html_e('Status', 'printful-for-fluentcart'); ?></th>
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
                <span class="spinner pifc-inline-spinner" id="pifc-bulk-fulfill-spinner"></span>
                <span id="pifc-bulk-selected-count"></span>
            </div>
        </div>

        <div id="pifc-bulk-log" class="pifc-sync-log" style="display:none;margin-top:12px"></div>
    </div>

</div>

<script type="text/javascript">
jQuery(function ($) {
    function escHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function updateCount() {
        var count = $('.pifc-order-check:checked').length;
        $('#pifc-bulk-selected-count').text(count ? ' (' + count + ' selected)' : '');
        $('#pifc-bulk-actions').toggle(count > 0);
    }

    function appendLog(message, isError) {
        var $line = $('<p>').text(message);
        if (isError) {
            $line.addClass('pifc-log-error');
        }
        $('#pifc-bulk-log').append($line).show().scrollTop($('#pifc-bulk-log')[0].scrollHeight);
    }

    $('#pifc-load-unfulfilled').on('click', function () {
        var $button = $(this).prop('disabled', true).text('<?php echo esc_js(__('Loading...', 'printful-for-fluentcart')); ?>');
        var $spinner = $('#pifc-load-unfulfilled-spinner');

        $spinner.addClass('is-active');
        $('#pifc-unfulfilled-wrap').hide();
        $('#pifc-bulk-log').empty().hide();

        $.post(pifcAdmin.ajaxUrl, {
            action: 'pifc_get_unfulfilled_orders',
            nonce: pifcAdmin.nonce
        })
        .done(function (res) {
            if (!res.success) {
                alert(res.data.message);
                return;
            }

            var orders = res.data.orders || [];

            if (!orders.length) {
                $('#pifc-unfulfilled-tbody').empty();
                $('#pifc-no-orders').show();
                $('#pifc-unfulfilled-wrap').show();
                $('#pifc-select-all,#pifc-deselect-all').hide();
                return;
            }

            $('#pifc-no-orders').hide();

            var rows = '';
            $.each(orders, function (i, order) {
                rows += '<tr>' +
                    '<td><input type="checkbox" class="pifc-order-check" value="' + order.id + '"></td>' +
                    '<td>#' + escHtml(order.id) + '</td>' +
                    '<td>' + escHtml(order.customer_name) + '</td>' +
                    '<td>' + escHtml(order.currency + ' ' + order.total) + '</td>' +
                    '<td>' + escHtml(order.date) + '</td>' +
                    '<td>' + escHtml(order.order_status) + '</td>' +
                    '</tr>';
            });

            $('#pifc-unfulfilled-tbody').html(rows);
            $('#pifc-unfulfilled-wrap').show();
            $('#pifc-select-all,#pifc-deselect-all').show();
            updateCount();
        })
        .fail(function () {
            alert('<?php echo esc_js(__('Request failed.', 'printful-for-fluentcart')); ?>');
        })
        .always(function () {
            $spinner.removeClass('is-active');
            $button.prop('disabled', false).text('<?php echo esc_js(__('Load Unfulfilled Orders', 'printful-for-fluentcart')); ?>');
        });
    });

    $(document).on('change', '.pifc-order-check, #pifc-check-all', function () {
        if (this.id === 'pifc-check-all') {
            $('.pifc-order-check').prop('checked', $(this).is(':checked'));
        }
        updateCount();
    });

    $('#pifc-select-all').on('click', function () {
        $('.pifc-order-check, #pifc-check-all').prop('checked', true);
        updateCount();
    });

    $('#pifc-deselect-all').on('click', function () {
        $('.pifc-order-check, #pifc-check-all').prop('checked', false);
        updateCount();
    });

    $('#pifc-bulk-fulfill-btn').on('click', function () {
        var ids = $('.pifc-order-check:checked').map(function () {
            return $(this).val();
        }).get().join(',');

        if (!ids) {
            alert('<?php echo esc_js(__('No orders selected.', 'printful-for-fluentcart')); ?>');
            return;
        }

        if (!confirm('<?php echo esc_js(__('Send selected orders to Printful for fulfillment?', 'printful-for-fluentcart')); ?>')) {
            return;
        }

        var $button = $(this).prop('disabled', true).text(pifcAdmin.i18n.fulfilling);
        var $spinner = $('#pifc-bulk-fulfill-spinner');

        $spinner.addClass('is-active');
        $('#pifc-bulk-log').empty().hide();

        $.post(pifcAdmin.ajaxUrl, {
            action: 'pifc_bulk_fulfill',
            nonce: pifcAdmin.nonce,
            order_ids: ids
        })
        .done(function (res) {
            appendLog(res.data.message, !res.success);

            if (res.data && res.data.results) {
                $.each(res.data.results.failed || [], function (i, failed) {
                    appendLog('Order #' + failed.id + ': ' + failed.reason, true);
                });

                $.each(res.data.results.fulfilled || [], function (i, fulfilled) {
                    appendLog('Order #' + fulfilled.id + ' -> Printful #' + fulfilled.printful_id, false);
                });
            }

            $('#pifc-load-unfulfilled').trigger('click');
        })
        .fail(function () {
            appendLog('<?php echo esc_js(__('Request failed.', 'printful-for-fluentcart')); ?>', true);
        })
        .always(function () {
            $spinner.removeClass('is-active');
            $button.prop('disabled', false).text('<?php echo esc_js(__('Send Selected to Printful', 'printful-for-fluentcart')); ?>');
        });
    });
});
</script>
