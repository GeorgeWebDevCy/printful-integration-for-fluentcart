<?php

namespace PrintfulForFluentCart\Services;

defined('ABSPATH') || exit;

/**
 * Tracks when a Printful-synced product is manually edited in FluentCart.
 *
 * When the store owner updates a product (title, price, description, etc.)
 * that was originally imported from Printful, the changes may drift from
 * what Printful has on file. This service:
 *
 * 1. Flags the post with _printful_needs_resync = 1 whenever the product is
 *    saved, so the product sync page can highlight it.
 * 2. Exposes a static helper used by the product sync page to clear the flag
 *    after a successful re-sync.
 *
 * Hook: fluent_cart/product_updated
 * Payload: ['data' => array, 'product' => ProductDetail|object]
 */
class ProductSyncStatusService
{
    /**
     * Action: fluent_cart/product_updated
     *
     * @param array $data  ['data' => array, 'product' => object]
     */
    public function onProductUpdated(array $data)
    {
        $product = $data['product'] ?? null;

        if (!$product) {
            return;
        }

        // FC stores the WP post ID in product_detail.post_id
        $postId = (int) ($product->post_id ?? $product->id ?? 0);

        if (!$postId) {
            return;
        }

        // Only flag products that were imported from Printful
        if (!get_post_meta($postId, '_printful_sync_product_id', true)) {
            return;
        }

        update_post_meta($postId, '_printful_needs_resync', 1);
    }

    /**
     * Clear the resync flag after a successful sync.
     *
     * @param int $postId
     */
    public static function clearResyncFlag($postId)
    {
        delete_post_meta($postId, '_printful_needs_resync');
    }

    /**
     * Return all post IDs that have been flagged for resync.
     *
     * @return int[]
     */
    public static function getFlaggedPostIds()
    {
        global $wpdb;
        $rows = $wpdb->get_col(
            "SELECT post_id FROM {$wpdb->postmeta}
             WHERE meta_key = '_printful_needs_resync' AND meta_value = '1'"
        );
        return array_map('intval', $rows ?: []);
    }
}
