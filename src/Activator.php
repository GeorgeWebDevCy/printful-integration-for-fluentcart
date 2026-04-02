<?php

namespace PrintfulForFluentCart;

defined('ABSPATH') || exit;

class Activator
{
    public static function activate()
    {
        if (get_option('pifc_version') === false) {
            add_option('pifc_version', PIFC_VERSION);
        }

        if (get_option('pifc_settings') === false) {
            add_option('pifc_settings', self::defaultSettings());
        }

        if (!wp_next_scheduled('pifc_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'pifc_daily_cleanup');
        }
    }

    /**
     * @return array
     */
    public static function defaultSettings()
    {
        return [
            'api_key'               => '',
            'auto_fulfill'          => true,
            'auto_confirm'          => false,
            'test_mode'             => false,
            'sync_on_import'        => true,
            'sync_product_costs'    => false,
            'disable_shipping_email'          => false,
            'disable_auto_cancel_on_refund'   => false,
            'auto_retry_failed'               => true,
            'webhook_secret'                  => wp_generate_password(32, false),
        ];
    }
}
