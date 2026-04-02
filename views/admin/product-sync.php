<?php defined('ABSPATH') || exit; ?>

<div class="wrap pifc-wrap">

    <h1 class="pifc-page-title">
        <span class="dashicons dashicons-update"></span>
        <?php esc_html_e('Printful — Product Sync', 'printful-for-fluentcart'); ?>
    </h1>

    <?php
    $settings = get_option('pifc_settings', []);
    if (empty($settings['api_key'])) :
    ?>
    <div class="notice notice-warning inline">
        <p>
            <?php
            printf(
                /* translators: %s: settings page URL */
                esc_html__('No Printful API key configured. %s first.', 'printful-for-fluentcart'),
                '<a href="' . esc_url(admin_url('admin.php?page=pifc-settings')) . '">'
                    . esc_html__('Configure your settings', 'printful-for-fluentcart')
                    . '</a>'
            );
            ?>
        </p>
    </div>
    <?php endif; ?>

    <div class="pifc-card">
        <h2><?php esc_html_e('Sync All Products', 'printful-for-fluentcart'); ?></h2>
        <p>
            <?php esc_html_e(
                'Imports every product from your connected Printful store into FluentCart. '
                . 'Existing products are updated; new products are created. '
                . 'Product images are downloaded and set as the featured image.',
                'printful-for-fluentcart'
            ); ?>
        </p>
        <p>
            <button type="button" id="pifc-sync-all" class="button button-primary"
                <?php echo empty($settings['api_key']) ? 'disabled' : ''; ?>>
                <?php esc_html_e('Sync All Products from Printful', 'printful-for-fluentcart'); ?>
            </button>
        </p>
        <div id="pifc-sync-log"></div>
    </div>

    <div class="pifc-card">
        <h2><?php esc_html_e('Sync Single Product', 'printful-for-fluentcart'); ?></h2>
        <p>
            <?php esc_html_e(
                'Enter the Printful sync-product ID to import or refresh a single product. '
                . 'You can find the ID in your Printful store product list URL.',
                'printful-for-fluentcart'
            ); ?>
        </p>
        <p>
            <input
                type="number"
                id="pifc-single-product-id"
                class="small-text"
                min="1"
                placeholder="<?php esc_attr_e('e.g. 123456789', 'printful-for-fluentcart'); ?>"
                <?php echo empty($settings['api_key']) ? 'disabled' : ''; ?>
            >
            <button type="button" id="pifc-sync-single" class="button button-secondary"
                <?php echo empty($settings['api_key']) ? 'disabled' : ''; ?>>
                <?php esc_html_e('Sync Product', 'printful-for-fluentcart'); ?>
            </button>
            <span id="pifc-single-sync-status"></span>
        </p>
    </div>

    <div class="pifc-card">
        <h2><?php esc_html_e('How It Works', 'printful-for-fluentcart'); ?></h2>
        <ol>
            <li><?php esc_html_e('Create and configure products in your Printful store (printful.com).', 'printful-for-fluentcart'); ?></li>
            <li><?php esc_html_e('Click "Sync All Products" to import them into FluentCart.', 'printful-for-fluentcart'); ?></li>
            <li><?php esc_html_e('Each Printful variant maps to a FluentCart product variation.', 'printful-for-fluentcart'); ?></li>
            <li><?php esc_html_e('When a customer places and pays for an order, it is automatically sent to Printful for production (if Auto-Fulfill is enabled).', 'printful-for-fluentcart'); ?></li>
        </ol>
    </div>

</div>
