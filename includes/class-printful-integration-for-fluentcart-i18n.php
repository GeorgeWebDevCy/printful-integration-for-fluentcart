<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://www.georgenicolaou.me/
 * @since      1.0.0
 *
 * @package    Printful_Integration_For_Fluentcart
 * @subpackage Printful_Integration_For_Fluentcart/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Printful_Integration_For_Fluentcart
 * @subpackage Printful_Integration_For_Fluentcart/includes
 * @author     George Nicolaou <orionas.elite@gmail.com>
 */
class Printful_Integration_For_Fluentcart_i18n {


	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'printful-integration-for-fluentcart',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);

	}



}
