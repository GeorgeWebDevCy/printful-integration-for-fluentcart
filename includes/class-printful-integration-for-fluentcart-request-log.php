<?php

/**
 * File-based request/response logger for Printful API calls.
 *
 * @package Printful_Integration_For_Fluentcart
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

class Printful_Integration_For_Fluentcart_Request_Log {

        /**
         * Determine whether verbose request logging is enabled in settings.
         *
         * @return bool
         */
        public static function is_enabled() {
                $settings = Printful_Integration_For_Fluentcart_Settings::all();

                return ! empty( $settings['enable_request_logging'] );
        }

        /**
         * Persist a request/response pair to the filesystem.
         *
         * @param array $entry Structured log entry.
         *
         * @return void
         */
        public static function store( array $entry ) {
                if ( ! self::is_enabled() ) {
                        return;
                }

                $path = self::get_storage_path();

                if ( ! $path ) {
                        return;
                }

                $entry['time'] = time();

                // Keep the file approachable by using JSON lines.
                $line = wp_json_encode( $entry );
                if ( ! $line ) {
                        return;
                }

                file_put_contents( $path, $line . PHP_EOL, FILE_APPEND );
        }

        /**
         * Retrieve the most recent log entries from disk.
         *
         * @param int $limit Maximum entries to read.
         *
         * @return array
         */
        public static function recent( $limit = 20 ) {
                $path = self::get_storage_path();

                if ( ! $path || ! file_exists( $path ) ) {
                        return array();
                }

                $lines = file( $path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
                if ( ! is_array( $lines ) ) {
                        return array();
                }

                $lines = array_slice( array_reverse( $lines ), 0, max( 1, (int) $limit ) );

                return array_values( array_filter( array_map( function( $line ) {
                        $decoded = json_decode( $line, true );
                        return is_array( $decoded ) ? $decoded : null;
                }, $lines ) ) );
        }

        /**
         * Build/return the storage path within uploads.
         *
         * @return string|null
         */
        protected static function get_storage_path() {
                $upload_dir = wp_upload_dir();

                if ( empty( $upload_dir['basedir'] ) ) {
                        return null;
                }

                $dir = trailingslashit( $upload_dir['basedir'] ) . 'printful-fluentcart-logs';

                if ( ! file_exists( $dir ) ) {
                        wp_mkdir_p( $dir );
                }

                if ( ! is_dir( $dir ) || ! is_writable( $dir ) ) {
                        return null;
                }

                return trailingslashit( $dir ) . 'requests.log';
        }
}

