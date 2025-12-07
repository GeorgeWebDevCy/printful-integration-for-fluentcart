<?php

/**
 * Simple asset cache for pulling remote templates into uploads.
 *
 * @package Printful_Integration_For_Fluentcart
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

class Printful_Integration_For_Fluentcart_Asset_Cache {

        const CACHE_FOLDER = 'pifc';

        /**
         * Download and cache a remote asset locally under uploads.
         *
         * @param string $url Remote URL.
         *
         * @return string Local URL or empty string.
         */
        public static function cache( $url ) {
                if ( ! $url || ! is_string( $url ) ) {
                        return '';
                }

                $upload_dir = wp_upload_dir();
                if ( ! empty( $upload_dir['error'] ) ) {
                        return '';
                }

                $folder     = trailingslashit( $upload_dir['basedir'] ) . self::CACHE_FOLDER;
                $public_url = trailingslashit( $upload_dir['baseurl'] ) . self::CACHE_FOLDER;

                if ( ! file_exists( $folder ) ) {
                        wp_mkdir_p( $folder );
                }

                $extension = pathinfo( parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION );
                $filename  = md5( $url ) . ( $extension ? '.' . $extension : '' );
                $path      = trailingslashit( $folder ) . $filename;

                if ( ! file_exists( $path ) || ( time() - filemtime( $path ) ) > WEEK_IN_SECONDS ) {
                        $response = wp_remote_get( esc_url_raw( $url ) );
                        if ( is_wp_error( $response ) ) {
                                return '';
                        }

                        $body = wp_remote_retrieve_body( $response );
                        if ( '' === $body ) {
                                return '';
                        }

                        file_put_contents( $path, $body ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
                }

                self::cleanup();

                return trailingslashit( $public_url ) . $filename;
        }

        /**
         * Remove assets older than 30 days.
         *
         * @return void
         */
        public static function cleanup() {
                $upload_dir = wp_upload_dir();
                if ( ! empty( $upload_dir['error'] ) ) {
                        return;
                }

                $folder = trailingslashit( $upload_dir['basedir'] ) . self::CACHE_FOLDER;
                if ( ! file_exists( $folder ) ) {
                        return;
                }

                $files = glob( $folder . '/*' );
                if ( ! $files ) {
                        return;
                }

                $threshold = time() - ( 30 * DAY_IN_SECONDS );

                foreach ( $files as $file ) {
                        if ( filemtime( $file ) < $threshold ) {
                                @unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
                        }
                }
        }
}
