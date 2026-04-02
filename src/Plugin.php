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
        $this->registerEmailService();
        $this->registerActivityLogger();
        $this->registerRefundHandler();
        $this->registerDashboardWidget();
        $this->registerCliCommands();
        $this->registerAddressUpdateService();
        $this->registerCustomerPortalService();
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
        $bulkFulfill  = new Admin\BulkFulfillPage();
        $catalogPage  = new Admin\CatalogBrowserPage();
        $shippingSetup= new Admin\ShippingSetupPage();

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

        // Bulk fulfill AJAX
        $this->loader->addAction('wp_ajax_pifc_get_unfulfilled_orders', $bulkFulfill, 'handleGetUnfulfilled');
        $this->loader->addAction('wp_ajax_pifc_bulk_fulfill', $bulkFulfill, 'handleBulkFulfill');

        // Catalog browser AJAX
        $this->loader->addAction('wp_ajax_pifc_get_catalog_categories', $catalogPage, 'handleGetCategories');
        $this->loader->addAction('wp_ajax_pifc_get_catalog_products',   $catalogPage, 'handleGetProducts');
        $this->loader->addAction('wp_ajax_pifc_get_catalog_product',    $catalogPage, 'handleGetProduct');

        // Shipping setup AJAX
        $this->loader->addAction('wp_ajax_pifc_save_shipping_services', $shippingSetup, 'handleSave');
        $this->loader->addAction('wp_ajax_pifc_get_shipping_services',  $shippingSetup, 'handleGet');
    }

    private function registerFulfillmentServices()
    {
        $settings = get_option('pifc_settings', []);

        if (empty($settings['api_key'])) {
            return;
        }

        $fulfillment = new Services\OrderFulfillmentService();
        $this->loader->addAction('fluent_cart/order_paid_done',     $fulfillment, 'onOrderPaid',          10, 1);
        $this->loader->addAction('fluent_cart/order_placed_offline', $fulfillment, 'onOrderPlacedOffline', 10, 1);
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

    private function registerEmailService()
    {
        $email = new Services\ShippingEmailService();
        $this->loader->addAction('pifc/order_shipped', $email, 'onOrderShipped', 10, 2);
    }

    private function registerActivityLogger()
    {
        $logger = new Services\ActivityLogger();
        $this->loader->addAction('pifc/order_fulfilled',     $logger, 'onOrderFulfilled',    10, 2);
        $this->loader->addAction('pifc/order_shipped',       $logger, 'onOrderShipped',      10, 2);
        $this->loader->addAction('pifc/fulfillment_failed',  $logger, 'onFulfillmentFailed', 10, 2);
        $this->loader->addAction('pifc/order_returned',      $logger, 'onOrderReturned',     10, 2);
        $this->loader->addAction('pifc/fulfillment_canceled',$logger, 'onFulfillmentCanceled',10, 2);
    }

    private function registerRefundHandler()
    {
        $settings = get_option('pifc_settings', []);
        if (empty($settings['api_key'])) {
            return;
        }

        $refund = new Services\RefundService();
        $this->loader->addAction('fluent_cart/order_fully_refunded',    $refund, 'onOrderRefunded', 10, 1);
        $this->loader->addAction('fluent_cart/order_partially_refunded', $refund, 'onOrderRefunded', 10, 1);
    }

    private function registerDashboardWidget()
    {
        if (is_admin()) {
            $widget = new Admin\DashboardWidget();
            $this->loader->addAction('wp_dashboard_setup', $widget, 'register');
        }
    }

    private function registerCliCommands()
    {
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('pifc', 'PrintfulForFluentCart\\Cli\\CliCommands');
        }
    }

    private function registerCustomerPortalService()
    {
        $portal = new Services\CustomerPortalService();

        $this->loader->addFilter(
            'fluent_cart/customer/order_details_section_parts',
            $portal,
            'injectPortalTracking',
            10,
            2
        );

        $this->loader->addAction(
            'fluent_cart/receipt/thank_you/after_order_items',
            $portal,
            'injectThankYouTracking',
            10,
            1
        );
    }

    private function registerAddressUpdateService()
    {
        $settings = get_option('pifc_settings', []);
        if (empty($settings['api_key'])) {
            return;
        }

        $addressUpdate = new Services\OrderAddressUpdateService();
        $this->loader->addAction('fluent_cart/order_customer_changed', $addressUpdate, 'onCustomerChanged', 10, 1);

        $logger = new Services\ActivityLogger();
        $this->loader->addAction('pifc/order_address_updated', $logger, 'onOrderAddressUpdated', 10, 3);
    }
