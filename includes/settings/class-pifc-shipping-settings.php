<?php

/**
 * Structured storage for shipping-related settings (services/origins).
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

class PIFC_Shipping_Settings {

        const OPTION_KEY = 'pifc_shipping_services';

        /**
         * Retrieve full settings payload.
         *
         * @return array
         */
        public static function all() {
                $defaults = array(
                        'carriers'        => array(),
                        'services'        => array(),
                        'enabled'         => array(
                                'carriers' => array(),
                                'services' => array(),
                        ),
                        'origins'         => array(),
                        'fallback_table'  => array(),
                        'last_refreshed'  => 0,
                );

                $stored = get_option( self::OPTION_KEY, array() );

                return wp_parse_args( is_array( $stored ) ? $stored : array(), $defaults );
        }

        /**
         * Persist settings.
         *
         * @param array $payload Payload to merge.
         *
         * @return void
         */
        public static function update( array $payload ) {
                $current = self::all();
                update_option( self::OPTION_KEY, array_merge( $current, $payload ) );
        }

        /**
         * Save carrier/service definitions fetched from Printful.
         *
         * @param array $carriers Carrier map.
         * @param array $services Service map.
         *
         * @return void
         */
        public static function cache_services( array $carriers, array $services ) {
                self::update(
                        array(
                                'carriers'       => $carriers,
                                'services'       => $services,
                                'last_refreshed' => time(),
                        )
                );
        }

        /**
         * Toggle enabled carriers/services.
         *
         * @param array $enabled Enabled payload.
         *
         * @return void
         */
        public static function set_enabled( array $enabled ) {
                $current          = self::all();
                $current['enabled'] = array(
                        'carriers' => isset( $enabled['carriers'] ) ? array_values( array_unique( array_filter( $enabled['carriers'] ) ) ) : array(),
                        'services' => isset( $enabled['services'] ) ? array_values( array_unique( array_filter( $enabled['services'] ) ) ) : array(),
                );

                update_option( self::OPTION_KEY, $current );
        }

        /**
         * Persist origin profiles.
         *
         * @param array $origins Origins data.
         *
         * @return void
         */
        public static function set_origins( array $origins ) {
                $current           = self::all();
                $current['origins'] = $origins;
                update_option( self::OPTION_KEY, $current );
        }

        /**
         * Persist fallback table rows.
         *
         * @param array $rows Rows.
         *
         * @return void
         */
        public static function set_fallback_table( array $rows ) {
                $current                = self::all();
                $current['fallback_table'] = $rows;
                update_option( self::OPTION_KEY, $current );
        }
}
