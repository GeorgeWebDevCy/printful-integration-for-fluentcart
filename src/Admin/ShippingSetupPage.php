<?php

namespace PrintfulForFluentCart\Admin;

use FluentCart\App\Models\ShippingMethod;

defined('ABSPATH') || exit;

/**
 * Admin page: Printful Shipping Setup
 *
 * Lets store owners map Printful shipping service codes to FluentCart
 * shipping methods. For each enabled service, a ShippingMethod record
 * is created (or updated) in the FC database. The ShippingRateService
 * then reads these methods and replaces their amount with the live
 * Printful rate at checkout time.
 *
 * Printful shipping service codes (documented):
 *   STANDARD         – Standard shipping
 *   PRINTFUL_FAST    – Fast shipping
 *   PRINTFUL_OVERNIGHT – Overnight / next-day
 *   EXPRESS          – Express
 *   ECONOMY          – Economy
 */
class ShippingSetupPage
{
    /** Known Printful service codes with human-readable defaults. */
    const SERVICES = [
        'STANDARD'           => 'Printful Standard Shipping',
        'PRINTFUL_FAST'      => 'Printful Fast Shipping',
        'PRINTFUL_OVERNIGHT' => 'Printful Overnight Shipping',
        'EXPRESS'            => 'Printful Express Shipping',
        'ECONOMY'            => 'Printful Economy Shipping',
    ];

    /** Option key that stores the enabled services config. */
    const OPTION_KEY = 'pifc_shipping_services';

    public function render()
    {
        $zones = $this->getShippingZones();
        include PIFC_PLUGIN_DIR . 'views/admin/shipping-setup.php';
    }

    public function getZonesForNativePanel()
    {
        return $this->getShippingZones();
    }

    // ─── AJAX ─────────────────────────────────────────────────────────────────

    public function handleGet()
    {
        check_ajax_referer('pifc_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'printful-for-fluentcart')]);
        }

        wp_send_json_success([
            'services' => get_option(self::OPTION_KEY, []),
            'zones'    => $this->getShippingZones(),
        ]);
    }

    public function handleSave()
    {
        check_ajax_referer('pifc_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'printful-for-fluentcart')]);
        }

        $raw      = $_POST['services'] ?? '';
        $services = json_decode(wp_unslash($raw), true);

        if (!is_array($services)) {
            wp_send_json_error(['message' => __('Invalid data.', 'printful-for-fluentcart')]);
        }

        $sanitized = [];
        foreach (self::SERVICES as $code => $defaultTitle) {
            $svc = $services[$code] ?? [];
            $sanitized[$code] = [
                'enabled' => !empty($svc['enabled']),
                'title'   => sanitize_text_field($svc['title'] ?? $defaultTitle),
                'zone_id' => (int) ($svc['zone_id'] ?? 0),
            ];
        }

        update_option(self::OPTION_KEY, $sanitized);

        // Sync to FluentCart shipping methods table
        $synced = $this->syncToFluentCart($sanitized);

        wp_send_json_success([
            'message' => sprintf(
                /* translators: %d: number of shipping methods upserted */
                __('Saved. %d FluentCart shipping method(s) updated.', 'printful-for-fluentcart'),
                $synced
            ),
        ]);
    }

    // ─── FluentCart sync ─────────────────────────────────────────────────────

    /**
     * Create or update FluentCart ShippingMethod records for each enabled service.
     * Disabled services have their method set to is_enabled = false.
     *
     * @param  array $services
     * @return int   Number of methods upserted.
     */
    private function syncToFluentCart(array $services)
    {
        $count = 0;

        foreach ($services as $code => $config) {
            $existing = $this->findManagedShippingMethodByCode($code);

            $methodData = [
                'title'      => $config['title'],
                'type'       => 'per_order',
                'amount'     => 0,          // Overridden at checkout by ShippingRateService
                'is_enabled' => $config['enabled'],
                'settings'   => ['printful_managed' => true],
                'meta'       => ['printful_service_code' => $code],
            ];

            if ($config['zone_id']) {
                $methodData['zone_id'] = $config['zone_id'];
            }

            if ($existing) {
                $existing->update($methodData);
            } elseif ($config['enabled']) {
                ShippingMethod::create($methodData);
            }

            $count++;
        }

        return $count;
    }

    /**
     * Find an existing Printful-managed shipping method without relying on
     * JSON-path query support in the model layer.
     *
     * @param  string $code
     * @return ShippingMethod|null
     */
    private function findManagedShippingMethodByCode($code)
    {
        $methods = ShippingMethod::query()->get();

        foreach ($methods as $method) {
            $meta = is_array($method->meta) ? $method->meta : [];

            if (($meta['printful_service_code'] ?? '') === $code) {
                return $method;
            }
        }

        return null;
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * @return array  [ ['id' => X, 'name' => 'Zone Name'], … ]
     */
    private function getShippingZones()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'fct_shipping_zones';

        // Table may not exist if FC hasn't been activated yet
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return [];
        }

        $rows = $wpdb->get_results("SELECT id, name FROM {$table} ORDER BY name ASC", ARRAY_A);

        return is_array($rows) ? $rows : [];
    }
}
