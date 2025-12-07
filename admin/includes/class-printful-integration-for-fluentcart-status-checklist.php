<?php

/**
 * Status checklist renderer and admin notices.
 *
 * @package Printful_Integration_For_Fluentcart
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

class Printful_Integration_For_Fluentcart_Status_Checklist {

        /**
         * Hook admin notices.
         *
         * @return void
         */
        public static function register() {
                add_action( 'admin_notices', array( __CLASS__, 'render_notices' ) );
        }

        /**
         * Provide checklist items similar to WooCommerce status page.
         *
         * @return array
         */
        public static function items() {
                $settings   = Printful_Integration_For_Fluentcart_Settings::all();
                $queue_len  = class_exists( 'Printful_Integration_For_Fluentcart_Sync_Queue' ) ? count( Printful_Integration_For_Fluentcart_Sync_Queue::all() ) : 0;
                $signature  = class_exists( 'Printful_Integration_For_Fluentcart_Logger' ) ? Printful_Integration_For_Fluentcart_Logger::signature_failures() : 0;

                return array(
                        array(
                                'id'      => 'api_key',
                                'label'   => __( 'API key configured', 'printful-integration-for-fluentcart' ),
                                'ok'      => ! empty( $settings['api_key'] ),
                                'message' => __( 'Add your Printful API key to enable communication.', 'printful-integration-for-fluentcart' ),
                        ),
                        array(
                                'id'      => 'webhooks',
                                'label'   => __( 'Webhooks enabled with secret', 'printful-integration-for-fluentcart' ),
                                'ok'      => ! empty( $settings['enable_webhooks'] ) && ! empty( $settings['webhook_secret'] ),
                                'message' => __( 'Enable Printful webhooks and set the shared secret so fulfilment events arrive.', 'printful-integration-for-fluentcart' ),
                        ),
                        array(
                                'id'      => 'polling',
                                'label'   => __( 'Background polling configured', 'printful-integration-for-fluentcart' ),
                                'ok'      => ! empty( $settings['enable_polling'] ),
                                'message' => __( 'Turn on polling to backstop webhook delivery.', 'printful-integration-for-fluentcart' ),
                        ),
                        array(
                                'id'      => 'queue_depth',
                                'label'   => __( 'Queue depth healthy', 'printful-integration-for-fluentcart' ),
                                'ok'      => $queue_len < 50,
                                'message' => __( 'High queue depth detected – review webhook delivery and API credentials.', 'printful-integration-for-fluentcart' ),
                        ),
                        array(
                                'id'      => 'signature_failures',
                                'label'   => __( 'Webhook signatures valid', 'printful-integration-for-fluentcart' ),
                                'ok'      => 0 === $signature,
                                'message' => __( 'Webhook signature mismatches detected. Verify the configured secret.', 'printful-integration-for-fluentcart' ),
                        ),
                        array(
                                'id'      => 'request_logging',
                                'label'   => __( 'Request logging disabled in production', 'printful-integration-for-fluentcart' ),
                                'ok'      => empty( $settings['enable_request_logging'] ),
                                'message' => __( 'Disable verbose HTTP body logging when not actively debugging.', 'printful-integration-for-fluentcart' ),
                        ),
                );
        }

        /**
         * Render admin notices for failing checklist items.
         *
         * @return void
         */
        public static function render_notices() {
                if ( ! current_user_can( 'manage_options' ) ) {
                        return;
                }

                foreach ( self::items() as $item ) {
                        if ( ! $item['ok'] ) {
                                printf(
                                        '<div class="notice notice-warning"><p>%s</p></div>',
                                        esc_html( $item['message'] )
                                );
                        }
                }
        }
}

