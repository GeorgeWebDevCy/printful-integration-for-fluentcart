<?php

namespace PrintfulIntegration\Printful;

use PrintfulIntegration\Printful\Exceptions\PrintfulException;
use PrintfulIntegration\Printful\Exceptions\PrintfulApiException;

/**
 * Printful API client
 */
class Client {

	const API_HOST = 'https://api.printful.com/';

	private $key = false;
	private $isOAuth = false;
	private $lastResponseRaw;
	private $lastResponse;
	private $userAgent = 'FluentCart Printful Integration';
	private $apiUrl;

	/**
	 * Constructor function
	 *
	 * @param string $key Printful Store API key
	 * @param bool|string $disable_ssl Force HTTP instead of HTTPS for API requests
	 * @param bool $is_oauth Should use OAuth header
	 *
	 * @throws PrintfulException if the library failed to initialize
	 */
	public function __construct( $key = '', $disable_ssl = false, $is_oauth = false ) {

		$key = (string) $key;
		$this->isOAuth = (bool) $is_oauth;

		$this->userAgent .= ' ' . PRINTFUL_INTEGRATION_FOR_FLUENTCART_VERSION . ' (WP ' . get_bloginfo( 'version' ) . ')';

		if ( ! function_exists( 'json_decode' ) || ! function_exists( 'json_encode' ) ) {
			throw new PrintfulException( 'PHP JSON extension is required for the Printful API library to work!' );
		}
		if ( strlen( $key ) < 32 ) {
			throw new PrintfulException( 'Missing or invalid Printful store key!' );
		}
		$this->key = $key;
		
		$this->apiUrl = self::API_HOST;

		if ( $disable_ssl ) {
			$this->apiUrl = str_replace( 'https://', 'http://', $this->apiUrl );
		}
	}

	/**
	 * Returns total available item count from the last request if it supports paging (e.g order list) or null otherwise.
	 *
	 * @return int|null Item count
	 */
	public function getItemCount() {
		return isset( $this->lastResponse['paging']['total'] ) ? $this->lastResponse['paging']['total'] : null;
	}

	/**
	 * Perform a GET request to the API
	 *
	 * @param string $path Request path (e.g. 'orders' or 'orders/123')
	 * @param array $params Additional GET parameters as an associative array
	 * @return mixed API response
	 * @throws PrintfulApiException if the API call status code is not in the 2xx range
	 * @throws PrintfulException if the API call has failed or the response is invalid
	 */
	public function get( $path, $params = array() ) {
		return $this->request( 'GET', $path, $params );
	}

	/**
	 * Perform a DELETE request to the API
	 *
	 * @param string $path Request path (e.g. 'orders' or 'orders/123')
	 * @param array $params Additional GET parameters as an associative array
	 * @return mixed API response
	 * @throws PrintfulApiException if the API call status code is not in the 2xx range
	 * @throws PrintfulException if the API call has failed or the response is invalid
	 */
	public function delete( $path, $params = array() ) {
		return $this->request( 'DELETE', $path, $params );
	}

	/**
	 * Perform a POST request to the API
	 *
	 * @param string $path Request path (e.g. 'orders' or 'orders/123')
	 * @param array $data Request body data as an associative array
	 * @param array $params Additional GET parameters as an associative array
	 * @return mixed API response
	 * @throws PrintfulApiException if the API call status code is not in the 2xx range
	 * @throws PrintfulException if the API call has failed or the response is invalid
	 */
	public function post( $path, $data = array(), $params = array() ) {
		return $this->request( 'POST', $path, $params, $data );
	}
	/**
	 * Perform a PUT request to the API
	 *
	 * @param string $path Request path (e.g. 'orders' or 'orders/123')
	 * @param array $data Request body data as an associative array
	 * @param array $params Additional GET parameters as an associative array
	 * @return mixed API response
	 * @throws PrintfulApiException if the API call status code is not in the 2xx range
	 * @throws PrintfulException if the API call has failed or the response is invalid
	 */
	public function put( $path, $data = array(), $params = array() ) {
		return $this->request( 'PUT', $path, $params, $data );
	}


	/**
	 * Perform a PATCH request to the API
	 *
	 * @param string $path Request path
	 * @param array $data Request body data as an associative array
	 * @param array $params
	 * @return mixed API response
	 * @throws PrintfulApiException if the API call status code is not in the 2xx range
	 * @throws PrintfulException if the API call has failed or the response is invalid
	 */
	public function patch( $path, $data = array(), $params = array() ) {
		return $this->request( 'PATCH', $path, $params, $data );
	}

	/**
	 * Return raw response data from the last request
	 *
	 * @return string|null Response data
	 */
	public function getLastResponseRaw() {
		return $this->lastResponseRaw;
	}
	/**
	 * Return decoded response data from the last request
	 *
	 * @return array|null Response data
	 */
	public function getLastResponse() {
		return $this->lastResponse;
	}

	/**
	 * Internal request implementation
	 *
	 * @param $method
	 * @param $path
	 * @param array $params
	 * @param null $data
	 *
	 * @return
	 * @throws PrintfulApiException
	 * @throws PrintfulException
	 */
	private function request( $method, $path, array $params = array(), $data = null ) {

		$this->lastResponseRaw = null;
		$this->lastResponse    = null;

		$url = trim( $path, '/' );

		if ( ! empty( $params ) ) {
			$url .= '?' . http_build_query( $params );
		}

		$authHeader = $this->isOAuth
			? 'Bearer ' . $this->key
			: 'Basic ' . base64_encode( $this->key );

		$request = array(
			'timeout'    => 10,
			'user-agent' => $this->userAgent,
			'method'     => $method,
			'headers'    => array( 'Authorization' => $authHeader, 'Content-Type' => 'application/json' ),
			'body'       => null !== $data ? json_encode( $data ) : null,
		);

		$result = wp_remote_request( $this->apiUrl . $url, $request );

		/**
		 * Allow other methods to hook in on the API result.
		 *
		 * @since 1.0.0
		 */
		$result = apply_filters( 'printful_api_result', $result, $method, $this->apiUrl . $url, $request );

		if ( is_wp_error( $result ) ) {
			throw new PrintfulException( 'API request failed - ' . esc_html($result->get_error_message()) );
		}
		$this->lastResponseRaw = $result['body'];
		$response = json_decode( $result['body'], true );
		$this->lastResponse    = $response;

		if ( ! isset( $response['code'], $response['result'] ) ) {
            // Check if it's an error response structure
            if(isset($response['code'], $response['error'])){
                 throw new PrintfulException( 'API Error: ' . $response['error'] );
            }
			throw new PrintfulException( 'Invalid API response format' );
		}
		$status = (int) $response['code'];
		if ( $status < 200 || $status >= 300 ) {
			throw new PrintfulApiException(  isset($response['result']) ? $response['result'] : 'Unknown API Error', $status);
		}

		return $response['result'];
	}
}
