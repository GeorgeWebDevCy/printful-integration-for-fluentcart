<?php

namespace PrintfulForFluentCart;

defined('ABSPATH') || exit;

class Deactivator
{
    public static function deactivate()
    {
        wp_clear_scheduled_hook('pifc_daily_cleanup');
    }
}
