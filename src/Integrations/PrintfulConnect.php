<?php

namespace PrintfulForFluentCart\Integrations;

use PrintfulForFluentCart\Activator;
use PrintfulForFluentCart\Api\PrintfulClient;
use PrintfulForFluentCart\Services\WebhookService;

defined('ABSPATH') || exit;

class PrintfulConnect extends \FluentCart\App\Modules\Integrations\BaseIntegrationManager
{
    public function __construct()
    {
        parent::__construct(__('Printful', 'printful-for-fluentcart'), 'printful', 18);

        $this->description = __('Connect Printful to sync products, control fulfillment behavior, and manage shipment automation from inside FluentCart.', 'printful-for-fluentcart');
        $this->logo = PIFC_PLUGIN_URL . 'assets/images/printful.svg';
        $this->category = 'core';
        $this->scopes = [];
    }

    public function boot()
    {
        $this->register();

        add_filter('fluent_cart/integration/global_integration_settings_printful', [$this, 'getGlobalSettings']);
        add_filter('fluent_cart/integration/global_integration_fields_printful', [$this, 'getGlobalSettingsFields']);
        add_action('fluent_cart/integration/authenticate_global_credentials_printful', [$this, 'authenticateGlobalCredentials']);
        add_action('fluent_cart/integration/save_global_integration_settings_printful', [$this, 'saveGlobalSettings']);
    }

    public function processAction($order, $eventData)
    {
        return;
    }

    public function getIntegrationDefaults($settings)
    {
        return [];
    }

    public function getSettingsFields($settings, $args = [])
    {
        return [
            'fields' => [],
            'button_require_list' => false,
            'integration_title' => __('Printful', 'printful-for-fluentcart'),
        ];
    }

    public function isConfigured()
    {
        $settings = $this->getStoredSettings();

        return !empty($settings['api_key']);
    }

    public function getApiSettings()
    {
        $settings = $this->getStoredSettings();

        return [
            'apiKey' => $settings['api_key'],
            'status' => !empty($settings['api_key']),
        ];
    }

    public function getGlobalSettings()
    {
        $settings = $this->getStoredSettings();

        return [
            'api_key' => $settings['api_key'],
            'auto_fulfill' => $settings['auto_fulfill'] ? 'yes' : 'no',
            'auto_confirm' => $settings['auto_confirm'] ? 'yes' : 'no',
            'test_mode' => $settings['test_mode'] ? 'yes' : 'no',
            'sync_on_import' => $settings['sync_on_import'] ? 'yes' : 'no',
            'sync_product_costs' => $settings['sync_product_costs'] ? 'yes' : 'no',
            'auto_retry_failed' => $settings['auto_retry_failed'] ? 'yes' : 'no',
            'disable_shipping_email' => $settings['disable_shipping_email'] ? 'yes' : 'no',
            'disable_auto_cancel_on_refund' => $settings['disable_auto_cancel_on_refund'] ? 'yes' : 'no',
            'webhook_url' => rest_url('pifc/v1/webhook'),
        ];
    }

    public function getGlobalSettingsFields()
    {
        return [
            'title' => __('Printful', 'printful-for-fluentcart'),
            'sub_title' => __('Connect your Printful store and control how fulfillment behaves inside FluentCart.', 'printful-for-fluentcart'),
            'save_button_text' => __('Save Settings', 'printful-for-fluentcart'),
            'valid_message' => __('Your Printful API key is valid.', 'printful-for-fluentcart'),
            'invalid_message' => __('Your Printful API key is not valid.', 'printful-for-fluentcart'),
            'fields' => [
                [
                    'key' => 'api_key',
                    'label' => __('Printful API Key', 'printful-for-fluentcart'),
                    'required' => true,
                    'placeholder' => __('Paste your Printful API key here', 'printful-for-fluentcart'),
                    'component' => 'text',
                    'inline_tip' => __('Generate your key in Printful Dashboard -> Settings -> API.', 'printful-for-fluentcart'),
                ],
                [
                    'key' => 'auto_fulfill',
                    'component' => 'yes-no-checkbox',
                    'checkbox_label' => __('Automatically send paid orders to Printful for fulfillment', 'printful-for-fluentcart'),
                    'inline_tip' => __('When enabled, paid FluentCart orders containing mapped Printful items are sent automatically.', 'printful-for-fluentcart'),
                ],
                [
                    'key' => 'auto_confirm',
                    'component' => 'yes-no-checkbox',
                    'checkbox_label' => __('Automatically confirm Printful orders', 'printful-for-fluentcart'),
                    'inline_tip' => __('Use with caution. Confirming an order can trigger billing and production in Printful.', 'printful-for-fluentcart'),
                ],
                [
                    'key' => 'test_mode',
                    'component' => 'yes-no-checkbox',
                    'checkbox_label' => __('Create orders in Printful draft mode', 'printful-for-fluentcart'),
                    'inline_tip' => __('Draft mode prevents charging and production while you validate the integration.', 'printful-for-fluentcart'),
                ],
                [
                    'key' => 'sync_on_import',
                    'component' => 'yes-no-checkbox',
                    'checkbox_label' => __('Pull fresh product data during product sync', 'printful-for-fluentcart'),
                ],
                [
                    'key' => 'sync_product_costs',
                    'component' => 'yes-no-checkbox',
                    'checkbox_label' => __('Store Printful production costs on synced variations', 'printful-for-fluentcart'),
                ],
                [
                    'key' => 'auto_retry_failed',
                    'component' => 'yes-no-checkbox',
                    'checkbox_label' => __('Retry failed Printful submissions once automatically', 'printful-for-fluentcart'),
                ],
                [
                    'key' => 'disable_shipping_email',
                    'component' => 'yes-no-checkbox',
                    'checkbox_label' => __('Disable the customer tracking email when Printful marks an order as shipped', 'printful-for-fluentcart'),
                ],
                [
                    'key' => 'disable_auto_cancel_on_refund',
                    'component' => 'yes-no-checkbox',
                    'checkbox_label' => __('Disable automatic Printful cancel attempts when a FluentCart order is refunded', 'printful-for-fluentcart'),
                ],
                [
                    'key' => 'webhook_url',
                    'label' => __('Webhook URL', 'printful-for-fluentcart'),
                    'component' => 'text',
                    'inline_tip' => __('This endpoint is registered with Printful when you save settings. It receives shipment and order events.', 'printful-for-fluentcart'),
                ],
            ],
        ];
    }

    public function authenticateGlobalCredentials($payload)
    {
        if (!current_user_can('manage_options')) {
            wp_send_json([
                'message' => __('Unauthorized.', 'printful-for-fluentcart'),
            ], 403);
        }

        $integration = $this->normalizePayload($payload);
        $apiKey = sanitize_text_field((string) ($integration['api_key'] ?? ''));

        if ($apiKey === '') {
            wp_send_json([
                'message' => __('API key is required.', 'printful-for-fluentcart'),
            ], 422);
        }

        $client = new PrintfulClient($apiKey);
        $store = $client->getStore();

        if (is_wp_error($store)) {
            wp_send_json([
                'message' => $store->get_error_message(),
            ], 422);
        }

        wp_send_json([
            'message' => sprintf(
                __('Connected to "%s" successfully.', 'printful-for-fluentcart'),
                esc_html($store['name'] ?? __('your store', 'printful-for-fluentcart'))
            ),
            'status' => true,
        ], 200);
    }

    public function saveGlobalSettings($payload)
    {
        if (!current_user_can('manage_options')) {
            wp_send_json([
                'message' => __('Unauthorized.', 'printful-for-fluentcart'),
            ], 403);
        }

        $current = $this->getStoredSettings();
        $integration = $this->normalizePayload($payload);
        $apiKey = sanitize_text_field(trim((string) ($integration['api_key'] ?? '')));

        if ($apiKey === '') {
            $apiKey = $current['api_key'] ?? '';
        }

        $settings = array_merge(Activator::defaultSettings(), $current, [
            'api_key' => $apiKey,
            'auto_fulfill' => $this->isEnabledValue($integration, 'auto_fulfill', $current),
            'auto_confirm' => $this->isEnabledValue($integration, 'auto_confirm', $current),
            'test_mode' => $this->isEnabledValue($integration, 'test_mode', $current),
            'sync_on_import' => $this->isEnabledValue($integration, 'sync_on_import', $current),
            'sync_product_costs' => $this->isEnabledValue($integration, 'sync_product_costs', $current),
            'disable_shipping_email' => $this->isEnabledValue($integration, 'disable_shipping_email', $current),
            'disable_auto_cancel_on_refund' => $this->isEnabledValue($integration, 'disable_auto_cancel_on_refund', $current),
            'auto_retry_failed' => $this->isEnabledValue($integration, 'auto_retry_failed', $current),
            'webhook_secret' => $current['webhook_secret'] ?? wp_generate_password(32, false),
        ]);

        update_option('pifc_settings', $settings);
        $this->syncFluentCartIntegrationOption($settings);

        $message = __('Printful settings saved.', 'printful-for-fluentcart');

        if (!empty($settings['api_key'])) {
            $webhookResult = WebhookService::registerWithPrintful($settings['api_key']);

            if (is_wp_error($webhookResult)) {
                $message = sprintf(
                    __('Settings saved, but webhook registration failed: %s', 'printful-for-fluentcart'),
                    $webhookResult->get_error_message()
                );
            }
        }

        wp_send_json([
            'message' => $message,
            'integration' => $this->getGlobalSettings(),
        ], 200);
    }

    private function getStoredSettings()
    {
        return array_merge(
            Activator::defaultSettings(),
            get_option('pifc_settings', [])
        );
    }

    private function normalizePayload($payload)
    {
        $integration = $payload['integration'] ?? [];

        if (is_string($integration)) {
            $decoded = json_decode(wp_unslash($integration), true);
            if (is_array($decoded)) {
                return $decoded;
            }

            parse_str($integration, $parsed);
            if (is_array($parsed)) {
                return $parsed;
            }

            return [];
        }

        return is_array($integration) ? $integration : [];
    }

    private function isEnabledValue(array $integration, $key, array $current)
    {
        if (!array_key_exists($key, $integration)) {
            return !empty($current[$key]);
        }

        return in_array($integration[$key], ['yes', '1', 1, true, 'true'], true);
    }

    private function syncFluentCartIntegrationOption(array $settings)
    {
        if (!function_exists('fluent_cart_update_option')) {
            return;
        }

        fluent_cart_update_option('_integration_api_printful', [
            'apiKey' => $settings['api_key'],
            'status' => !empty($settings['api_key']),
        ]);
    }
}
