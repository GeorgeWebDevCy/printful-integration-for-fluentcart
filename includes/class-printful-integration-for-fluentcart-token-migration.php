<?php
/**
 * Placeholder for future token migration helpers.
 *
 * @package Printful_Integration_For_Fluentcart
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

class Printful_Integration_For_Fluentcart_Token_Migration {

        /**
         * Register hooks.
         *
         * @return void
         */
        public static function register() {
                add_action( 'admin_post_printful_fluentcart_migrate_tokens', array( __CLASS__, 'run_migration' ) );

                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                        \WP_CLI::add_command( 'printful-fluentcart migrate-tokens', array( __CLASS__, 'cli_migrate' ) );
                }
        }

        /**
         * Attempt to migrate legacy Printful tokens.
         *
         * @return void
         */
        public static function run_migration() {
                if ( ! current_user_can( 'manage_options' ) ) {
                        wp_die( esc_html__( 'Permission denied.', 'printful-integration-for-fluentcart' ) );
                }

                check_admin_referer( 'printful_fluentcart_migrate_tokens' );

                $dry_run  = ! empty( $_POST['printful_migrate_dry_run'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
                $report   = self::migrate( $dry_run );
                $message  = isset( $report['message'] ) ? $report['message'] : '';
                $type     = ! empty( $report['migrated'] ) ? 'updated' : 'info';

                add_settings_error( 'printful_fluentcart', 'printful_fluentcart_migration', $message, $type );

                $redirect = add_query_arg(
                        array(
                                'page'             => 'printful-fluentcart-tools',
                                'migration_source' => $report['source'],
                                'migration_found'  => $report['found'],
                                'migration_status' => $report['migrated'] ? 'completed' : 'skipped',
                                'settings-updated' => 'true',
                        ),
                        admin_url( class_exists( '\\FluentCart\\App\\App' ) ? 'admin.php' : 'options-general.php' )
                );

                wp_safe_redirect( $redirect );
                exit;
        }

        /**
         * Runable migration for REST/CLI usage.
         *
         * @param bool $dry_run Whether this is a dry-run migration.
         * @return array
         */
        public static function migrate( $dry_run = false ) {
                $migrator = new PIFC_Token_Migrator();
                return $migrator->migrate( $dry_run );
        }

        /**
         * WP-CLI entry point for token migration.
         *
         * @param array $args       Positional args.
         * @param array $assoc_args Keyed args.
         *
         * @return void
         */
        public static function cli_migrate( $args, $assoc_args ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
                $dry_run = ! empty( $assoc_args['dry-run'] );
                $report  = self::migrate( $dry_run );

                if ( class_exists( '\\WP_CLI' ) ) {
                        \WP_CLI::log( $report['message'] );
                        if ( ! empty( $report['migrated'] ) ) {
                                \WP_CLI::success( 'Token migration completed.' );
                        } else {
                                \WP_CLI::warning( 'No tokens migrated.' );
                        }
                }
        }
}
