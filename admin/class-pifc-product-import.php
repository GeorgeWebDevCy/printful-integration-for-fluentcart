<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Basic catalog browser under the admin menu.
 */
class PIFC_Product_Import_Screen {
    /**
     * Hook admin menu and assets.
     */
    public static function register() {
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
    }

    /**
     * Register submenu under FluentCart.
     */
    public static function menu() {
        add_submenu_page(
            'fluentcart',
            __( 'Printful Catalog', 'printful-integration-for-fluentcart' ),
            __( 'Printful Catalog', 'printful-integration-for-fluentcart' ),
            'manage_options',
            'pifc-catalog',
            array( __CLASS__, 'render' )
        );
    }

    /**
     * Render catalog table using cached catalog.
     */
    public static function render() {
        $catalog = new PIFC_Printful_Catalog();
        $products = $catalog->get_products();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Printful Catalog', 'printful-integration-for-fluentcart' ); ?></h1>
            <p><?php esc_html_e( 'Browse cached Printful products and preview variants.', 'printful-integration-for-fluentcart' ); ?></p>
            <table class="widefat striped">
                <thead>
                <tr>
                    <th><?php esc_html_e( 'Product', 'printful-integration-for-fluentcart' ); ?></th>
                    <th><?php esc_html_e( 'Variants', 'printful-integration-for-fluentcart' ); ?></th>
                    <th><?php esc_html_e( 'Preview', 'printful-integration-for-fluentcart' ); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ( $products as $product ) : ?>
                    <tr>
                        <td>
                            <?php if ( ! empty( $product['thumbnail'] ) ) : ?>
                                <img src="<?php echo esc_url( $product['thumbnail'] ); ?>" style="width:48px;height:48px;object-fit:cover;margin-right:8px;vertical-align:middle;" />
                            <?php endif; ?>
                            <strong><?php echo esc_html( isset( $product['name'] ) ? $product['name'] : '' ); ?></strong>
                            <div class="description"><?php echo esc_html( sprintf( __( 'Printful ID: %d', 'printful-integration-for-fluentcart' ), isset( $product['id'] ) ? $product['id'] : 0 ) ); ?></div>
                        </td>
                        <td><?php echo isset( $product['variants'] ) ? esc_html( count( $product['variants'] ) ) : 0; ?></td>
                        <td>
                            <?php if ( ! empty( $product['variants'] ) ) : ?>
                                <details>
                                    <summary><?php esc_html_e( 'View variants', 'printful-integration-for-fluentcart' ); ?></summary>
                                    <ul>
                                        <?php foreach ( $product['variants'] as $variant ) : ?>
                                            <li>
                                                <?php echo esc_html( sprintf( '#%s - %s %s', isset( $variant['id'] ) ? $variant['id'] : '', isset( $variant['size'] ) ? $variant['size'] : '', isset( $variant['color'] ) ? $variant['color'] : '' ) ); ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </details>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
