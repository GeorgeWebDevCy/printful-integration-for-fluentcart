<?php

/**
 * Placeholder for future token migration helpers.
 *
 * @package Printful_Integration_For_Fluentcart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Printful_Integration_For_Fluentcart_Token_Migration {

	/**
	 * Register hooks (no-op placeholder).
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'admin_post_printful_fluentcart_migrate_tokens', array( __CLASS__, 'run_migration' ) );
	}

	/**
	 * Attempt to migrate legacy Printful tokens (placeholder logic).
	 *
	 * @return void
	 */
	public static function run_migration() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'printful-integration-for-fluentcart' ) );
		}

		check_admin_referer( 'printful_fluentcart_migrate_tokens' );

		// Example legacy option keys.
		$legacy_keys = array(
			'printful_api_key',
			'printful_shipping_api_key',
		);

		$migrated = false;
		$message  = '';

		foreach ( $legacy_keys as $key ) {
			$legacy = get_option( $key );
			if ( $legacy ) {
				$settings          = Printful_Integration_For_Fluentcart_Settings::all();
				$settings['api_key'] = $legacy;
				Printful_Integration_For_Fluentcart_Settings::update( $settings );
				$migrated = true;
				$message  = sprintf( /* translators: %s legacy key */ esc_html__( 'Migrated legacy token from %s.', 'printful-integration-for-fluentcart' ), $key );
				break;
			}
		}

		if ( ! $migrated ) {
			$message = esc_html__( 'No legacy tokens found to migrate.', 'printful-integration-for-fluentcart' );
		}

		add_settings_error( 'printful_fluentcart', 'printful_fluentcart_migration', $message, $migrated ? 'updated' : 'info' );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'             => 'printful-fluentcart-diagnostics',
					'settings-updated' => 'true',
				),
				admin_url( class_exists( '\FluentCart\App\App' ) ? 'admin.php' : 'options-general.php' )
			)
		);
		exit;
	}
}
