<?php

namespace PrintfulForFluentCart\Services;

use FluentCart\App\Models\Activity;
use FluentCart\App\Models\Order;

defined('ABSPATH') || exit;

/**
 * Writes Printful fulfillment events into FluentCart's fct_activity table
 * so they appear in the order timeline visible to admins.
 *
 * Activity model fillable fields:
 *   status, log_type, module_id, module_type, module_name,
 *   title, content, user_id, read_status, created_by
 */
class ActivityLogger
{
    /**
     * pifc/order_fulfilled
     *
     * @param Order $order
     * @param array $printfulOrder  Printful API response data.
     */
    public function onOrderFulfilled(Order $order, array $printfulOrder)
    {
        $printfulId = $printfulOrder['id'] ?? '—';

        $this->log($order, 'info',
            __('Sent to Printful for fulfillment', 'printful-for-fluentcart'),
            sprintf(
                /* translators: 1: Printful order ID 2: Printful status */
                __('Printful order #%1$s created with status "%2$s".', 'printful-for-fluentcart'),
                $printfulId,
                $printfulOrder['status'] ?? 'draft'
            )
        );
    }

    /**
     * pifc/order_shipped
     *
     * @param Order $order
     * @param array $shipment  Printful shipment object.
     */
    public function onOrderShipped(Order $order, array $shipment)
    {
        $trackingNo  = $shipment['tracking_number'] ?? '';
        $trackingUrl = $shipment['tracking_url']    ?? '';
        $carrier     = $shipment['service']         ?? '';

        $detail = $trackingNo
            ? sprintf(
                /* translators: 1: carrier 2: tracking number */
                __('Carrier: %1$s — Tracking: %2$s', 'printful-for-fluentcart'),
                $carrier ?: __('N/A', 'printful-for-fluentcart'),
                $trackingNo
            )
            : __('No tracking number provided.', 'printful-for-fluentcart');

        if ($trackingUrl) {
            $detail .= ' ' . $trackingUrl;
        }

        $this->log($order, 'info',
            __('Shipped by Printful', 'printful-for-fluentcart'),
            $detail
        );
    }

    /**
     * pifc/fulfillment_failed
     *
     * @param Order  $order
     * @param string $reason
     */
    public function onFulfillmentFailed(Order $order, $reason)
    {
        $this->log($order, 'error',
            __('Printful fulfillment failed', 'printful-for-fluentcart'),
            sprintf(
                /* translators: %s: failure reason */
                __('Reason: %s', 'printful-for-fluentcart'),
                $reason
            )
        );
    }

    /**
     * pifc/order_returned
     *
     * @param Order $order
     * @param array $data  Webhook payload.
     */
    public function onOrderReturned(Order $order, array $data)
    {
        $this->log($order, 'warning',
            __('Package returned via Printful', 'printful-for-fluentcart'),
            __('Printful reported the package as returned.', 'printful-for-fluentcart')
        );
    }

    /**
     * pifc/fulfillment_canceled
     *
     * @param Order $order
     * @param int   $printfulOrderId
     */
    public function onFulfillmentCanceled(Order $order, $printfulOrderId)
    {
        $this->log($order, 'warning',
            __('Printful order canceled', 'printful-for-fluentcart'),
            sprintf(
                /* translators: %s: Printful order ID */
                __('Printful order #%s was canceled.', 'printful-for-fluentcart'),
                $printfulOrderId
            )
        );
    }

    /**
     * pifc/order_address_updated
     *
     * @param Order $order
     * @param int   $printfulOrderId
     * @param mixed $result  API response array or WP_Error.
     */
    public function onOrderAddressUpdated(Order $order, $printfulOrderId, $result)
    {
        if (is_wp_error($result)) {
            $this->log($order, 'warning',
                __('Printful recipient update failed', 'printful-for-fluentcart'),
                sprintf(
                    /* translators: %s: error message */
                    __('Could not update recipient for Printful order #%1$s: %2$s', 'printful-for-fluentcart'),
                    $printfulOrderId,
                    $result->get_error_message()
                )
            );
        } else {
            $this->log($order, 'info',
                __('Printful recipient updated', 'printful-for-fluentcart'),
                sprintf(
                    /* translators: %d: Printful order ID */
                    __('Shipping address synced to Printful order #%d after customer change.', 'printful-for-fluentcart'),
                    $printfulOrderId
                )
            );
        }
    }

    // ─── Internal ─────────────────────────────────────────────────────────────

    /**
     * @param Order  $order
     * @param string $logType   'info' | 'error' | 'warning'
     * @param string $title
     * @param string $content
     */
    private function log(Order $order, $logType, $title, $content)
    {
        try {
            Activity::create([
                'module_id'   => $order->id,
                'module_type' => 'order',
                'module_name' => 'printful',
                'log_type'    => $logType,
                'title'       => $title,
                'content'     => $content,
                'status'      => 'new',
                'read_status' => 0,
                'user_id'     => 0,
                'created_by'  => 'system',
            ]);
        } catch (\Exception $e) {
            // Silently bail — logging should never break the main flow.
        }
    }
}
