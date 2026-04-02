<?php

namespace PrintfulForFluentCart\Services;

use FluentCart\App\Models\Order;
use PrintfulForFluentCart\Api\PrintfulClient;

defined('ABSPATH') || exit;

/**
 * Reacts to FluentCart refund events and attempts to cancel the corresponding
 * Printful order when it is still in a cancellable state (draft or pending).
 *
 * Printful does not expose a "return request" API — once an order enters
 * production it cannot be stopped via the API. For those cases we log the
 * situation in order meta and fire an action hook so store owners can handle
 * it manually or via a third-party notification.
 *
 * Hooks into:
 *   fluent_cart/order_fully_refunded    — array payload
 *   fluent_cart/order_partially_refunded — array payload
 */
class RefundService
{
    /** Printful statuses that can still be canceled via API. */
    const CANCELLABLE_STATUSES = ['draft', 'pending', 'failed'];

    /** @var PrintfulClient */
    private $client;

    public function __construct()
    {
        $this->client = new PrintfulClient();
    }

    /**
     * @param array $payload
     */
    public function onOrderRefunded(array $payload)
    {
        $settings = get_option('pifc_settings', []);

        if (!empty($settings['disable_auto_cancel_on_refund'])) {
            return;
        }

        $order = $payload['order'] ?? null;

        if (!$order instanceof Order) {
            return;
        }

        $printfulOrderId = $order->getMeta('_printful_order_id');

        if (!$printfulOrderId) {
            // Order was never sent to Printful — nothing to do.
            return;
        }

        $printfulStatus = $order->getMeta('_printful_order_status', '');

        if (in_array($printfulStatus, self::CANCELLABLE_STATUSES, true)) {
            $this->cancelPrintfulOrder($order, (int) $printfulOrderId);
        } else {
            $this->flagForManualReturn($order, (int) $printfulOrderId, $printfulStatus);
        }
    }

    // ─── Internal ─────────────────────────────────────────────────────────────

    /**
     * @param Order $order
     * @param int   $printfulOrderId
     */
    private function cancelPrintfulOrder(Order $order, $printfulOrderId)
    {
        $result = $this->client->cancelOrder($printfulOrderId);

        if (is_wp_error($result)) {
            $order->updateMeta(
                '_printful_refund_note',
                sprintf(
                    /* translators: %s: error message */
                    __('Attempted to cancel Printful order on refund but got error: %s', 'printful-for-fluentcart'),
                    $result->get_error_message()
                )
            );

            do_action('pifc/refund_cancel_failed', $order, $printfulOrderId, $result);
            return;
        }

        $order->updateMeta('_printful_order_status', 'canceled');
        $order->updateMeta(
            '_printful_refund_note',
            __('Printful order canceled automatically on full/partial refund.', 'printful-for-fluentcart')
        );

        do_action('pifc/order_canceled_on_refund', $order, $printfulOrderId);
    }

    /**
     * Order is already in production — flag it so the admin can handle it.
     *
     * @param Order  $order
     * @param int    $printfulOrderId
     * @param string $currentStatus
     */
    private function flagForManualReturn(Order $order, $printfulOrderId, $currentStatus)
    {
        $note = sprintf(
            /* translators: 1: Printful order ID 2: current Printful status */
            __(
                'Order was refunded but Printful order #%1$s is already in "%2$s" status '
                . 'and cannot be canceled via API. Please handle the return manually in your Printful dashboard.',
                'printful-for-fluentcart'
            ),
            $printfulOrderId,
            $currentStatus
        );

        $order->updateMeta('_printful_refund_note', $note);
        $order->updateMeta('_printful_needs_manual_return', true);

        do_action('pifc/manual_return_required', $order, $printfulOrderId, $currentStatus);
    }
}
