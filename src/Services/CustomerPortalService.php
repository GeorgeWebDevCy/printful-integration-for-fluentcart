<?php

namespace PrintfulForFluentCart\Services;

use FluentCart\App\Models\Order;

defined('ABSPATH') || exit;

/**
 * Injects Printful fulfillment and tracking data into FluentCart's customer-
 * facing surfaces:
 *
 * 1. Customer portal order-detail page
 *    Filter: fluent_cart/customer/order_details_section_parts
 *    → Adds a "Printful Tracking" block to the end of the order detail view.
 *
 * 2. Thank You / receipt page
 *    Action: fluent_cart/receipt/thank_you/after_order_items
 *    → Shows a shipment notice (with tracking link) once the order has shipped.
 */
class CustomerPortalService
{
    // ─── Customer portal order detail ────────────────────────────────────────

    /**
     * Filter: fluent_cart/customer/order_details_section_parts
     *
     * @param  array $sections  Keyed HTML blocks (before_summary, end_of_order, …)
     * @param  array $context   ['order' => Order, 'formattedData' => array]
     * @return array
     */
    public function injectPortalTracking(array $sections, array $context)
    {
        /** @var Order|null $order */
        $order = $context['order'] ?? null;

        if (!$order instanceof Order) {
            return $sections;
        }

        $printfulStatus = $order->getMeta('_printful_order_status', '');
        $tracking       = $order->getMeta('_printful_tracking', []);

        if (!is_array($tracking)) {
            $tracking = [];
        }

        // Nothing worth showing yet
        if (!$printfulStatus && empty($tracking)) {
            return $sections;
        }

        ob_start();
        include PIFC_PLUGIN_DIR . 'views/customer/tracking-section.php';
        $html = ob_get_clean();

        $sections['end_of_order'] = ($sections['end_of_order'] ?? '') . $html;

        return $sections;
    }

    // ─── Thank You / receipt page ─────────────────────────────────────────────

    /**
     * Action: fluent_cart/receipt/thank_you/after_order_items
     *
     * @param array $config  Receipt config — 'order' key holds the Order model.
     */
    public function injectThankYouTracking(array $config)
    {
        /** @var Order|null $order */
        $order = $config['order'] ?? null;

        if (!$order instanceof Order) {
            return;
        }

        $printfulOrderId = $order->getMeta('_printful_order_id');

        if (!$printfulOrderId) {
            return;
        }

        $printfulStatus = $order->getMeta('_printful_order_status', '');
        $tracking       = $order->getMeta('_printful_tracking', []);

        if (!is_array($tracking)) {
            $tracking = [];
        }

        include PIFC_PLUGIN_DIR . 'views/customer/thank-you-tracking.php';
    }
}
