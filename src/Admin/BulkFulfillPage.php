<?php

namespace PrintfulForFluentCart\Admin;

use FluentCart\App\Models\Order;
use FluentCart\App\Models\OrderMeta;
use FluentCart\App\Helpers\Status;
use PrintfulForFluentCart\Services\OrderFulfillmentService;

defined('ABSPATH') || exit;

/**
 * Admin page: Bulk Fulfill
 *
 * Lists paid FluentCart orders that contain Printful-linked items but have
 * not yet been sent to Printful. Admins can select any subset and push them
 * all to Printful with one click.
 */
class BulkFulfillPage
{
    public function render()
    {
        include PIFC_PLUGIN_DIR . 'views/admin/bulk-fulfill.php';
    }

    // ─── AJAX: fetch unfulfilled orders ───────────────────────────────────────

    public function handleGetUnfulfilled()
    {
        check_ajax_referer('pifc_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'printful-for-fluentcart')]);
        }

        // IDs of orders already sent to Printful
        $fulfilledIds = OrderMeta::where('meta_key', '_printful_order_id')
            ->pluck('order_id')
            ->toArray();

        // Paid orders not yet fulfilled — limit to 200 to keep the page snappy
        $query = Order::with(['customer'])
            ->where('payment_status', Status::PAYMENT_PAID);

        if (!empty($fulfilledIds)) {
            $query->whereNotIn('id', $fulfilledIds);
        }

        $orders = $query->orderBy('id', 'desc')->limit(200)->get();

        $rows = [];
        foreach ($orders as $order) {
            // Only include orders that have at least one Printful-linked item
            if (!$this->hasPrintfulItems($order)) {
                continue;
            }

            $rows[] = [
                'id'            => $order->id,
                'customer_name' => $this->customerName($order),
                'total'         => number_format(($order->total_amount ?? 0) / 100, 2),
                'currency'      => strtoupper($order->currency ?? 'USD'),
                'date'          => $order->created_at
                    ? date_i18n(get_option('date_format'), strtotime($order->created_at))
                    : '',
                'order_status'  => $order->status ?? '',
            ];
        }

        wp_send_json_success(['orders' => $rows]);
    }

    // ─── AJAX: bulk fulfill ───────────────────────────────────────────────────

    public function handleBulkFulfill()
    {
        check_ajax_referer('pifc_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'printful-for-fluentcart')]);
        }

        $rawIds   = $_POST['order_ids'] ?? '';
        $orderIds = array_filter(
            array_map('intval', explode(',', $rawIds)),
            fn($id) => $id > 0
        );

        if (empty($orderIds)) {
            wp_send_json_error(['message' => __('No order IDs provided.', 'printful-for-fluentcart')]);
        }

        $service  = new OrderFulfillmentService();
        $results  = ['fulfilled' => [], 'failed' => []];

        foreach ($orderIds as $orderId) {
            $order = Order::find($orderId);

            if (!$order) {
                $results['failed'][] = ['id' => $orderId, 'reason' => __('Not found.', 'printful-for-fluentcart')];
                continue;
            }

            if ($order->getMeta('_printful_order_id')) {
                $results['failed'][] = ['id' => $orderId, 'reason' => __('Already fulfilled.', 'printful-for-fluentcart')];
                continue;
            }

            $result = $service->fulfillOrder($order);

            if (is_wp_error($result)) {
                $results['failed'][] = ['id' => $orderId, 'reason' => $result->get_error_message()];
            } else {
                $results['fulfilled'][] = ['id' => $orderId, 'printful_id' => $result['id'] ?? ''];
            }
        }

        wp_send_json_success([
            'message' => sprintf(
                /* translators: 1: fulfilled count 2: failed count */
                __('%1$d fulfilled, %2$d failed.', 'printful-for-fluentcart'),
                count($results['fulfilled']),
                count($results['failed'])
            ),
            'results' => $results,
        ]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * @param Order $order
     */
    private function hasPrintfulItems(Order $order)
    {
        $order->load('order_items');

        foreach ($order->order_items as $item) {
            $variationId = (int) ($item->object_id ?? 0);
            if (!$variationId) continue;
            if (($item->fulfillment_type ?? '') !== 'physical') continue;

            $exists = \FluentCart\App\Models\ProductMeta::where('object_type', 'variation')
                ->where('object_id', $variationId)
                ->where('meta_key', '_printful_sync_variant_id')
                ->exists();

            if ($exists) return true;
        }

        return false;
    }

    /**
     * @param  Order $order
     * @return string
     */
    private function customerName(Order $order)
    {
        if ($order->customer) {
            $name = trim(
                ($order->customer->first_name ?? '') . ' ' .
                ($order->customer->last_name  ?? '')
            );
            if ($name !== '') return $name;
            return $order->customer->email ?? "#{$order->id}";
        }
        return "#{$order->id}";
    }
}
