<?php

namespace PrintfulForFluentCart;

defined('ABSPATH') || exit;

/**
 * Central orchestrator: wires all services and admin pages together.
 */
class Plugin
{
    /** @var self|null */
    private static $instance = null;

    /** @var Loader */
    private $loader;

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->loader = new Loader();
    }

    public function boot()
    {
        $this->loadTextDomain();
        $this->registerAdminServices();
        $this->registerFulfillmentServices();
        $this->registerShippingServices();
        $this->registerWebhookService();
        $this->loader->run();
    }

    private function loadTextDomain()
    {
        load_plugin_textdomain(
            'printful-for-fluentcart',
            false,
            dirname(plugin_basename(PIFC_PLUGIN_FILE)) . '/languages'
        );
    }

    private function registerAdminServices()
    {
        if (!is_admin()) {
            return;
        }

        $adminMenu    = new Admin\AdminMenu();
        $settingsPage = new Admin\SettingsPage();
        $syncPage     = new Admin\ProductSyncPage();
        $orderPanel   = new Admin\OrderPanel();

        $this->loader->addAction('admin_menu', $adminMenu, 'register', 25);
        $this->loader->addAction('admin_enqueue_scripts', $adminMenu, 'enqueueAssets');

        // Settings AJAX
        $this->loader->addAction('wp_ajax_pifc_test_connection', $settingsPage, 'handleConnectionTest');
        $this->loader->addAction('wp_ajax_pifc_save_settings', $settingsPage, 'handleSaveSettings');

        // Product sync AJAX
        $this->loader->addAction('wp_ajax_pifc_sync_all_products', $syncPage, 'handleSyncAll');
        $this->loader->addAction('wp_ajax_pifc_sync_single_product', $syncPage, 'handleSyncSingle');

        // Order panel AJAX
        $this->loader->addAction('wp_ajax_pifc_get_orders', $orderPanel, 'handleGetOrders');
        $this->loader->addAction('wp_ajax_pifc_fulfill_order', $orderPanel, 'handleManualFulfill');
        $this->loader->addAction('wp_ajax_pifc_cancel_fulfillment', $orderPanel, 'handleCancelFulfillment');
        $this->loader->addAction('wp_ajax_pifc_get_order_detail', $orderPanel, 'handleGetOrderDetail');
    }

    private function registerFulfillmentServices()
    {
        $settings = get_option('pifc_settings', []);

        if (empty($settings['api_key'])) {
            return;
        }

        $fulfillment = new Services\OrderFulfillmentService();
        $this->loader->addAction('fluent_cart/order_paid_done', $fulfillment, 'onOrderPaid', 10, 1);
    }

    private function registerShippingServices()
    {
        $settings = get_option('pifc_settings', []);

        if (empty($settings['api_key'])) {
            return;
        }

        $shipping = new Services\ShippingRateService();
        // Hook into checkout data patching to inject Printful rates
        $this->loader->addFilter(
            'fluent_cart/checkout/before_patch_checkout_data',
            $shipping,
            'injectShippingRates',
            10,
            2
        );
    }

    private function registerWebhookService()
    {
        $webhookService = new Services\WebhookService();
        $this->loader->addAction('rest_api_init', $webhookService, 'registerEndpoint');
    }
}
