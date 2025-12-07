<?php
/**
 * Admin tool screen for token migration.
 *
 * @package Printful_Integration_For_Fluentcart
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

class PIFC_Token_Migration_Tool {

        /**
         * Render the migration tool page.
         *
         * @return void
         */
        public static function render_page() {
                $settings        = Printful_Integration_For_Fluentcart_Settings::all();
                $last_migration  = isset( $settings['last_migration'] ) ? $settings['last_migration'] : array();
                $status          = isset( $_GET['migration_status'] ) ? sanitize_text_field( wp_unslash( $_GET['migration_status'] ) ) : 'idle'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $found           = isset( $_GET['migration_found'] ) ? (int) $_GET['migration_found'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $source          = isset( $_GET['migration_source'] ) ? sanitize_text_field( wp_unslash( $_GET['migration_source'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $steps           = self::build_steps( $status, $found, $source, $last_migration );
                ?>
                <div class="wrap">
                        <h1><?php esc_html_e( 'Printful Tools', 'printful-integration-for-fluentcart' ); ?></h1>
                        <?php settings_errors( 'printful_fluentcart' ); ?>
                        <p><?php esc_html_e( 'Use this utility to migrate legacy Printful tokens into the FluentCart settings store. Run a dry run first to verify discovery.', 'printful-integration-for-fluentcart' ); ?></p>
                        <style>
                                .printful-migration-steps {list-style:decimal; margin-left:20px;}
                                .printful-migration-steps li {margin:8px 0; padding-left:4px;}
                                .printful-migration-steps li.complete strong {color:#0073aa;}
                                .printful-migration-steps li.skipped strong {color:#999;}
                        </style>

                        <h2><?php esc_html_e( 'Migration progress', 'printful-integration-for-fluentcart' ); ?></h2>
                        <ol class="printful-migration-steps">
                                <?php foreach ( $steps as $step ) : ?>
                                        <li class="<?php echo esc_attr( $step['state'] ); ?>">
                                                <strong><?php echo esc_html( $step['label'] ); ?></strong>
                                                <?php if ( ! empty( $step['detail'] ) ) : ?>
                                                        <br /><span class="description"><?php echo esc_html( $step['detail'] ); ?></span>
                                                <?php endif; ?>
                                        </li>
                                <?php endforeach; ?>
                        </ol>

                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:20px;">
                                <?php wp_nonce_field( 'printful_fluentcart_migrate_tokens' ); ?>
                                <input type="hidden" name="action" value="printful_fluentcart_migrate_tokens" />
                                <p>
                                        <label>
                                                <input type="checkbox" name="printful_migrate_dry_run" value="1" />
                                                <?php esc_html_e( 'Dry run (detect only)', 'printful-integration-for-fluentcart' ); ?>
                                        </label>
                                </p>
                                <p>
                                        <button type="submit" class="button button-primary"><?php esc_html_e( 'Run migration', 'printful-integration-for-fluentcart' ); ?></button>
                                </p>
                        </form>

                        <?php if ( ! empty( $last_migration ) ) : ?>
                                <h3><?php esc_html_e( 'Last migration', 'printful-integration-for-fluentcart' ); ?></h3>
                                <p>
                                        <?php esc_html_e( 'Source:', 'printful-integration-for-fluentcart' ); ?>
                                        <strong><?php echo isset( $last_migration['source'] ) ? esc_html( $last_migration['source'] ) : '—'; ?></strong><br />
                                        <?php esc_html_e( 'Timestamp:', 'printful-integration-for-fluentcart' ); ?>
                                        <strong><?php echo isset( $last_migration['time'] ) ? esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $last_migration['time'] ) ) : '—'; ?></strong>
                                </p>
                        <?php endif; ?>
                </div>
                <?php
        }

        /**
         * Build progression data.
         *
         * @param string $status         Current status slug.
         * @param int    $found          Number of tokens discovered.
         * @param string $source         Source identifier.
         * @param array  $last_migration Last migration settings entry.
         *
         * @return array
         */
        protected static function build_steps( $status, $found, $source, $last_migration ) {
                $steps = array(
                        array(
                                'label' => __( 'Scan legacy keys', 'printful-integration-for-fluentcart' ),
                                'state' => ( 'completed' === $status || 'skipped' === $status || 'checking' === $status ) ? 'complete' : 'pending',
                                'detail' => $found ? sprintf( __( '%d potential token(s) detected.', 'printful-integration-for-fluentcart' ), $found ) : __( 'Awaiting scan', 'printful-integration-for-fluentcart' ),
                        ),
                        array(
                                'label' => __( 'Write to new storage', 'printful-integration-for-fluentcart' ),
                                'state' => ( 'completed' === $status ) ? 'complete' : ( 'skipped' === $status ? 'skipped' : 'pending' ),
                                'detail' => $source ? sprintf( __( 'Latest source: %s', 'printful-integration-for-fluentcart' ), $source ) : '',
                        ),
                        array(
                                'label' => __( 'Record migration metadata', 'printful-integration-for-fluentcart' ),
                                'state' => ! empty( $last_migration ) ? 'complete' : 'pending',
                                'detail' => ! empty( $last_migration['time'] ) ? __( 'Last run stored in settings.', 'printful-integration-for-fluentcart' ) : '',
                        ),
                );

                return $steps;
        }
}
