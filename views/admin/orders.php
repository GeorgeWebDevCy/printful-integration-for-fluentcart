<?php defined('ABSPATH') || exit; ?>

<div class="wrap pifc-wrap">
    <?php
    $pifc_current_page = 'orders';
    $pifc_page_title = __('Printful Orders', 'printful-for-fluentcart');
    $pifc_page_icon = 'dashicons-airplane';
    $pifc_page_subtitle = __('Track synced fulfillment activity, inspect shipment details, and manually resend orders when needed.', 'printful-for-fluentcart');
    include __DIR__ . '/partials/header.php';
    ?>

    <?php
    $settings = get_option('pifc_settings', []);
    if (empty($settings['api_key'])) :
    ?>
    <div class="notice notice-warning inline">
        <p>
            <?php
            printf(
                /* translators: %s: settings page link */
                esc_html__('No Printful API key configured. %s first.', 'printful-for-fluentcart'),
                '<a href="' . esc_url(admin_url('admin.php?page=pifc-settings')) . '">'
                    . esc_html__('Configure your settings', 'printful-for-fluentcart')
                    . '</a>'
            );
            ?>
        </p>
    </div>
    <?php endif; ?>

    <div class="pifc-card" id="pifc-orders-container">

        <h2><?php esc_html_e('Orders with Printful Fulfillment', 'printful-for-fluentcart'); ?></h2>
        <p class="description">
            <?php esc_html_e(
                'Shows all FluentCart orders that have been sent to Printful (or attempted). '
                . 'Use the detail panel to view tracking info, manually trigger fulfillment, or cancel an order.',
                'printful-for-fluentcart'
            ); ?>
        </p>

        <div class="pifc-orders-table-wrap" id="pifc-orders-table-wrap">
            <table class="pifc-orders-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('FC Order', 'printful-for-fluentcart'); ?></th>
                        <th><?php esc_html_e('Customer',  'printful-for-fluentcart'); ?></th>
                        <th><?php esc_html_e('FC Status', 'printful-for-fluentcart'); ?></th>
                        <th><?php esc_html_e('Printful Status', 'printful-for-fluentcart'); ?></th>
                        <th><?php esc_html_e('Printful #', 'printful-for-fluentcart'); ?></th>
                        <th><?php esc_html_e('Tracking', 'printful-for-fluentcart'); ?></th>
                        <th><?php esc_html_e('Actions', 'printful-for-fluentcart'); ?></th>
                    </tr>
                </thead>
                <tbody id="pifc-orders-tbody">
                    <tr>
                        <td colspan="7"><?php esc_html_e('Loading...', 'printful-for-fluentcart'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div id="pifc-pagination" class="pifc-pagination"></div>

        <div id="pifc-order-detail"></div>

    </div>

    <div class="pifc-card">
        <h2><?php esc_html_e('Fulfill Any Order Manually', 'printful-for-fluentcart'); ?></h2>
        <p>
            <?php esc_html_e(
                'Enter a FluentCart order ID to view its Printful fulfillment status or trigger manual fulfillment.',
                'printful-for-fluentcart'
            ); ?>
        </p>
        <p>
            <input
                type="number"
                id="pifc-manual-order-id"
                class="small-text"
                min="1"
                placeholder="<?php esc_attr_e('FC Order ID', 'printful-for-fluentcart'); ?>"
            >
            <button type="button" id="pifc-manual-lookup" class="button button-secondary">
                <?php esc_html_e('Load Order', 'printful-for-fluentcart'); ?>
            </button>
            <span class="spinner pifc-inline-spinner" id="pifc-manual-lookup-spinner"></span>
            <span id="pifc-manual-status" class="pifc-inline-status"></span>
        </p>
        <div id="pifc-manual-detail"></div>
    </div>

</div>

<script type="text/javascript">
jQuery(function ($) {
    $('#pifc-manual-lookup').on('click', function () {
        var id = parseInt($('#pifc-manual-order-id').val(), 10);
        if (!id) {
            return;
        }

        var $detail = $('#pifc-manual-detail');
        var $lookupBtn = $(this);
        var $spinner = $('#pifc-manual-lookup-spinner');
        var $status = $('#pifc-manual-status');

        $lookupBtn.prop('disabled', true);
        $spinner.addClass('is-active');
        $status.removeClass('pifc-ok pifc-err').text('<?php echo esc_js(__('Loading order...', 'printful-for-fluentcart')); ?>');
        $detail.html('<p><?php echo esc_js(__('Loading...', 'printful-for-fluentcart')); ?></p>');

        $.post(pifcAdmin.ajaxUrl, {
            action: 'pifc_get_order_detail',
            nonce: pifcAdmin.nonce,
            order_id: id
        })
        .done(function (res) {
            if (!res.success) {
                $status.addClass('pifc-err').text(res.data.message);
                $detail.html('<p class="pifc-log-error">' + res.data.message + '</p>');
                return;
            }

            $status.addClass('pifc-ok').text('<?php echo esc_js(__('Order loaded.', 'printful-for-fluentcart')); ?>');

            var d = res.data;
            var trackingLink = d.tracking_number
                ? (d.tracking_url
                    ? '<a href="' + d.tracking_url + '" target="_blank" rel="noopener">' + d.tracking_number + '</a>'
                    : d.tracking_number)
                : '-';

            var html =
                '<div class="pifc-card" style="margin-top:0">' +
                '<h2><?php echo esc_js(__('Order Detail', 'printful-for-fluentcart')); ?> #' + d.order_id + '</h2>' +
                '<div class="pifc-detail-grid">' +
                '<div class="pifc-detail-item"><label>Printful Order</label><span>' + (d.printful_order_id ? '#' + d.printful_order_id : '-') + '</span></div>' +
                '<div class="pifc-detail-item"><label>Printful Status</label><span>' + (d.printful_status || '-') + '</span></div>' +
                '<div class="pifc-detail-item"><label>FC Status</label><span>' + (d.order_status || '-') + '</span></div>' +
                '<div class="pifc-detail-item"><label>Shipping Status</label><span>' + (d.shipping_status || '-') + '</span></div>' +
                '<div class="pifc-detail-item"><label>Carrier</label><span>' + (d.carrier || '-') + '</span></div>' +
                '<div class="pifc-detail-item"><label>Tracking</label><span>' + trackingLink + '</span></div>' +
                '<div class="pifc-detail-item"><label>Ship Date</label><span>' + (d.ship_date || '-') + '</span></div>' +
                '</div>';

            if (!d.printful_order_id) {
                html += '<div class="pifc-actions">' +
                    '<button class="button button-primary" id="pifc-manual-fulfill-btn" data-id="' + d.order_id + '">' +
                    '<?php echo esc_js(__('Send to Printful', 'printful-for-fluentcart')); ?></button></div>';
            }

            if (d.fulfillment_error) {
                html += '<p class="description pifc-log-error"><?php echo esc_js(__('Last error:', 'printful-for-fluentcart')); ?> ' + d.fulfillment_error + '</p>';
            }

            html += '</div>';
            $detail.html(html);

            $('#pifc-manual-fulfill-btn').on('click', function () {
                if (!confirm(pifcAdmin.i18n.confirmFulfill)) {
                    return;
                }

                var $btn = $(this).prop('disabled', true).text(pifcAdmin.i18n.fulfilling);
                $spinner.addClass('is-active');
                $status.removeClass('pifc-ok pifc-err').text('<?php echo esc_js(__('Sending order to Printful...', 'printful-for-fluentcart')); ?>');

                $.post(pifcAdmin.ajaxUrl, {
                    action: 'pifc_fulfill_order',
                    nonce: pifcAdmin.nonce,
                    order_id: d.order_id
                })
                .done(function (r) {
                    $status.addClass(r.success ? 'pifc-ok' : 'pifc-err').text(r.data.message);
                    alert(r.data.message);

                    if (r.success) {
                        $('#pifc-manual-order-id').val(d.order_id);
                        $('#pifc-manual-lookup').trigger('click');
                    }
                })
                .fail(function () {
                    $status.addClass('pifc-err').text('<?php echo esc_js(__('Request failed.', 'printful-for-fluentcart')); ?>');
                    alert('<?php echo esc_js(__('Request failed.', 'printful-for-fluentcart')); ?>');
                })
                .always(function () {
                    $spinner.removeClass('is-active');
                    $btn.prop('disabled', false);
                });
            });
        })
        .fail(function () {
            $status.addClass('pifc-err').text('<?php echo esc_js(__('Request failed.', 'printful-for-fluentcart')); ?>');
            $detail.html('<p class="pifc-log-error"><?php echo esc_js(__('Request failed.', 'printful-for-fluentcart')); ?></p>');
        })
        .always(function () {
            $spinner.removeClass('is-active');
            $lookupBtn.prop('disabled', false);
        });
    });
});
</script>
