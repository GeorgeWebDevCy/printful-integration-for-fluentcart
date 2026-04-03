<?php

namespace PrintfulForFluentCart\Services;

use FluentCart\App\CPT\FluentProducts;
use FluentCart\App\Helpers\Helper;
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
 * • Each Printful sync-product  → one WordPress post (CPT fluent-products)
 *   post_meta key: _printful_sync_product_id = <printful product id>
 *
 * • Each Printful sync-variant  → one ProductVariation row
 *   fct_product_meta row:  object_type=variation, meta_key=_printful_sync_variant_id
 *   fct_product_meta row:  object_type=variation, meta_key=_printful_variant_id (catalog id)
 *   ProductVariation.other_info.printful_attributes = { Color: 'Black', Size: 'XL', … }
 *   ProductVariation.item_cost = Printful catalog production cost (if enabled)
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
            ProductSyncStatusService::clearResyncFlag($existingPostId);
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
            'post_type'    => FluentProducts::CPT_NAME,
            'post_content' => wp_kses_post($syncProduct['description'] ?? ''),
        ], true);

        if (is_wp_error($postId)) {
            return 0;
        }

        update_post_meta($postId, '_printful_sync_product_id', (int) $syncProduct['id']);
        update_post_meta($postId, '_printful_external_id', sanitize_text_field($syncProduct['external_id'] ?? ''));

        if (!empty($syncProduct['thumbnail_url'])) {
            $this->maybeSetThumbnail($postId, $syncProduct['thumbnail_url']);
        }

        $variationIds = [];
        foreach ($syncVariants as $index => $syncVariant) {
            $variationId = $this->createVariation($postId, $syncVariant, $index);
            if ($variationId) {
                $variationIds[] = $variationId;
            }
        }

        $this->syncProductDetail($postId, $variationIds);

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
            'post_content' => wp_kses_post($syncProduct['description'] ?? ''),
        ]);

        $activeSyncVariantIds = [];
        $variationIds         = [];

        foreach ($syncVariants as $index => $syncVariant) {
            $activeSyncVariantIds[] = (int) ($syncVariant['id'] ?? 0);

            $existingVariationId = $this->findVariationByPrintfulSyncId(
                $postId,
                (int) $syncVariant['id']
            );

            if ($existingVariationId) {
                $this->updateVariation($existingVariationId, $syncVariant);
                $variationIds[] = $existingVariationId;
            } else {
                $variationId = $this->createVariation($postId, $syncVariant, $index);
                if ($variationId) {
                    $variationIds[] = $variationId;
                }
            }
        }

        $this->deactivateMissingVariations($postId, $activeSyncVariantIds);
        $this->syncProductDetail($postId, $variationIds);
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

        // ── Feature: Product Cost Sync ──────────────────────────────────────
        $itemCost = $this->fetchProductionCost($syncVariant);

        // ── Feature: Variant Attribute Display ─────────────────────────────
        $attributes    = $this->parseAttributes($syncVariant['name'] ?? '');
        $variationTitle = $this->buildVariationTitle($syncVariant, $attributes);

        $variation = ProductVariation::create([
            'post_id'              => $postId,
            'serial_index'         => $index,
            'variation_title'      => sanitize_text_field($variationTitle),
            'variation_identifier' => $this->getVariationIdentifier($syncVariant),
            'sku'                  => sanitize_text_field($syncVariant['sku'] ?? ''),
            'item_price'           => $retailPrice,
            'item_cost'            => $itemCost,
            'compare_price'        => 0,
            'manage_stock'         => 0,
            'fulfillment_type'     => 'physical',
            'payment_type'         => 'onetime',
            'stock_status'         => Helper::IN_STOCK,
            'item_status'          => 'active',
            'other_info'           => ['printful_attributes' => $attributes],
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

        $attributes    = $this->parseAttributes($syncVariant['name'] ?? '');
        $variationTitle = $this->buildVariationTitle($syncVariant, $attributes);
        $itemCost       = $this->fetchProductionCost($syncVariant);

        ProductVariation::where('id', $variationId)->update([
            'item_price'           => $retailPrice,
            'item_cost'            => $itemCost,
            'sku'                  => sanitize_text_field($syncVariant['sku'] ?? ''),
            'variation_identifier' => $this->getVariationIdentifier($syncVariant),
            'variation_title'      => sanitize_text_field($variationTitle),
            'stock_status'         => Helper::IN_STOCK,
            'item_status'          => 'active',
            'other_info'           => ['printful_attributes' => $attributes],
        ]);
    }

    /**
     * Mark locally synced variations inactive when they no longer exist in
     * Printful's current sync-variant list.
     *
     * @param int   $postId
     * @param int[] $activeSyncVariantIds
     */
    private function deactivateMissingVariations($postId, array $activeSyncVariantIds)
    {
        $activeSyncVariantIds = array_values(array_filter(array_map('intval', $activeSyncVariantIds)));

        $metas = ProductMeta::where('object_type', 'variation')
            ->where('meta_key', '_printful_sync_variant_id')
            ->get();

        foreach ($metas as $meta) {
            $variation = ProductVariation::where('id', $meta->object_id)
                ->where('post_id', $postId)
                ->first();

            if (!$variation) {
                continue;
            }

            if (in_array((int) $meta->meta_value, $activeSyncVariantIds, true)) {
                continue;
            }

            $variation->update([
                'item_status'  => 'inactive',
                'stock_status' => Helper::OUT_OF_STOCK,
            ]);
        }
    }

    /**
     * Ensure the FluentCart product detail row reflects the imported variants.
     *
     * @param int   $postId
     * @param int[] $variationIds
     */
    private function syncProductDetail($postId, array $variationIds)
    {
        $variationIds = array_values(array_filter(array_map('intval', $variationIds)));
        $variants     = ProductVariation::where('post_id', $postId)
            ->where('item_status', 'active')
            ->orderBy('serial_index', 'asc')
            ->get();

        $defaultVariationId = $variationIds[0] ?? (int) ($variants->first()->id ?? 0);
        $prices             = [];

        foreach ($variants as $variant) {
            $prices[] = (int) ($variant->item_price ?? 0);
        }

        $detailData = [
            'post_id'               => $postId,
            'fulfillment_type'      => 'physical',
            'variation_type'        => count($variants) > 1 ? Helper::PRODUCT_TYPE_SIMPLE_VARIATION : Helper::PRODUCT_TYPE_SIMPLE,
            'min_price'             => !empty($prices) ? min($prices) : 0,
            'max_price'             => !empty($prices) ? max($prices) : 0,
            'default_variation_id'  => $defaultVariationId ?: null,
            'stock_availability'    => !empty($variants) ? Helper::IN_STOCK : Helper::OUT_OF_STOCK,
            'manage_stock'          => 0,
            'manage_downloadable'   => 0,
            'other_info'            => [
                'group_pricing_by' => 'payment_type',
                'use_pricing_table' => 'no',
            ],
        ];

        $detail = ProductDetail::where('post_id', $postId)->first();

        if ($detail) {
            $detail->update($detailData);
            return;
        }

        ProductDetail::create($detailData);
    }

    // ─── Lookup helpers ───────────────────────────────────────────────────────

    /**
     * @param  int $printfulProductId
     * @return int  Post ID (0 = not found)
     */
    public function findPostByPrintfulId($printfulProductId)
    {
        $posts = get_posts([
            'post_type'      => [FluentProducts::CPT_NAME, 'fluent_cart_product'],
            'meta_key'       => '_printful_sync_product_id',
            'meta_value'     => (int) $printfulProductId,
            'posts_per_page' => 1,
            'post_status'    => 'any',
        ]);

        if (empty($posts)) {
            return 0;
        }

        $post = $posts[0];

        if (($post->post_type ?? '') === 'fluent_cart_product') {
            wp_update_post([
                'ID'        => (int) $post->ID,
                'post_type' => FluentProducts::CPT_NAME,
            ]);
        }

        return (int) $post->ID;
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

    // ─── Product Cost Sync ────────────────────────────────────────────────────

    /**
     * Fetch the Printful production cost for a sync-variant via the catalog API.
     *
     * Enabled only when 'sync_product_costs' is true in plugin settings.
     * Returns 0 if disabled, the catalog variant is unavailable, or any error occurs.
     *
     * @param  array $syncVariant
     * @return int  Cost in cents.
     */
    private function fetchProductionCost(array $syncVariant)
    {
        $settings = get_option('pifc_settings', []);

        if (empty($settings['sync_product_costs'])) {
            return 0;
        }

        $catalogVariantId = (int) ($syncVariant['variant_id'] ?? 0);

        if (!$catalogVariantId) {
            return 0;
        }

        $catalogVariant = $this->client->getCatalogVariant($catalogVariantId);

        if (is_wp_error($catalogVariant) || empty($catalogVariant['price'])) {
            return 0;
        }

        return (int) round((float) $catalogVariant['price'] * 100);
    }

    // ─── Variant Attribute Display ────────────────────────────────────────────

    /**
     * Parse a Printful variant name like "Black / XL" or "White - S"
     * into a key→value attribute map.
     *
     * Returns an empty array when no separators are found (single-option variant).
     *
     * @param  string $variantName  e.g. "Black / XL"
     * @return array                e.g. ['Color' => 'Black', 'Size' => 'XL']
     */
    private function parseAttributes($variantName)
    {
        $name = trim($variantName);
        if ($name === '') {
            return [];
        }

        // Printful uses " / " as a separator between attribute values.
        $parts = preg_split('/\s*[\/|]\s*/', $name);

        if (count($parts) < 2) {
            return [];
        }

        // Map positions to well-known attribute label names.
        // Printful's convention: first part = color/style, second = size.
        $labels = apply_filters('pifc/variant_attribute_labels', ['Color', 'Size', 'Style']);

        $attributes = [];
        foreach ($parts as $i => $part) {
            $label = $labels[$i] ?? 'Option ' . ($i + 1);
            $attributes[$label] = trim($part);
        }

        return $attributes;
    }

    /**
     * Build a human-readable variation title from the parsed attributes.
     * Falls back to the raw Printful variant name.
     *
     * @param  array  $syncVariant
     * @param  array  $attributes   Parsed attribute map.
     * @return string
     */
    private function buildVariationTitle(array $syncVariant, array $attributes)
    {
        if (empty($attributes)) {
            return $syncVariant['name'] ?? 'Default';
        }

        return implode(' / ', array_values($attributes));
    }

    /**
     * Prefer SKU for admin readability, but fall back to the Printful sync ID
     * so every variation stays uniquely addressable inside FluentCart.
     *
     * @param array $syncVariant
     * @return string
     */
    private function getVariationIdentifier(array $syncVariant)
    {
        $identifier = sanitize_text_field($syncVariant['sku'] ?? '');

        if ($identifier !== '') {
            return $identifier;
        }

        return (string) (int) ($syncVariant['id'] ?? 0);
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
