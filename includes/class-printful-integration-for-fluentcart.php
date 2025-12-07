<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://www.georgenicolaou.me/
 * @since      1.0.0
 *
 * @package    Printful_Integration_For_Fluentcart
 * @subpackage Printful_Integration_For_Fluentcart/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Printful_Integration_For_Fluentcart
 * @subpackage Printful_Integration_For_Fluentcart/includes
 * @author     George Nicolaou <orionas.elite@gmail.com>
 */
class Printful_Integration_For_Fluentcart {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Printful_Integration_For_Fluentcart_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Order sync handler instance.
	 *
	 * @var Printful_Integration_For_Fluentcart_Order_Sync|null
	 */
	protected $order_sync = null;

	/**
	 * Sync manager instance.
	 *
	 * @var Printful_Integration_For_Fluentcart_Sync_Manager|null
	 */
	protected $sync_manager = null;

        /**
         * Webhook controller instance.
         *
         * @var Printful_Integration_For_Fluentcart_Webhook_Controller|null
         */
        protected $webhook_controller = null;

        /**
         * Shipping integration handler.
         *
         * @var Printful_Integration_For_Fluentcart_Shipping|null
         */
        protected $shipping = null;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'PRINTFUL_INTEGRATION_FOR_FLUENTCART_VERSION' ) ) {
			$this->version = PRINTFUL_INTEGRATION_FOR_FLUENTCART_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'printful-integration-for-fluentcart';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->define_integration_bootstrap();

                Printful_Integration_For_Fluentcart_Rest::register();
                Printful_Integration_For_Fluentcart_Size_Guide::register();
                Printful_Integration_For_Fluentcart_Product_Meta::register();
                Printful_Integration_For_Fluentcart_Token_Migration::register();
                PIFC_Variant_Meta::register();
                PIFC_Delta_Sync::register();
                PIFC_Product_Import_Screen::register();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Printful_Integration_For_Fluentcart_Loader. Orchestrates the hooks of the plugin.
	 * - Printful_Integration_For_Fluentcart_i18n. Defines internationalization functionality.
	 * - Printful_Integration_For_Fluentcart_Admin. Defines all hooks for the admin area.
	 * - Printful_Integration_For_Fluentcart_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-printful-integration-for-fluentcart-loader.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-printful-integration-for-fluentcart-settings.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-printful-integration-for-fluentcart-api.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-printful-integration-for-fluentcart-catalog.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-printful-integration-for-fluentcart-product-mapping.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-printful-integration-for-fluentcart-sync-queue.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-printful-integration-for-fluentcart-sync-manager.php';
                require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-printful-integration-for-fluentcart-webhook-controller.php';
                require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-printful-integration-for-fluentcart-order-sync.php';
                require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-printful-integration-for-fluentcart-shipping.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-printful-integration-for-fluentcart-logger.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-printful-integration-for-fluentcart-rest.php';
                require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-printful-integration-for-fluentcart-size-guide.php';
                require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-printful-integration-for-fluentcart-product-importer.php';
                require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-printful-integration-for-fluentcart-product-meta.php';
                require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-printful-integration-for-fluentcart-token-migration.php';
                require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-printful-integration-for-fluentcart-order-actions.php';
                require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/printful/class-pifc-printful-catalog.php';
                require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/printful/class-pifc-product-creator.php';
                require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/cron/class-pifc-delta-sync.php';
                require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/admin/meta/class-pifc-variant-meta.php';
                require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-pifc-product-import.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-printful-integration-for-fluentcart-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-printful-integration-for-fluentcart-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-printful-integration-for-fluentcart-public.php';

		Printful_Integration_For_Fluentcart_Catalog::register_cron();

		$this->loader = new Printful_Integration_For_Fluentcart_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Printful_Integration_For_Fluentcart_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Printful_Integration_For_Fluentcart_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Printful_Integration_For_Fluentcart_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Printful_Integration_For_Fluentcart_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

	}

	/**
	 * Register bootstrap hook that wires up the FluentCart integration layer.
	 *
	 * We wait until all plugins are loaded to make sure FluentCart classes are
	 * available before instantiating the sync layer.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function define_integration_bootstrap() {
		$this->loader->add_action( 'plugins_loaded', $this, 'maybe_bootstrap_integration', 20 );
	}

	/**
	 * Instantiate order sync logic once WordPress + FluentCart are ready.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function maybe_bootstrap_integration() {
		if ( ! class_exists( '\FluentCart\App\Models\Order' ) ) {
			return;
		}

		$settings = Printful_Integration_For_Fluentcart_Settings::all();
		$api_key  = isset( $settings['api_key'] ) ? trim( $settings['api_key'] ) : '';

		if ( empty( $api_key ) ) {
			return;
		}

		$api = new Printful_Integration_For_Fluentcart_Api(
			$api_key,
			isset( $settings['api_base'] ) ? $settings['api_base'] : 'https://api.printful.com',
			! empty( $settings['log_api_calls'] )
		);

                $this->order_sync = new Printful_Integration_For_Fluentcart_Order_Sync( $api, $settings );
                $this->order_sync->register();

                $this->sync_manager = new Printful_Integration_For_Fluentcart_Sync_Manager( $api, $settings );
                $this->sync_manager->register();

                $this->webhook_controller = new Printful_Integration_For_Fluentcart_Webhook_Controller( $this->sync_manager, $settings );
                $this->webhook_controller->register();

                $this->shipping = new Printful_Integration_For_Fluentcart_Shipping( $api, $settings );
                $this->shipping->register();

		$order_actions = new Printful_Integration_For_Fluentcart_Order_Actions( $api, $this->order_sync, $this->sync_manager, $settings );
		$order_actions->register();

		if ( ! empty( $settings['auto_sync_catalog'] ) ) {
			Printful_Integration_For_Fluentcart_Catalog::ensure_cron();
		} else {
			Printful_Integration_For_Fluentcart_Catalog::clear_cron();
		}
        }

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Printful_Integration_For_Fluentcart_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
