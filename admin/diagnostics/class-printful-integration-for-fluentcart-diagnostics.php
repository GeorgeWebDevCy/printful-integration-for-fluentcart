<?php

/**
 * Helper for collecting diagnostic data for the admin dashboard.
 *
 * @package Printful_Integration_For_Fluentcart
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

class Printful_Integration_For_Fluentcart_Diagnostics {

        /**
         * Aggregate data for the diagnostics dashboard.
         *
         * @return array
         */
        public static function snapshot() {
                $settings  = Printful_Integration_For_Fluentcart_Settings::all();
                $signature = class_exists( 'Printful_Integration_For_Fluentcart_Logger' ) ? Printful_Integration_For_Fluentcart_Logger::signature_failures() : 0;
                $queue_len = class_exists( 'Printful_Integration_For_Fluentcart_Sync_Queue' ) ? count( Printful_Integration_For_Fluentcart_Sync_Queue::all() ) : 0;
                $last_error = class_exists( 'Printful_Integration_For_Fluentcart_Logger' ) ? Printful_Integration_For_Fluentcart_Logger::last_error() : null;

                return array(
                        'api_key_present'      => ! empty( $settings['api_key'] ),
                        'webhooks_enabled'     => ! empty( $settings['enable_webhooks'] ) && ! empty( $settings['webhook_secret'] ),
                        'polling_enabled'      => ! empty( $settings['enable_polling'] ),
                        'queue_length'         => $queue_len,
                        'signature_failures'   => $signature,
                        'last_error'           => $last_error,
                        'request_logging'      => ! empty( $settings['enable_request_logging'] ),
                        'log_api_calls'        => ! empty( $settings['log_api_calls'] ),
                        'recent_errors'        => self::recent_errors(),
                        'request_log_entries'  => class_exists( 'Printful_Integration_For_Fluentcart_Request_Log' ) ? Printful_Integration_For_Fluentcart_Request_Log::recent( 5 ) : array(),
                );
        }

        /**
         * Get a small slice of recent error-level log entries.
         *
         * @param int $limit Number of entries.
         *
         * @return array
         */
        public static function recent_errors( $limit = 5 ) {
                if ( ! class_exists( 'Printful_Integration_For_Fluentcart_Logger' ) ) {
                        return array();
                }

                return array_slice( Printful_Integration_For_Fluentcart_Logger::filter( 'error' ), 0, $limit );
        }
}

