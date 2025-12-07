<?php

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Alert helper for webhook signature failures.
 */
class Printful_Integration_For_Fluentcart_Webhook_Alerts {

        const OPTION_NOTICE   = 'pifc_webhook_signature_notice';
        const OPTION_LASTMAIL = 'pifc_webhook_signature_lastmail';

        /**
         * Hook admin notices.
         *
         * @return void
         */
        public static function register() {
                add_action( 'admin_notices', array( __CLASS__, 'render_admin_notice' ) );
                add_action( 'network_admin_notices', array( __CLASS__, 'render_admin_notice' ) );
        }

        /**
         * Render admin notice when a signature failure is recorded.
         *
         * @return void
         */
        public static function render_admin_notice() {
                $notice = get_option( self::OPTION_NOTICE, array() );

                if ( empty( $notice ) || ! is_array( $notice ) ) {
                        return;
                }

                $last = isset( $notice['time'] ) ? (int) $notice['time'] : 0;
                $when = $last ? sprintf( __( 'Most recent failure at %s.', 'printful-integration-for-fluentcart' ), date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last ) ) : '';
                $message = isset( $notice['message'] ) ? $notice['message'] : __( 'Printful webhook signature failed validation.', 'printful-integration-for-fluentcart' );

                printf(
                        '<div class="notice notice-error"><p>%s</p><p>%s</p></div>',
                        esc_html( $message ),
                        esc_html( $when )
                );
        }

        /**
         * Record a signature failure and trigger alerts.
         *
         * @param \WP_REST_Request|null $request Request instance.
         *
         * @return void
         */
        public static function record_failure( $request = null ) {
                $payload = array(
                        'time'    => time(),
                        'route'   => $request && method_exists( $request, 'get_route' ) ? $request->get_route() : '',
                        'message' => __( 'Printful webhook signature failed validation.', 'printful-integration-for-fluentcart' ),
                );

                update_option( self::OPTION_NOTICE, $payload );
                self::maybe_email_admin( $payload );
        }

        /**
         * Send an email alert to the site administrator.
         *
         * @param array $payload Details about the failure.
         *
         * @return void
         */
        protected static function maybe_email_admin( array $payload ) {
                $last_sent = (int) get_option( self::OPTION_LASTMAIL, 0 );
                if ( $last_sent && ( time() - $last_sent ) < HOUR_IN_SECONDS ) {
                        return;
                }

                $admin_email = get_option( 'admin_email' );
                if ( ! $admin_email ) {
                        return;
                }

                $subject = __( 'Printful webhook signature failed', 'printful-integration-for-fluentcart' );
                $body    = sprintf(
                        "%s\n\n%s",
                        __( 'Printful webhook signature validation failed for a recent request. Verify the configured secret matches Printful dashboard settings.', 'printful-integration-for-fluentcart' ),
                        isset( $payload['route'] ) && $payload['route'] ? sprintf( __( 'Route: %s', 'printful-integration-for-fluentcart' ), $payload['route'] ) : ''
                );

                wp_mail( $admin_email, $subject, trim( $body ) );
                update_option( self::OPTION_LASTMAIL, time() );
        }
}
