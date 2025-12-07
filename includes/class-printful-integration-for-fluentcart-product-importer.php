<?php

/**
 * Imports Printful products into FluentCart products/variations.
 *
 * @package Printful_Integration_For_Fluentcart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use FluentCart\App\CPT\FluentProducts;

class Printful_Integration_For_Fluentcart_Product_Importer {

	/**
	 * @var Printful_Integration_For_Fluentcart_Api
	 */
	protected $api;

	/**
	 * Constructor.
	 *
	 * @param Printful_Integration_For_Fluentcart_Api $api API client.
	 */
	public function __construct( Printful_Integration_For_Fluentcart_Api $api ) {
		$this->api = $api;
	}

	/**
	 * Import a Printful product with variations and media.
	 *
	 * @param int   $printful_product_id Printful product ID.
	 * @param float $markup_percent      Optional price markup.
	 *
	 * @return array|\WP_Error
	 */
	public function import( $printful_product_id, $markup_percent = 0.0 ) {
		$printful_product_id = (int) $printful_product_id;

		if ( $printful_product_id <= 0 ) {
			return new WP_Error( 'printful_invalid_product', __( 'Invalid Printful product ID.', 'printful-integration-for-fluentcart' ) );
		}

		$response = $this->api->get( 'store/products/' . $printful_product_id );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$result   = isset( $response['result'] ) ? $response['result'] : $response;
		$product  = isset( $result['sync_product'] ) ? $result['sync_product'] : $result;
		$variants = isset( $result['sync_variants'] ) && is_array( $result['sync_variants'] ) ? $result['sync_variants'] : array();

		if ( empty( $product ) ) {
			return new WP_Error( 'printful_empty_product', __( 'Printful product payload was empty.', 'printful-integration-for-fluentcart' ) );
		}

		$post_id = $this->create_product_post( $product );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$gallery_ids = array();
		if ( ! empty( $product['thumbnail_url'] ) ) {
			$thumb_id = $this->sideload_media( $product['thumbnail_url'], $post_id, $product['name'] );
			if ( $thumb_id ) {
				set_post_thumbnail( $post_id, $thumb_id );
				$gallery_ids[] = $this->format_gallery_item( $thumb_id, $product['thumbnail_url'], $product['name'] );
			}
		}

		$created_variations = $this->create_variations( $post_id, $variants, $markup_percent, $gallery_ids );

		$this->create_product_detail( $post_id, $created_variations );

		return array(
			'post_id'    => $post_id,
			'variations' => count( $created_variations ),
		);
	}

	/**
	 * Create product post.
	 *
	 * @param array $product Printful product payload.
	 *
	 * @return int|\WP_Error
	 */
	protected function create_product_post( array $product ) {
		$postarr = array(
			'post_type'    => FluentProducts::CPT_NAME,
			'post_status'  => 'draft',
			'post_title'   => isset( $product['name'] ) ? $product['name'] : __( 'Printful product', 'printful-integration-for-fluentcart' ),
			'post_content' => isset( $product['description'] ) ? wp_kses_post( $product['description'] ) : '',
		);

		$post_id = wp_insert_post( $postarr, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( $post_id, Printful_Integration_For_Fluentcart_Product_Mapping::META_KEY_PRODUCT, (int) $product['id'] );

		return $post_id;
	}

	/**
	 * Create variations for product.
	 *
	 * @param int   $post_id         Product post ID.
	 * @param array $variants        Printful variants.
	 * @param float $markup_percent  Markup percent.
	 * @param array $gallery_ids_ref Gallery array to push to.
	 *
	 * @return array Created variation IDs.
	 */
	protected function create_variations( $post_id, array $variants, $markup_percent, array &$gallery_ids_ref ) {
		$created = array();
		$serial  = 0;

		foreach ( $variants as $variant ) {
			$printful_variant_id = isset( $variant['id'] ) ? $variant['id'] : '';
			if ( ! $printful_variant_id ) {
				continue;
			}

			$title = isset( $variant['name'] ) ? $variant['name'] : '';
			$price = isset( $variant['retail_price'] ) ? floatval( $variant['retail_price'] ) : 0.0;

			if ( $markup_percent !== 0.0 ) {
				$price += ( $price * ( $markup_percent / 100 ) );
			}

			$media_id = null;
			$file     = isset( $variant['files'][0]['preview_url'] ) ? $variant['files'][0]['preview_url'] : '';
			if ( $file ) {
				$media_id = $this->sideload_media( $file, $post_id, $title );
				if ( $media_id ) {
					$gallery_ids_ref[] = $this->format_gallery_item( $media_id, $file, $title );
				}
			}

			if ( class_exists( '\FluentCart\App\Models\ProductVariation' ) ) {
				$model = new \FluentCart\App\Models\ProductVariation();
				$model->fill(
					array(
						'post_id'             => $post_id,
						'media_id'            => $media_id,
						'serial_index'        => $serial,
						'sold_individually'   => 0,
						'variation_title'     => $title,
						'variation_identifier'=> (string) $printful_variant_id,
						'manage_stock'        => 0,
						'payment_type'        => 'onetime',
						'stock_status'        => 'in_stock',
						'backorders'          => 0,
						'total_stock'         => 0,
						'available'           => 0,
						'committed'           => 0,
						'on_hold'             => 0,
						'fulfillment_type'    => 'physical',
						'item_status'         => 'active',
						'manage_cost'         => 0,
						'item_price'          => $price,
						'item_cost'           => $price,
						'compare_price'       => $price,
						'other_info'          => array(
							'printful_variant_id' => $printful_variant_id,
						),
						'downloadable'        => 0,
						'shipping_class'      => null,
					)
				);
				$model->save();
				$variation_id = $model->id;
			} else {
				$variation_id = wp_insert_post(
					array(
						'post_type'   => 'product_variation',
						'post_status' => 'publish',
						'post_title'  => $title,
						'post_parent' => $post_id,
					)
				);
			}

			if ( $variation_id ) {
				Printful_Integration_For_Fluentcart_Product_Mapping::set_variation_mapping( $variation_id, $printful_variant_id );
				$created[] = $variation_id;
			}

			$serial++;
		}

		if ( $gallery_ids_ref ) {
			update_post_meta( $post_id, FluentProducts::CPT_NAME . '-gallery-image', $gallery_ids_ref );
		}

		return $created;
	}

	/**
	 * Create product detail row for min/max pricing.
	 *
	 * @param int   $post_id   Product post ID.
	 * @param array $variation_ids Variation IDs.
	 *
	 * @return void
	 */
	protected function create_product_detail( $post_id, array $variation_ids ) {
		if ( ! class_exists( '\FluentCart\App\Models\ProductDetail' ) || empty( $variation_ids ) ) {
			return;
		}

		$prices = \FluentCart\App\Models\ProductVariation::query()
			->whereIn( 'id', $variation_ids )
			->pluck( 'item_price' )
			->toArray();

		$min = $prices ? min( $prices ) : 0;
		$max = $prices ? max( $prices ) : 0;

		$detail = new \FluentCart\App\Models\ProductDetail();
		$detail->fill(
			array(
				'post_id'              => $post_id,
				'fulfillment_type'     => 'physical',
				'min_price'            => $min,
				'max_price'            => $max,
				'default_variation_id' => isset( $variation_ids[0] ) ? $variation_ids[0] : null,
				'variation_type'       => 'advance_variation',
				'stock_availability'   => 'in_stock',
				'other_info'           => array(),
				'manage_stock'         => 0,
			)
		);
		$detail->save();
	}

	/**
	 * Download and attach media to product.
	 *
	 * @param string $url     Image URL.
	 * @param int    $post_id Post ID to attach.
	 * @param string $desc    Description.
	 *
	 * @return int|null Attachment ID or null.
	 */
	protected function sideload_media( $url, $post_id, $desc = '' ) {
		if ( ! $url ) {
			return null;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_id = media_sideload_image( esc_url_raw( $url ), $post_id, $desc, 'id' );

		if ( is_wp_error( $attachment_id ) ) {
			return null;
		}

		return (int) $attachment_id;
	}

	/**
	 * Format gallery item for FluentCart meta.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $url           URL.
	 * @param string $title         Title.
	 *
	 * @return array
	 */
	protected function format_gallery_item( $attachment_id, $url, $title ) {
		return array(
			'id'    => $attachment_id,
			'url'   => $url,
			'title' => $title,
		);
	}
}
