<?php

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * REST controller for product-level mutations.
 */
class Printful_Integration_For_Fluentcart_Rest_Products_Controller extends Printful_Integration_For_Fluentcart_Rest_Controller {

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
                        '/products/(?P<product_id>\\d+)',
                        array(
                                'methods'             => \WP_REST_Server::EDITABLE,
                                'callback'            => array( __CLASS__, 'update_product' ),
                                'permission_callback' => array( __CLASS__, 'permission' ),
                        )
                );

                register_rest_route(
                        self::REST_NAMESPACE,
                        '/products/(?P<product_id>\\d+)',
                        array(
                                'methods'             => \WP_REST_Server::DELETABLE,
                                'callback'            => array( __CLASS__, 'clear_product' ),
                                'permission_callback' => array( __CLASS__, 'permission' ),
                        )
                );
        }

        /**
         * Update product mapping/meta.
         *
         * @param \WP_REST_Request $request Request.
         *
         * @return \WP_REST_Response|\WP_Error
         */
        public static function update_product( \WP_REST_Request $request ) {
                $product_id = isset( $request['product_id'] ) ? absint( $request['product_id'] ) : 0;
                $params     = self::json_body( $request );

                if ( ! $product_id ) {
                        return new \WP_Error( 'printful_missing_product', __( 'Product ID is required.', 'printful-integration-for-fluentcart' ), array( 'status' => 400 ) );
                }

                $fields  = array();
                $updated = array();

                if ( array_key_exists( 'printful_product_id', $params ) ) {
                        $fields['printful_product_id'] = sanitize_text_field( $params['printful_product_id'] );
                }

                if ( array_key_exists( 'fulfilment_mode', $params ) ) {
                        $fields['fulfilment_mode'] = sanitize_text_field( $params['fulfilment_mode'] );
                }

                if ( array_key_exists( 'service_code', $params ) ) {
                        $fields['service_code'] = sanitize_text_field( $params['service_code'] );
                }

                if ( array_key_exists( 'origin_index', $params ) ) {
                        $fields['origin_index'] = '' === $params['origin_index'] ? '' : (int) $params['origin_index'];
                }

                if ( array_key_exists( 'mockup_url', $params ) ) {
                        $fields['mockup_url'] = $params['mockup_url'] ? esc_url_raw( $params['mockup_url'] ) : '';
                }

                if ( array_key_exists( 'designer_url', $params ) ) {
                        $fields['designer_url'] = $params['designer_url'] ? esc_url_raw( $params['designer_url'] ) : '';
                }

                if ( isset( $fields['printful_product_id'] ) ) {
                        if ( $fields['printful_product_id'] ) {
                                Printful_Integration_For_Fluentcart_Product_Mapping::set_product_mapping( $product_id, $fields['printful_product_id'] );
                        } else {
                                Printful_Integration_For_Fluentcart_Product_Mapping::delete_product_mapping( $product_id );
                        }
                        $updated[] = 'printful_product_id';
                }

                if ( isset( $fields['fulfilment_mode'] ) ) {
                        Printful_Integration_For_Fluentcart_Product_Mapping::set_fulfilment_mode( $product_id, $fields['fulfilment_mode'] );
                        $updated[] = 'fulfilment_mode';
                }

                if ( isset( $fields['service_code'] ) ) {
                        Printful_Integration_For_Fluentcart_Product_Mapping::set_product_service( $product_id, $fields['service_code'] );
                        $updated[] = 'service_code';
                }

                if ( array_key_exists( 'origin_index', $fields ) ) {
                        Printful_Integration_For_Fluentcart_Product_Mapping::set_product_origin( $product_id, $fields['origin_index'] );
                        $updated[] = 'origin_index';
                }

                if ( isset( $fields['mockup_url'] ) ) {
                        Printful_Integration_For_Fluentcart_Product_Mapping::set_product_mockup( $product_id, $fields['mockup_url'] );
                        $updated[] = 'mockup_url';
                }

                if ( isset( $fields['designer_url'] ) ) {
                        Printful_Integration_For_Fluentcart_Product_Mapping::set_designer_link( $product_id, $fields['designer_url'] );
                        $updated[] = 'designer_url';
                }

                $payload = array(
                        'product_id' => $product_id,
                        'updated'    => $updated,
                );

                return new \WP_REST_Response( $payload, 200 );
        }

        /**
         * Clear Printful-related product metadata and mapping.
         *
         * @param \WP_REST_Request $request Request.
         *
         * @return \WP_REST_Response
         */
        public static function clear_product( \WP_REST_Request $request ) {
                $product_id = isset( $request['product_id'] ) ? absint( $request['product_id'] ) : 0;

                if ( ! $product_id ) {
                        return new \WP_REST_Response( array( 'cleared' => false ), 400 );
                }

                Printful_Integration_For_Fluentcart_Product_Mapping::delete_product_mapping( $product_id );
                Printful_Integration_For_Fluentcart_Product_Mapping::set_fulfilment_mode( $product_id, '' );
                Printful_Integration_For_Fluentcart_Product_Mapping::set_product_service( $product_id, '' );
                Printful_Integration_For_Fluentcart_Product_Mapping::set_product_origin( $product_id, '' );
                Printful_Integration_For_Fluentcart_Product_Mapping::set_product_mockup( $product_id, '' );
                Printful_Integration_For_Fluentcart_Product_Mapping::set_designer_link( $product_id, '' );

                return new \WP_REST_Response(
                        array(
                                'product_id' => $product_id,
                                'cleared'    => true,
                        ),
                        200
                );
        }
}
