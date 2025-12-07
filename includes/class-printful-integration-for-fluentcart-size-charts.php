<?php

/**
 * Server-side renderer for Printful size charts.
 *
 * @package Printful_Integration_For_Fluentcart
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

class Printful_Integration_For_Fluentcart_Size_Charts {

        /**
         * Register shortcode, block, and hooks.
         *
         * @return void
         */
        public static function register() {
                add_shortcode( 'pifc_size_chart', array( __CLASS__, 'render_shortcode' ) );
                add_action( 'init', array( __CLASS__, 'register_block' ) );
                add_filter( 'the_content', array( __CLASS__, 'maybe_append_to_product' ) );
        }

        /**
         * Render via shortcode.
         *
         * @param array $atts Attributes.
         *
         * @return string
         */
        public static function render_shortcode( $atts ) {
                $atts = shortcode_atts(
                        array(
                                'product_id'           => get_the_ID(),
                                'printful_product_id'  => '',
                                'template_product_id'  => '',
                        ),
                        $atts,
                        'pifc_size_chart'
                );

                return self::render_chart(
                        (int) $atts['product_id'],
                        $atts['printful_product_id'],
                        $atts['template_product_id']
                );
        }

        /**
         * Register a simple dynamic block that reuses the shortcode callback.
         *
         * @return void
         */
        public static function register_block() {
                if ( ! function_exists( 'register_block_type' ) ) {
                        return;
                }

                wp_register_script(
                        'pifc-size-chart-block',
                        plugins_url( 'public/blocks/size-chart.js', dirname( __DIR__ ) . '/printful-integration-for-fluentcart.php' ),
                        array( 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n' ),
                        PRINTFUL_INTEGRATION_FOR_FLUENTCART_VERSION,
                        true
                );

                register_block_type(
                        'pifc/size-chart',
                        array(
                                'editor_script'   => 'pifc-size-chart-block',
                                'render_callback' => function( $attributes ) {
                                        $product_id          = isset( $attributes['productId'] ) ? (int) $attributes['productId'] : get_the_ID();
                                        $printful_product_id = isset( $attributes['printfulProductId'] ) ? $attributes['printfulProductId'] : '';
                                        $template_product_id = isset( $attributes['templateProductId'] ) ? $attributes['templateProductId'] : '';

                                        return self::render_chart( $product_id, $printful_product_id, $template_product_id );
                                },
                        )
                );
        }

        /**
         * Append size chart to FluentCart product content if enabled.
         *
         * @param string $content Post content.
         *
         * @return string
         */
        public static function maybe_append_to_product( $content ) {
                if ( ! is_singular( 'fluent-products' ) ) {
                        return $content;
                }

                $settings = Printful_Integration_For_Fluentcart_Settings::all();

                if ( empty( $settings['enable_size_guides'] ) || empty( $settings['enable_size_tab'] ) ) {
                        return $content;
                }

                $chart = self::render_chart( get_the_ID(), '', '' );

                if ( '' === $chart ) {
                        return $content;
                }

                $tab_markup  = '<div class="pifc-size-chart-tab">';
                $tab_markup .= '<h2>' . esc_html__( 'Size guide', 'printful-integration-for-fluentcart' ) . '</h2>';
                $tab_markup .= $chart;
                $tab_markup .= '</div>';

                return $content . $tab_markup;
        }

        /**
         * Render the size chart markup for a product.
         *
         * @param int    $product_id FluentCart product ID.
         * @param string $printful_product_id Optional Printful product id.
         * @param string $template_product_id Optional Printful template product id.
         *
         * @return string
         */
        public static function render_chart( $product_id, $printful_product_id = '', $template_product_id = '' ) {
                $product_id = (int) $product_id;

                $settings = Printful_Integration_For_Fluentcart_Settings::all();

                if ( empty( $settings['enable_size_guides'] ) ) {
                        return '';
                }

                if ( ! $printful_product_id && $product_id ) {
                        $printful_product_id = Printful_Integration_For_Fluentcart_Product_Mapping::get_product_mapping( $product_id );
                }

                if ( ! $template_product_id && $printful_product_id ) {
                        $template_product_id = self::maybe_pick_template( $printful_product_id, $settings );
                }

                $chart_data = self::get_chart_data( $template_product_id ? $template_product_id : $printful_product_id );
                if ( empty( $chart_data ) ) {
                        return '';
                }

                $output  = '<div class="pifc-size-chart">';
                $output .= '<table><thead><tr>';
                foreach ( $chart_data['headers'] as $header ) {
                        $output .= '<th>' . esc_html( $header ) . '</th>';
                }
                $output .= '</tr></thead><tbody>';

                foreach ( $chart_data['rows'] as $row ) {
                        $output .= '<tr>';
                        foreach ( $row as $cell ) {
                                $output .= '<td>' . esc_html( $cell ) . '</td>';
                        }
                        $output .= '</tr>';
                }

                $output .= '</tbody></table>';

                if ( ! empty( $chart_data['asset'] ) ) {
                        $output .= '<p class="pifc-size-chart-download"><a href="' . esc_url( $chart_data['asset'] ) . '" target="_blank" rel="noreferrer">' . esc_html__( 'Download template', 'printful-integration-for-fluentcart' ) . '</a></p>';
                }

                $output .= '</div>';

                return $output;
        }

        /**
         * Attempt to pick a default template mapping using settings.
         *
         * @param string $printful_product_id Printful product id.
         * @param array  $settings Settings array.
         *
         * @return string
         */
        protected static function maybe_pick_template( $printful_product_id, array $settings ) {
                if ( empty( $settings['size_template_map'] ) || ! is_array( $settings['size_template_map'] ) ) {
                        return '';
                }

                $catalog = new PIFC_Printful_Catalog();
                $product = $catalog->get_product( $printful_product_id );

                $product_type = isset( $product['type'] ) ? $product['type'] : ( isset( $product['name'] ) ? $product['name'] : '' );

                foreach ( $settings['size_template_map'] as $mapping ) {
                        if ( empty( $mapping['type'] ) || empty( $mapping['template'] ) ) {
                                continue;
                        }

                        if ( strtolower( $mapping['type'] ) === strtolower( $product_type ) ) {
                                return $mapping['template'];
                        }
                }

                return '';
        }

        /**
         * Build chart data from the cached catalog.
         *
         * @param string $printful_product_id Printful product id used for template.
         *
         * @return array
         */
        protected static function get_chart_data( $printful_product_id ) {
                if ( ! $printful_product_id ) {
                        return array();
                }

                $catalog = new PIFC_Printful_Catalog();
                $product = $catalog->get_product( $printful_product_id );

                if ( ! $product ) {
                        return array();
                }

                $headers = array( __( 'Size', 'printful-integration-for-fluentcart' ) );
                $rows    = array();

                if ( isset( $product['size_tables'] ) && is_array( $product['size_tables'] ) ) {
                        $table = reset( $product['size_tables'] );
                        if ( isset( $table['headers'] ) ) {
                                $headers = $table['headers'];
                        }
                        if ( isset( $table['rows'] ) ) {
                                $rows = $table['rows'];
                        }
                }

                if ( empty( $rows ) && isset( $product['variants'] ) && is_array( $product['variants'] ) ) {
                        foreach ( $product['variants'] as $variant ) {
                                $size_label = isset( $variant['size'] ) ? $variant['size'] : ( isset( $variant['name'] ) ? $variant['name'] : '' );
                                if ( '' === $size_label ) {
                                        continue;
                                }
                                $rows[] = array( $size_label );
                        }
                }

                $asset = '';

                if ( isset( $product['size_guide'] ) && is_array( $product['size_guide'] ) ) {
                        $asset_url = isset( $product['size_guide']['url'] ) ? $product['size_guide']['url'] : '';
                        $asset     = Printful_Integration_For_Fluentcart_Asset_Cache::cache( $asset_url );
                }

                return array(
                        'headers' => $headers,
                        'rows'    => $rows,
                        'asset'   => $asset,
                );
        }
}
