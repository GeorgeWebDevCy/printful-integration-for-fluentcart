<?php

/**
 * Fired during plugin deactivation
 *
 * @link       https://www.georgenicolaou.me/
 * @since      1.0.0
 *
 * @package    Printful_Integration_For_Fluentcart
 * @subpackage Printful_Integration_For_Fluentcart/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Printful_Integration_For_Fluentcart
 * @subpackage Printful_Integration_For_Fluentcart/includes
 * @author     George Nicolaou <orionas.elite@gmail.com>
 */
class Printful_Integration_For_Fluentcart_Deactivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {

		if ( class_exists( 'Printful_Integration_For_Fluentcart_Sync_Manager' ) ) {
			wp_clear_scheduled_hook( Printful_Integration_For_Fluentcart_Sync_Manager::CRON_HOOK );
		} else {
			wp_clear_scheduled_hook( 'printful_fluentcart_sync_orders' );
		}

	}

}
