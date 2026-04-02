<?php

namespace PrintfulForFluentCart\Admin;

use PrintfulForFluentCart\Api\PrintfulClient;

defined('ABSPATH') || exit;

/**
 * Admin page: Printful Catalog Browser
 *
 * Lets store owners browse the Printful catalog (categories → products →
 * variants) and one-click import any product into FluentCart.
 *
 * Import flow
 * ───────────
 * 1. Admin browses catalog, finds a product they like.
 * 2. They first configure it in Printful (add mockup, set retail price) —
 *    we link to the Printful dashboard for that step.
 * 3. Once the product appears in their Printful store, they can import it
 *    directly from this page via the ProductSyncService.
 */
class CatalogBrowserPage
{
    public function render()
    {
        include PIFC_PLUGIN_DIR . 'views/admin/catalog-browser.php';
    }

    // ─── AJAX ─────────────────────────────────────────────────────────────────

    public function handleGetCategories()
    {
        check_ajax_referer('pifc_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'printful-for-fluentcart')]);
        }

        $client     = new PrintfulClient();
        $categories = $client->get('/catalog/categories');

        if (is_wp_error($categories)) {
            wp_send_json_error(['message' => $categories->get_error_message()]);
        }

        // Flatten to id/title pairs
        $list = array_map(function ($cat) {
            return [
                'id'    => $cat['id']    ?? 0,
                'title' => $cat['title'] ?? '(unknown)',
            ];
        }, $categories);

        wp_send_json_success(['categories' => $list]);
    }

    public function handleGetProducts()
    {
        check_ajax_referer('pifc_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'printful-for-fluentcart')]);
        }

        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $client     = new PrintfulClient();
        $products   = $client->get('/catalog/products', ['category_id' => $categoryId]);

        if (is_wp_error($products)) {
            wp_send_json_error(['message' => $products->get_error_message()]);
        }

        $list = array_map(function ($p) {
            return [
                'id'            => $p['id']            ?? 0,
                'model'         => $p['model']         ?? '',
                'type'          => $p['type']          ?? '',
                'brand'         => $p['brand']         ?? '',
                'image'         => $p['image']         ?? '',
                'variant_count' => $p['variant_count'] ?? 0,
            ];
        }, $products);

        wp_send_json_success(['products' => $list]);
    }

    public function handleGetProduct()
    {
        check_ajax_referer('pifc_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'printful-for-fluentcart')]);
        }

        $productId = (int) ($_POST['product_id'] ?? 0);
        if (!$productId) {
            wp_send_json_error(['message' => __('Invalid product ID.', 'printful-for-fluentcart')]);
        }

        $client  = new PrintfulClient();
        $product = $client->getCatalogProduct($productId);

        if (is_wp_error($product)) {
            wp_send_json_error(['message' => $product->get_error_message()]);
        }

        // Check if already in the store (synced)
        $syncService  = new PrintfulClient();
        $storeProduct = $syncService->get('/store/products', ['search' => $product['model'] ?? '']);
        $alreadySynced = false;

        if (!is_wp_error($storeProduct) && !empty($storeProduct)) {
            $catalogModel = strtolower(trim((string) ($product['model'] ?? '')));

            foreach ($storeProduct as $candidate) {
                $candidateName = strtolower(trim(sanitize_text_field((string) ($candidate['name'] ?? ''))));
                $candidateExternalId = strtolower(trim(sanitize_text_field((string) ($candidate['external_id'] ?? ''))));

                if ($catalogModel !== '' && ($candidateName === $catalogModel || $candidateExternalId === $catalogModel)) {
                    $alreadySynced = true;
                    break;
                }
            }
        }

        $variants = array_map(function ($v) {
            return [
                'id'        => $v['id']        ?? 0,
                'name'      => $v['name']       ?? '',
                'size'      => $v['size']       ?? '',
                'color'     => $v['color']      ?? '',
                'price'     => $v['price']      ?? '0.00',
                'in_stock'  => !empty($v['availability_regions']),
            ];
        }, $product['variants'] ?? []);

        wp_send_json_success([
            'product'       => [
                'id'          => $product['id']          ?? 0,
                'model'       => $product['model']       ?? '',
                'brand'       => $product['brand']       ?? '',
                'image'       => $product['image']       ?? '',
                'description' => $product['description'] ?? '',
                'type_name'   => $product['type_name']   ?? '',
            ],
            'variants'      => $variants,
            'already_synced'=> $alreadySynced,
        ]);
    }
}
