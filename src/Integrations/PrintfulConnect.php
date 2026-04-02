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
        $this->scopes = ['global'];
    }

    public function boot()
    {
        $this->register();

        add_filter('fluent_cart/integration/global_integration_settings_printful', [$this, 'getGlobalSettings']);
        add_filter('fluent_cart/integration/global_integration_fields_printful', [$this, 'getGlobalSettingsFields']);
        add_action('fluent_cart/integration/authenticate_global_credentials_printful', [$this, 'authenticateGlobalCredentials']);
        add_action('fluent_cart/integration/save_global_integration_settings_printful', [$this, 'saveGlobalSettings']);
        add_filter('rest_pre_dispatch', [$this, 'interceptGlobalSettingsRequests'], 10, 3);
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
            'status' => $this->isConfigured(),
            'webhook_url' => WebhookService::getWebhookUrl(),
            'auto_fulfill' => !empty($settings['auto_fulfill']) ? 'yes' : 'no',
            'auto_confirm' => !empty($settings['auto_confirm']) ? 'yes' : 'no',
            'test_mode' => !empty($settings['test_mode']) ? 'yes' : 'no',
            'sync_on_import' => !empty($settings['sync_on_import']) ? 'yes' : 'no',
            'sync_product_costs' => !empty($settings['sync_product_costs']) ? 'yes' : 'no',
            'auto_retry_failed' => !empty($settings['auto_retry_failed']) ? 'yes' : 'no',
            'disable_shipping_email' => !empty($settings['disable_shipping_email']) ? 'yes' : 'no',
            'disable_auto_cancel_on_refund' => !empty($settings['disable_auto_cancel_on_refund']) ? 'yes' : 'no',
        ];
    }

    public function getGlobalSettingsFields()
    {
        return [
            'logo' => $this->logo,
            'menu_title' => __('Printful', 'printful-for-fluentcart'),
            'menu_description' => __('Connect your Printful store and control how fulfillment behaves inside FluentCart.', 'printful-for-fluentcart'),
            'config_instruction' => wp_kses_post(
                __(
                    '<p>Add your Printful API key to connect the store. After saving, you can use the product sync, orders, shipping, and bulk fulfillment tools from the Printful submenu.</p>',
                    'printful-for-fluentcart'
                )
            ),
            'save_button_text' => __('Save Settings', 'printful-for-fluentcart'),
            'valid_message' => __('Your Printful API key is valid.', 'printful-for-fluentcart'),
            'invalid_message' => __('Your Printful API key is not valid.', 'printful-for-fluentcart'),
            'fields' => [
                'api_key' => [
                    'label' => __('Printful API Key', 'printful-for-fluentcart'),
                    'placeholder' => __('Paste your Printful API key here', 'printful-for-fluentcart'),
                    'type' => 'password',
                    'tips' => __('Generate your key in Printful Dashboard -> Settings -> API.', 'printful-for-fluentcart'),
                ],
                'connection_test' => [
                    'label' => __('Connection Test', 'printful-for-fluentcart'),
                    'type' => 'authenticate-button',
                    'button_text' => __('Test Connection', 'printful-for-fluentcart'),
                    'end_point' => '/authenticate',
                ],
                'webhook_url' => [
                    'label' => __('Webhook URL', 'printful-for-fluentcart'),
                    'type' => 'text',
                    'tips' => __('This endpoint is registered with Printful when you save settings. It receives shipment and order events.', 'printful-for-fluentcart'),
                ],
                'auto_fulfill' => [
                    'label' => __('Auto-Fulfill Orders', 'printful-for-fluentcart'),
                    'type' => 'select',
                    'options' => [
                        'yes' => __('Enabled', 'printful-for-fluentcart'),
                        'no' => __('Disabled', 'printful-for-fluentcart'),
                    ],
                    'tips' => __('Automatically send paid orders to Printful for fulfillment.', 'printful-for-fluentcart'),
                ],
                'auto_confirm' => [
                    'label' => __('Auto-Confirm Orders', 'printful-for-fluentcart'),
                    'type' => 'select',
                    'options' => [
                        'no' => __('Disabled', 'printful-for-fluentcart'),
                        'yes' => __('Enabled', 'printful-for-fluentcart'),
                    ],
                    'tips' => __('Automatically confirm Printful orders. This can trigger charges in your Printful account.', 'printful-for-fluentcart'),
                ],
                'test_mode' => [
                    'label' => __('Draft / Test Mode', 'printful-for-fluentcart'),
                    'type' => 'select',
                    'options' => [
                        'no' => __('Disabled', 'printful-for-fluentcart'),
                        'yes' => __('Enabled', 'printful-for-fluentcart'),
                    ],
                    'tips' => __('Create Printful orders in draft mode so they do not charge or enter production immediately.', 'printful-for-fluentcart'),
                ],
                'sync_on_import' => [
                    'label' => __('Sync On Import', 'printful-for-fluentcart'),
                    'type' => 'select',
                    'options' => [
                        'yes' => __('Enabled', 'printful-for-fluentcart'),
                        'no' => __('Disabled', 'printful-for-fluentcart'),
                    ],
                    'tips' => __('Pull fresh product data from Printful whenever you run a product sync.', 'printful-for-fluentcart'),
                ],
                'sync_product_costs' => [
                    'label' => __('Sync Product Costs', 'printful-for-fluentcart'),
                    'type' => 'select',
                    'options' => [
                        'no' => __('Disabled', 'printful-for-fluentcart'),
                        'yes' => __('Enabled', 'printful-for-fluentcart'),
                    ],
                    'tips' => __('Store Printful production costs on synced variations when Printful provides them.', 'printful-for-fluentcart'),
                ],
                'auto_retry_failed' => [
                    'label' => __('Auto-Retry Failed Orders', 'printful-for-fluentcart'),
                    'type' => 'select',
                    'options' => [
                        'yes' => __('Enabled', 'printful-for-fluentcart'),
                        'no' => __('Disabled', 'printful-for-fluentcart'),
                    ],
                    'tips' => __('Retry a failed Printful order once after a recoverable failure is reported.', 'printful-for-fluentcart'),
                ],
                'disable_shipping_email' => [
                    'label' => __('Shipping Emails', 'printful-for-fluentcart'),
                    'type' => 'select',
                    'options' => [
                        'no' => __('Enabled', 'printful-for-fluentcart'),
                        'yes' => __('Disabled', 'printful-for-fluentcart'),
                    ],
                    'tips' => __('Disable the customer tracking email that would normally be sent when Printful marks an order as shipped.', 'printful-for-fluentcart'),
                ],
                'disable_auto_cancel_on_refund' => [
                    'label' => __('Refund Handling', 'printful-for-fluentcart'),
                    'type' => 'select',
                    'options' => [
                        'no' => __('Automatic cancel attempts enabled', 'printful-for-fluentcart'),
                        'yes' => __('Automatic cancel attempts disabled', 'printful-for-fluentcart'),
                    ],
                    'tips' => __('Control whether the plugin attempts to cancel the related Printful order when a FluentCart order is refunded.', 'printful-for-fluentcart'),
                ],
                'printful_dashboard' => [
                    'label' => __('Printful Dashboard', 'printful-for-fluentcart'),
                    'type' => 'link',
                    'link' => 'https://www.printful.com/dashboard',
                    'link_text' => __('Open Printful Dashboard', 'printful-for-fluentcart'),
                    'target' => '_blank',
                    'btn_class' => 'el-button el-button--default',
                    'tips' => __('Manage API keys, billing, and store-level Printful settings.', 'printful-for-fluentcart'),
                ],
                'product_sync' => [
                    'label' => __('Product Sync', 'printful-for-fluentcart'),
                    'type' => 'link',
                    'link' => admin_url('admin.php?page=pifc-product-sync'),
                    'link_text' => __('Open Product Sync', 'printful-for-fluentcart'),
                    'btn_class' => 'el-button el-button--default',
                    'tips' => __('Sync catalog products, variants, and mapped data from Printful.', 'printful-for-fluentcart'),
                ],
                'orders' => [
                    'label' => __('Printful Orders', 'printful-for-fluentcart'),
                    'type' => 'link',
                    'link' => admin_url('admin.php?page=pifc-orders'),
                    'link_text' => __('Open Orders Panel', 'printful-for-fluentcart'),
                    'btn_class' => 'el-button el-button--default',
                    'tips' => __('Review fulfillment status, inspect payloads, and trigger order actions manually.', 'printful-for-fluentcart'),
                ],
                'bulk_fulfill' => [
                    'label' => __('Bulk Fulfillment', 'printful-for-fluentcart'),
                    'type' => 'link',
                    'link' => admin_url('admin.php?page=pifc-bulk-fulfill'),
                    'link_text' => __('Open Bulk Fulfillment', 'printful-for-fluentcart'),
                    'btn_class' => 'el-button el-button--default',
                    'tips' => __('Send multiple eligible orders to Printful in one workflow.', 'printful-for-fluentcart'),
                ],
                'shipping_setup' => [
                    'label' => __('Shipping Setup', 'printful-for-fluentcart'),
                    'type' => 'link',
                    'link' => admin_url('admin.php?page=pifc-shipping-setup'),
                    'link_text' => __('Open Shipping Setup', 'printful-for-fluentcart'),
                    'btn_class' => 'el-button el-button--default',
                    'tips' => __('Map Printful shipping services and adjust delivery handling.', 'printful-for-fluentcart'),
                ],
                'advanced_settings' => [
                    'label' => __('Advanced Settings', 'printful-for-fluentcart'),
                    'type' => 'link',
                    'link' => admin_url('admin.php?page=pifc-advanced'),
                    'link_text' => __('Open Advanced Settings', 'printful-for-fluentcart'),
                    'btn_class' => 'el-button el-button--default',
                    'tips' => __('Manage auto-fulfillment, draft mode, retry, refund, shipping email, and product sync behavior.', 'printful-for-fluentcart'),
                ],
            ],
        ];
    }

    public function authenticateGlobalCredentials($payload)
    {
        $result = $this->authenticateGlobalCredentialsData($payload);
        $this->sendWrappedJsonResult($result);
    }

    public function saveGlobalSettings($payload)
    {
        $result = $this->saveGlobalSettingsData($payload);
        $this->sendWrappedJsonResult($result);
    }

    public function interceptGlobalSettingsRequests($response, $server, $request)
    {
        if (!($request instanceof \WP_REST_Request)) {
            return $response;
        }

        $route = $request->get_route();
        $settingsKey = sanitize_text_field((string) $request->get_param('settings_key'));

        if ($settingsKey !== 'printful') {
            return $response;
        }

        if ($route === '/fluent-cart/v2/integration/global-settings' && $request->get_method() === 'GET') {
            return new \WP_REST_Response([
                'data' => [
                    'integration' => $this->getGlobalSettings(),
                    'settings' => $this->getGlobalSettingsFields(),
                ],
            ], 200);
        }

        if ($route === '/fluent-cart/v2/integration/global-settings' && $request->get_method() === 'POST') {
            return $this->toWrappedRestResponse($this->saveGlobalSettingsData($request->get_params()));
        }

        if ($route === '/fluent-cart/v2/integration/global-settings/authenticate' && $request->get_method() === 'POST') {
            return $this->toWrappedRestResponse($this->authenticateGlobalCredentialsData($request->get_params()));
        }

        return $response;
    }

    private function getStoredSettings()
    {
        return array_merge(
            Activator::defaultSettings(),
            get_option('pifc_settings', [])
        );
    }

    private function authenticateGlobalCredentialsData($payload)
    {
        if (!current_user_can('manage_options')) {
            return new \WP_Error(
                'pifc_unauthorized',
                __('Unauthorized.', 'printful-for-fluentcart'),
                ['status' => 403]
            );
        }

        $integration = $this->normalizePayload($payload);
        $apiKey = sanitize_text_field((string) ($integration['api_key'] ?? ''));

        if ($apiKey === '') {
            return [
                'message' => __('API key is required.', 'printful-for-fluentcart'),
                'status' => false,
            ];
        }

        $client = new PrintfulClient($apiKey);
        $store = $client->getStore();

        if (is_wp_error($store)) {
            return [
                'message' => $store->get_error_message(),
                'status' => false,
            ];
        }

        return [
            'message' => sprintf(
                __('Connected to "%s" successfully.', 'printful-for-fluentcart'),
                esc_html($store['name'] ?? __('your store', 'printful-for-fluentcart'))
            ),
            'status' => true,
        ];
    }

    private function saveGlobalSettingsData($payload)
    {
        if (!current_user_can('manage_options')) {
            return new \WP_Error(
                'pifc_unauthorized',
                __('Unauthorized.', 'printful-for-fluentcart'),
                ['status' => 403]
            );
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

        return [
            'message' => $message,
            'status' => !empty($settings['api_key']),
            'integration' => $this->getGlobalSettings(),
        ];
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

    private function toWrappedRestResponse($result)
    {
        if (is_wp_error($result)) {
            return new \WP_REST_Response([
                'data' => [
                    'message' => $result->get_error_message(),
                ],
            ], (int) ($result->get_error_data()['status'] ?? 422));
        }

        return new \WP_REST_Response([
            'data' => $result,
        ], 200);
    }

    private function sendWrappedJsonResult($result)
    {
        if (is_wp_error($result)) {
            wp_send_json([
                'data' => [
                    'message' => $result->get_error_message(),
                ],
            ], (int) ($result->get_error_data()['status'] ?? 422));
        }

        wp_send_json([
            'data' => $result,
        ], 200);
    }
}
