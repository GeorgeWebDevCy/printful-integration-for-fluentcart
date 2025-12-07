<?php

namespace PrintfulIntegration\Printful;

use PrintfulIntegration\Printful\Exceptions\PrintfulException;
use Exception;

class Integration {

	const PF_API_CONNECT_STATUS = 'printful_integration_api_connect_status';
	const PF_CONNECT_ERROR = 'printful_integration_connect_error';
	const OPTION_NAME = 'printful_integration_for_fluentcart_settings';

	public static $_instance;

	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function __construct() {
		self::$_instance = $this;
	}

	/**
	 * Get client.
	 *
	 * @return Client
	 * @throws PrintfulException
	 */
	public function get_client() {
		$key = $this->get_option( 'printful_key' );
		
		if ( empty( $key ) ) {
			throw new PrintfulException( 'Printful API Key is not configured.' );
		}

		$client = new Client( $key, $this->get_option( 'disable_ssl' ) == 'yes' );

		return $client;
	}

	/**
	 * Check if the connection to Printful is working
	 *
	 * @param bool $force
	 * @return bool
	 * @throws PrintfulException
	 */
	public function is_connected( $force = false ) {
		$api_key = $this->get_option( 'printful_key' );

		//dont need to show error - the plugin is simply not setup
		if ( empty( $api_key ) ) {
			return false;
		}

		//validate length, show error
		if ( strlen( $api_key ) < 32 ) { // Basic length check, actual keys are 32+ chars
			$this->set_connect_error( 'Invalid API key format.' );
			return false;
		}

		//show connect status from cache
		if ( ! $force ) {
			$connected = get_transient( self::PF_API_CONNECT_STATUS );
			if ( $connected && 1 == $connected['status']) {
				$this->clear_connect_error();

				return true;
			} else if ( $connected && 0 == $connected['status'] ) {    //try again in a minute
				return false;
			}
		}

		$client   = $this->get_client();
		$response = false;

		//attempt to connect to printful to verify the API key
		try {
			$storeData = $client->get( 'store' );
            // We accept any store type, checking name or id might be useful but existence is enough
			if ( ! empty( $storeData ) ) {
				$response = true;
				$this->clear_connect_error();
				set_transient( self::PF_API_CONNECT_STATUS, array( 'status' => 1 ) );  //no expiry until cleared
			}
		} catch ( Exception $e ) {

			if ( $e->getCode() == 401 ) {
				$this->set_connect_error( 'Invalid API Key. Unauthorized.' );
				set_transient( self::PF_API_CONNECT_STATUS, array( 'status' => 0 ), MINUTE_IN_SECONDS );
			} else {
				$this->set_connect_error( 'Could not connect to Printful API. Error ' . $e->getCode() . ': ' . $e->getMessage() );
			}

			set_transient( self::PF_API_CONNECT_STATUS, array( 'status' => 0 ), MINUTE_IN_SECONDS );
		}

		return $response;
	}

	/**
	 * Update connect error message
	 *
	 * @param string $error
	 */
	public function set_connect_error( $error = '' ) {
		update_option( self::PF_CONNECT_ERROR, $error );
	}

	/**
	 * Get current connect error message
	 */
	public function get_connect_error() {
		return get_option( self::PF_CONNECT_ERROR, false );
	}

	/**
	 * Remove option used for storing current connect error
	 */
	public function clear_connect_error() {
		delete_option( self::PF_CONNECT_ERROR );
	}

	/**
	 * Wrapper method for getting an option
	 *
	 * @param $name
	 * @param array $default
	 * @return bool|mixed
	 */
	public function get_option( $name, $default = false ) {
		$options = $this->get_settings();
		if ( isset( $options[ $name ] ) ) {
			return $options[ $name ];
		}

		return $default;
	}

	/**
	 * Wrapper method for getting all the settings
	 *
	 * @return array
	 */
	public function get_settings() {
		return get_option( self::OPTION_NAME, array() );
	}

	/**
	 * Save the setting
	 *
	 * @param $settings
	 */
	public function update_settings( $settings ) {
		update_option( self::OPTION_NAME, $settings );
        // Clear connection status cache on update
        delete_transient( self::PF_API_CONNECT_STATUS );
	}
}
