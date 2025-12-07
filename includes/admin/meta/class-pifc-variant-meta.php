<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Stores option-level variant mapping on FluentCart products.
 */
class PIFC_Variant_Meta {
    const META_KEY = '_pifc_variant_map';

    /**
     * Register hooks.
     */
    public static function register() {
        add_action( 'add_meta_boxes', array( __CLASS__, 'register_box' ) );
        add_action( 'save_post', array( __CLASS__, 'save' ) );
    }

    /**
     * Add metabox to fluent products.
     */
    public static function register_box() {
        add_meta_box(
            'pifc_variant_map',
            __( 'Printful Variant Map', 'printful-integration-for-fluentcart' ),
            array( __CLASS__, 'render' ),
            'fluent-products',
            'side',
            'default'
        );
    }

    /**
     * Render mapping field.
     *
     * @param WP_Post $post Post instance.
     */
    public static function render( $post ) {
        wp_nonce_field( 'pifc_variant_map', 'pifc_variant_map_nonce' );
        $raw = get_post_meta( $post->ID, self::META_KEY, true );
        $json = $raw ? wp_json_encode( $raw, JSON_PRETTY_PRINT ) : '';
        echo '<p class="description">' . esc_html__( 'Keyed by Printful variant id => FluentCart variant id.', 'printful-integration-for-fluentcart' ) . '</p>';
        echo '<textarea style="width:100%;min-height:120px;" name="pifc_variant_map_json">' . esc_textarea( $json ) . '</textarea>';
    }

    /**
     * Persist mapping field.
     *
     * @param int $post_id Post id.
     */
    public static function save( $post_id ) {
        if ( ! isset( $_POST['pifc_variant_map_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pifc_variant_map_nonce'] ) ), 'pifc_variant_map' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $json = isset( $_POST['pifc_variant_map_json'] ) ? wp_unslash( $_POST['pifc_variant_map_json'] ) : '';
        if ( '' === $json ) {
            delete_post_meta( $post_id, self::META_KEY );
            return;
        }

        $decoded = json_decode( $json, true );
        if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $decoded ) ) {
            return;
        }

        update_post_meta( $post_id, self::META_KEY, $decoded );
    }
}
