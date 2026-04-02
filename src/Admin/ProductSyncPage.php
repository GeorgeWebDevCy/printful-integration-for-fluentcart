<?php

namespace PrintfulForFluentCart\Admin;

use PrintfulForFluentCart\Services\ProductSyncService;

defined('ABSPATH') || exit;

class ProductSyncPage
{
    public function render()
    {
        include PIFC_PLUGIN_DIR . 'views/admin/product-sync.php';
    }

    public function handleSyncAll()
    {
        check_ajax_referer('pifc_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'printful-for-fluentcart')]);
        }

        $settings = get_option('pifc_settings', []);
        if (empty($settings['api_key'])) {
            wp_send_json_error([
                'message' => __('No API key configured. Please save your settings first.', 'printful-for-fluentcart'),
            ]);
        }

        $service = new ProductSyncService();
        $results = $service->syncAllProducts();

        wp_send_json_success([
            'message' => sprintf(
                /* translators: 1: created count 2: updated count */
                __('Sync complete — %1$d created, %2$d updated.', 'printful-for-fluentcart'),
                $results['created'],
                $results['updated']
            ),
            'errors'  => $results['errors'],
        ]);
    }

    public function handleSyncSingle()
    {
        check_ajax_referer('pifc_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'printful-for-fluentcart')]);
        }

        $printfulProductId = (int) ($_POST['printful_product_id'] ?? 0);

        if (!$printfulProductId) {
            wp_send_json_error(['message' => __('Invalid Printful product ID.', 'printful-for-fluentcart')]);
        }

        $service = new ProductSyncService();
        $result  = $service->syncProduct($printfulProductId);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => sprintf(
                /* translators: 1: product ID 2: "created" or "updated" */
                __('Product #%1$d %2$s successfully.', 'printful-for-fluentcart'),
                $printfulProductId,
                $result
            ),
        ]);
    }
}
