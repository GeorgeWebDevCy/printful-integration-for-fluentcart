<?php

/**
 * Settings helper for the Printful → FluentCart integration.
 *
 * Centralises option keys and provides convenience wrappers so the rest of the
 * plugin can stay decoupled from WordPress' options API.
 *
 * @package Printful_Integration_For_Fluentcart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Printful_Integration_For_Fluentcart_Settings {

	/**
	 * Name of the option in wp_options.
	 */
	const OPTION_KEY = 'printful_fluentcart_settings';

	/**
	 * Retrieve all settings with sane defaults applied.
	 *
	 * @param array $defaults Optional defaults to merge.
	 *
	 * @return array
	 */
	public static function all( $defaults = array() ) {
		$stored   = get_option( self::OPTION_KEY, array() );
		$defaults = wp_parse_args(
			$defaults,
			array(
				'api_key'                 => '',
				'api_base'                => 'https://api.printful.com',
				'auto_fulfill_paid'       => true,
				'log_api_calls'           => true,
				'enable_webhooks'         => false,
				'webhook_secret'          => '',
				'enable_polling'          => true,
				'poll_interval_minutes'   => 10,
				'enable_live_rates'       => false,
				'shipping_markup_percent' => 0,
				'product_markup_percent'  => 0,
				'auto_sync_catalog'       => false,
				'default_shipping_method' => '',
				'mapped_products'         => array(),
				'allowed_carriers'        => array(),
				'fallback_rate'           => array(),
				'allowed_services'        => array(),
				'origin_address'          => array(
					'name'      => '',
					'company'   => '',
					'address_1' => '',
					'address_2' => '',
					'city'      => '',
					'state'     => '',
					'postcode'  => '',
					'country'   => '',
					'phone'     => '',
				),
				'origin_overrides'        => array(),
				'enable_printful_tax'     => false,
				'tax_inclusive_prices'    => false,
			)
		);

		return wp_parse_args( is_array( $stored ) ? $stored : array(), $defaults );
	}

	/**
	 * Persist settings back to the database.
	 *
	 * @param array $settings Sanitised settings array.
	 *
	 * @return void
	 */
	public static function update( array $settings ) {
		$current = self::all();
		update_option( self::OPTION_KEY, array_merge( $current, $settings ) );
	}

	/**
	 * Convenience wrapper to fetch a single setting value.
	 *
	 * @param string $key     Array key.
	 * @param mixed  $default Default value if not set.
	 *
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$settings = self::all();

		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}

	/**
	 * Update a single setting value.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Setting value.
	 *
	 * @return void
	 */
	public static function set( $key, $value ) {
		$settings         = self::all();
		$settings[ $key ] = $value;
		update_option( self::OPTION_KEY, $settings );
	}
}
