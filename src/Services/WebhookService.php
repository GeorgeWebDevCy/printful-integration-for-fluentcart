<?php

namespace PrintfulForFluentCart\Services;

use FluentCart\App\Models\Order;
use FluentCart\App\Models\OrderMeta;
use FluentCart\App\Helpers\Status;
use PrintfulForFluentCart\Api\PrintfulClient;

defined('ABSPATH') || exit;

/**
 * Registers a REST endpoint to receive Printful webhook events and
 * keeps FluentCart order data in sync with Printful fulfillment events.
 *
 * Endpoint: POST /wp-json/pifc/v1/webhook
 *
 * Handled events
 * ───────────────
 * • package_shipped   → update FC shipping status to Shipped, store tracking
 * • package_returned  → mark returned in order meta
 * • order_failed      → store error reason in order meta
 * • order_canceled    → mark canceled in order meta
 * • order_updated     → refresh stored Printful status
 */
class WebhookService
{
    // ─── Bootstrap ───────────────────────────────────────────────────────────

    public function registerEndpoint()
    {
        register_rest_route('pifc/v1', '/webhook', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handleWebhook'],
            'permission_callback' => '__return_true',
        ]);
    }

    // ─── Webhook dispatcher ───────────────────────────────────────────────────

    /**
     * @param  \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handleWebhook(\WP_REST_Request $request)
    {
        $body = $request->get_json_params();

        if (empty($body) || !is_array($body)) {
            return new \WP_REST_Response(['ok' => false, 'message' => 'Empty body.'], 400);
        }

        $type = $body['type'] ?? '';

        if ($type === '') {
            return new \WP_REST_Response(['ok' => false, 'message' => 'Missing event type.'], 400);
        }

        do_action('pifc/webhook_received', $type, $body);

        switch ($type) {
            case 'package_shipped':
                $this->onPackageShipped($body);
                break;

            case 'package_returned':
                $this->onPackageReturned($body);
                break;

            case 'order_failed':
                $this->onOrderFailed($body);
                break;

            case 'order_canceled':
                $this->onOrderCanceled($body);
                break;

            case 'order_updated':
                $this->onOrderUpdated($body);
                break;
        }

        return new \WP_REST_Response(['ok' => true], 200);
    }

    // ─── Event handlers ───────────────────────────────────────────────────────

    /** @param array $body */
    private function onPackageShipped(array $body)
    {
        $order = $this->findOrderByPrintfulId(
            (int) ($body['data']['order']['id'] ?? 0)
        );

        if (!$order) {
            return;
        }

        $shipment = $body['data']['shipment'] ?? [];

        // Update FluentCart shipping status
        $order->updateStatus('shipping_status', Status::SHIPPING_SHIPPED);
        $order->updateMeta('_printful_order_status', 'fulfilled');

        // Persist tracking data
        if (!empty($shipment['tracking_number'])) {
            $order->updateMeta('_printful_tracking', [
                'tracking_number' => sanitize_text_field($shipment['tracking_number']),
                'tracking_url'    => esc_url_raw($shipment['tracking_url']  ?? ''),
                'carrier'         => sanitize_text_field($shipment['service'] ?? ''),
                'ship_date'       => sanitize_text_field($shipment['ship_date'] ?? ''),
                'shipped_at'      => current_time('mysql'),
            ]);
        }

        do_action('pifc/order_shipped', $order, $shipment);
    }

    /** @param array $body */
    private function onPackageReturned(array $body)
    {
        $order = $this->findOrderByPrintfulId(
            (int) ($body['data']['order']['id'] ?? 0)
        );

        if (!$order) {
            return;
        }

        $order->updateMeta('_printful_order_status', 'returned');
        do_action('pifc/order_returned', $order, $body);
    }

    /** @param array $body */
    private function onOrderFailed(array $body)
    {
        $order = $this->findOrderByPrintfulId(
            (int) ($body['data']['order']['id'] ?? 0)
        );

        if (!$order) {
            return;
        }

        $reason = sanitize_text_field($body['data']['reason'] ?? 'Unknown');
        $order->updateMeta('_printful_order_status', 'failed');
        $order->updateMeta('_printful_fulfillment_error', $reason);

        do_action('pifc/fulfillment_failed', $order, $reason);

        // Auto-retry: re-create the Printful order once unless already retried.
        $settings = get_option('pifc_settings', []);
        if (!empty($settings['auto_retry_failed']) && !$order->getMeta('_printful_retry_attempted')) {
            $order->updateMeta('_printful_retry_attempted', 1);
            // Remove the old failed order ID so fulfillOrder creates a fresh one.
            $order->deleteMeta('_printful_order_id');
            $order->deleteMeta('_printful_order_status');

            $service = new OrderFulfillmentService();
            $result  = $service->fulfillOrder($order);

            if (is_wp_error($result)) {
                $order->updateMeta('_printful_order_status', 'failed');
                $order->updateMeta('_printful_fulfillment_error', $result->get_error_message());
            }
        }
    }

    /** @param array $body */
    private function onOrderCanceled(array $body)
    {
        $order = $this->findOrderByPrintfulId(
            (int) ($body['data']['order']['id'] ?? 0)
        );

        if (!$order) {
            return;
        }

        $order->updateMeta('_printful_order_status', 'canceled');
        do_action('pifc/order_canceled', $order, $body);
    }

    /** @param array $body */
    private function onOrderUpdated(array $body)
    {
        $order = $this->findOrderByPrintfulId(
            (int) ($body['data']['order']['id'] ?? 0)
        );

        if (!$order) {
            return;
        }

        $newStatus = sanitize_text_field($body['data']['order']['status'] ?? '');

        if ($newStatus !== '') {
            $order->updateMeta('_printful_order_status', $newStatus);
        }

        do_action('pifc/order_updated', $order, $body['data']['order'] ?? []);
    }

    // ─── Lookup ───────────────────────────────────────────────────────────────

    /**
     * @param  int        $printfulOrderId
     * @return Order|null
     */
    private function findOrderByPrintfulId($printfulOrderId)
    {
        if (!$printfulOrderId) {
            return null;
        }

        $meta = OrderMeta::where('meta_key', '_printful_order_id')
            ->where('meta_value', $printfulOrderId)
            ->first();

        if (!$meta) {
            return null;
        }

        return Order::find($meta->order_id);
    }

    // ─── Static registration with Printful ───────────────────────────────────

    /**
     * Register our webhook URL with Printful.
     * Called automatically after settings are saved.
     *
     * @param string $apiKey
     */
    public static function registerWithPrintful($apiKey = '')
    {
        $client = new PrintfulClient($apiKey);

        // Remove any existing webhook registration to avoid duplicates
        $client->deleteWebhooks();

        $client->setWebhooks(
            rest_url('pifc/v1/webhook'),
            [
                'package_shipped',
                'package_returned',
                'order_failed',
                'order_canceled',
                'order_updated',
            ]
        );
    }
}
