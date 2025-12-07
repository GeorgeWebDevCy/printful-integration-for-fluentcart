<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Schedules lightweight catalog delta refreshes.
 */
class PIFC_Delta_Sync {
    const HOOK = 'pifc_delta_sync_catalog';

    /**
     * Register cron hook.
     */
    public static function register() {
        add_action( self::HOOK, array( __CLASS__, 'run' ) );
        if ( ! wp_next_scheduled( self::HOOK ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::HOOK );
        }
    }

    /**
     * Refresh cached catalog and mappings.
     */
    public static function run() {
        if ( ! class_exists( 'Printful_Integration_For_Fluentcart_Catalog' ) ) {
            return;
        }

        $settings = Printful_Integration_For_Fluentcart_Settings::all();
        $api_key  = isset( $settings['api_key'] ) ? trim( $settings['api_key'] ) : '';
        if ( '' === $api_key ) {
            return;
        }

        $api     = new Printful_Integration_For_Fluentcart_Api( $api_key, isset( $settings['api_base'] ) ? $settings['api_base'] : 'https://api.printful.com', ! empty( $settings['log_api_calls'] ) );
        $catalog = new Printful_Integration_For_Fluentcart_Catalog( $api, $settings );
        $result  = $catalog->sync();

        if ( is_wp_error( $result ) ) {
            return;
        }

        // Basic mapping refresh: ensure variant map meta remains intact.
        $products = get_posts(
            array(
                'post_type'      => 'fluent-products',
                'posts_per_page' => 50,
                'post_status'    => 'any',
            )
        );

        foreach ( $products as $product ) {
            $map = get_post_meta( $product->ID, PIFC_Variant_Meta::META_KEY, true );
            if ( empty( $map ) ) {
                continue;
            }
            update_post_meta( $product->ID, PIFC_Variant_Meta::META_KEY, $map );
        }
    }
}
