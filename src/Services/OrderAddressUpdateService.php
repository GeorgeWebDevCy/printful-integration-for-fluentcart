<?php

namespace PrintfulForFluentCart\Services;

use FluentCart\App\Models\Order;
use PrintfulForFluentCart\Api\PrintfulClient;
use PrintfulForFluentCart\Helpers\OrderMapper;

defined('ABSPATH') || exit;

/**
 * Keeps the Printful draft/pending order recipient in sync when a FluentCart
 * order's customer (and therefore shipping address) is changed by an admin.
 *
 * Listens to: fluent_cart/order_customer_changed
 * Payload:
 *   'order'               => Order
 *   'old_customer'        => Customer
 *   'new_customer'        => Customer
 *   'connected_order_ids' => int[]
 */
class OrderAddressUpdateService
{
    /** Printful statuses where the recipient can still be edited. */
    const EDITABLE_STATUSES = ['draft', 'pending', 'failed'];

    /** @var PrintfulClient */
    private $client;

    public function __construct()
    {
        $this->client = new PrintfulClient();
    }

    /**
     * Hook: fluent_cart/order_customer_changed
     *
     * @param array $data
     */
    public function onCustomerChanged(array $data)
    {
        $orders = [];

        /** @var Order|null $order */
        $order = $data['order'] ?? null;

        if ($order instanceof Order) {
            $orders[$order->id] = $order;
        }

        $connectedOrderIds = array_filter(array_map('intval', (array) ($data['connected_order_ids'] ?? [])));

        foreach ($connectedOrderIds as $connectedOrderId) {
            if (isset($orders[$connectedOrderId])) {
                continue;
            }

            $connectedOrder = Order::find($connectedOrderId);

            if ($connectedOrder instanceof Order) {
                $orders[$connectedOrderId] = $connectedOrder;
            }
        }

        if (!$orders) {
            return;
        }

        foreach ($orders as $targetOrder) {
            $this->syncRecipientForOrder($targetOrder);
        }
    }

    /**
     * Push recipient changes for one order if the linked Printful order is still
     * editable.
     *
     * @param Order $order
     */
    private function syncRecipientForOrder(Order $order)
    {
        $printfulOrderId = (int) $order->getMeta('_printful_order_id');

        if (!$printfulOrderId) {
            return;
        }

        $currentStatus = $order->getMeta('_printful_order_status', '');

        if (!in_array($currentStatus, self::EDITABLE_STATUSES, true)) {
            return;
        }

        // Reload relations so the new customer's address is available
        $order->load(['order_items', 'shipping_address', 'billing_address', 'customer']);

        $recipient = OrderMapper::buildRecipient($order);

        if (is_wp_error($recipient)) {
            return;
        }

        $result = $this->client->updateOrder($printfulOrderId, ['recipient' => $recipient]);

        if (is_wp_error($result)) {
            $order->updateMeta('_printful_fulfillment_error', $result->get_error_message());
        } else {
            $order->deleteMeta('_printful_fulfillment_error');
        }

        do_action('pifc/order_address_updated', $order, $printfulOrderId, $result);
    }
}
