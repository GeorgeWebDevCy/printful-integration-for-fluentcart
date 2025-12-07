<?php

/**
 * REST controller for receiving Printful webhook callbacks.
 *
 * @package Printful_Integration_For_Fluentcart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Printful_Integration_For_Fluentcart_Webhook_Controller {

	/**
	 * REST namespace.
	 */
	const REST_NAMESPACE = 'printful-fluentcart/v1';

	/**
	 * REST route.
	 */
	const REST_ROUTE = '/webhook';

	/**
	 * Shared sync manager.
	 *
	 * @var Printful_Integration_For_Fluentcart_Sync_Manager
	 */
	protected $sync_manager;

	/**
	 * Plugin settings.
	 *
	 * @var array
	 */
	protected $settings = array();

	/**
	 * Constructor.
	 *
	 * @param Printful_Integration_For_Fluentcart_Sync_Manager $sync_manager Sync manager instance.
	 * @param array                                            $settings Plugin settings.
	 */
	public function __construct( Printful_Integration_For_Fluentcart_Sync_Manager $sync_manager, array $settings ) {
		$this->sync_manager = $sync_manager;
		$this->settings     = $settings;
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register webhook endpoint.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_webhook' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Handle incoming webhook request.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_webhook( \WP_REST_Request $request ) {
		if ( empty( $this->settings['enable_webhooks'] ) ) {
			return new \WP_Error(
				'printful_webhooks_disabled',
				__( 'Printful webhooks are disabled.', 'printful-integration-for-fluentcart' ),
				array( 'status' => 403 )
			);
		}

		$secret = isset( $this->settings['webhook_secret'] ) ? $this->settings['webhook_secret'] : '';
		if ( empty( $secret ) ) {
			return new \WP_Error(
				'printful_webhook_secret_missing',
				__( 'Webhook secret is not configured.', 'printful-integration-for-fluentcart' ),
				array( 'status' => 403 )
			);
		}

		$body      = $request->get_body();
		$signature = $request->get_header( 'x-pf-signature' );

		if ( ! $signature ) {
			// Fallback for potential capitalisation differences.
			$signature = $request->get_header( 'X-PF-Signature' );
		}

		if ( ! $this->validate_signature( $body, $secret, $signature ) ) {
			if ( function_exists( 'fluent_cart_error_log' ) ) {
				fluent_cart_error_log(
					'Printful webhook signature failed',
					'Invalid signature received for Printful webhook.',
					array( 'module_type' => __CLASS__ )
				);
			}
			return new \WP_Error(
				'printful_invalid_signature',
				__( 'Invalid Printful webhook signature.', 'printful-integration-for-fluentcart' ),
				array( 'status' => 403 )
			);
		}

		$payload = json_decode( $body, true );
		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $payload ) ) {
			return new \WP_Error(
				'printful_invalid_payload',
				__( 'Could not decode webhook payload.', 'printful-integration-for-fluentcart' ),
				array( 'status' => 400 )
			);
		}

		$processed = $this->sync_manager->process_webhook_payload( $payload );

		if ( ! $processed ) {
			return new \WP_Error(
				'printful_unmatched_order',
				__( 'No matching FluentCart order found for webhook event.', 'printful-integration-for-fluentcart' ),
				array( 'status' => 202 )
			);
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
			),
			200
		);
	}

	/**
	 * Verify webhook signature.
	 *
	 * @param string $payload   Raw body.
	 * @param string $secret    Shared secret.
	 * @param string $signature Header signature.
	 *
	 * @return bool
	 */
	protected function validate_signature( $payload, $secret, $signature ) {
		if ( empty( $payload ) || empty( $secret ) || empty( $signature ) ) {
			return false;
		}

		$computed = hash_hmac( 'sha256', $payload, $secret );

		return hash_equals( $computed, $signature );
	}
}
