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
               add_action( 'wp_ajax_printful_fluentcart_test_connection', array( $this, 'handle_test_connection' ) );
               add_action( 'wp_ajax_printful_fluentcart_sync_catalog', array( $this, 'handle_sync_catalog' ) );
       add_action( 'admin_post_printful_fluentcart_import_product', array( $this, 'handle_import_product' ) );
       add_action( 'admin_post_printful_fluentcart_clear_logs', array( $this, 'handle_clear_logs' ) );
       add_action( 'admin_post_printful_fluentcart_clear_queue', array( $this, 'handle_clear_queue' ) );
       add_action( 'wp_ajax_printful_fluentcart_fetch_carriers', array( $this, 'handle_fetch_carriers' ) );
       add_action( 'wp_dashboard_setup', array( $this, 'register_dashboard_widget' ) );
       add_action( 'admin_notices', array( $this, 'maybe_show_notices' ) );
       Printful_Integration_For_Fluentcart_Status_Checklist::register();
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
		wp_enqueue_style(
			$this->plugin_name . '-order-widget',
			plugin_dir_url( __FILE__ ) . 'css/printful-integration-for-fluentcart-order-widget.css',
			array(),
			$this->version,
			'all'
		);

		if ( isset( $_GET['page'] ) && $_GET['page'] === 'printful-fluentcart-diagnostics' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_enqueue_style(
				$this->plugin_name . '-order-widget',
				plugin_dir_url( __FILE__ ) . 'css/printful-integration-for-fluentcart-order-widget.css',
				array(),
				$this->version,
				'all'
			);
		}
	}

	/**
	 * Show admin notices for critical integration states.
	 *
	 * @return void
	 */
	public function maybe_show_notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$signature = class_exists( 'Printful_Integration_For_Fluentcart_Logger' ) ? Printful_Integration_For_Fluentcart_Logger::signature_failures() : 0;
		$settings  = Printful_Integration_For_Fluentcart_Settings::all();

		if ( $signature > 0 ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Printful: Webhook signature failures detected. Verify your webhook secret.', 'printful-integration-for-fluentcart' ) . '</p></div>';
		}

		if ( empty( $settings['api_key'] ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Printful: API key is not configured. Orders will not sync.', 'printful-integration-for-fluentcart' ) . '</p></div>';
		}
	}

	/**
	 * Diagnostics screen.
	 *
	 * @return void
	 */
	public function render_diagnostics_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'printful-integration-for-fluentcart' ) );
		}

                $settings        = Printful_Integration_For_Fluentcart_Settings::all();
                $level           = isset( $_GET['printful_log_level'] ) ? sanitize_text_field( wp_unslash( $_GET['printful_log_level'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $search          = isset( $_GET['printful_log_search'] ) ? sanitize_text_field( wp_unslash( $_GET['printful_log_search'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $logs            = array();
                if ( class_exists( 'Printful_Integration_For_Fluentcart_Logger' ) ) {
                        if ( $search ) {
                                $logs = Printful_Integration_For_Fluentcart_Logger::limit( Printful_Integration_For_Fluentcart_Logger::search( $search ), 100 );
                        } else {
                                $logs = Printful_Integration_For_Fluentcart_Logger::limit( Printful_Integration_For_Fluentcart_Logger::filter( $level ), 100 );
                        }
                }
                $stats           = class_exists( 'Printful_Integration_For_Fluentcart_Logger' ) ? Printful_Integration_For_Fluentcart_Logger::stats() : array();
                $snapshot        = Printful_Integration_For_Fluentcart_Diagnostics::snapshot();
                $last_migration  = isset( $settings['last_migration'] ) ? $settings['last_migration'] : array();
                $checklist_items = Printful_Integration_For_Fluentcart_Status_Checklist::items();
                $request_logs    = class_exists( 'Printful_Integration_For_Fluentcart_Request_Log' ) ? Printful_Integration_For_Fluentcart_Request_Log::recent( 5 ) : array();
                $error_logs      = isset( $snapshot['recent_errors'] ) ? $snapshot['recent_errors'] : array();

                ?>
                <div class="wrap">
                        <h1><?php esc_html_e( 'Printful Diagnostics', 'printful-integration-for-fluentcart' ); ?></h1>
                        <p><?php esc_html_e( 'Quick health overview and recent API/webhook activity.', 'printful-integration-for-fluentcart' ); ?></p>
                        <style>
                        .printful-diagnostics-grid {display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));grid-gap:12px;margin:16px 0;}
                        .printful-diagnostics-card {border:1px solid #ddd;border-radius:4px;padding:12px;background:#fff;}
                        .printful-diagnostics-card h3 {margin:0 0 6px;font-size:15px;}
                        .printful-diagnostics-card .status-ok {color:#2e8b57;font-weight:600;}
                        .printful-diagnostics-card .status-warn {color:#cc0000;font-weight:600;}
                        </style>

                        <div class="printful-diagnostics-grid">
                                <div class="printful-diagnostics-card">
                                        <h3><?php esc_html_e( 'Connectivity', 'printful-integration-for-fluentcart' ); ?></h3>
                                        <p class="<?php echo $snapshot['api_key_present'] ? 'status-ok' : 'status-warn'; ?>"><?php echo $snapshot['api_key_present'] ? esc_html__( 'API key configured', 'printful-integration-for-fluentcart' ) : esc_html__( 'API key missing', 'printful-integration-for-fluentcart' ); ?></p>
                                        <p><?php esc_html_e( 'API error count', 'printful-integration-for-fluentcart' ); ?>: <?php echo isset( $stats['errors'] ) ? esc_html( (int) $stats['errors'] ) : 0; ?></p>
                                </div>
                                <div class="printful-diagnostics-card">
                                        <h3><?php esc_html_e( 'Webhooks', 'printful-integration-for-fluentcart' ); ?></h3>
                                        <p class="<?php echo $snapshot['webhooks_enabled'] ? 'status-ok' : 'status-warn'; ?>"><?php echo $snapshot['webhooks_enabled'] ? esc_html__( 'Enabled with secret', 'printful-integration-for-fluentcart' ) : esc_html__( 'Disabled or missing secret', 'printful-integration-for-fluentcart' ); ?></p>
                                        <p><?php esc_html_e( 'Signature failures', 'printful-integration-for-fluentcart' ); ?>: <?php echo esc_html( $snapshot['signature_failures'] ); ?></p>
                                </div>
                                <div class="printful-diagnostics-card">
                                        <h3><?php esc_html_e( 'Queue', 'printful-integration-for-fluentcart' ); ?></h3>
                                        <p><?php esc_html_e( 'Queued orders waiting for sync', 'printful-integration-for-fluentcart' ); ?>: <strong><?php echo esc_html( $snapshot['queue_length'] ); ?></strong></p>
                                        <p><?php esc_html_e( 'Polling enabled', 'printful-integration-for-fluentcart' ); ?>: <?php echo $snapshot['polling_enabled'] ? esc_html__( 'Yes', 'printful-integration-for-fluentcart' ) : esc_html__( 'No', 'printful-integration-for-fluentcart' ); ?></p>
                                </div>
                                <div class="printful-diagnostics-card">
                                        <h3><?php esc_html_e( 'Errors', 'printful-integration-for-fluentcart' ); ?></h3>
                                        <?php if ( $snapshot['last_error'] ) : ?>
                                                <p class="status-warn"><?php esc_html_e( 'Last error', 'printful-integration-for-fluentcart' ); ?>: <?php echo esc_html( isset( $snapshot['last_error']['title'] ) ? $snapshot['last_error']['title'] : '' ); ?></p>
                                        <?php else : ?>
                                                <p class="status-ok"><?php esc_html_e( 'No recent errors recorded', 'printful-integration-for-fluentcart' ); ?></p>
                                        <?php endif; ?>
                                        <p><?php esc_html_e( 'Request body logging', 'printful-integration-for-fluentcart' ); ?>: <?php echo $snapshot['request_logging'] ? esc_html__( 'Enabled', 'printful-integration-for-fluentcart' ) : esc_html__( 'Disabled', 'printful-integration-for-fluentcart' ); ?></p>
                                </div>
                        </div>

                        <h2 style="margin-top:20px;"><?php esc_html_e( 'Status Checklist', 'printful-integration-for-fluentcart' ); ?></h2>
                        <ul class="printful-checklist">
                                <?php foreach ( $checklist_items as $item ) : ?>
                                        <li><?php echo $item['ok'] ? '✅ ' : '⚠️ '; ?><?php echo esc_html( $item['label'] ); ?></li>
                                <?php endforeach; ?>
                                <li><?php echo ! empty( $settings['enable_live_rates'] ) ? '✅ ' : 'ℹ️ '; ?><?php esc_html_e( 'Live rates enabled', 'printful-integration-for-fluentcart' ); ?></li>
                                <li><?php echo ! empty( Arr::get( $settings, 'origin_address.country' ) ) ? '✅ ' : 'ℹ️ '; ?><?php esc_html_e( 'Origin address set', 'printful-integration-for-fluentcart' ); ?></li>
                                <li><?php echo ! empty( $settings['enable_printful_tax'] ) ? '✅ ' : 'ℹ️ '; ?><?php esc_html_e( 'Tax helper enabled', 'printful-integration-for-fluentcart' ); ?></li>
                                <li><?php echo ! empty( $last_migration ) ? '✅ ' : 'ℹ️ '; ?><?php esc_html_e( 'Token migration checked', 'printful-integration-for-fluentcart' ); ?><?php if ( ! empty( $last_migration['source'] ) ) : ?> (<?php echo esc_html( $last_migration['source'] ); ?> @ <?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), Arr::get( $last_migration, 'time', time() ) ) ); ?>)<?php endif; ?></li>
                        </ul>

                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px;">
                                <?php wp_nonce_field( 'printful_fluentcart_clear_logs' ); ?>
                                <input type="hidden" name="action" value="printful_fluentcart_clear_logs" />
                                <?php submit_button( __( 'Clear Logs', 'printful-integration-for-fluentcart' ), 'secondary', 'submit', false ); ?>
                                <a class="button" href="<?php echo esc_url( rest_url( 'printful-fluentcart/v1/logs' ) ); ?>" target="_blank" rel="noreferrer"><?php esc_html_e( 'View via REST', 'printful-integration-for-fluentcart' ); ?></a>
                                <button type="submit" formaction="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" formmethod="post" name="action" value="printful_fluentcart_migrate_tokens" class="button"><?php esc_html_e( 'Run token migration', 'printful-integration-for-fluentcart' ); ?></button>
                                <label style="margin-left:8px;"><input type="checkbox" name="printful_migrate_dry_run" value="1" /> <?php esc_html_e( 'Dry run (do not save)', 'printful-integration-for-fluentcart' ); ?></label>
                                <?php wp_nonce_field( 'printful_fluentcart_migrate_tokens' ); ?>
                                <button type="submit" formaction="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" formmethod="post" name="action" value="printful_fluentcart_clear_queue" class="button button-secondary" style="margin-left:8px;"><?php esc_html_e( 'Clear queue', 'printful-integration-for-fluentcart' ); ?></button>
                        </form>

                        <h2 style="margin-top:20px;"><?php esc_html_e( 'Recent HTTP Interactions', 'printful-integration-for-fluentcart' ); ?></h2>
                        <p class="description"><?php esc_html_e( 'Request and response bodies are only captured when payload logging is enabled.', 'printful-integration-for-fluentcart' ); ?></p>
                        <table class="widefat striped">
                                <thead><tr><th><?php esc_html_e( 'Time', 'printful-integration-for-fluentcart' ); ?></th><th><?php esc_html_e( 'Direction', 'printful-integration-for-fluentcart' ); ?></th><th><?php esc_html_e( 'Method', 'printful-integration-for-fluentcart' ); ?></th><th><?php esc_html_e( 'URL', 'printful-integration-for-fluentcart' ); ?></th><th><?php esc_html_e( 'Status', 'printful-integration-for-fluentcart' ); ?></th></tr></thead>
                                <tbody>
                                        <?php if ( empty( $request_logs ) ) : ?>
                                                <tr><td colspan="5"><?php esc_html_e( 'No request log entries captured.', 'printful-integration-for-fluentcart' ); ?></td></tr>
                                        <?php else : ?>
                                                <?php foreach ( $request_logs as $entry ) : ?>
                                                        <tr>
                                                                <td><?php echo esc_html( isset( $entry['time'] ) ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $entry['time'] ) : '' ); ?></td>
                                                                <td><?php echo esc_html( isset( $entry['direction'] ) ? $entry['direction'] : '' ); ?></td>
                                                                <td><?php echo esc_html( isset( $entry['method'] ) ? $entry['method'] : '' ); ?></td>
                                                                <td><code><?php echo esc_html( isset( $entry['url'] ) ? $entry['url'] : '' ); ?></code></td>
                                                                <td><?php echo esc_html( isset( $entry['status'] ) ? $entry['status'] : '' ); ?></td>
                                                        </tr>
                                                <?php endforeach; ?>
                                        <?php endif; ?>
                                </tbody>
                        </table>

                        <h2 style="margin-top:20px;"><?php esc_html_e( 'Recent API / Webhook Logs', 'printful-integration-for-fluentcart' ); ?></h2>
                        <form method="get" style="margin-bottom:10px;">
                                <input type="hidden" name="page" value="printful-fluentcart-diagnostics" />
                                <label for="printful_log_level"><?php esc_html_e( 'Filter by level:', 'printful-integration-for-fluentcart' ); ?></label>
				<select id="printful_log_level" name="printful_log_level">
					<option value=""><?php esc_html_e( 'All', 'printful-integration-for-fluentcart' ); ?></option>
					<option value="info" <?php selected( $level, 'info' ); ?>>info</option>
					<option value="error" <?php selected( $level, 'error' ); ?>>error</option>
				</select>
				<label for="printful_log_search"><?php esc_html_e( 'Search:', 'printful-integration-for-fluentcart' ); ?></label>
				<input type="search" id="printful_log_search" name="printful_log_search" value="<?php echo esc_attr( $search ); ?>" />
				<?php submit_button( __( 'Filter', 'printful-integration-for-fluentcart' ), 'secondary', 'submit', false ); ?>
			</form>
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'Time', 'printful-integration-for-fluentcart' ); ?></th><th><?php esc_html_e( 'Title', 'printful-integration-for-fluentcart' ); ?></th><th><?php esc_html_e( 'Level', 'printful-integration-for-fluentcart' ); ?></th><th><?php esc_html_e( 'Context', 'printful-integration-for-fluentcart' ); ?></th></tr></thead>
				<tbody>
				<?php if ( empty( $logs ) ) : ?>
					<tr><td colspan="4"><?php esc_html_e( 'No log entries captured yet.', 'printful-integration-for-fluentcart' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( array_slice( $logs, 0, 25 ) as $entry ) : ?>
						<tr>
							<td><?php echo esc_html( isset( $entry['time'] ) ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $entry['time'] ) : '' ); ?></td>
							<td><?php echo esc_html( isset( $entry['title'] ) ? $entry['title'] : '' ); ?></td>
							<td><?php echo esc_html( isset( $entry['level'] ) ? $entry['level'] : 'info' ); ?></td>
							<td><code style="white-space:pre-wrap;display:block;"><?php echo esc_html( wp_json_encode( isset( $entry['context'] ) ? $entry['context'] : array() ) ); ?></code></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>

			<h2 style="margin-top:20px;"><?php esc_html_e( 'Recent Errors', 'printful-integration-for-fluentcart' ); ?></h2>
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'Time', 'printful-integration-for-fluentcart' ); ?></th><th><?php esc_html_e( 'Title', 'printful-integration-for-fluentcart' ); ?></th><th><?php esc_html_e( 'Context', 'printful-integration-for-fluentcart' ); ?></th></tr></thead>
				<tbody>
				<?php if ( empty( $error_logs ) ) : ?>
					<tr><td colspan="3"><?php esc_html_e( 'No error entries.', 'printful-integration-for-fluentcart' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $error_logs as $entry ) : ?>
						<tr>
							<td><?php echo esc_html( isset( $entry['time'] ) ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $entry['time'] ) : '' ); ?></td>
							<td><?php echo esc_html( isset( $entry['title'] ) ? $entry['title'] : '' ); ?></td>
							<td><code style="white-space:pre-wrap;display:block;"><?php echo esc_html( wp_json_encode( isset( $entry['context'] ) ? $entry['context'] : array() ) ); ?></code></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
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

		wp_localize_script(
			$this->plugin_name,
			'PrintfulFluentcart',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'printful_fluentcart_admin' ),
				'messages' => array(
					'testing' => __( 'Testing connection...', 'printful-integration-for-fluentcart' ),
					'syncing' => __( 'Syncing catalog...', 'printful-integration-for-fluentcart' ),
					'error'   => __( 'Something went wrong. Please try again.', 'printful-integration-for-fluentcart' ),
				),
			)
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

	       add_submenu_page(
		       $parent_slug,
		       __( 'Printful Diagnostics', 'printful-integration-for-fluentcart' ),
		       __( 'Printful Diagnostics', 'printful-integration-for-fluentcart' ),
		       'manage_options',
		       'printful-fluentcart-diagnostics',
		       array( $this, 'render_diagnostics_page' )
	       );
       }

	/**
	 * Dashboard widget for quick health.
	 *
	 * @return void
	 */
	public function register_dashboard_widget() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'printful_fluentcart_health',
			__( 'Printful Integration Health', 'printful-integration-for-fluentcart' ),
			array( $this, 'render_dashboard_widget' )
		);
	}

	/**
	 * Render dashboard widget content.
	 *
	 * @return void
	 */
	public function render_dashboard_widget() {
		$settings  = Printful_Integration_For_Fluentcart_Settings::all();
		$queue_len = class_exists( 'Printful_Integration_For_Fluentcart_Sync_Queue' ) ? count( Printful_Integration_For_Fluentcart_Sync_Queue::all() ) : 0;
		$signature = class_exists( 'Printful_Integration_For_Fluentcart_Logger' ) ? Printful_Integration_For_Fluentcart_Logger::signature_failures() : 0;
		$stats     = class_exists( 'Printful_Integration_For_Fluentcart_Logger' ) ? Printful_Integration_For_Fluentcart_Logger::stats() : array();
		?>
		<ul style="margin:0;padding-left:16px;">
			<li><?php esc_html_e( 'API key present:', 'printful-integration-for-fluentcart' ); ?> <strong><?php echo ! empty( $settings['api_key'] ) ? esc_html__( 'Yes', 'printful-integration-for-fluentcart' ) : esc_html__( 'No', 'printful-integration-for-fluentcart' ); ?></strong></li>
			<li><?php esc_html_e( 'Webhooks enabled:', 'printful-integration-for-fluentcart' ); ?> <strong><?php echo ! empty( $settings['enable_webhooks'] ) && ! empty( $settings['webhook_secret'] ) ? esc_html__( 'Yes', 'printful-integration-for-fluentcart' ) : esc_html__( 'No', 'printful-integration-for-fluentcart' ); ?></strong></li>
			<li><?php esc_html_e( 'Queue length:', 'printful-integration-for-fluentcart' ); ?> <strong><?php echo esc_html( $queue_len ); ?></strong></li>
			<li><?php esc_html_e( 'Signature failures:', 'printful-integration-for-fluentcart' ); ?> <strong><?php echo esc_html( $signature ); ?></strong></li>
			<li><?php esc_html_e( 'API errors:', 'printful-integration-for-fluentcart' ); ?> <strong><?php echo isset( $stats['errors'] ) ? esc_html( (int) $stats['errors'] ) : 0; ?></strong></li>
		</ul>
		<p style="margin-top:8px;"><a href="<?php echo esc_url( admin_url( 'admin.php?page=printful-fluentcart-diagnostics' ) ); ?>" class="button button-secondary"><?php esc_html_e( 'View diagnostics', 'printful-integration-for-fluentcart' ); ?></a></p>
		<?php
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
		$catalog_cache = class_exists( 'Printful_Integration_For_Fluentcart_Catalog' ) ? Printful_Integration_For_Fluentcart_Catalog::cached() : array();
		$mapping_lines = array();
		$catalog_variants = array();
		$carrier_cache = get_option( 'printful_fluentcart_carriers_cache', array() );

		foreach ( $variant_map as $variation_id => $printful_id ) {
			$mapping_lines[] = $variation_id . ':' . $printful_id;
		}

		$mapping_text   = implode( PHP_EOL, $mapping_lines );
		$webhook_url    = rest_url( Printful_Integration_For_Fluentcart_Webhook_Controller::REST_NAMESPACE . Printful_Integration_For_Fluentcart_Webhook_Controller::REST_ROUTE );
		$poll_interval  = isset( $settings['poll_interval_minutes'] ) ? (int) $settings['poll_interval_minutes'] : 10;
		$poll_interval  = max( 5, $poll_interval );
		$webhook_secret = isset( $settings['webhook_secret'] ) ? $settings['webhook_secret'] : '';
		$queue_length   = class_exists( 'Printful_Integration_For_Fluentcart_Sync_Queue' ) ? count( Printful_Integration_For_Fluentcart_Sync_Queue::all() ) : 0;
		$catalog_synced = isset( $catalog_cache['synced_at'] ) ? (int) $catalog_cache['synced_at'] : 0;
		$catalog_products = isset( $catalog_cache['products'] ) && is_array( $catalog_cache['products'] ) ? count( $catalog_cache['products'] ) : 0;
		$catalog_variants = isset( $catalog_cache['variants'] ) ? (int) $catalog_cache['variants'] : 0;

		// Flatten a small sample of variants for quick mapping assistance.
		if ( ! empty( $catalog_cache['products'] ) && is_array( $catalog_cache['products'] ) ) {
			$count = 0;
			foreach ( $catalog_cache['products'] as $product ) {
				$product_name = isset( $product['name'] ) ? $product['name'] : '';
				if ( empty( $product['variants'] ) || ! is_array( $product['variants'] ) ) {
					continue;
				}

				foreach ( $product['variants'] as $variant ) {
					$catalog_variants[] = array(
						'id'       => isset( $variant['id'] ) ? $variant['id'] : '',
						'name'     => isset( $variant['name'] ) ? $variant['name'] : '',
						'sku'      => isset( $variant['sku'] ) ? $variant['sku'] : '',
						'product'  => $product_name,
						'price'    => isset( $variant['retail_price'] ) ? $variant['retail_price'] : '',
					);
					$count++;
					if ( $count >= 200 ) {
						break 2;
					}
				}
			}
		}

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

				<h2><?php esc_html_e( 'Connection & Diagnostics', 'printful-integration-for-fluentcart' ); ?></h2>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'API connection', 'printful-integration-for-fluentcart' ); ?></th>
							<td>
								<button type="button" class="button" id="printful_fluentcart_test_connection">
									<?php esc_html_e( 'Test connection', 'printful-integration-for-fluentcart' ); ?>
								</button>
								<span class="printful-fluentcart-status" id="printful_fluentcart_test_status"></span>
								<p class="description"><?php esc_html_e( 'Verifies the API key and basic connectivity to Printful.', 'printful-integration-for-fluentcart' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Catalog cache', 'printful-integration-for-fluentcart' ); ?></th>
							<td>
								<button type="button" class="button button-secondary" id="printful_fluentcart_sync_catalog">
									<?php esc_html_e( 'Sync catalog', 'printful-integration-for-fluentcart' ); ?>
								</button>
								<span class="printful-fluentcart-status" id="printful_fluentcart_catalog_status">
									<?php
									if ( $catalog_synced ) {
										printf(
											/* translators: 1: number of products, 2: number of variants */
											esc_html__( '%1$s products / %2$s variants cached. Last sync: %3$s', 'printful-integration-for-fluentcart' ),
											(int) $catalog_products,
											(int) $catalog_variants,
											esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $catalog_synced ) )
										);
									}
									?>
								</span>
								<p class="description"><?php esc_html_e( 'Pull latest Printful products to help with variant mapping.', 'printful-integration-for-fluentcart' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Queue depth', 'printful-integration-for-fluentcart' ); ?></th>
							<td>
								<strong><?php echo esc_html( $queue_length ); ?></strong>
								<p class="description"><?php esc_html_e( 'Orders currently waiting for Printful status refresh.', 'printful-integration-for-fluentcart' ); ?></p>
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
                                                        <th scope="row"><?php esc_html_e( 'Store HTTP payloads', 'printful-integration-for-fluentcart' ); ?></th>
                                                        <td>
                                                                <label for="printful_fluentcart_request_logging">
                                                                        <input type="checkbox" id="printful_fluentcart_request_logging" name="printful_fluentcart_settings[enable_request_logging]" value="1" <?php checked( ! empty( $settings['enable_request_logging'] ) ); ?> />
                                                                        <?php esc_html_e( 'Persist recent Printful request/response bodies to disk for debugging.', 'printful-integration-for-fluentcart' ); ?>
                                                                </label>
                                                                <p class="description"><?php esc_html_e( 'Disable after debugging to avoid storing sensitive payloads.', 'printful-integration-for-fluentcart' ); ?></p>
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
						<tr>
							<th scope="row"><?php esc_html_e( 'Auto-refresh catalog daily', 'printful-integration-for-fluentcart' ); ?></th>
							<td>
								<label for="printful_fluentcart_auto_catalog">
									<input type="checkbox" id="printful_fluentcart_auto_catalog" name="printful_fluentcart_settings[auto_sync_catalog]" value="1" <?php checked( ! empty( $settings['auto_sync_catalog'] ) ); ?> />
									<?php esc_html_e( 'Fetch Printful products once per day to keep the catalog helper fresh.', 'printful-integration-for-fluentcart' ); ?>
								</label>
							</td>
						</tr>
					</tbody>
				</table>

				<h2><?php esc_html_e( 'Tax Helpers', 'printful-integration-for-fluentcart' ); ?></h2>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Enable Printful tax assistance', 'printful-integration-for-fluentcart' ); ?></th>
							<td>
								<label for="printful_fluentcart_enable_tax">
									<input type="checkbox" id="printful_fluentcart_enable_tax" name="printful_fluentcart_settings[enable_printful_tax]" value="1" <?php checked( ! empty( $settings['enable_printful_tax'] ) ); ?> />
									<?php esc_html_e( 'Use Printful tax calculations (informational; ensure rates align with your store tax rules).', 'printful-integration-for-fluentcart' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Prices include tax', 'printful-integration-for-fluentcart' ); ?></th>
							<td>
								<label for="printful_fluentcart_tax_inclusive">
									<input type="checkbox" id="printful_fluentcart_tax_inclusive" name="printful_fluentcart_settings[tax_inclusive_prices]" value="1" <?php checked( ! empty( $settings['tax_inclusive_prices'] ) ); ?> />
									<?php esc_html_e( 'Indicate that prices passed to Printful are tax-inclusive.', 'printful-integration-for-fluentcart' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Embed Printful designer', 'printful-integration-for-fluentcart' ); ?></th>
							<td>
								<label for="printful_fluentcart_designer_embed">
									<input type="checkbox" id="printful_fluentcart_designer_embed" name="printful_fluentcart_settings[enable_designer_embed]" value="1" <?php checked( ! empty( $settings['enable_designer_embed'] ) ); ?> />
									<?php esc_html_e( 'Open Printful designer in an embedded modal (falls back to link).', 'printful-integration-for-fluentcart' ); ?>
								</label>
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
						<tr>
							<th scope="row">
								<label for="printful_fluentcart_allowed_carriers"><?php esc_html_e( 'Allowed carriers', 'printful-integration-for-fluentcart' ); ?></label>
							</th>
							<td>
								<?php if ( ! empty( $carrier_cache['carriers'] ) ) : ?>
									<?php foreach ( $carrier_cache['carriers'] as $carrier_key => $carrier_label ) : ?>
										<label style="display:block;"><input type="checkbox" name="printful_fluentcart_settings[allowed_carriers][]" value="<?php echo esc_attr( $carrier_key ); ?>" <?php checked( in_array( $carrier_key, isset( $settings['allowed_carriers'] ) ? (array) $settings['allowed_carriers'] : array(), true ) ); ?> /> <?php echo esc_html( $carrier_label ); ?></label>
									<?php endforeach; ?>
									<p class="description"><?php esc_html_e( 'Select carriers to allow. Leave all unchecked to allow all.', 'printful-integration-for-fluentcart' ); ?></p>
								<?php else : ?>
									<input type="text" class="regular-text" id="printful_fluentcart_allowed_carriers" name="printful_fluentcart_settings[allowed_carriers]" value="<?php echo esc_attr( isset( $settings['allowed_carriers'] ) && is_array( $settings['allowed_carriers'] ) ? implode( ',', $settings['allowed_carriers'] ) : '' ); ?>" />
									<p class="description"><?php esc_html_e( 'Comma-separated carrier names. Use "Fetch carriers" below to load from API.', 'printful-integration-for-fluentcart' ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="printful_fluentcart_allowed_services"><?php esc_html_e( 'Allowed services', 'printful-integration-for-fluentcart' ); ?></label>
							</th>
							<td>
								<?php if ( ! empty( $carrier_cache['services'] ) ) : ?>
									<?php foreach ( $carrier_cache['services'] as $service_key => $service_label ) : ?>
										<label style="display:block;"><input type="checkbox" name="printful_fluentcart_settings[allowed_services][]" value="<?php echo esc_attr( $service_key ); ?>" <?php checked( in_array( $service_key, isset( $settings['allowed_services'] ) ? (array) $settings['allowed_services'] : array(), true ) ); ?> /> <?php echo esc_html( $service_label ); ?></label>
									<?php endforeach; ?>
									<p class="description"><?php esc_html_e( 'Select services to allow. Leave all unchecked to allow all.', 'printful-integration-for-fluentcart' ); ?></p>
								<?php else : ?>
									<input type="text" class="regular-text" id="printful_fluentcart_allowed_services" name="printful_fluentcart_settings[allowed_services]" value="<?php echo esc_attr( isset( $settings['allowed_services'] ) && is_array( $settings['allowed_services'] ) ? implode( ',', $settings['allowed_services'] ) : '' ); ?>" />
									<p class="description"><?php esc_html_e( 'Filter by Printful service IDs (e.g. STANDARD, EXPRESS). Use "Fetch carriers" to load options.', 'printful-integration-for-fluentcart' ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="printful_fluentcart_fallback_label"><?php esc_html_e( 'Fallback shipping label', 'printful-integration-for-fluentcart' ); ?></label>
							</th>
							<td>
								<input type="text" class="regular-text" id="printful_fluentcart_fallback_label" name="printful_fluentcart_settings[fallback_rate][label]" value="<?php echo esc_attr( isset( $settings['fallback_rate']['label'] ) ? $settings['fallback_rate']['label'] : '' ); ?>" />
								<p class="description"><?php esc_html_e( 'Shown when Printful rates are unavailable and a fallback is used.', 'printful-integration-for-fluentcart' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="printful_fluentcart_fallback_amount"><?php esc_html_e( 'Fallback shipping amount', 'printful-integration-for-fluentcart' ); ?></label>
							</th>
							<td>
								<input type="number" step="0.01" min="0" id="printful_fluentcart_fallback_amount" name="printful_fluentcart_settings[fallback_rate][amount]" value="<?php echo esc_attr( isset( $settings['fallback_rate']['amount'] ) ? $settings['fallback_rate']['amount'] : '' ); ?>" />
								<p class="description"><?php esc_html_e( 'If set, this rate will be offered when Printful rates fail.', 'printful-integration-for-fluentcart' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>

				<h2><?php esc_html_e( 'Fulfilment Origin (optional)', 'printful-integration-for-fluentcart' ); ?></h2>
				<table class="form-table" role="presentation">
					<tbody>
						<tr><th scope="row"><label for="printful_origin_name"><?php esc_html_e( 'Contact name', 'printful-integration-for-fluentcart' ); ?></label></th><td><input type="text" class="regular-text" id="printful_origin_name" name="printful_fluentcart_settings[origin_address][name]" value="<?php echo esc_attr( Arr::get( $settings, 'origin_address.name', '' ) ); ?>" /></td></tr>
						<tr><th scope="row"><label for="printful_origin_company"><?php esc_html_e( 'Company', 'printful-integration-for-fluentcart' ); ?></label></th><td><input type="text" class="regular-text" id="printful_origin_company" name="printful_fluentcart_settings[origin_address][company]" value="<?php echo esc_attr( Arr::get( $settings, 'origin_address.company', '' ) ); ?>" /></td></tr>
						<tr><th scope="row"><label for="printful_origin_address1"><?php esc_html_e( 'Address line 1', 'printful-integration-for-fluentcart' ); ?></label></th><td><input type="text" class="regular-text" id="printful_origin_address1" name="printful_fluentcart_settings[origin_address][address_1]" value="<?php echo esc_attr( Arr::get( $settings, 'origin_address.address_1', '' ) ); ?>" /></td></tr>
						<tr><th scope="row"><label for="printful_origin_address2"><?php esc_html_e( 'Address line 2', 'printful-integration-for-fluentcart' ); ?></label></th><td><input type="text" class="regular-text" id="printful_origin_address2" name="printful_fluentcart_settings[origin_address][address_2]" value="<?php echo esc_attr( Arr::get( $settings, 'origin_address.address_2', '' ) ); ?>" /></td></tr>
						<tr><th scope="row"><label for="printful_origin_city"><?php esc_html_e( 'City', 'printful-integration-for-fluentcart' ); ?></label></th><td><input type="text" class="regular-text" id="printful_origin_city" name="printful_fluentcart_settings[origin_address][city]" value="<?php echo esc_attr( Arr::get( $settings, 'origin_address.city', '' ) ); ?>" /></td></tr>
						<tr><th scope="row"><label for="printful_origin_state"><?php esc_html_e( 'State/Region', 'printful-integration-for-fluentcart' ); ?></label></th><td><input type="text" class="regular-text" id="printful_origin_state" name="printful_fluentcart_settings[origin_address][state]" value="<?php echo esc_attr( Arr::get( $settings, 'origin_address.state', '' ) ); ?>" /></td></tr>
						<tr><th scope="row"><label for="printful_origin_postcode"><?php esc_html_e( 'Postcode', 'printful-integration-for-fluentcart' ); ?></label></th><td><input type="text" class="regular-text" id="printful_origin_postcode" name="printful_fluentcart_settings[origin_address][postcode]" value="<?php echo esc_attr( Arr::get( $settings, 'origin_address.postcode', '' ) ); ?>" /></td></tr>
						<tr><th scope="row"><label for="printful_origin_country"><?php esc_html_e( 'Country code', 'printful-integration-for-fluentcart' ); ?></label></th><td><input type="text" class="regular-text" id="printful_origin_country" name="printful_fluentcart_settings[origin_address][country]" value="<?php echo esc_attr( Arr::get( $settings, 'origin_address.country', '' ) ); ?>" /></td></tr>
						<tr><th scope="row"><label for="printful_origin_phone"><?php esc_html_e( 'Phone', 'printful-integration-for-fluentcart' ); ?></label></th><td><input type="text" class="regular-text" id="printful_origin_phone" name="printful_fluentcart_settings[origin_address][phone]" value="<?php echo esc_attr( Arr::get( $settings, 'origin_address.phone', '' ) ); ?>" /></td></tr>
					</tbody>
				</table>

				<h2><?php esc_html_e( 'Regional Origins', 'printful-integration-for-fluentcart' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Optional: provide alternate origins for specific destination countries (comma-separated ISO codes). Add as many profiles as needed.', 'printful-integration-for-fluentcart' ); ?></p>
				<div id="printful_origin_profiles">
					<?php
					$overrides = isset( $settings['origin_overrides'] ) && is_array( $settings['origin_overrides'] ) ? $settings['origin_overrides'] : array();
					if ( empty( $overrides ) ) {
						$overrides[] = array();
					}
					foreach ( $overrides as $idx => $entry ) :
					?>
					<table class="form-table printful-origin-block" role="presentation" style="border:1px solid #e2e8f0;margin-bottom:10px;">
						<tbody>
							<tr><th scope="row"><label><?php esc_html_e( 'Destination countries', 'printful-integration-for-fluentcart' ); ?></label></th><td><input type="text" class="regular-text" name="printful_fluentcart_settings[origin_overrides][<?php echo (int) $idx; ?>][countries]" value="<?php echo esc_attr( isset( $entry['countries'] ) ? implode( ',', (array) $entry['countries'] ) : '' ); ?>" placeholder="US,CA,GB" /></td></tr>
							<tr><th scope="row"><label><?php esc_html_e( 'Contact name', 'printful-integration-for-fluentcart' ); ?></label></th><td><input type="text" class="regular-text" name="printful_fluentcart_settings[origin_overrides][<?php echo (int) $idx; ?>][name]" value="<?php echo esc_attr( Arr::get( $entry, 'name', '' ) ); ?>" /></td></tr>
							<tr><th scope="row"><label><?php esc_html_e( 'Company', 'printful-integration-for-fluentcart' ); ?></label></th><td><input type="text" class="regular-text" name="printful_fluentcart_settings[origin_overrides][<?php echo (int) $idx; ?>][company]" value="<?php echo esc_attr( Arr::get( $entry, 'company', '' ) ); ?>" /></td></tr>
							<tr><th scope="row"><label><?php esc_html_e( 'Address line 1', 'printful-integration-for-fluentcart' ); ?></label></th><td><input type="text" class="regular-text" name="printful_fluentcart_settings[origin_overrides][<?php echo (int) $idx; ?>][address_1]" value="<?php echo esc_attr( Arr::get( $entry, 'address_1', '' ) ); ?>" /></td></tr>
							<tr><th scope="row"><label><?php esc_html_e( 'Address line 2', 'printful-integration-for-fluentcart' ); ?></label></th><td><input type="text" class="regular-text" name="printful_fluentcart_settings[origin_overrides][<?php echo (int) $idx; ?>][address_2]" value="<?php echo esc_attr( Arr::get( $entry, 'address_2', '' ) ); ?>" /></td></tr>
							<tr><th scope="row"><label><?php esc_html_e( 'City', 'printful-integration-for-fluentcart' ); ?></label></th><td><input type="text" class="regular-text" name="printful_fluentcart_settings[origin_overrides][<?php echo (int) $idx; ?>][city]" value="<?php echo esc_attr( Arr::get( $entry, 'city', '' ) ); ?>" /></td></tr>
							<tr><th scope="row"><label><?php esc_html_e( 'State/Region', 'printful-integration-for-fluentcart' ); ?></label></th><td><input type="text" class="regular-text" name="printful_fluentcart_settings[origin_overrides][<?php echo (int) $idx; ?>][state]" value="<?php echo esc_attr( Arr::get( $entry, 'state', '' ) ); ?>" /></td></tr>
							<tr><th scope="row"><label><?php esc_html_e( 'Postcode', 'printful-integration-for-fluentcart' ); ?></label></th><td><input type="text" class="regular-text" name="printful_fluentcart_settings[origin_overrides][<?php echo (int) $idx; ?>][postcode]" value="<?php echo esc_attr( Arr::get( $entry, 'postcode', '' ) ); ?>" /></td></tr>
							<tr><th scope="row"><label><?php esc_html_e( 'Country code', 'printful-integration-for-fluentcart' ); ?></label></th><td><input type="text" class="regular-text" name="printful_fluentcart_settings[origin_overrides][<?php echo (int) $idx; ?>][country]" value="<?php echo esc_attr( Arr::get( $entry, 'country', '' ) ); ?>" /></td></tr>
							<tr><th scope="row"><label><?php esc_html_e( 'Phone', 'printful-integration-for-fluentcart' ); ?></label></th><td><input type="text" class="regular-text" name="printful_fluentcart_settings[origin_overrides][<?php echo (int) $idx; ?>][phone]" value="<?php echo esc_attr( Arr::get( $entry, 'phone', '' ) ); ?>" /></td></tr>
						</tbody>
					</table>
					<?php endforeach; ?>
				</div>
				<p><button type="button" class="button" id="printful_add_origin_profile"><?php esc_html_e( 'Add origin profile', 'printful-integration-for-fluentcart' ); ?></button></p>

				<p>
					<button type="button" class="button" id="printful_fluentcart_fetch_carriers"><?php esc_html_e( 'Fetch carriers/services from Printful', 'printful-integration-for-fluentcart' ); ?></button>
				</p>

                                <h2><?php esc_html_e( 'Variant Mapping', 'printful-integration-for-fluentcart' ); ?></h2>
                                <p><?php esc_html_e( 'Map each FluentCart variation ID to a Printful variant ID. Use one mapping per line: variation_id:printful_variant_id', 'printful-integration-for-fluentcart' ); ?></p>
                                <textarea class="large-text code" rows="8" name="printful_fluentcart_variant_mapping" id="printful_fluentcart_variant_mapping"><?php echo esc_textarea( $mapping_text ); ?></textarea>

				<?php if ( ! empty( $catalog_variants ) ) : ?>
				<h3><?php esc_html_e( 'Catalog helper', 'printful-integration-for-fluentcart' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Click a variant to append its mapping. Showing first 200 cached variants.', 'printful-integration-for-fluentcart' ); ?></p>
				<input type="search" id="printful_fluentcart_mapping_search" placeholder="<?php esc_attr_e( 'Search Printful variants...', 'printful-integration-for-fluentcart' ); ?>" class="regular-text" />
				<table class="widefat striped printful-fluentcart-variant-table" id="printful_fluentcart_variant_table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Printful Variant', 'printful-integration-for-fluentcart' ); ?></th>
							<th><?php esc_html_e( 'SKU', 'printful-integration-for-fluentcart' ); ?></th>
							<th><?php esc_html_e( 'Product', 'printful-integration-for-fluentcart' ); ?></th>
							<th><?php esc_html_e( 'Price', 'printful-integration-for-fluentcart' ); ?></th>
							<th><?php esc_html_e( 'Insert', 'printful-integration-for-fluentcart' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $catalog_variants as $variant ) : ?>
							<tr data-variant-id="<?php echo esc_attr( $variant['id'] ); ?>" data-name="<?php echo esc_attr( $variant['name'] ); ?>" data-sku="<?php echo esc_attr( $variant['sku'] ); ?>" data-product="<?php echo esc_attr( $variant['product'] ); ?>">
								<td><strong><?php echo esc_html( $variant['name'] ); ?></strong> (#<?php echo esc_html( $variant['id'] ); ?>)</td>
								<td><?php echo esc_html( $variant['sku'] ); ?></td>
								<td><?php echo esc_html( $variant['product'] ); ?></td>
								<td><?php echo esc_html( $variant['price'] ); ?></td>
								<td><button type="button" class="button button-small printful-fluentcart-add-mapping"><?php esc_html_e( 'Add', 'printful-integration-for-fluentcart' ); ?></button></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php endif; ?>

				<?php submit_button( __( 'Save Settings', 'printful-integration-for-fluentcart' ) ); ?>
			</form>

			<hr />
			<h2><?php esc_html_e( 'Product import (beta)', 'printful-integration-for-fluentcart' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Create a basic FluentCart product shell from a Printful product ID. Variations and mappings are stored as meta for manual refinement.', 'printful-integration-for-fluentcart' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'printful_fluentcart_import_product' ); ?>
				<input type="hidden" name="action" value="printful_fluentcart_import_product" />
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="printful_import_product_id"><?php esc_html_e( 'Printful product ID', 'printful-integration-for-fluentcart' ); ?></label></th>
							<td><input type="number" min="1" required class="regular-text" id="printful_import_product_id" name="printful_import_product_id" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="printful_import_markup"><?php esc_html_e( 'Price markup (%)', 'printful-integration-for-fluentcart' ); ?></label></th>
							<td><input type="number" step="0.1" id="printful_import_markup" name="printful_import_markup" value="<?php echo esc_attr( isset( $settings['product_markup_percent'] ) ? $settings['product_markup_percent'] : 0 ); ?>" /></td>
						</tr>
					</tbody>
				</table>
				<?php submit_button( __( 'Import Product', 'printful-integration-for-fluentcart' ) ); ?>
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
			'auto_sync_catalog'       => ! empty( $input['auto_sync_catalog'] ),
			'allowed_carriers'        => isset( $input['allowed_carriers'] ) ? array_filter( array_map( 'trim', explode( ',', sanitize_text_field( $input['allowed_carriers'] ) ) ) ) : array(),
			'allowed_services'        => isset( $input['allowed_services'] ) ? array_filter( array_map( 'trim', explode( ',', sanitize_text_field( $input['allowed_services'] ) ) ) ) : array(),
			'fallback_rate'           => array(
				'label'  => isset( $input['fallback_rate']['label'] ) ? sanitize_text_field( $input['fallback_rate']['label'] ) : '',
				'amount' => isset( $input['fallback_rate']['amount'] ) ? floatval( $input['fallback_rate']['amount'] ) : 0,
			),
			'origin_address'          => array(
				'name'      => sanitize_text_field( Arr::get( $input, 'origin_address.name', '' ) ),
				'company'   => sanitize_text_field( Arr::get( $input, 'origin_address.company', '' ) ),
				'address_1' => sanitize_text_field( Arr::get( $input, 'origin_address.address_1', '' ) ),
				'address_2' => sanitize_text_field( Arr::get( $input, 'origin_address.address_2', '' ) ),
				'city'      => sanitize_text_field( Arr::get( $input, 'origin_address.city', '' ) ),
				'state'     => sanitize_text_field( Arr::get( $input, 'origin_address.state', '' ) ),
				'postcode'  => sanitize_text_field( Arr::get( $input, 'origin_address.postcode', '' ) ),
				'country'   => sanitize_text_field( Arr::get( $input, 'origin_address.country', '' ) ),
				'phone'     => sanitize_text_field( Arr::get( $input, 'origin_address.phone', '' ) ),
			),
			'origin_overrides'        => array(
				array_values( array_filter( array_map( function( $entry ) {
					$countries_raw = isset( $entry['countries'] ) ? $entry['countries'] : '';
					$countries     = array_filter( array_map( 'trim', explode( ',', $countries_raw ) ) );

					if ( empty( $countries ) && empty( array_filter( $entry ) ) ) {
						return null;
					}

					return array(
						'countries' => $countries,
						'name'      => sanitize_text_field( Arr::get( $entry, 'name', '' ) ),
						'company'   => sanitize_text_field( Arr::get( $entry, 'company', '' ) ),
						'address_1' => sanitize_text_field( Arr::get( $entry, 'address_1', '' ) ),
						'address_2' => sanitize_text_field( Arr::get( $entry, 'address_2', '' ) ),
						'city'      => sanitize_text_field( Arr::get( $entry, 'city', '' ) ),
						'state'     => sanitize_text_field( Arr::get( $entry, 'state', '' ) ),
						'postcode'  => sanitize_text_field( Arr::get( $entry, 'postcode', '' ) ),
						'country'   => sanitize_text_field( Arr::get( $entry, 'country', '' ) ),
						'phone'     => sanitize_text_field( Arr::get( $entry, 'phone', '' ) ),
					);
				}, isset( $input['origin_overrides'] ) ? (array) $input['origin_overrides'] : array() ) ) ),
			),
                        'enable_printful_tax'     => ! empty( $input['enable_printful_tax'] ),
                        'tax_inclusive_prices'    => ! empty( $input['tax_inclusive_prices'] ),
                        'enable_designer_embed'   => ! empty( $input['enable_designer_embed'] ),
                        'enable_request_logging'  => ! empty( $input['enable_request_logging'] ),
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

	/**
	 * AJAX: Test connectivity to Printful using saved API key.
	 *
	 * @return void
	 */
	public function handle_test_connection() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'printful-integration-for-fluentcart' ) ), 403 );
		}

		check_ajax_referer( 'printful_fluentcart_admin', 'nonce' );

		$settings = Printful_Integration_For_Fluentcart_Settings::all();
		$api_key  = isset( $settings['api_key'] ) ? trim( $settings['api_key'] ) : '';

		if ( '' === $api_key ) {
			wp_send_json_error( array( 'message' => __( 'Please provide an API key first.', 'printful-integration-for-fluentcart' ) ) );
		}

		$api      = new Printful_Integration_For_Fluentcart_Api( $api_key, isset( $settings['api_base'] ) ? $settings['api_base'] : 'https://api.printful.com', ! empty( $settings['log_api_calls'] ) );
		$response = $api->get( 'store' );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error(
				array(
					'message' => $response->get_error_message(),
				)
			);
		}

		$result = isset( $response['result'] ) ? $response['result'] : $response;
		$label  = isset( $result['name'] ) ? $result['name'] : __( 'Connected', 'printful-integration-for-fluentcart' );

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %s: Printful store name */
					__( 'Connected to Printful store: %s', 'printful-integration-for-fluentcart' ),
					$label
				),
			)
		);
	}

	/**
	 * AJAX: Pull Printful catalog and cache it for mapping.
	 *
	 * @return void
	 */
	public function handle_sync_catalog() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'printful-integration-for-fluentcart' ) ), 403 );
		}

		check_ajax_referer( 'printful_fluentcart_admin', 'nonce' );

		$settings = Printful_Integration_For_Fluentcart_Settings::all();
		$api_key  = isset( $settings['api_key'] ) ? trim( $settings['api_key'] ) : '';

		if ( '' === $api_key ) {
			wp_send_json_error( array( 'message' => __( 'Please provide an API key first.', 'printful-integration-for-fluentcart' ) ) );
		}

		$api      = new Printful_Integration_For_Fluentcart_Api( $api_key, isset( $settings['api_base'] ) ? $settings['api_base'] : 'https://api.printful.com', ! empty( $settings['log_api_calls'] ) );
		$catalog  = new Printful_Integration_For_Fluentcart_Catalog( $api, $settings );
		$result   = $catalog->sync();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
				)
			);
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: 1: product count, 2: variant count */
					__( 'Catalog synced (%1$s products, %2$s variants).', 'printful-integration-for-fluentcart' ),
					isset( $result['products'] ) ? count( $result['products'] ) : 0,
					isset( $result['variants'] ) ? (int) $result['variants'] : 0
				),
			)
		);
	}

	/**
	 * Handle product import request.
	 *
	 * @return void
	 */
	public function handle_import_product() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'printful-integration-for-fluentcart' ) );
		}

		check_admin_referer( 'printful_fluentcart_import_product' );

		$product_id = isset( $_POST['printful_import_product_id'] ) ? intval( $_POST['printful_import_product_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$markup     = isset( $_POST['printful_import_markup'] ) ? floatval( $_POST['printful_import_markup'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$settings = Printful_Integration_For_Fluentcart_Settings::all();
		$api_key  = isset( $settings['api_key'] ) ? trim( $settings['api_key'] ) : '';

		if ( ! $product_id || '' === $api_key ) {
			wp_die( esc_html__( 'Missing product ID or API key.', 'printful-integration-for-fluentcart' ) );
		}

		$api     = new Printful_Integration_For_Fluentcart_Api( $api_key, isset( $settings['api_base'] ) ? $settings['api_base'] : 'https://api.printful.com', ! empty( $settings['log_api_calls'] ) );
		$catalog = new Printful_Integration_For_Fluentcart_Catalog( $api, $settings );
		$catalog->sync(); // refresh cache best effort.

		$importer = new Printful_Integration_For_Fluentcart_Product_Importer( $api );
		$result   = $importer->import( $product_id, $markup );

		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}

		$post_id = isset( $result['post_id'] ) ? (int) $result['post_id'] : 0;

		if ( $post_id ) {
			add_settings_error(
				'printful_fluentcart',
				'printful_fluentcart_imported',
				sprintf(
					/* translators: 1: post ID, 2: variation count */
					esc_html__( 'Imported product as draft (ID %1$d) with %2$d variations.', 'printful-integration-for-fluentcart' ),
					$post_id,
					isset( $result['variations'] ) ? (int) $result['variations'] : 0
				),
				'updated'
			);
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'             => 'printful-fluentcart',
					'settings-updated' => 'true',
				),
				admin_url( class_exists( '\FluentCart\App\App' ) ? 'admin.php' : 'options-general.php' )
			)
		);
		exit;
	}

	/**
	 * Fetch carriers/services from Printful.
	 *
	 * @return void
	 */
	public function handle_fetch_carriers() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'printful-integration-for-fluentcart' ) ), 403 );
		}

		check_ajax_referer( 'printful_fluentcart_admin', 'nonce' );

		$settings = Printful_Integration_For_Fluentcart_Settings::all();
		$api_key  = isset( $settings['api_key'] ) ? trim( $settings['api_key'] ) : '';

		if ( '' === $api_key ) {
			wp_send_json_error( array( 'message' => __( 'Please provide an API key first.', 'printful-integration-for-fluentcart' ) ) );
		}

		$api      = new Printful_Integration_For_Fluentcart_Api( $api_key, isset( $settings['api_base'] ) ? $settings['api_base'] : 'https://api.printful.com', ! empty( $settings['log_api_calls'] ) );
		$response = $api->get( 'shipping/carriers' );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		$result   = isset( $response['result'] ) ? $response['result'] : $response;
		$carriers = array();
		$services = array();

		if ( is_array( $result ) ) {
			foreach ( $result as $carrier ) {
				$key = isset( $carrier['name'] ) ? sanitize_title( $carrier['name'] ) : '';
				if ( $key ) {
					$carriers[ $key ] = isset( $carrier['name'] ) ? $carrier['name'] : $key;
				}

				if ( isset( $carrier['services'] ) && is_array( $carrier['services'] ) ) {
					foreach ( $carrier['services'] as $service ) {
						$service_key = isset( $service['id'] ) ? $service['id'] : ( isset( $service['name'] ) ? sanitize_title( $service['name'] ) : '' );
						if ( $service_key ) {
							$label             = isset( $service['name'] ) ? $service['name'] : $service_key;
							$services[ $service_key ] = $label . ( isset( $carrier['name'] ) ? ' (' . $carrier['name'] . ')' : '' );
						}
					}
				}
			}
		}

		update_option(
			'printful_fluentcart_carriers_cache',
			array(
				'carriers' => $carriers,
				'services' => $services,
				'fetched'  => time(),
			)
		);

		wp_send_json_success(
			array(
				'carriers' => $carriers,
				'services' => $services,
			)
		);
	}

	/**
	 * Clear request logs.
	 *
	 * @return void
	 */
	public function handle_clear_logs() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'printful-integration-for-fluentcart' ) );
		}

		if ( class_exists( 'Printful_Integration_For_Fluentcart_Logger' ) ) {
			Printful_Integration_For_Fluentcart_Logger::clear();
		}

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=printful-fluentcart-diagnostics' ) );
		exit;
	}

	/**
	 * Clear sync queue.
	 *
	 * @return void
	 */
	public function handle_clear_queue() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'printful-integration-for-fluentcart' ) );
		}

		if ( class_exists( 'Printful_Integration_For_Fluentcart_Sync_Queue' ) ) {
			Printful_Integration_For_Fluentcart_Sync_Queue::reset();
		}

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=printful-fluentcart-diagnostics' ) );
		exit;
	}
}
