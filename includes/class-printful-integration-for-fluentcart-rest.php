<?php

/**
 * REST endpoints for health/config/log viewing.
 *
 * @package Printful_Integration_For_Fluentcart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Printful_Integration_For_Fluentcart_Rest {

	const NAMESPACE = 'printful-fluentcart/v1';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * Routes definition.
	 *
	 * @return void
	 */
	public static function routes() {
		register_rest_route(
			self::NAMESPACE,
			'/health',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'health' ),
				'permission_callback' => array( __CLASS__, 'permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/config',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'config' ),
				'permission_callback' => array( __CLASS__, 'permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/tax-status',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'tax_status' ),
				'permission_callback' => array( __CLASS__, 'permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/logs',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'logs' ),
				'permission_callback' => array( __CLASS__, 'permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/settings',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'settings_get' ),
				'permission_callback' => array( __CLASS__, 'permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/settings',
			array(
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => array( __CLASS__, 'settings_update' ),
				'permission_callback' => array( __CLASS__, 'permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/mappings',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'mappings' ),
				'permission_callback' => array( __CLASS__, 'permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/products',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'products' ),
				'permission_callback' => array( __CLASS__, 'permission' ),
			)
		);
	}

	/**
	 * Permission check.
	 *
	 * @return bool
	 */
	public static function permission() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Health payload.
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response
	 */
	public static function health( $request ) {
		$settings = Printful_Integration_For_Fluentcart_Settings::all();

		$data = array(
			'api_key_present'   => ! empty( $settings['api_key'] ),
			'webhooks_enabled'  => ! empty( $settings['enable_webhooks'] ) && ! empty( $settings['webhook_secret'] ),
			'polling_enabled'   => ! empty( $settings['enable_polling'] ),
			'queue_length'      => count( Printful_Integration_For_Fluentcart_Sync_Queue::all() ),
			'catalog_products'  => count( isset( $settings['mapped_products'] ) ? $settings['mapped_products'] : array() ),
			'signature_failures'=> class_exists( 'Printful_Integration_For_Fluentcart_Logger' ) ? Printful_Integration_For_Fluentcart_Logger::signature_failures() : 0,
		);

		return new \WP_REST_Response( $data, 200 );
	}

	/**
	 * Config payload.
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response
	 */
	public static function config( $request ) {
		$settings = Printful_Integration_For_Fluentcart_Settings::all();

		$exposed = array(
			'enable_webhooks'         => ! empty( $settings['enable_webhooks'] ),
			'poll_interval_minutes'   => isset( $settings['poll_interval_minutes'] ) ? (int) $settings['poll_interval_minutes'] : 10,
			'enable_live_rates'       => ! empty( $settings['enable_live_rates'] ),
			'shipping_markup_percent' => isset( $settings['shipping_markup_percent'] ) ? (float) $settings['shipping_markup_percent'] : 0,
			'allowed_carriers'        => isset( $settings['allowed_carriers'] ) ? $settings['allowed_carriers'] : array(),
			'fallback_rate'           => isset( $settings['fallback_rate'] ) ? $settings['fallback_rate'] : array(),
		);

		return new \WP_REST_Response( $exposed, 200 );
	}

	/**
	 * Tax status payload (helpers/toggle states).
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response
	 */
	public static function tax_status( $request ) {
		$settings = Printful_Integration_For_Fluentcart_Settings::all();

		return new \WP_REST_Response(
			array(
				'enable_printful_tax'   => ! empty( $settings['enable_printful_tax'] ),
				'tax_inclusive_prices'  => ! empty( $settings['tax_inclusive_prices'] ),
			),
			200
		);
	}

	/**
	 * Logs payload.
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response
	 */
	public static function logs( $request ) {
		$level  = $request->get_param( 'level' );
		$search = $request->get_param( 'search' );
		$limit  = $request->get_param( 'limit' );
		$limit  = $limit ? max( 1, (int) $limit ) : 100;
		$logs   = class_exists( 'Printful_Integration_For_Fluentcart_Logger' ) ? ( $search ? Printful_Integration_For_Fluentcart_Logger::search( $search ) : Printful_Integration_For_Fluentcart_Logger::filter( $level ) ) : array();

		return new \WP_REST_Response(
			array(
				'logs' => class_exists( 'Printful_Integration_For_Fluentcart_Logger' ) ? Printful_Integration_For_Fluentcart_Logger::limit( $logs, $limit ) : $logs,
			),
			200
		);
	}

	/**
	 * Return settings subset.
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response
	 */
	public static function settings_get( $request ) {
		$settings = Printful_Integration_For_Fluentcart_Settings::all();
		unset( $settings['api_key'] ); // do not expose secret.

		return new \WP_REST_Response( $settings, 200 );
	}

	/**
	 * Update settings subset via REST (limited).
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response
	 */
	public static function settings_update( $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			return new \WP_REST_Response( array( 'message' => 'Invalid payload' ), 400 );
		}

		$allowed = array(
			'enable_webhooks',
			'webhook_secret',
			'enable_polling',
			'poll_interval_minutes',
			'enable_live_rates',
			'shipping_markup_percent',
			'allowed_carriers',
			'allowed_services',
			'fallback_rate',
			'origin_address',
			'origin_overrides',
			'enable_printful_tax',
			'tax_inclusive_prices',
			'designer_links',
			'enable_designer_embed',
			'last_migration',
		);

		$updates = array();
		foreach ( $allowed as $key ) {
			if ( array_key_exists( $key, $params ) ) {
				$updates[ $key ] = $params[ $key ];
			}
		}

		if ( $updates ) {
			Printful_Integration_For_Fluentcart_Settings::update( $updates );
		}

		return new \WP_REST_Response( array( 'updated' => array_keys( $updates ) ), 200 );
	}

	/**
	 * Return mappings.
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response
	 */
	public static function mappings( $request ) {
		$data = Printful_Integration_For_Fluentcart_Product_Mapping::get_all_variation_mappings();

		return new \WP_REST_Response( array( 'mappings' => $data ), 200 );
	}

	/**
	 * Return mapped products list (id, printful id, fulfilment mode, preferred service).
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response
	 */
	public static function products( $request ) {
		$products = array();

		if ( class_exists( '\FluentCart\App\Models\Product' ) ) {
			$list = \FluentCart\App\Models\Product::query()
				->select( array( 'ID', 'post_title', 'post_status' ) )
				->limit( 100 )
				->get();

			foreach ( $list as $product ) {
				$products[] = array(
					'id'           => $product->ID,
					'title'        => $product->post_title,
					'status'       => $product->post_status,
					'printful_id'  => Printful_Integration_For_Fluentcart_Product_Mapping::get_product_mapping( $product->ID ),
					'fulfilment'   => get_post_meta( $product->ID, Printful_Integration_For_Fluentcart_Product_Mapping::META_KEY_DISABLE, true ),
					'service'      => Printful_Integration_For_Fluentcart_Product_Mapping::get_product_service( $product->ID ),
					'origin_index' => Printful_Integration_For_Fluentcart_Product_Mapping::get_product_origin( $product->ID ),
					'designer_url' => Printful_Integration_For_Fluentcart_Product_Mapping::get_designer_link( $product->ID ),
					'mockup_url'   => Printful_Integration_For_Fluentcart_Product_Mapping::get_product_mockup( $product->ID ),
				);
			}
		}

		return new \WP_REST_Response( array( 'products' => $products ), 200 );
	}
}
