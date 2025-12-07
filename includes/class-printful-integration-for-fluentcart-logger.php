<?php

/**
 * Lightweight request/error log helper.
 *
 * @package Printful_Integration_For_Fluentcart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Printful_Integration_For_Fluentcart_Logger {

	const OPTION_KEY = 'printful_fluentcart_request_log';
	const OPTION_SIG = 'printful_fluentcart_signature_failures';
	const OPTION_META = 'printful_fluentcart_log_meta';

	/**
	 * Append a log entry (kept in a rolling buffer).
	 *
	 * @param array $entry Log payload.
	 *
	 * @return void
	 */
	public static function add( array $entry ) {
		$entry['time'] = time();

		$log = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}

		array_unshift( $log, $entry );
		$log = array_slice( $log, 0, 100 );

		update_option( self::OPTION_KEY, $log );
		self::touch_meta( isset( $entry['level'] ) ? $entry['level'] : 'info' );
	}

	/**
	 * Retrieve log buffer.
	 *
	 * @return array
	 */
	public static function all() {
		$log = get_option( self::OPTION_KEY, array() );

		return is_array( $log ) ? $log : array();
	}

	/**
	 * Retrieve filtered logs.
	 *
	 * @param string|null $level Level filter.
	 *
	 * @return array
	 */
	public static function filter( $level = null ) {
		$logs = self::all();
		if ( ! $level ) {
			return $logs;
		}

		$level = strtolower( $level );

		return array_values(
			array_filter(
				$logs,
				function( $entry ) use ( $level ) {
					return isset( $entry['level'] ) && strtolower( $entry['level'] ) === $level;
				}
			)
		);
	}

	/**
	 * Search logs by term.
	 *
	 * @param string $term Search term.
	 *
	 * @return array
	 */
	public static function search( $term ) {
		$term = strtolower( $term );
		return array_values(
			array_filter(
				self::all(),
				function( $entry ) use ( $term ) {
					$hay = strtolower( wp_json_encode( $entry ) );
					return strpos( $hay, $term ) !== false;
				}
			)
		);
	}

	/**
	 * Limit slice of logs.
	 *
	 * @param array $logs Logs array.
	 * @param int   $limit Max entries.
	 *
	 * @return array
	 */
	public static function limit( array $logs, $limit = 50 ) {
		$limit = max( 1, (int) $limit );
		return array_slice( $logs, 0, $limit );
	}

	/**
	 * Increment signature failure count.
	 *
	 * @return void
	 */
	public static function increment_signature_failure() {
		$count = (int) get_option( self::OPTION_SIG, 0 );
		update_option( self::OPTION_SIG, $count + 1 );
	}

	/**
	 * Get signature failure count.
	 *
	 * @return int
	 */
	public static function signature_failures() {
		return (int) get_option( self::OPTION_SIG, 0 );
	}

	/**
	 * Clear all log entries and meta.
	 *
	 * @return void
	 */
	public static function clear() {
		delete_option( self::OPTION_KEY );
		delete_option( self::OPTION_META );
	}

	/**
	 * Get aggregated stats.
	 *
	 * @return array
	 */
	public static function stats() {
		$meta = get_option( self::OPTION_META, array() );
		return is_array( $meta ) ? $meta : array();
	}

	/**
	 * Get last error entry.
	 *
	 * @return array|null
	 */
	public static function last_error() {
		$logs = self::all();
		foreach ( $logs as $log ) {
			if ( isset( $log['level'] ) && $log['level'] === 'error' ) {
				return $log;
			}
		}
		return null;
	}

	/**
	 * Touch meta counters.
	 *
	 * @param string $level Level.
	 *
	 * @return void
	 */
	protected static function touch_meta( $level ) {
		$meta = get_option( self::OPTION_META, array( 'errors' => 0 ) );
		if ( ! is_array( $meta ) ) {
			$meta = array( 'errors' => 0 );
		}
		if ( 'error' === $level ) {
			$meta['errors'] = isset( $meta['errors'] ) ? ( (int) $meta['errors'] + 1 ) : 1;
		}
		update_option( self::OPTION_META, $meta );
	}
}
