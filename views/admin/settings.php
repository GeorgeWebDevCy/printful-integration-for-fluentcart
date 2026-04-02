<?php defined('ABSPATH') || exit; ?>

<div class="wrap pifc-wrap">
    <?php
    $pifc_current_page = 'settings';
    $pifc_page_title = __('Printful Settings', 'printful-for-fluentcart');
    $pifc_page_subtitle = __('Connect your Printful store and control how fulfillment behaves inside FluentCart.', 'printful-for-fluentcart');
    include __DIR__ . '/partials/layout-start.php';
    ?>

    <div class="pifc-card">
        <h2><?php esc_html_e('API Connection', 'printful-for-fluentcart'); ?></h2>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="pifc_api_key">
                        <?php esc_html_e('Printful API Key', 'printful-for-fluentcart'); ?>
                    </label>
                </th>
                <td>
                    <input
                        type="password"
                        id="pifc_api_key"
                        name="api_key"
                        value=""
                        class="regular-text"
                        autocomplete="off"
                        placeholder="<?php echo !empty($settings['api_key']) ? esc_attr__('Enter a new Printful API key to replace the saved one', 'printful-for-fluentcart') : esc_attr__('Paste your Printful API key here', 'printful-for-fluentcart'); ?>"
                    >
                    <button type="button" id="pifc-test-connection" class="button button-secondary">
                        <?php esc_html_e('Test Connection', 'printful-for-fluentcart'); ?>
                    </button>
                    <span class="spinner pifc-inline-spinner" id="pifc-test-spinner"></span>
                    <span id="pifc-connection-status"></span>
                    <?php if (!empty($settings['api_key'])): ?>
                        <p class="description">
                            <?php
                            printf(
                                /* translators: %s: masked api key suffix */
                                esc_html__('A Printful API key is already saved. Enter a new key only if you want to replace it. Current key ending: %s', 'printful-for-fluentcart'),
                                esc_html(substr($settings['api_key'], -6))
                            );
                            ?>
                        </p>
                    <?php endif; ?>
                    <p class="description">
                        <?php
                        printf(
                            /* translators: %s: Printful dashboard link */
                            esc_html__('Generate your key in the %s → Settings → API.', 'printful-for-fluentcart'),
                            '<a href="https://www.printful.com/dashboard" target="_blank" rel="noopener noreferrer">Printful Dashboard</a>'
                        );
                        ?>
                    </p>
                </td>
            </tr>
        </table>
    </div>

    <div class="pifc-card">
        <h2><?php esc_html_e('Fulfillment Options', 'printful-for-fluentcart'); ?></h2>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php esc_html_e('Auto-Fulfill Orders', 'printful-for-fluentcart'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="auto_fulfill" value="1"
                            <?php checked(!empty($settings['auto_fulfill'])); ?>>
                        <?php esc_html_e('Automatically send paid orders to Printful for fulfillment', 'printful-for-fluentcart'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Auto-Confirm Orders', 'printful-for-fluentcart'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="auto_confirm" value="1"
                            <?php checked(!empty($settings['auto_confirm'])); ?>>
                        <?php esc_html_e('Automatically confirm Printful orders (this charges your Printful account — use with caution)', 'printful-for-fluentcart'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Draft / Test Mode', 'printful-for-fluentcart'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="test_mode" value="1"
                            <?php checked(!empty($settings['test_mode'])); ?>>
                        <?php esc_html_e('Create Printful orders in draft mode (no charge, no production)', 'printful-for-fluentcart'); ?>
                    </label>
                    <?php if (!empty($settings['test_mode'])): ?>
                        <p class="description pifc-notice-warning">
                            <?php esc_html_e('Test mode is active — orders will NOT be produced.', 'printful-for-fluentcart'); ?>
                        </p>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Sync on Import', 'printful-for-fluentcart'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="sync_on_import" value="1"
                            <?php checked(!empty($settings['sync_on_import'])); ?>>
                        <?php esc_html_e('Pull fresh product data from Printful when running a product sync', 'printful-for-fluentcart'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Sync Product Costs', 'printful-for-fluentcart'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="sync_product_costs" value="1"
                            <?php checked(!empty($settings['sync_product_costs'])); ?>>
                        <?php esc_html_e('Store Printful production costs on synced variations when available', 'printful-for-fluentcart'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Auto-Retry Failed Orders', 'printful-for-fluentcart'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="auto_retry_failed" value="1"
                            <?php checked(!empty($settings['auto_retry_failed'])); ?>>
                        <?php esc_html_e('Automatically re-submit a Printful order once if Printful reports it as failed', 'printful-for-fluentcart'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Only one retry is attempted per order to prevent infinite loops.', 'printful-for-fluentcart'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Shipping Emails', 'printful-for-fluentcart'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="disable_shipping_email" value="1"
                            <?php checked(!empty($settings['disable_shipping_email'])); ?>>
                        <?php esc_html_e('Disable the customer tracking email sent when Printful marks an order as shipped', 'printful-for-fluentcart'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Refund Handling', 'printful-for-fluentcart'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="disable_auto_cancel_on_refund" value="1"
                            <?php checked(!empty($settings['disable_auto_cancel_on_refund'])); ?>>
                        <?php esc_html_e('Disable automatic cancel attempts in Printful when a FluentCart order is refunded', 'printful-for-fluentcart'); ?>
                    </label>
                </td>
            </tr>
        </table>
    </div>

    <div class="pifc-card">
        <h2><?php esc_html_e('Webhooks', 'printful-for-fluentcart'); ?></h2>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php esc_html_e('Webhook URL', 'printful-for-fluentcart'); ?></th>
                <td>
                    <code class="pifc-code"><?php echo esc_html(rest_url('pifc/v1/webhook')); ?></code>
                    <p class="description">
                        <?php esc_html_e('This URL is registered with Printful automatically when you save settings. It receives shipment and order events.', 'printful-for-fluentcart'); ?>
                    </p>
                </td>
            </tr>
        </table>
    </div>

    <p class="submit">
        <button type="button" id="pifc-save-settings" class="button button-primary">
            <?php esc_html_e('Save Settings', 'printful-for-fluentcart'); ?>
        </button>
        <span class="spinner pifc-inline-spinner" id="pifc-save-spinner"></span>
        <span id="pifc-save-status"></span>
    </p>

    <?php include __DIR__ . '/partials/layout-end.php'; ?>
</div>
