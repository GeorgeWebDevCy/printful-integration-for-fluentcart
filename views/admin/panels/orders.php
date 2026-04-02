<?php defined('ABSPATH') || exit; ?>
<?php if (empty($settings['api_key'])) : ?>
<div class="notice notice-warning inline">
    <p>
        <?php
        printf(
            esc_html__('No Printful API key configured. %s first.', 'printful-for-fluentcart'),
            '<a href="' . esc_url(admin_url('admin.php?page=fluent-cart#/integrations/printful')) . '">' . esc_html__('Configure your settings', 'printful-for-fluentcart') . '</a>'
        );
        ?>
    </p>
</div>
<?php endif; ?>

<div class="pifc-card" id="pifc-orders-container">
    <h2><?php esc_html_e('Orders with Printful Fulfillment', 'printful-for-fluentcart'); ?></h2>
    <p class="description"><?php esc_html_e('Shows all FluentCart orders that have been sent to Printful (or attempted). Use the detail panel to view tracking info, manually trigger fulfillment, or cancel an order.', 'printful-for-fluentcart'); ?></p>
    <div class="pifc-orders-table-wrap" id="pifc-orders-table-wrap">
        <table class="pifc-orders-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('FC Order', 'printful-for-fluentcart'); ?></th>
                    <th><?php esc_html_e('Customer', 'printful-for-fluentcart'); ?></th>
                    <th><?php esc_html_e('FC Status', 'printful-for-fluentcart'); ?></th>
                    <th><?php esc_html_e('Printful Status', 'printful-for-fluentcart'); ?></th>
                    <th><?php esc_html_e('Printful #', 'printful-for-fluentcart'); ?></th>
                    <th><?php esc_html_e('Tracking', 'printful-for-fluentcart'); ?></th>
                    <th><?php esc_html_e('Actions', 'printful-for-fluentcart'); ?></th>
                </tr>
            </thead>
            <tbody id="pifc-orders-tbody"><tr><td colspan="7"><?php esc_html_e('Loading...', 'printful-for-fluentcart'); ?></td></tr></tbody>
        </table>
    </div>
    <div id="pifc-pagination" class="pifc-pagination"></div>
    <div id="pifc-order-detail"></div>
</div>

<div class="pifc-card">
    <h2><?php esc_html_e('Fulfill Any Order Manually', 'printful-for-fluentcart'); ?></h2>
    <p><?php esc_html_e('Enter a FluentCart order ID to view its Printful fulfillment status or trigger manual fulfillment.', 'printful-for-fluentcart'); ?></p>
    <p>
        <input type="number" id="pifc-manual-order-id" class="small-text" min="1" placeholder="<?php esc_attr_e('FC Order ID', 'printful-for-fluentcart'); ?>">
        <button type="button" id="pifc-manual-lookup" class="button button-secondary"><?php esc_html_e('Load Order', 'printful-for-fluentcart'); ?></button>
        <span class="spinner pifc-inline-spinner" id="pifc-manual-lookup-spinner"></span>
        <span id="pifc-manual-status" class="pifc-inline-status"></span>
    </p>
    <div id="pifc-manual-detail"></div>
</div>

<script>
window.pifcAdminInitPanel && window.pifcAdminInitPanel('orders');
</script>
