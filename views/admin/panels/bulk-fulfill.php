<?php defined('ABSPATH') || exit; ?>

<div class="pifc-card">
    <h2><?php esc_html_e('Unfulfilled Printful Orders', 'printful-for-fluentcart'); ?></h2>
    <p><?php esc_html_e('Shows paid FluentCart orders that contain Printful-linked products but have not yet been sent to Printful. Select the orders you want to fulfill and click the button below.', 'printful-for-fluentcart'); ?></p>
    <p>
        <button type="button" id="pifc-load-unfulfilled" class="button button-secondary"><?php esc_html_e('Load Unfulfilled Orders', 'printful-for-fluentcart'); ?></button>
        <span class="spinner pifc-inline-spinner" id="pifc-load-unfulfilled-spinner"></span>
        <button type="button" id="pifc-select-all" class="button" style="display:none"><?php esc_html_e('Select All', 'printful-for-fluentcart'); ?></button>
        <button type="button" id="pifc-deselect-all" class="button" style="display:none"><?php esc_html_e('Deselect All', 'printful-for-fluentcart'); ?></button>
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
        <p id="pifc-no-orders" style="display:none"><?php esc_html_e('No unfulfilled Printful orders found. All paid orders have already been sent to Printful.', 'printful-for-fluentcart'); ?></p>
        <div id="pifc-bulk-actions" style="margin-top:12px;display:none">
            <button type="button" id="pifc-bulk-fulfill-btn" class="button button-primary"><?php esc_html_e('Send Selected to Printful', 'printful-for-fluentcart'); ?></button>
            <span class="spinner pifc-inline-spinner" id="pifc-bulk-fulfill-spinner"></span>
            <span id="pifc-bulk-selected-count"></span>
        </div>
    </div>
    <div id="pifc-bulk-log" class="pifc-sync-log" style="display:none;margin-top:12px"></div>
</div>

<script>
window.pifcAdminInitPanel && window.pifcAdminInitPanel('bulk');
</script>
