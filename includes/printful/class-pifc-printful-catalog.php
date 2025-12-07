<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Lightweight wrapper around the existing catalog cache helpers.
 */
class PIFC_Printful_Catalog {
    /**
     * Retrieve cached catalog products.
     *
     * @return array
     */
    public function get_products() {
        if ( class_exists( 'Printful_Integration_For_Fluentcart_Catalog' ) ) {
            $catalog = Printful_Integration_For_Fluentcart_Catalog::cached();
            return isset( $catalog['products'] ) && is_array( $catalog['products'] ) ? $catalog['products'] : array();
        }

        return array();
    }

    /**
     * Retrieve a product detail from cache by ID.
     *
     * @param int $product_id Product id.
     *
     * @return array|null
     */
    public function get_product( $product_id ) {
        foreach ( $this->get_products() as $product ) {
            if ( isset( $product['id'] ) && (int) $product['id'] === (int) $product_id ) {
                return $product;
            }
        }

        return null;
    }

    /**
     * Retrieve variant list for a product.
     *
     * @param int $product_id Product id.
     *
     * @return array
     */
    public function get_variants( $product_id ) {
        $product = $this->get_product( $product_id );

        if ( $product && isset( $product['variants'] ) && is_array( $product['variants'] ) ) {
            return $product['variants'];
        }

        return array();
    }
}
