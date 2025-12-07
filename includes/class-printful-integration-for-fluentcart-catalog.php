<?php

/**
 * Handles fetching and caching Printful catalog data for FluentCart mapping.
 *
 * @package Printful_Integration_For_Fluentcart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Printful_Integration_For_Fluentcart_Catalog {

	const OPTION_KEY = 'printful_fluentcart_catalog';
	const CRON_HOOK  = 'printful_fluentcart_sync_catalog';

	/**
	 * @var Printful_Integration_For_Fluentcart_Api
	 */
	protected $api;

	/**
	 * @var array
	 */
	protected $settings = array();

	/**
	 * Constructor.
	 *
	 * @param Printful_Integration_For_Fluentcart_Api $api API client.
	 * @param array                                   $settings Plugin settings.
	 */
	public function __construct( Printful_Integration_For_Fluentcart_Api $api, array $settings ) {
		$this->api      = $api;
		$this->settings = $settings;
	}

	/**
	 * Register cron hook.
	 *
	 * @return void
	 */
	public static function register_cron() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'cron_sync' ) );
	}

	/**
	 * Ensure cron is scheduled when enabled.
	 *
	 * @return void
	 */
	public static function ensure_cron() {
		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			return;
		}

		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
	}

	/**
	 * Clear scheduled cron.
	 *
	 * @return void
	 */
	public static function clear_cron() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
			$timestamp = wp_next_scheduled( self::CRON_HOOK );
		}
	}

	/**
	 * Cron callback: sync catalog using stored settings.
	 *
	 * @return void
	 */
	public static function cron_sync() {
		$settings = Printful_Integration_For_Fluentcart_Settings::all();
		if ( empty( $settings['auto_sync_catalog'] ) ) {
			return;
		}

		$api_key = isset( $settings['api_key'] ) ? trim( $settings['api_key'] ) : '';

		if ( '' === $api_key ) {
			return;
		}

		$api     = new Printful_Integration_For_Fluentcart_Api( $api_key, isset( $settings['api_base'] ) ? $settings['api_base'] : 'https://api.printful.com', ! empty( $settings['log_api_calls'] ) );
		$catalog = new self( $api, $settings );
		$result  = $catalog->sync();

		if ( is_wp_error( $result ) && function_exists( 'fluent_cart_error_log' ) ) {
			fluent_cart_error_log(
				'Printful catalog sync failed',
				$result->get_error_message(),
				array( 'module_type' => __CLASS__ )
			);
		}
	}

	/**
	 * Pull full catalog from Printful and store in options.
	 *
	 * @return array|\WP_Error Synced catalog or error.
	 */
	public function sync() {
		$list = $this->fetch_product_list();

		if ( is_wp_error( $list ) ) {
			return $list;
		}

		$catalog         = array(
			'synced_at' => time(),
			'products'  => array(),
			'variants'  => 0,
		);
		$total_variants  = 0;

		foreach ( $list as $product_summary ) {
			$product_id = isset( $product_summary['id'] ) ? (int) $product_summary['id'] : 0;

			if ( $product_id <= 0 ) {
				continue;
			}

			$detail = $this->fetch_product_detail( $product_id );
			if ( is_wp_error( $detail ) ) {
				return $detail;
			}

			$variants = isset( $detail['variants'] ) && is_array( $detail['variants'] ) ? $detail['variants'] : array();

			$total_variants += count( $variants );

			$catalog['products'][] = array(
				'id'          => $product_id,
				'external_id' => isset( $detail['external_id'] ) ? $detail['external_id'] : '',
				'name'        => isset( $detail['name'] ) ? $detail['name'] : '',
				'thumbnail'   => isset( $detail['thumbnail'] ) ? $detail['thumbnail'] : '',
				'variants'    => $variants,
			);
		}

		$catalog['variants'] = $total_variants;

		update_option( self::OPTION_KEY, $catalog );

		return $catalog;
	}

	/**
	 * Retrieve cached catalog.
	 *
	 * @return array
	 */
	public static function cached() {
		$catalog = get_option( self::OPTION_KEY, array() );
		return is_array( $catalog ) ? $catalog : array();
	}

	/**
	 * Fetch product list (ids + names) from Printful.
	 *
	 * @return array|\WP_Error
	 */
	protected function fetch_product_list() {
		$products = array();
		$offset   = 0;
		$limit    = 50;

		for ( $page = 0; $page < 40; $page++ ) {
			$response = $this->api->get( 'store/products', array(
				'limit'  => $limit,
				'offset' => $offset,
			) );

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$items = $this->extract_list_items( $response );

			if ( is_wp_error( $items ) ) {
				return $items;
			}

			if ( empty( $items ) ) {
				break;
			}

			foreach ( $items as $item ) {
				$products[] = $item;
			}

			if ( count( $items ) < $limit ) {
				break;
			}

			$offset += $limit;
		}

		return $products;
	}

	/**
	 * Fetch detailed product (with variants) from Printful.
	 *
	 * @param int $product_id Printful sync product ID.
	 *
	 * @return array|\WP_Error
	 */
	protected function fetch_product_detail( $product_id ) {
		$response = $this->api->get( 'store/products/' . (int) $product_id );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$result = isset( $response['result'] ) ? $response['result'] : $response;

		if ( empty( $result ) || ! is_array( $result ) ) {
			return new WP_Error(
				'printful_invalid_product_detail',
				__( 'Unexpected response while fetching Printful product detail.', 'printful-integration-for-fluentcart' )
			);
		}

		$product = isset( $result['sync_product'] ) ? $result['sync_product'] : $result;

		$variants = isset( $result['sync_variants'] ) ? $result['sync_variants'] : array();
		$variants = array_map( array( $this, 'normalise_variant' ), is_array( $variants ) ? $variants : array() );

		return array(
			'id'          => isset( $product['id'] ) ? $product['id'] : $product_id,
			'external_id' => isset( $product['external_id'] ) ? $product['external_id'] : '',
			'name'        => isset( $product['name'] ) ? $product['name'] : '',
			'thumbnail'   => isset( $product['thumbnail_url'] ) ? $product['thumbnail_url'] : '',
			'variants'    => $variants,
		);
	}

	/**
	 * Extract product list items from API response.
	 *
	 * @param array $response API response.
	 *
	 * @return array|\WP_Error
	 */
	protected function extract_list_items( array $response ) {
		$result = isset( $response['result'] ) ? $response['result'] : $response;

		if ( isset( $result['items'] ) && is_array( $result['items'] ) ) {
			$result = $result['items'];
		}

		if ( empty( $result ) ) {
			return array();
		}

		if ( ! is_array( $result ) ) {
			return new WP_Error(
				'printful_invalid_catalog',
				__( 'Unexpected response while fetching Printful catalog.', 'printful-integration-for-fluentcart' )
			);
		}

		return $result;
	}

	/**
	 * Normalise variant payload into minimal structure.
	 *
	 * @param array $variant Raw variant.
	 *
	 * @return array
	 */
	protected function normalise_variant( $variant ) {
		return array(
			'id'            => isset( $variant['id'] ) ? $variant['id'] : '',
			'external_id'   => isset( $variant['external_id'] ) ? $variant['external_id'] : '',
			'name'          => isset( $variant['name'] ) ? $variant['name'] : '',
			'sku'           => isset( $variant['sku'] ) ? $variant['sku'] : '',
			'currency'      => isset( $variant['currency'] ) ? $variant['currency'] : '',
			'retail_price'  => isset( $variant['retail_price'] ) ? $variant['retail_price'] : '',
			'product_id'    => isset( $variant['product']['id'] ) ? $variant['product']['id'] : '',
			'product_name'  => isset( $variant['product']['name'] ) ? $variant['product']['name'] : '',
			'variant_id'    => isset( $variant['variant_id'] ) ? $variant['variant_id'] : '',
			'files'         => isset( $variant['files'] ) ? $variant['files'] : array(),
		);
	}
}
