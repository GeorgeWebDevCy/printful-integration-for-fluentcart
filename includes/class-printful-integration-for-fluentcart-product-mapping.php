<?php

/**
 * Helpers for linking FluentCart products/variations to Printful catalogue items.
 *
 * @package Printful_Integration_For_Fluentcart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Printful_Integration_For_Fluentcart_Product_Mapping {

	const META_KEY_VARIATION = '_printful_variant_id';
	const META_KEY_PRODUCT   = '_printful_product_id';
	const META_KEY_DISABLE   = '_printful_fulfilment_mode';
	const META_KEY_SERVICE   = '_printful_service_code';
	const META_KEY_ORIGIN    = '_printful_origin_index';
	const META_KEY_MOCKUP    = '_printful_mockup_url';

	/**
	 * Persist a Printful product ID against a FluentCart product (post).
	 *
	 * @param int    $product_id WordPress post ID for the FluentCart product.
	 * @param string $printful_product_id Printful product identifier.
	 *
	 * @return bool
	 */
	public static function set_product_mapping( $product_id, $printful_product_id ) {
		if ( ! $product_id ) {
			return false;
		}

		return update_post_meta( $product_id, self::META_KEY_PRODUCT, sanitize_text_field( $printful_product_id ) );
	}

	/**
	 * Fetch mapped Printful product ID for a FluentCart product.
	 *
	 * @param int $product_id WordPress post ID.
	 *
	 * @return string|null
	 */
	public static function get_product_mapping( $product_id ) {
		$value = get_post_meta( $product_id, self::META_KEY_PRODUCT, true );

		return $value ? sanitize_text_field( $value ) : null;
	}

	/**
	 * Get product-specific origin profile index.
	 *
	 * @param int $product_id Product ID.
	 *
	 * @return int|null
	 */
	public static function get_product_origin( $product_id ) {
		$value = get_post_meta( $product_id, self::META_KEY_ORIGIN, true );

		return ( '' !== $value && null !== $value ) ? (int) $value : null;
	}

	/**
	 * Check if a product is disabled for Printful fulfilment.
	 *
	 * @param int $product_id Product ID.
	 *
	 * @return bool
	 */
	public static function is_product_disabled( $product_id ) {
		$mode = get_post_meta( $product_id, self::META_KEY_DISABLE, true );

		return 'disabled' === $mode;
	}

	/**
	 * Get preferred service code for a product.
	 *
	 * @param int $product_id Product ID.
	 *
	 * @return string|null
	 */
	public static function get_product_service( $product_id ) {
		$value = get_post_meta( $product_id, self::META_KEY_SERVICE, true );

		return $value ? sanitize_text_field( $value ) : null;
	}

	/**
	 * Get mockup preview URL for product.
	 *
	 * @param int $product_id Product ID.
	 *
	 * @return string
	 */
	public static function get_product_mockup( $product_id ) {
		$value = get_post_meta( $product_id, self::META_KEY_MOCKUP, true );
		return $value ? esc_url( $value ) : '';
	}

	/**
	 * Persist a Printful variant ID for a FluentCart variation.
	 *
	 * @param int    $variation_id FluentCart variation ID (fct_product_variations.id).
	 * @param string $printful_variant_id Printful variant identifier.
	 *
	 * @return bool
	 */
	public static function set_variation_mapping( $variation_id, $printful_variant_id ) {
		if ( ! $variation_id ) {
			return false;
		}

		$variant = self::get_product_meta_model( $variation_id );
		$sanitised = sanitize_text_field( $printful_variant_id );

		if ( $variant ) {
			$variant->meta_value = $sanitised;
			return $variant->save();
		}

		if ( ! class_exists( '\FluentCart\App\Models\ProductMeta' ) ) {
			return update_post_meta( $variation_id, self::META_KEY_VARIATION, $sanitised );
		}

		$model              = new \FluentCart\App\Models\ProductMeta();
		$model->object_id   = $variation_id;
		$model->meta_key    = self::META_KEY_VARIATION;
		$model->object_type = 'product_variant';
		$model->meta_value  = $sanitised;

		return (bool) $model->save();
	}

	/**
	 * Locate existing meta row for a variation, if FluentCart models are available.
	 *
	 * @param int $variation_id Variation ID.
	 *
	 * @return \FluentCart\App\Models\ProductMeta|null
	 */
	protected static function get_product_meta_model( $variation_id ) {
		if ( ! class_exists( '\FluentCart\App\Models\ProductMeta' ) ) {
			return null;
		}

		return \FluentCart\App\Models\ProductMeta::query()
			->where( 'object_id', $variation_id )
			->where( 'meta_key', self::META_KEY_VARIATION )
			->where( 'object_type', 'product_variant' )
			->first();
	}

	/**
	 * Fetch mapped Printful variant ID for a variation.
	 *
	 * @param int $variation_id Variation ID.
	 *
	 * @return string|null
	 */
	public static function get_variation_mapping( $variation_id ) {
		if ( ! $variation_id ) {
			return null;
		}

		if ( class_exists( '\FluentCart\App\Models\ProductMeta' ) ) {
			$meta = self::get_product_meta_model( $variation_id );
			if ( $meta && $meta->meta_value ) {
				return sanitize_text_field( $meta->meta_value );
			}
		}

		$value = get_post_meta( $variation_id, self::META_KEY_VARIATION, true );

		return $value ? sanitize_text_field( $value ) : null;
	}

	/**
	 * Remove stored mapping for a variation.
	 *
	 * @param int $variation_id Variation ID.
	 *
	 * @return bool
	 */
	public static function delete_variation_mapping( $variation_id ) {
		if ( class_exists( '\FluentCart\App\Models\ProductMeta' ) ) {
			$existing = self::get_product_meta_model( $variation_id );
			if ( $existing ) {
				return (bool) $existing->delete();
			}
		}

		return (bool) delete_post_meta( $variation_id, self::META_KEY_VARIATION );
	}

	/**
	 * Retrieve all variation mappings as [variation_id => printful_variant_id].
	 *
	 * @return array
	 */
	public static function get_all_variation_mappings() {
		$mappings = array();

		if ( class_exists( '\FluentCart\App\Models\ProductMeta' ) ) {
			$records = \FluentCart\App\Models\ProductMeta::query()
				->where( 'meta_key', self::META_KEY_VARIATION )
				->where( 'object_type', 'product_variant' )
				->get();

			foreach ( $records as $record ) {
				$variation_id = (int) $record->object_id;
				$value        = $record->meta_value;
				if ( $variation_id && $value ) {
					$mappings[ $variation_id ] = sanitize_text_field( $value );
				}
			}
		}

		return $mappings;
	}

	/**
	 * Helper to get designer link for a mapped product.
	 *
	 * @param int         $product_id  Product ID.
	 * @param string|null $printful_id Optional mapped ID.
	 *
	 * @return string
	 */
	public static function get_designer_link( $product_id, $printful_id = null ) {
		$pid = $printful_id ? $printful_id : self::get_product_mapping( $product_id );
		if ( ! $pid ) {
			return '';
		}

		return 'https://www.printful.com/dashboard/designer?product=' . rawurlencode( $pid );
	}
}
