<?php

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * REST controller for settings mutations.
 */
class Printful_Integration_For_Fluentcart_Rest_Settings_Controller extends Printful_Integration_For_Fluentcart_Rest_Controller {

        /**
         * Register routes.
         *
         * @return void
         */
        public static function register() {
                add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
        }

        /**
         * Define endpoints.
         *
         * @return void
         */
        public static function routes() {
                register_rest_route(
                        self::REST_NAMESPACE,
                        '/settings',
                        array(
                                'methods'             => \WP_REST_Server::CREATABLE,
                                'callback'            => array( __CLASS__, 'create_or_update' ),
                                'permission_callback' => array( __CLASS__, 'permission' ),
                        )
                );

                register_rest_route(
                        self::REST_NAMESPACE,
                        '/settings/(?P<key>[\\w_-]+)',
                        array(
                                'methods'             => \WP_REST_Server::DELETABLE,
                                'callback'            => array( __CLASS__, 'delete' ),
                                'permission_callback' => array( __CLASS__, 'permission' ),
                        )
                );
        }

        /**
         * Create or update settings keys.
         *
         * @param \WP_REST_Request $request Request.
         *
         * @return \WP_REST_Response|\WP_Error
         */
        public static function create_or_update( \WP_REST_Request $request ) {
                $params   = self::json_body( $request );
                $allowed  = array_keys( Printful_Integration_For_Fluentcart_Settings::all() );
                $updates  = array();
                $received = array();

                foreach ( $params as $key => $value ) {
                        if ( ! in_array( $key, $allowed, true ) ) {
                                continue;
                        }

                        $received[]      = $key;
                        $updates[ $key ] = self::sanitize_value( $value );
                }

                if ( empty( $updates ) ) {
                        return new \WP_Error( 'printful_no_settings', __( 'No valid settings provided.', 'printful-integration-for-fluentcart' ), array( 'status' => 400 ) );
                }

                Printful_Integration_For_Fluentcart_Settings::update( $updates );

                return new \WP_REST_Response(
                        array(
                                'saved'      => array_keys( $updates ),
                                'requested'  => $received,
                                'count'      => count( $updates ),
                        ),
                        200
                );
        }

        /**
         * Delete a stored setting key.
         *
         * @param \WP_REST_Request $request Request.
         *
         * @return \WP_REST_Response
         */
        public static function delete( \WP_REST_Request $request ) {
                $key     = isset( $request['key'] ) ? sanitize_key( $request['key'] ) : '';
                $allowed = array_keys( Printful_Integration_For_Fluentcart_Settings::all() );

                if ( ! $key || ! in_array( $key, $allowed, true ) ) {
                        return new \WP_REST_Response( array( 'deleted' => false, 'key' => $key ), 400 );
                }

                $deleted = Printful_Integration_For_Fluentcart_Settings::remove( $key );

                return new \WP_REST_Response(
                        array(
                                'key'     => $key,
                                'deleted' => $deleted,
                        ),
                        $deleted ? 200 : 404
                );
        }
}
