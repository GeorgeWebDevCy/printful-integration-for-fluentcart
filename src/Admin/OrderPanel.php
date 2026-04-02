<?php

namespace PrintfulForFluentCart\Admin;

use FluentCart\App\Models\Order;
use FluentCart\App\Models\OrderMeta;
use FluentCart\App\Models\Customer;
use PrintfulForFluentCart\Services\OrderFulfillmentService;

defined('ABSPATH') || exit;

/**
 * Admin page: Printful Orders
 *
 * Lists all FluentCart orders that have been sent to Printful (or attempted),
 * and provides per-order actions: view detail, manual fulfill, cancel.
 */
class OrderPanel
{
    public function render()
    {
        include PIFC_PLUGIN_DIR . 'views/admin/orders.php';
    }

    // ─── AJAX: list orders ────────────────────────────────────────────────────

    public function handleGetOrders()
    {
        check_ajax_referer('pifc_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'printful-for-fluentcart')]);
        }

        $page    = max(1, (int) ($_POST['page']     ?? 1));
        $perPage = max(1, (int) ($_POST['per_page'] ?? 20));
        $offset  = ($page - 1) * $perPage;

        // Find all orders that have a Printful order ID stored
        $metas = OrderMeta::where('meta_key', '_printful_order_id')
            ->orderBy('order_id', 'desc')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        $total = OrderMeta::where('meta_key', '_printful_order_id')->count();

        $orders = [];

        foreach ($metas as $meta) {
            $order = Order::with(['customer'])->find($meta->order_id);

            if (!$order) {
                continue;
            }

            $tracking   = $order->getMeta('_printful_tracking', []);
            $trackingNo = is_array($tracking) ? ($tracking['tracking_number'] ?? '') : '';

            $orders[] = [
                'id'               => $order->id,
                'customer_name'    => $this->resolveCustomerName($order),
                'order_status'     => $order->status         ?? '',
                'printful_order_id'=> $meta->meta_value,
                'printful_status'  => $order->getMeta('_printful_order_status', 'unknown'),
                'tracking_number'  => $trackingNo,
            ];
        }

        wp_send_json_success([
            'orders' => $orders,
            'total'  => (int) $total,
        ]);
    }

    // ─── AJAX: order detail ───────────────────────────────────────────────────

    public function handleGetOrderDetail()
    {
        check_ajax_referer('pifc_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'printful-for-fluentcart')]);
        }

        $orderId = (int) ($_POST['order_id'] ?? 0);

        if (!$orderId) {
            wp_send_json_error(['message' => __('Invalid order ID.', 'printful-for-fluentcart')]);
        }

        $order = Order::find($orderId);

        if (!$order) {
            wp_send_json_error(['message' => __('Order not found.', 'printful-for-fluentcart')]);
        }

        $tracking = $order->getMeta('_printful_tracking', []);
        if (!is_array($tracking)) {
            $tracking = [];
        }

        wp_send_json_success([
            'order_id'         => $order->id,
            'order_status'     => $order->status          ?? '',
            'shipping_status'  => $order->shipping_status ?? '',
            'printful_order_id'=> $order->getMeta('_printful_order_id'),
            'printful_status'  => $order->getMeta('_printful_order_status', ''),
            'fulfillment_error'=> $order->getMeta('_printful_fulfillment_error', ''),
            'tracking_number'  => $tracking['tracking_number'] ?? '',
            'tracking_url'     => $tracking['tracking_url']    ?? '',
            'carrier'          => $tracking['carrier']         ?? '',
            'ship_date'        => $tracking['ship_date']       ?? '',
        ]);
    }

    // ─── AJAX: manual fulfill ─────────────────────────────────────────────────

    public function handleManualFulfill()
    {
        check_ajax_referer('pifc_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'printful-for-fluentcart')]);
        }

        $orderId = (int) ($_POST['order_id'] ?? 0);

        if (!$orderId) {
            wp_send_json_error(['message' => __('Invalid order ID.', 'printful-for-fluentcart')]);
        }

        $order = Order::find($orderId);

        if (!$order) {
            wp_send_json_error(['message' => __('Order not found.', 'printful-for-fluentcart')]);
        }

        $service = new OrderFulfillmentService();
        $result  = $service->fulfillOrder($order);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message'          => __('Order sent to Printful successfully.', 'printful-for-fluentcart'),
            'printful_order_id'=> $result['id'] ?? '',
        ]);
    }

    // ─── AJAX: cancel fulfillment ─────────────────────────────────────────────

    public function handleCancelFulfillment()
    {
        check_ajax_referer('pifc_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'printful-for-fluentcart')]);
        }

        $orderId = (int) ($_POST['order_id'] ?? 0);

        if (!$orderId) {
            wp_send_json_error(['message' => __('Invalid order ID.', 'printful-for-fluentcart')]);
        }

        $order = Order::find($orderId);

        if (!$order) {
            wp_send_json_error(['message' => __('Order not found.', 'printful-for-fluentcart')]);
        }

        $service = new OrderFulfillmentService();
        $result  = $service->cancelFulfillment($order);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => __('Printful fulfillment canceled.', 'printful-for-fluentcart'),
        ]);
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    /**
     * @param  Order $order
     * @return string
     */
    private function resolveCustomerName(Order $order)
    {
        if ($order->customer) {
            $name = trim(
                ($order->customer->first_name ?? '') . ' ' .
                ($order->customer->last_name  ?? '')
            );
            if ($name !== '') {
                return $name;
            }
        }

        return $order->customer->email ?? sprintf(
            /* translators: %d: order ID */
            __('Order #%d', 'printful-for-fluentcart'),
            $order->id
        );
    }
}
