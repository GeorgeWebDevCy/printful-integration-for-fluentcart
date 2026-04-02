<?php

namespace PrintfulForFluentCart\Services;

use FluentCart\App\Models\ProductDetail;
use FluentCart\App\Models\ProductMeta;
use FluentCart\App\Models\ProductVariation;
use PrintfulForFluentCart\Api\PrintfulClient;

defined('ABSPATH') || exit;

/**
 * Imports and updates Printful store products inside FluentCart.
 *
 * Mapping strategy
 * ─────────────────
 * • Each Printful sync-product  → one WordPress post (CPT fluent_cart_product)
 *   post_meta key: _printful_sync_product_id = <printful product id>
 *
 * • Each Printful sync-variant  → one ProductVariation row
 *   fct_product_meta row:  object_type=variation, meta_key=_printful_sync_variant_id
 *   fct_product_meta row:  object_type=variation, meta_key=_printful_variant_id (catalog id)
 */
class ProductSyncService
{
    /** @var PrintfulClient */
    private $client;

    public function __construct()
    {
        $this->client = new PrintfulClient();
    }

    // ─── Public entry points ──────────────────────────────────────────────────

    /**
     * Sync every product in the connected Printful store.
     *
     * @return array { created: int, updated: int, errors: string[] }
     */
    public function syncAllProducts()
    {
        $results = ['created' => 0, 'updated' => 0, 'errors' => []];
        $offset  = 0;
        $limit   = 100;

        do {
            $products = $this->client->getProducts($offset, $limit);

            if (is_wp_error($products)) {
                $results['errors'][] = $products->get_error_message();
                break;
            }

            if (empty($products)) {
                break;
            }

            foreach ($products as $printfulProduct) {
                $result = $this->syncProduct((int) $printfulProduct['id']);

                if (is_wp_error($result)) {
                    $results['errors'][] = sprintf(
                        'Product %d: %s',
                        $printfulProduct['id'],
                        $result->get_error_message()
                    );
                } elseif ($result === 'created') {
                    $results['created']++;
                } else {
                    $results['updated']++;
                }
            }

            $offset += $limit;
        } while (count($products) === $limit);

        return $results;
    }

    /**
     * Sync a single Printful product by its sync-product ID.
     *
     * @param  int $printfulProductId
     * @return string|\WP_Error  'created' | 'updated' | WP_Error
     */
    public function syncProduct($printfulProductId)
    {
        $data = $this->client->getProduct($printfulProductId);

        if (is_wp_error($data)) {
            return $data;
        }

        $syncProduct  = $data['sync_product']  ?? null;
        $syncVariants = $data['sync_variants'] ?? [];

        if (empty($syncProduct)) {
            return new \WP_Error(
                'pifc_invalid_product',
                __('Invalid product data received from Printful.', 'printful-for-fluentcart')
            );
        }

        $existingPostId = $this->findPostByPrintfulId($printfulProductId);

        if ($existingPostId) {
            $this->updatePost($existingPostId, $syncProduct, $syncVariants);
            return 'updated';
        }

        $this->createPost($syncProduct, $syncVariants);
        return 'created';
    }

    // ─── WP Post ─────────────────────────────────────────────────────────────

    /**
     * @param  array $syncProduct
     * @param  array $syncVariants
     * @return int   Post ID (0 on failure)
     */
    private function createPost(array $syncProduct, array $syncVariants)
    {
        $postId = wp_insert_post([
            'post_title'   => sanitize_text_field($syncProduct['name']),
            'post_status'  => 'publish',
            'post_type'    => 'fluent_cart_product',
            'post_content' => '',
        ], true);

        if (is_wp_error($postId)) {
            return 0;
        }

        update_post_meta($postId, '_printful_sync_product_id', (int) $syncProduct['id']);
        update_post_meta($postId, '_printful_external_id', sanitize_text_field($syncProduct['external_id'] ?? ''));

        if (!empty($syncProduct['thumbnail_url'])) {
            $this->maybeSetThumbnail($postId, $syncProduct['thumbnail_url']);
        }

        // ProductDetail record required by FluentCart
        ProductDetail::create([
            'post_id'            => $postId,
            'fulfillment_type'   => 'physical',
            'variation_type'     => 'simple',
            'stock_availability' => 'yes',
            'other_info'         => [],
        ]);

        foreach ($syncVariants as $index => $syncVariant) {
            $this->createVariation($postId, $syncVariant, $index);
        }

        return $postId;
    }

    /**
     * @param int   $postId
     * @param array $syncProduct
     * @param array $syncVariants
     */
    private function updatePost($postId, array $syncProduct, array $syncVariants)
    {
        wp_update_post([
            'ID'         => $postId,
            'post_title' => sanitize_text_field($syncProduct['name']),
        ]);

        foreach ($syncVariants as $index => $syncVariant) {
            $existingVariationId = $this->findVariationByPrintfulSyncId(
                $postId,
                (int) $syncVariant['id']
            );

            if ($existingVariationId) {
                $this->updateVariation($existingVariationId, $syncVariant);
            } else {
                $this->createVariation($postId, $syncVariant, $index);
            }
        }
    }

    // ─── ProductVariation ─────────────────────────────────────────────────────

    /**
     * @param  int   $postId
     * @param  array $syncVariant
     * @param  int   $index
     * @return int   Variation ID (0 on failure)
     */
    private function createVariation($postId, array $syncVariant, $index)
    {
        // Printful retail_price is a decimal string like "24.95"
        $retailPrice = isset($syncVariant['retail_price'])
            ? (int) round((float) $syncVariant['retail_price'] * 100)
            : 0;

        $variation = ProductVariation::create([
            'post_id'              => $postId,
            'serial_index'         => $index,
            'variation_title'      => sanitize_text_field($syncVariant['name'] ?? 'Default'),
            'variation_identifier' => sanitize_text_field($syncVariant['sku'] ?? ''),
            'sku'                  => sanitize_text_field($syncVariant['sku'] ?? ''),
            'item_price'           => $retailPrice,
            'item_cost'            => 0,
            'compare_price'        => 0,
            'manage_stock'         => false,
            'fulfillment_type'     => 'physical',
            'payment_type'         => 'onetime',
            'stock_status'         => 'in_stock',
            'item_status'          => 'active',
            'other_info'           => [],
        ]);

        if (!$variation || !$variation->id) {
            return 0;
        }

        // Store Printful IDs as product meta for order fulfillment lookup
        ProductMeta::create([
            'object_id'   => $variation->id,
            'object_type' => 'variation',
            'meta_key'    => '_printful_sync_variant_id',
            'meta_value'  => (int) $syncVariant['id'],
        ]);

        if (!empty($syncVariant['variant_id'])) {
            ProductMeta::create([
                'object_id'   => $variation->id,
                'object_type' => 'variation',
                'meta_key'    => '_printful_variant_id',
                'meta_value'  => (int) $syncVariant['variant_id'],
            ]);
        }

        return (int) $variation->id;
    }

    /**
     * @param int   $variationId
     * @param array $syncVariant
     */
    private function updateVariation($variationId, array $syncVariant)
    {
        $retailPrice = isset($syncVariant['retail_price'])
            ? (int) round((float) $syncVariant['retail_price'] * 100)
            : 0;

        ProductVariation::where('id', $variationId)->update([
            'item_price'           => $retailPrice,
            'sku'                  => sanitize_text_field($syncVariant['sku'] ?? ''),
            'variation_identifier' => sanitize_text_field($syncVariant['sku'] ?? ''),
        ]);
    }

    // ─── Lookup helpers ───────────────────────────────────────────────────────

    /**
     * @param  int $printfulProductId
     * @return int  Post ID (0 = not found)
     */
    public function findPostByPrintfulId($printfulProductId)
    {
        $posts = get_posts([
            'post_type'      => 'fluent_cart_product',
            'meta_key'       => '_printful_sync_product_id',
            'meta_value'     => (int) $printfulProductId,
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ]);

        return !empty($posts) ? (int) $posts[0] : 0;
    }

    /**
     * @param  int $postId
     * @param  int $syncVariantId
     * @return int  Variation ID (0 = not found)
     */
    private function findVariationByPrintfulSyncId($postId, $syncVariantId)
    {
        $meta = ProductMeta::where('object_type', 'variation')
            ->where('meta_key', '_printful_sync_variant_id')
            ->where('meta_value', (int) $syncVariantId)
            ->first();

        if (!$meta) {
            return 0;
        }

        $variation = ProductVariation::where('id', $meta->object_id)
            ->where('post_id', $postId)
            ->first();

        return $variation ? (int) $variation->id : 0;
    }

    // ─── Thumbnail ────────────────────────────────────────────────────────────

    /**
     * Downloads the Printful thumbnail and sets it as the post thumbnail.
     * Silently bails on any error to avoid blocking the sync.
     *
     * @param int    $postId
     * @param string $imageUrl
     */
    private function maybeSetThumbnail($postId, $imageUrl)
    {
        if (!filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            return;
        }

        if (has_post_thumbnail($postId)) {
            return;
        }

        $tmpFile = download_url($imageUrl);
        if (is_wp_error($tmpFile)) {
            return;
        }

        $filename  = basename(wp_parse_url($imageUrl, PHP_URL_PATH));
        $fileArray = [
            'name'     => $filename,
            'tmp_name' => $tmpFile,
        ];

        $attachId = media_handle_sideload($fileArray, $postId);

        if (!is_wp_error($attachId)) {
            set_post_thumbnail($postId, $attachId);
        } elseif (file_exists($tmpFile)) {
            @unlink($tmpFile); // phpcs:ignore
        }
    }
}
