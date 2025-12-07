<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles creating FluentCart products from Printful catalog data.
 */
class PIFC_Product_Creator {
    /**
     * Create FluentCart products/variations from a Printful catalog entry.
     *
     * @param array $catalog_entry Printful catalog product with variants.
     *
     * @return int|null Created product ID or null on failure.
     */
    public function create_from_catalog( array $catalog_entry ) {
        if ( ! class_exists( 'Printful_Integration_For_Fluentcart_Product_Importer' ) ) {
            return null;
        }

        $importer = new Printful_Integration_For_Fluentcart_Product_Importer();
        $product  = array(
            'title'       => isset( $catalog_entry['name'] ) ? $catalog_entry['name'] : '',
            'printful_id' => isset( $catalog_entry['id'] ) ? $catalog_entry['id'] : 0,
            'thumbnail'   => isset( $catalog_entry['thumbnail'] ) ? $catalog_entry['thumbnail'] : '',
            'variants'    => isset( $catalog_entry['variants'] ) ? $catalog_entry['variants'] : array(),
        );

        return $importer->import_product( $product );
    }
}
