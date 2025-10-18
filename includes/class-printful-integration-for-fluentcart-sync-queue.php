<?php

/**
 * Simple queue utility for tracking Printful orders that require status refreshes.
 *
 * @package Printful_Integration_For_Fluentcart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Printful_Integration_For_Fluentcart_Sync_Queue {

	const OPTION_KEY = 'printful_fluentcart_sync_queue';

	/**
	 * Retrieve the current queue.
	 *
	 * @return int[]
	 */
	public static function all() {
		$queue = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $queue ) ) {
			return array();
		}

		$queue = array_map( 'intval', $queue );
		$queue = array_filter( $queue );

		return array_values( array_unique( $queue ) );
	}

	/**
	 * Add an order to the queue.
	 *
	 * @param int $order_id FluentCart order ID.
	 *
	 * @return void
	 */
	public static function add( $order_id ) {
		$order_id = (int) $order_id;

		if ( $order_id <= 0 ) {
			return;
		}

		$queue = self::all();

		if ( ! in_array( $order_id, $queue, true ) ) {
			$queue[] = $order_id;
			update_option( self::OPTION_KEY, $queue );
		}
	}

	/**
	 * Remove one or more order IDs from the queue.
	 *
	 * @param int|int[] $order_ids Order IDs to remove.
	 *
	 * @return void
	 */
	public static function remove( $order_ids ) {
		$queue = self::all();

		$order_ids = (array) $order_ids;
		$order_ids = array_map( 'intval', $order_ids );

		if ( empty( $queue ) || empty( $order_ids ) ) {
			return;
		}

		$queue = array_values( array_diff( $queue, $order_ids ) );
		update_option( self::OPTION_KEY, $queue );
	}

	/**
	 * Pop a batch of IDs off the queue.
	 *
	 * @param int $limit Number of IDs to pop.
	 *
	 * @return int[]
	 */
	public static function pop_batch( $limit = 5 ) {
		$queue = self::all();

		if ( empty( $queue ) ) {
			return array();
		}

		$limit  = max( 1, (int) $limit );
		$batch  = array_slice( $queue, 0, $limit );
		$remain = array_slice( $queue, $limit );

		update_option( self::OPTION_KEY, $remain );

		return $batch;
	}

	/**
	 * Completely clear the queue.
	 *
	 * @return void
	 */
	public static function reset() {
		delete_option( self::OPTION_KEY );
	}
}

