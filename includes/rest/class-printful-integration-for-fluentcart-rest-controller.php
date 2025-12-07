<?php

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Base REST controller helpers.
 */
class Printful_Integration_For_Fluentcart_Rest_Controller {

        /**
         * REST namespace shared across endpoints.
         */
        const REST_NAMESPACE = Printful_Integration_For_Fluentcart_Rest::NAMESPACE;

        /**
         * Permission check for management endpoints.
         *
         * @return bool
         */
        public static function permission() {
                return current_user_can( 'manage_options' );
        }

        /**
         * Return decoded JSON params as array.
         *
         * @param \WP_REST_Request $request Request instance.
         *
         * @return array
         */
        protected static function json_body( \WP_REST_Request $request ) {
                $params = $request->get_json_params();

                return is_array( $params ) ? $params : array();
        }

        /**
         * Simple array sanitization.
         *
         * @param mixed $value Arbitrary value.
         *
         * @return mixed
         */
        protected static function sanitize_value( $value ) {
                if ( is_array( $value ) ) {
                        return array_map( array( __CLASS__, 'sanitize_value' ), $value );
                }

                if ( is_bool( $value ) || is_numeric( $value ) ) {
                        return $value;
                }

                return is_string( $value ) ? sanitize_text_field( $value ) : $value;
        }
}
