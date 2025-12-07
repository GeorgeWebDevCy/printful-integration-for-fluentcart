<?php

/**
 * Thin wrapper around the Printful REST API.
 *
 * Handles authentication headers, request formatting, error handling, and
 * optional logging so other parts of the plugin can focus on domain logic.
 *
 * @package Printful_Integration_For_Fluentcart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Printful_Integration_For_Fluentcart_Api {

	/**
	 * API base URL.
	 *
	 * @var string
	 */
	protected $base_url;

	/**
	 * Printful API key.
	 *
	 * @var string
	 */
	protected $api_key;

	/**
	 * Whether to log requests/responses using FluentCart's logging facility.
	 *
	 * @var bool
	 */
	protected $log_api_calls = true;

	/**
	 * Constructor.
	 *
	 * @param string $api_key API key obtained from Printful dashboard.
	 * @param string $base_url Optional override for API base URL.
	 * @param bool   $log_api_calls Whether to record API calls in FluentCart logs.
	 */
	public function __construct( $api_key, $base_url = 'https://api.printful.com', $log_api_calls = true ) {
		$this->api_key       = $api_key;
		$this->base_url      = untrailingslashit( $base_url );
		$this->log_api_calls = (bool) $log_api_calls;
	}

	/**
	 * Perform a GET request.
	 *
	 * @param string $path   Endpoint path.
	 * @param array  $query  Query parameters.
	 *
	 * @return array|\WP_Error
	 */
	public function get( $path, $query = array() ) {
		return $this->request( 'GET', $path, array(), $query );
	}

	/**
	 * Perform a POST request.
	 *
	 * @param string $path Endpoint path.
	 * @param array  $body Request body.
	 * @param array  $query Query params.
	 *
	 * @return array|\WP_Error
	 */
	public function post( $path, $body = array(), $query = array() ) {
		return $this->request( 'POST', $path, $body, $query );
	}

	/**
	 * Perform a PUT request.
	 *
	 * @param string $path Endpoint path.
	 * @param array  $body Request body.
	 *
	 * @return array|\WP_Error
	 */
	public function put( $path, $body = array() ) {
		return $this->request( 'PUT', $path, $body );
	}

	/**
	 * Perform a DELETE request.
	 *
	 * @param string $path Endpoint path.
	 *
	 * @return array|\WP_Error
	 */
	public function delete( $path ) {
		return $this->request( 'DELETE', $path );
	}

	/**
	 * Generic request implementation.
	 *
	 * @param string $method HTTP verb.
	 * @param string $path   Endpoint path relative to $base_url.
	 * @param array  $body   Request body.
	 * @param array  $query  Query params.
	 *
	 * @return array|\WP_Error
	 */
	public function request( $method, $path, $body = array(), $query = array() ) {
		$url = $this->prepare_url( $path, $query );

		$args = array(
			'method'  => strtoupper( $method ),
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type'  => 'application/json',
				'User-Agent'    => $this->get_user_agent(),
			),
			'timeout' => 20,
		);

		if ( ! empty( $body ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$this->log( 'API request error', array(
				'method' => $method,
				'url'    => $url,
				'error'  => $response->get_error_message(),
			), 'error' );
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		$this->log( 'API response', array(
			'method'   => $method,
			'url'      => $url,
			'status'   => $code,
			'response' => $data,
		) );

		if ( $code < 200 || $code >= 300 ) {
			$message = isset( $data['error'] ) ? $data['error'] : __( 'Unexpected Printful API error.', 'printful-integration-for-fluentcart' );

			return new WP_Error(
				'printful_api_error',
				$message,
				array(
					'status'   => $code,
					'response' => $data,
				)
			);
		}

		return $data;
	}

	/**
	 * Prepare the target URL by merging path + query parameters.
	 *
	 * @param string $path  Endpoint path.
	 * @param array  $query Query parameters.
	 *
	 * @return string
	 */
	protected function prepare_url( $path, $query = array() ) {
		$url = trailingslashit( $this->base_url ) . ltrim( $path, '/' );

		if ( ! empty( $query ) ) {
			$url = add_query_arg( $query, $url );
		}

		return $url;
	}

	/**
	 * Build a descriptive user agent string.
	 *
	 * @return string
	 */
	protected function get_user_agent() {
		$parts = array(
			'PrintfulFluentCart/' . PRINTFUL_INTEGRATION_FOR_FLUENTCART_VERSION,
			'WordPress/' . get_bloginfo( 'version' ),
			'PHP/' . PHP_VERSION,
		);

		return implode( ' ', array_filter( $parts ) );
	}

	/**
	 * Optionally push a log entry into FluentCart logs.
	 *
	 * @param string $title   Log title.
	 * @param array  $context Contextual data.
	 * @param string $level   Log level.
	 *
	 * @return void
	 */
	protected function log( $title, $context = array(), $level = 'info' ) {
		if ( ! $this->log_api_calls || ! function_exists( 'fluent_cart_add_log' ) ) {
			// Still store a lightweight rolling log.
			if ( class_exists( 'Printful_Integration_For_Fluentcart_Logger' ) ) {
				Printful_Integration_For_Fluentcart_Logger::add(
					array(
						'title'   => $title,
						'level'   => $level,
						'context' => $context,
					)
				);
			}
			return;
		}

		$context = is_array( $context ) ? $context : array( 'context' => $context );

		if ( class_exists( 'Printful_Integration_For_Fluentcart_Logger' ) ) {
			Printful_Integration_For_Fluentcart_Logger::add(
				array(
					'title'   => $title,
					'level'   => $level,
					'context' => $context,
				)
			);
		}

		fluent_cart_add_log(
			$title,
			wp_json_encode( $context ),
			$level,
			array(
				'module_type' => __CLASS__,
				'module_name' => 'Printful API',
			)
		);
	}
}
