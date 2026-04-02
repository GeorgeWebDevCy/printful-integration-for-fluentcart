<?php defined('ABSPATH') || exit; ?>

<div class="wrap pifc-wrap">

    <h1 class="pifc-page-title">
        <span class="dashicons dashicons-store"></span>
        <?php esc_html_e('Printful Integration for FluentCart', 'printful-for-fluentcart'); ?>
    </h1>

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
                        value="<?php echo esc_attr($settings['api_key'] ?? ''); ?>"
                        class="regular-text"
                        autocomplete="off"
                        placeholder="<?php esc_attr_e('Paste your Printful API key here', 'printful-for-fluentcart'); ?>"
                    >
                    <button type="button" id="pifc-test-connection" class="button button-secondary">
                        <?php esc_html_e('Test Connection', 'printful-for-fluentcart'); ?>
                    </button>
                    <span id="pifc-connection-status"></span>
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
        <span id="pifc-save-status"></span>
    </p>

</div>
