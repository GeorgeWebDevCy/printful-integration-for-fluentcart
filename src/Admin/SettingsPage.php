<?php

namespace PrintfulForFluentCart\Admin;

use PrintfulForFluentCart\Activator;
use PrintfulForFluentCart\Api\PrintfulClient;
use PrintfulForFluentCart\Services\WebhookService;

defined('ABSPATH') || exit;

class SettingsPage
{
    public function render()
    {
        $settings = get_option('pifc_settings', Activator::defaultSettings());
        include PIFC_PLUGIN_DIR . 'views/admin/settings.php';
    }

    public function handleConnectionTest()
    {
        check_ajax_referer('pifc_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'printful-for-fluentcart')]);
        }

        $apiKey = sanitize_text_field(wp_unslash($_POST['api_key'] ?? ''));

        if (empty($apiKey)) {
            wp_send_json_error(['message' => __('API key is required.', 'printful-for-fluentcart')]);
        }

        $client = new PrintfulClient($apiKey);
        $store  = $client->getStore();

        if (is_wp_error($store)) {
            wp_send_json_error(['message' => $store->get_error_message()]);
        }

        wp_send_json_success([
            'message' => sprintf(
                /* translators: %s: Printful store name */
                __('Connected to "%s" successfully.', 'printful-for-fluentcart'),
                esc_html($store['name'] ?? 'your store')
            ),
        ]);
    }

    public function handleSaveSettings()
    {
        check_ajax_referer('pifc_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'printful-for-fluentcart')]);
        }

        $current = get_option('pifc_settings', Activator::defaultSettings());

        $settings = [
            'api_key'        => sanitize_text_field(wp_unslash($_POST['api_key'] ?? '')),
            'auto_fulfill'   => !empty($_POST['auto_fulfill']),
            'auto_confirm'   => !empty($_POST['auto_confirm']),
            'test_mode'      => !empty($_POST['test_mode']),
            'sync_on_import' => !empty($_POST['sync_on_import']),
            'webhook_secret' => $current['webhook_secret'] ?? wp_generate_password(32, false),
        ];

        update_option('pifc_settings', $settings);

        // Re-register webhook with Printful whenever settings are saved.
        if (!empty($settings['api_key'])) {
            WebhookService::registerWithPrintful($settings['api_key']);
        }

        wp_send_json_success(['message' => __('Settings saved.', 'printful-for-fluentcart')]);
    }
}
