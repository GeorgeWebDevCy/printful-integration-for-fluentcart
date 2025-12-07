<?php

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * REST controller for creating/updating/deleting variant mappings.
 */
class Printful_Integration_For_Fluentcart_Rest_Mappings_Controller extends Printful_Integration_For_Fluentcart_Rest_Controller {

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
                        '/mappings/(?P<variation_id>\\d+)',
                        array(
                                'methods'             => \WP_REST_Server::CREATABLE,
                                'callback'            => array( __CLASS__, 'create_or_update' ),
                                'permission_callback' => array( __CLASS__, 'permission' ),
                        )
                );

                register_rest_route(
                        self::REST_NAMESPACE,
                        '/mappings/(?P<variation_id>\\d+)',
                        array(
                                'methods'             => \WP_REST_Server::EDITABLE,
                                'callback'            => array( __CLASS__, 'create_or_update' ),
                                'permission_callback' => array( __CLASS__, 'permission' ),
                        )
                );

                register_rest_route(
                        self::REST_NAMESPACE,
                        '/mappings/(?P<variation_id>\\d+)',
                        array(
                                'methods'             => \WP_REST_Server::DELETABLE,
                                'callback'            => array( __CLASS__, 'delete' ),
                                'permission_callback' => array( __CLASS__, 'permission' ),
                        )
                );
        }

        /**
         * Create or update a variation mapping.
         *
         * @param \WP_REST_Request $request Request.
         *
         * @return \WP_REST_Response|\WP_Error
         */
        public static function create_or_update( \WP_REST_Request $request ) {
                $variation_id = isset( $request['variation_id'] ) ? absint( $request['variation_id'] ) : 0;
                $params       = self::json_body( $request );
                $variant      = isset( $params['printful_variant_id'] ) ? sanitize_text_field( $params['printful_variant_id'] ) : '';

                if ( ! $variation_id ) {
                        return new \WP_Error( 'printful_missing_variation', __( 'Variation ID is required.', 'printful-integration-for-fluentcart' ), array( 'status' => 400 ) );
                }

                if ( ! $variant ) {
                        return new \WP_Error( 'printful_missing_variant', __( 'Printful variant ID is required.', 'printful-integration-for-fluentcart' ), array( 'status' => 400 ) );
                }

                $saved = Printful_Integration_For_Fluentcart_Product_Mapping::set_variation_mapping( $variation_id, $variant );

                if ( ! $saved ) {
                        return new \WP_Error( 'printful_mapping_failed', __( 'Could not persist mapping.', 'printful-integration-for-fluentcart' ), array( 'status' => 500 ) );
                }

                return new \WP_REST_Response(
                        array(
                                'variation_id'        => $variation_id,
                                'printful_variant_id' => $variant,
                        ),
                        200
                );
        }

        /**
         * Delete a variation mapping.
         *
         * @param \WP_REST_Request $request Request.
         *
         * @return \WP_REST_Response
         */
        public static function delete( \WP_REST_Request $request ) {
                $variation_id = isset( $request['variation_id'] ) ? absint( $request['variation_id'] ) : 0;

                if ( ! $variation_id ) {
                        return new \WP_REST_Response( array( 'deleted' => false ), 400 );
                }

                $deleted = Printful_Integration_For_Fluentcart_Product_Mapping::delete_variation_mapping( $variation_id );

                return new \WP_REST_Response(
                        array(
                                'variation_id' => $variation_id,
                                'deleted'      => (bool) $deleted,
                        ),
                        $deleted ? 200 : 404
                );
        }
}
