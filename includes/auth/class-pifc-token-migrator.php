<?php
/**
 * Token migration helper to move legacy Printful keys into the FluentCart store.
 *
 * @package Printful_Integration_For_Fluentcart
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

class PIFC_Token_Migrator {

        /**
         * Legacy key sources to inspect.
         *
         * @var array
         */
        protected $legacy_keys = array(
                'printful_api_key',
                'printful_shipping_api_key',
                'woocommerce_printful_settings_api_key',
                'env:PRINTFUL_API_KEY',
        );

        /**
         * Current settings snapshot.
         *
         * @var array
         */
        protected $settings = array();

        /**
         * Constructor.
         *
         * @param array $settings Settings array.
         */
        public function __construct( array $settings = array() ) {
                $this->settings = $settings ? $settings : Printful_Integration_For_Fluentcart_Settings::all();
        }

        /**
         * Discover legacy sources with tokens.
         *
         * @return array
         */
        public function discover() {
                $found = array();

                foreach ( $this->legacy_keys as $key ) {
                        $legacy = $this->read_source( $key );
                        if ( $legacy ) {
                                $found[] = array(
                                        'source' => $key,
                                        'token'  => $legacy,
                                );
                        }
                }

                return $found;
        }

        /**
         * Run migration.
         *
         * @param bool $dry_run Whether to skip persistence.
         * @return array Migration report.
         */
        public function migrate( $dry_run = false ) {
                $discovered = $this->discover();

                $report = array(
                        'found'     => count( $discovered ),
                        'migrated'  => false,
                        'dry_run'   => (bool) $dry_run,
                        'source'    => null,
                        'stored_in' => 'printful_fluentcart_settings.api_key',
                );

                if ( empty( $discovered ) ) {
                        $report['message'] = __( 'No legacy tokens found to migrate.', 'printful-integration-for-fluentcart' );
                        return $report;
                }

                $primary       = $discovered[0];
                $report['source'] = $primary['source'];
                $report['message'] = sprintf( /* translators: %s legacy key */ __( 'Found legacy Printful token in %s.', 'printful-integration-for-fluentcart' ), $primary['source'] );

                if ( $dry_run ) {
                        return $report;
                }

                Printful_Integration_For_Fluentcart_Settings::set( 'api_key', $primary['token'] );
                Printful_Integration_For_Fluentcart_Settings::set(
                        'last_migration',
                        array(
                                'source' => $primary['source'],
                                'time'   => time(),
                        )
                );

                if ( class_exists( 'Printful_Integration_For_Fluentcart_Logger' ) ) {
                        Printful_Integration_For_Fluentcart_Logger::log( 'info', 'token_migration', array( 'source' => $primary['source'] ) );
                }

                $report['migrated'] = true;
                $report['message']  = sprintf( /* translators: %s legacy key */ __( 'Migrated legacy Printful token from %s.', 'printful-integration-for-fluentcart' ), $primary['source'] );

                return $report;
        }

        /**
         * Read legacy source value.
         *
         * @param string $key Key identifier.
         * @return string|null
         */
        protected function read_source( $key ) {
                if ( strpos( $key, 'env:' ) === 0 ) {
                        $env = substr( $key, 4 );
                        return getenv( $env ) ? getenv( $env ) : null;
                }

                $value = get_option( $key );
                return $value ? $value : null;
        }
}
