<?php

/**
 * Admin experience for the Printful <-> FluentCart integration.
 *
 * Handles menu registration, settings UI, and persistence for API credentials
 * as well as simple variant mapping management.
 *
 * @package Printful_Integration_For_Fluentcart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Printful_Integration_For_Fluentcart_Admin {

	/**
	 * Plugin slug.
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Constructor.
	 *
	 * @param string $plugin_name Plugin slug.
	 * @param string $version     Plugin version.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;

               add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
               add_action( 'admin_post_printful_fluentcart_save_settings', array( $this, 'handle_save_settings' ) );
               add_action( 'admin_init', array( $this, 'maybe_redirect_broken_slug' ) );
       }

	/**
	 * Register stylesheet in admin when needed.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 *
	 * @return void
	 */
	public function enqueue_styles( $hook_suffix = '' ) {
		if ( strpos( $hook_suffix, 'printful-fluentcart' ) === false ) {
			return;
		}

		wp_enqueue_style(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'css/printful-integration-for-fluentcart-admin.css',
			array(),
			$this->version,
			'all'
		);
	}

	/**
	 * Register JavaScript in admin when needed.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 *
	 * @return void
	 */
	public function enqueue_scripts( $hook_suffix = '' ) {
		if ( strpos( $hook_suffix, 'printful-fluentcart' ) === false ) {
			return;
		}

		wp_enqueue_script(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'js/printful-integration-for-fluentcart-admin.js',
			array( 'jquery' ),
			$this->version,
			false
		);
	}

	/**
	 * Attach submenu under FluentCart (or Settings if FluentCart is missing).
	 *
	 * @return void
	 */
	public function register_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

               $parent_slug = class_exists( '\FluentCart\App\App' ) ? 'fluent-cart' : 'options-general.php';

               add_submenu_page(
                       $parent_slug,
                       __( 'Printful Integration', 'printful-integration-for-fluentcart' ),
                       __( 'Printful Integration', 'printful-integration-for-fluentcart' ),
                       'manage_options',
                       'printful-fluentcart',
                       array( $this, 'render_settings_page' )
               );
       }

       /**
        * Ensure broken FluentCart submenu links redirect to the correct URL.
        *
        * Some FluentCart builds mutate submenu links which can drop the
        * `admin.php?page=` portion of the URL. When that happens WordPress ends
        * up handling the request as `/wp-admin/printful-fluentcart` which
        * produces a 404. Detect that request and redirect to the canonical
        * settings URL so the screen loads normally.
        *
        * @return void
        */
       public function maybe_redirect_broken_slug() {
               if ( ! is_admin() ) {
                       return;
               }

               if ( headers_sent() ) {
                       return;
               }

               $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';

               if ( '' === $request_uri ) {
                       return;
               }

               $request_path = strtok( $request_uri, '?' );
               $admin_path   = wp_parse_url( admin_url(), PHP_URL_PATH );

               if ( false === $admin_path ) {
                       $admin_path = '/wp-admin/';
               }

               $admin_path = trailingslashit( $admin_path );

               if ( $request_path !== $admin_path . 'printful-fluentcart' && $request_path !== $admin_path . 'printful-fluentcart/' ) {
                       return;
               }

               $target_url = admin_url( 'admin.php?page=printful-fluentcart' );

               if ( $target_url === home_url( $request_uri ) ) {
                       return;
               }

               wp_safe_redirect( $target_url, 301 );
               exit;
       }

	/**
	 * Render settings form.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'printful-integration-for-fluentcart' ) );
		}

		$settings      = Printful_Integration_For_Fluentcart_Settings::all();
		$variant_map   = Printful_Integration_For_Fluentcart_Product_Mapping::get_all_variation_mappings();
		$mapping_lines = array();

		foreach ( $variant_map as $variation_id => $printful_id ) {
			$mapping_lines[] = $variation_id . ':' . $printful_id;
		}

		$mapping_text   = implode( PHP_EOL, $mapping_lines );
		$webhook_url    = rest_url( Printful_Integration_For_Fluentcart_Webhook_Controller::REST_NAMESPACE . Printful_Integration_For_Fluentcart_Webhook_Controller::REST_ROUTE );
		$poll_interval  = isset( $settings['poll_interval_minutes'] ) ? (int) $settings['poll_interval_minutes'] : 10;
		$poll_interval  = max( 5, $poll_interval );
		$webhook_secret = isset( $settings['webhook_secret'] ) ? $settings['webhook_secret'] : '';

		settings_errors( 'printful_fluentcart' );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Printful Integration for FluentCart', 'printful-integration-for-fluentcart' ); ?></h1>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'printful_fluentcart_save_settings' ); ?>
				<input type="hidden" name="action" value="printful_fluentcart_save_settings" />

				<h2><?php esc_html_e( 'API Credentials', 'printful-integration-for-fluentcart' ); ?></h2>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="printful_fluentcart_api_key"><?php esc_html_e( 'Printful API Key', 'printful-integration-for-fluentcart' ); ?></label>
							</th>
							<td>
								<input type="text" class="regular-text" id="printful_fluentcart_api_key" name="printful_fluentcart_settings[api_key]" value="<?php echo esc_attr( $settings['api_key'] ); ?>" autocomplete="off" />
								<p class="description"><?php esc_html_e( 'Generate a key under Stores → Integrations in your Printful dashboard.', 'printful-integration-for-fluentcart' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="printful_fluentcart_default_shipping"><?php esc_html_e( 'Default Shipping Method', 'printful-integration-for-fluentcart' ); ?></label>
							</th>
							<td>
								<input type="text" class="regular-text" id="printful_fluentcart_default_shipping" name="printful_fluentcart_settings[default_shipping_method]" value="<?php echo esc_attr( $settings['default_shipping_method'] ); ?>" />
								<p class="description"><?php esc_html_e( 'Optional Printful shipping service code (e.g. STANDARD). Leave blank to let Printful choose automatically.', 'printful-integration-for-fluentcart' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>

				<h2><?php esc_html_e( 'Webhooks', 'printful-integration-for-fluentcart' ); ?></h2>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Enable Printful webhooks', 'printful-integration-for-fluentcart' ); ?></th>
							<td>
								<label for="printful_fluentcart_enable_webhooks">
									<input type="checkbox" id="printful_fluentcart_enable_webhooks" name="printful_fluentcart_settings[enable_webhooks]" value="1" <?php checked( ! empty( $settings['enable_webhooks'] ) ); ?> />
									<?php esc_html_e( 'Accept fulfilment status callbacks from Printful using the endpoint below.', 'printful-integration-for-fluentcart' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="printful_fluentcart_webhook_secret"><?php esc_html_e( 'Webhook Secret', 'printful-integration-for-fluentcart' ); ?></label>
							</th>
							<td>
								<input type="text" class="regular-text" id="printful_fluentcart_webhook_secret" name="printful_fluentcart_settings[webhook_secret]" value="<?php echo esc_attr( $webhook_secret ); ?>" autocomplete="off" />
								<p class="description"><?php esc_html_e( 'Use the same secret when configuring your Printful webhook so signatures can be validated.', 'printful-integration-for-fluentcart' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Webhook Endpoint', 'printful-integration-for-fluentcart' ); ?></th>
							<td>
								<input type="text" class="regular-text code" readonly value="<?php echo esc_attr( $webhook_url ); ?>" onclick="this.select();" />
								<p class="description"><?php esc_html_e( 'Configure this URL for order-related events inside the Printful dashboard.', 'printful-integration-for-fluentcart' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>

                                <h2><?php esc_html_e( 'Automation', 'printful-integration-for-fluentcart' ); ?></h2>
                                <table class="form-table" role="presentation">
                                        <tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Auto-fulfil paid orders', 'printful-integration-for-fluentcart' ); ?></th>
							<td>
								<label for="printful_fluentcart_auto_fulfill">
									<input type="checkbox" id="printful_fluentcart_auto_fulfill" name="printful_fluentcart_settings[auto_fulfill_paid]" value="1" <?php checked( ! empty( $settings['auto_fulfill_paid'] ) ); ?> />
									<?php esc_html_e( 'Automatically push paid FluentCart orders to Printful.', 'printful-integration-for-fluentcart' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'API call logging', 'printful-integration-for-fluentcart' ); ?></th>
							<td>
								<label for="printful_fluentcart_log_api">
									<input type="checkbox" id="printful_fluentcart_log_api" name="printful_fluentcart_settings[log_api_calls]" value="1" <?php checked( ! empty( $settings['log_api_calls'] ) ); ?> />
									<?php esc_html_e( 'Record Printful API calls in FluentCart logs.', 'printful-integration-for-fluentcart' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Background polling', 'printful-integration-for-fluentcart' ); ?></th>
							<td>
								<label for="printful_fluentcart_enable_polling">
									<input type="checkbox" id="printful_fluentcart_enable_polling" name="printful_fluentcart_settings[enable_polling]" value="1" <?php checked( ! empty( $settings['enable_polling'] ) ); ?> />
									<?php esc_html_e( 'Periodically query Printful for order updates (via WP-Cron). Recommended if webhooks are unavailable.', 'printful-integration-for-fluentcart' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="printful_fluentcart_poll_interval"><?php esc_html_e( 'Polling Interval (minutes)', 'printful-integration-for-fluentcart' ); ?></label>
							</th>
							<td>
								<input type="number" min="5" id="printful_fluentcart_poll_interval" name="printful_fluentcart_settings[poll_interval_minutes]" value="<?php echo esc_attr( $poll_interval ); ?>" />
								<p class="description"><?php esc_html_e( 'Defines how frequently pending orders are refreshed when background polling is enabled.', 'printful-integration-for-fluentcart' ); ?></p>
							</td>
						</tr>
                                        </tbody>
                                </table>

                                <h2><?php esc_html_e( 'Live Shipping Rates', 'printful-integration-for-fluentcart' ); ?></h2>
                                <table class="form-table" role="presentation">
                                        <tbody>
                                                <tr>
                                                        <th scope="row"><?php esc_html_e( 'Enable live rates', 'printful-integration-for-fluentcart' ); ?></th>
                                                        <td>
                                                                <label for="printful_fluentcart_enable_live_rates">
                                                                        <input type="checkbox" id="printful_fluentcart_enable_live_rates" name="printful_fluentcart_settings[enable_live_rates]" value="1" <?php checked( ! empty( $settings['enable_live_rates'] ) ); ?> />
                                                                        <?php esc_html_e( 'Fetch Printful shipping rates during checkout.', 'printful-integration-for-fluentcart' ); ?>
                                                                </label>
                                                        </td>
                                                </tr>
                                                <tr>
                                                        <th scope="row">
                                                                <label for="printful_fluentcart_shipping_markup"><?php esc_html_e( 'Rate markup (%)', 'printful-integration-for-fluentcart' ); ?></label>
                                                        </th>
                                                        <td>
                                                                <input type="number" step="0.1" id="printful_fluentcart_shipping_markup" name="printful_fluentcart_settings[shipping_markup_percent]" value="<?php echo esc_attr( isset( $settings['shipping_markup_percent'] ) ? $settings['shipping_markup_percent'] : 0 ); ?>" />
                                                                <p class="description"><?php esc_html_e( 'Optional percentage added on top of Printful rates.', 'printful-integration-for-fluentcart' ); ?></p>
                                                        </td>
                                                </tr>
                                        </tbody>
                                </table>

                                <h2><?php esc_html_e( 'Variant Mapping', 'printful-integration-for-fluentcart' ); ?></h2>
                                <p><?php esc_html_e( 'Map each FluentCart variation ID to a Printful variant ID. Use one mapping per line: variation_id:printful_variant_id', 'printful-integration-for-fluentcart' ); ?></p>
                                <textarea class="large-text code" rows="8" name="printful_fluentcart_variant_mapping"><?php echo esc_textarea( $mapping_text ); ?></textarea>

				<?php submit_button( __( 'Save Settings', 'printful-integration-for-fluentcart' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Persist settings & mappings.
	 *
	 * @return void
	 */
	public function handle_save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to perform this action.', 'printful-integration-for-fluentcart' ) );
		}

		check_admin_referer( 'printful_fluentcart_save_settings' );

		$input       = isset( $_POST['printful_fluentcart_settings'] ) ? wp_unslash( $_POST['printful_fluentcart_settings'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw_mapping = isset( $_POST['printful_fluentcart_variant_mapping'] ) ? wp_unslash( $_POST['printful_fluentcart_variant_mapping'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$settings = array(
			'api_key'                 => isset( $input['api_key'] ) ? sanitize_text_field( $input['api_key'] ) : '',
			'default_shipping_method' => isset( $input['default_shipping_method'] ) ? sanitize_text_field( $input['default_shipping_method'] ) : '',
			'auto_fulfill_paid'       => ! empty( $input['auto_fulfill_paid'] ),
			'log_api_calls'           => ! empty( $input['log_api_calls'] ),
			'enable_webhooks'         => ! empty( $input['enable_webhooks'] ),
			'webhook_secret'          => isset( $input['webhook_secret'] ) ? sanitize_text_field( $input['webhook_secret'] ) : '',
                        'enable_polling'          => ! empty( $input['enable_polling'] ),
                        'poll_interval_minutes'   => isset( $input['poll_interval_minutes'] ) ? max( 5, (int) $input['poll_interval_minutes'] ) : 10,
                        'enable_live_rates'       => ! empty( $input['enable_live_rates'] ),
                        'shipping_markup_percent' => isset( $input['shipping_markup_percent'] ) ? floatval( $input['shipping_markup_percent'] ) : 0,
                );

		$general_errors = array();

		if ( $settings['enable_webhooks'] && '' === $settings['webhook_secret'] ) {
			$general_errors[] = __( 'Please provide a webhook secret when enabling Printful webhooks.', 'printful-integration-for-fluentcart' );
		}

		Printful_Integration_For_Fluentcart_Settings::update( $settings );

		$new_mappings   = array();
		$mapping_errors = array();

		$lines = array_filter( array_map( 'trim', explode( PHP_EOL, (string) $raw_mapping ) ) );
		foreach ( $lines as $line ) {
			$parts = array_map( 'trim', explode( ':', $line ) );
			if ( count( $parts ) !== 2 ) {
				$mapping_errors[] = sprintf(
					/* translators: %s: provided mapping line */
					esc_html__( 'Invalid mapping format: %s', 'printful-integration-for-fluentcart' ),
					esc_html( $line )
				);
				continue;
			}

			list( $variation_id, $printful_id ) = $parts;
			$variation_id = (int) $variation_id;

			if ( $variation_id <= 0 || '' === $printful_id ) {
				continue;
			}

			$new_mappings[ $variation_id ] = sanitize_text_field( $printful_id );
			Printful_Integration_For_Fluentcart_Product_Mapping::set_variation_mapping( $variation_id, $printful_id );
		}

		$current_mappings = Printful_Integration_For_Fluentcart_Product_Mapping::get_all_variation_mappings();
		foreach ( $current_mappings as $variation_id => $printful_id ) {
			if ( ! isset( $new_mappings[ $variation_id ] ) ) {
				Printful_Integration_For_Fluentcart_Product_Mapping::delete_variation_mapping( $variation_id );
			}
		}

		Printful_Integration_For_Fluentcart_Settings::set( 'mapped_products', $new_mappings );

		if ( class_exists( 'Printful_Integration_For_Fluentcart_Sync_Manager' ) ) {
			wp_clear_scheduled_hook( Printful_Integration_For_Fluentcart_Sync_Manager::CRON_HOOK );
		}

		foreach ( $general_errors as $error ) {
			add_settings_error( 'printful_fluentcart', 'printful_fluentcart_general_error_' . md5( $error ), $error, 'error' );
		}

		if ( $mapping_errors ) {
			foreach ( $mapping_errors as $error ) {
				add_settings_error( 'printful_fluentcart', 'printful_fluentcart_mapping_error_' . md5( $error ), $error, 'error' );
			}
		} elseif ( empty( $general_errors ) ) {
			add_settings_error(
				'printful_fluentcart',
				'printful_fluentcart_saved',
				esc_html__( 'Settings saved.', 'printful-integration-for-fluentcart' ),
				'updated'
			);
		}

		$redirect_page = class_exists( '\FluentCart\App\App' ) ? 'admin.php' : 'options-general.php';

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'             => 'printful-fluentcart',
					'settings-updated' => 'true',
				),
				admin_url( $redirect_page )
			)
		);
		exit;
	}
}

