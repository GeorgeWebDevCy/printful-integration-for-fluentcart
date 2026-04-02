<?php

namespace PrintfulForFluentCart\Services;

use FluentCart\App\Models\Order;
use FluentCart\App\Models\ProductMeta;
use FluentCart\App\Helpers\Status;
use PrintfulForFluentCart\Api\PrintfulClient;
use PrintfulForFluentCart\Helpers\OrderMapper;

defined('ABSPATH') || exit;

/**
 * Handles automatic and manual fulfillment of FluentCart orders via Printful.
 *
 * Flow
 * ─────
 * 1. fluent_cart/order_paid_done fires → onOrderPaid()
 * 2. If auto_fulfill is on, fulfillOrder() is called.
 * 3. Order items are mapped to Printful sync-variant IDs.
 * 4. A Printful order is created (draft or live depending on settings).
 * 5. If auto_confirm is on, the Printful order is confirmed (production starts).
 * 6. Printful order ID + status are stored in FluentCart order meta.
 */
class OrderFulfillmentService
{
    /** @var PrintfulClient */
    private $client;

    public function __construct()
    {
        $this->client = new PrintfulClient();
    }

    // ─── Hook handler ─────────────────────────────────────────────────────────

    /**
     * Called on fluent_cart/order_paid_done.
     *
     * $eventData = [
     *   'order'        => Order,
     *   'transaction'  => OrderTransaction,
     *   'customer'     => Customer,
     *   'subscription' => Subscription|null,
     * ]
     *
     * @param array $eventData
     */
    public function onOrderPaid(array $eventData)
    {
        $settings = get_option('pifc_settings', []);

        if (empty($settings['auto_fulfill'])) {
            return;
        }

        /** @var Order|null $order */
        $order = $eventData['order'] ?? null;

        if (!$order instanceof Order) {
            return;
        }

        // Prevent double-fulfillment
        if ($order->getMeta('_printful_order_id')) {
            return;
        }

        $this->fulfillOrder($order);
    }

    // ─── Core fulfillment ─────────────────────────────────────────────────────

    /**
     * Build and push a Printful order for the given FluentCart order.
     *
     * @param  Order $order
     * @return array|\WP_Error  Printful order data on success.
     */
    public function fulfillOrder(Order $order)
    {
        // Eager-load all required relations
        $order->load(['order_items', 'shipping_address', 'billing_address', 'customer']);

        $items = $this->buildPrintfulItems($order);

        if (empty($items)) {
            $err = new \WP_Error(
                'pifc_no_printful_items',
                __('No Printful-linked items found in this order.', 'printful-for-fluentcart')
            );
            $order->updateMeta('_printful_fulfillment_error', $err->get_error_message());
            return $err;
        }

        $recipient = OrderMapper::buildRecipient($order);

        if (is_wp_error($recipient)) {
            $order->updateMeta('_printful_fulfillment_error', $recipient->get_error_message());
            return $recipient;
        }

        $settings = get_option('pifc_settings', []);

        $payload = [
            'external_id'   => 'fct-' . $order->id,
            'shipping'      => 'STANDARD',
            'recipient'     => $recipient,
            'items'         => $items,
            'retail_costs'  => [
                'subtotal' => OrderMapper::centsToDecimal($order->subtotal       ?? 0),
                'shipping' => OrderMapper::centsToDecimal($order->shipping_total ?? 0),
                'tax'      => OrderMapper::centsToDecimal($order->tax_total      ?? 0),
                'total'    => OrderMapper::centsToDecimal($order->total_amount   ?? 0),
            ],
        ];

        // Draft mode: Printful stores the order but does not produce it.
        if (!empty($settings['test_mode'])) {
            $payload['is_draft'] = true;
        }

        $result = $this->client->createOrder($payload);

        if (is_wp_error($result)) {
            $order->updateMeta('_printful_fulfillment_error', $result->get_error_message());
            return $result;
        }

        $printfulOrderId = (int) ($result['id'] ?? 0);
        $printfulStatus  = $result['status'] ?? 'draft';

        // Persist Printful order info in FC order meta
        $order->updateMeta('_printful_order_id',     $printfulOrderId);
        $order->updateMeta('_printful_order_status', $printfulStatus);
        $order->updateMeta('_printful_order_data',   $result);
        $order->deleteMeta('_printful_fulfillment_error');

        // Optionally confirm (triggers billing + production)
        if (!empty($settings['auto_confirm']) && empty($settings['test_mode'])) {
            $confirmed = $this->client->confirmOrder($printfulOrderId);

            if (!is_wp_error($confirmed)) {
                $order->updateMeta('_printful_order_status', $confirmed['status'] ?? 'pending');
            }
        }

        // Mark FluentCart shipping status as unshipped (waiting for Printful)
        $order->updateStatus('shipping_status', Status::SHIPPING_UNSHIPPED);

        do_action('pifc/order_fulfilled', $order, $result);

        return $result;
    }

    /**
     * Cancel a Printful order and update local meta.
     *
     * @param  Order $order
     * @return true|\WP_Error
     */
    public function cancelFulfillment(Order $order)
    {
        $printfulOrderId = $order->getMeta('_printful_order_id');

        if (!$printfulOrderId) {
            return new \WP_Error(
                'pifc_not_fulfilled',
                __('This order has not been sent to Printful yet.', 'printful-for-fluentcart')
            );
        }

        $result = $this->client->cancelOrder((int) $printfulOrderId);

        if (is_wp_error($result)) {
            return $result;
        }

        $order->updateMeta('_printful_order_status', 'canceled');
        do_action('pifc/fulfillment_canceled', $order, (int) $printfulOrderId);

        return true;
    }

    // ─── Item mapping ─────────────────────────────────────────────────────────

    /**
     * Convert FluentCart order items to the Printful items array.
     * Only includes items that have a Printful sync-variant ID mapped.
     *
     * @param  Order $order
     * @return array
     */
    private function buildPrintfulItems(Order $order)
    {
        $items = [];

        foreach ($order->order_items as $item) {
            $variationId = (int) ($item->object_id ?? 0);

            if (!$variationId) {
                continue;
            }

            // Only physical items need fulfillment
            if (($item->fulfillment_type ?? '') !== 'physical') {
                continue;
            }

            $meta = ProductMeta::where('object_type', 'variation')
                ->where('object_id', $variationId)
                ->where('meta_key', '_printful_sync_variant_id')
                ->first();

            if (!$meta) {
                continue; // Not a Printful product — skip silently
            }

            $lineMeta = is_array($item->line_meta) ? $item->line_meta : [];

            $items[] = [
                'sync_variant_id' => (int) $meta->meta_value,
                'quantity'        => (int) ($item->quantity ?? 1),
                'retail_price'    => OrderMapper::centsToDecimal($item->unit_price ?? 0),
                'name'            => sanitize_text_field($lineMeta['item_title'] ?? ''),
            ];
        }

        return $items;
    }
}
