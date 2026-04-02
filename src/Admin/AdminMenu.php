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
            [$this, 'renderSettings']
        );

        add_submenu_page(
            'fluent-cart',
            __('Printful — Product Sync', 'printful-for-fluentcart'),
            __('Printful Sync', 'printful-for-fluentcart'),
            'manage_options',
            'pifc-product-sync',
            [$this, 'renderProductSync']
        );

        add_submenu_page(
            'fluent-cart',
            __('Printful — Orders', 'printful-for-fluentcart'),
            __('Printful Orders', 'printful-for-fluentcart'),
            'manage_options',
            'pifc-orders',
            [$this, 'renderOrders']
        );
    }

    public function renderSettings()
    {
        (new SettingsPage())->render();
    }

    public function renderProductSync()
    {
        (new ProductSyncPage())->render();
    }

    public function renderOrders()
    {
        (new OrderPanel())->render();
    }

    public function enqueueAssets($hook)
    {
        $pifc_pages = [
            'fluent-cart_page_pifc-settings',
            'fluent-cart_page_pifc-product-sync',
            'fluent-cart_page_pifc-orders',
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
            'i18n'        => [
                'testing'      => __('Testing…', 'printful-for-fluentcart'),
                'saving'       => __('Saving…', 'printful-for-fluentcart'),
                'syncing'      => __('Syncing…', 'printful-for-fluentcart'),
                'fulfilling'   => __('Sending to Printful…', 'printful-for-fluentcart'),
                'canceling'    => __('Canceling…', 'printful-for-fluentcart'),
                'loading'      => __('Loading…', 'printful-for-fluentcart'),
                'confirmFulfill' => __('Send this order to Printful for fulfillment?', 'printful-for-fluentcart'),
                'confirmCancel'  => __('Cancel fulfillment for this order in Printful?', 'printful-for-fluentcart'),
            ],
        ]);
    }
}
