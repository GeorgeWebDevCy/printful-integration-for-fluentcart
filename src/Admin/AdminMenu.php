<?php

namespace PrintfulForFluentCart\Admin;

defined('ABSPATH') || exit;

class AdminMenu
{
    public function register()
    {
        add_submenu_page(
            'fluent-cart',
            __('Printful Integration', 'printful-for-fluentcart'),
            __('Printful', 'printful-for-fluentcart'),
            'manage_options',
            'pifc-settings',
            function () {
                $this->renderNativeRouteRedirect('settings');
            }
        );

        add_submenu_page(
            'fluent-cart',
            __('Printful - Advanced Settings', 'printful-for-fluentcart'),
            __('Printful Advanced', 'printful-for-fluentcart'),
            'manage_options',
            'pifc-advanced',
            function () {
                $this->renderNativeRouteRedirect('advanced');
            }
        );

        add_submenu_page(
            'fluent-cart',
            __('Printful - Product Sync', 'printful-for-fluentcart'),
            __('Printful Sync', 'printful-for-fluentcart'),
            'manage_options',
            'pifc-product-sync',
            function () {
                $this->renderNativeRouteRedirect('sync');
            }
        );

        add_submenu_page(
            'fluent-cart',
            __('Printful - Orders', 'printful-for-fluentcart'),
            __('Printful Orders', 'printful-for-fluentcart'),
            'manage_options',
            'pifc-orders',
            function () {
                $this->renderNativeRouteRedirect('orders');
            }
        );

        add_submenu_page(
            'fluent-cart',
            __('Printful - Bulk Fulfill', 'printful-for-fluentcart'),
            __('Printful Bulk Fulfill', 'printful-for-fluentcart'),
            'manage_options',
            'pifc-bulk-fulfill',
            function () {
                $this->renderNativeRouteRedirect('bulk');
            }
        );

        add_submenu_page(
            'fluent-cart',
            __('Printful - Catalog Browser', 'printful-for-fluentcart'),
            __('Printful Catalog', 'printful-for-fluentcart'),
            'manage_options',
            'pifc-catalog',
            function () {
                $this->renderNativeRouteRedirect('catalog');
            }
        );

        add_submenu_page(
            'fluent-cart',
            __('Printful - Shipping Setup', 'printful-for-fluentcart'),
            __('Printful Shipping', 'printful-for-fluentcart'),
            'manage_options',
            'pifc-shipping-setup',
            function () {
                $this->renderNativeRouteRedirect('shipping');
            }
        );
    }

    public function renderNativeRouteRedirect($view = 'settings')
    {
        $target = $this->getNativeRouteUrl($view);
        ?>
        <div class="wrap">
            <meta http-equiv="refresh" content="0;url=<?php echo esc_url($target); ?>">
            <script>
                window.location.replace(<?php echo wp_json_encode($target); ?>);
            </script>
            <p>
                <?php esc_html_e('Redirecting to the FluentCart Printful integration screen...', 'printful-for-fluentcart'); ?>
                <a href="<?php echo esc_url($target); ?>">
                    <?php esc_html_e('Continue', 'printful-for-fluentcart'); ?>
                </a>
            </p>
        </div>
        <?php
    }

    public function enqueueAssets($hook)
    {
        $pifc_pages = [
            'toplevel_page_fluent-cart',
            'fluent-cart_page_pifc-settings',
            'fluent-cart_page_pifc-advanced',
            'fluent-cart_page_pifc-product-sync',
            'fluent-cart_page_pifc-orders',
            'fluent-cart_page_pifc-bulk-fulfill',
            'fluent-cart_page_pifc-catalog',
            'fluent-cart_page_pifc-shipping-setup',
        ];

        if (!in_array($hook, $pifc_pages, true)) {
            return;
        }

        wp_enqueue_style(
            'pifc-admin',
            PIFC_PLUGIN_URL . 'assets/css/admin.css',
            [],
            PIFC_VERSION
        );

        wp_enqueue_script(
            'pifc-admin',
            PIFC_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            PIFC_VERSION,
            true
        );

        wp_localize_script('pifc-admin', 'pifcAdmin', [
            'ajaxUrl'     => admin_url('admin-ajax.php'),
            'nonce'       => wp_create_nonce('pifc_admin_nonce'),
            'currentPage' => $hook,
            'routes'      => [
                'settings' => $this->getNativeRouteUrl('settings'),
                'advanced' => $this->getNativeRouteUrl('advanced'),
                'sync'     => $this->getNativeRouteUrl('sync'),
                'orders'   => $this->getNativeRouteUrl('orders'),
                'bulk'     => $this->getNativeRouteUrl('bulk'),
                'catalog'  => $this->getNativeRouteUrl('catalog'),
                'shipping' => $this->getNativeRouteUrl('shipping'),
            ],
            'i18n'        => [
                'testing'        => __('Testing...', 'printful-for-fluentcart'),
                'saving'         => __('Saving...', 'printful-for-fluentcart'),
                'syncing'        => __('Syncing...', 'printful-for-fluentcart'),
                'fulfilling'     => __('Sending to Printful...', 'printful-for-fluentcart'),
                'canceling'      => __('Canceling...', 'printful-for-fluentcart'),
                'loading'        => __('Loading...', 'printful-for-fluentcart'),
                'loadingPanel'   => __('Loading panel...', 'printful-for-fluentcart'),
                'confirmFulfill' => __('Send this order to Printful for fulfillment?', 'printful-for-fluentcart'),
                'confirmCancel'  => __('Cancel fulfillment for this order in Printful?', 'printful-for-fluentcart'),
            ],
        ]);
    }

    private function getNativeRouteUrl($view = 'settings')
    {
        $base = 'admin.php?page=fluent-cart#/integrations/printful';

        if ($view && $view !== 'settings') {
            $base .= '?view=' . rawurlencode($view);
        }

        return admin_url($base);
    }

}
