<?php

namespace PrintfulForFluentCart\Admin;

use PrintfulForFluentCart\Activator;

defined('ABSPATH') || exit;

class NativeAdminBridge
{
    public function handleGetPanel()
    {
        check_ajax_referer('pifc_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'printful-for-fluentcart')], 403);
        }

        $panel = sanitize_key((string) ($_POST['panel'] ?? 'settings'));
        $html = $this->renderPanel($panel);

        if ($html === null) {
            wp_send_json_error(['message' => __('Unknown Printful panel.', 'printful-for-fluentcart')], 404);
        }

        wp_send_json_success([
            'panel' => $panel,
            'html' => $html,
        ]);
    }

    public function renderPanel($panel)
    {
        $panelMap = [
            'advanced' => 'advanced',
            'sync' => 'product-sync',
            'orders' => 'orders',
            'bulk' => 'bulk-fulfill',
            'catalog' => 'catalog-browser',
            'shipping' => 'shipping-setup',
        ];

        if (!isset($panelMap[$panel])) {
            return null;
        }

        $settings = get_option('pifc_settings', Activator::defaultSettings());
        $zones = [];

        if ($panel === 'shipping') {
            $zones = (new ShippingSetupPage())->getZonesForNativePanel();
        }

        ob_start();
        include PIFC_PLUGIN_DIR . 'views/admin/panels/' . $panelMap[$panel] . '.php';
        return ob_get_clean();
    }
}
