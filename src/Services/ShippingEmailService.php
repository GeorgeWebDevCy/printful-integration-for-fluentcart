<?php

namespace PrintfulForFluentCart\Services;

use FluentCart\App\Models\Order;

defined('ABSPATH') || exit;

/**
 * Sends a shipping confirmation email to the customer when Printful
 * marks an order as shipped and we receive the package_shipped webhook.
 *
 * Hooks into: pifc/order_shipped
 */
class ShippingEmailService
{
    /**
     * @param Order $order
     * @param array $shipment  Printful shipment object from the webhook payload.
     */
    public function onOrderShipped(Order $order, array $shipment)
    {
        $settings = get_option('pifc_settings', []);

        if (!empty($settings['disable_shipping_email'])) {
            return;
        }

        $order->load(['customer', 'billing_address', 'shipping_address']);

        $email = $this->resolveEmail($order);

        if (!$email || !is_email($email)) {
            return;
        }

        $tracking = $order->getMeta('_printful_tracking', []);
        if (!is_array($tracking)) {
            $tracking = [];
        }

        $subject = apply_filters(
            'pifc/shipping_email_subject',
            sprintf(
                /* translators: %s: site name */
                __('Your order from %s has shipped!', 'printful-for-fluentcart'),
                get_bloginfo('name')
            ),
            $order
        );

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->buildFromHeader(),
        ];

        $body = $this->buildEmailBody($order, $tracking);

        wp_mail($email, $subject, $body, $headers);

        do_action('pifc/shipping_email_sent', $order, $email);
    }

    // ─── Email body ───────────────────────────────────────────────────────────

    /**
     * @param  Order $order
     * @param  array $tracking
     * @return string  HTML
     */
    private function buildEmailBody(Order $order, array $tracking)
    {
        ob_start();
        include PIFC_PLUGIN_DIR . 'views/email/tracking-notification.php';
        return ob_get_clean();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * @param  Order $order
     * @return string
     */
    private function resolveEmail(Order $order)
    {
        if ($order->customer) {
            return $order->customer->email ?? '';
        }

        $billing = $order->billing_address ?? null;
        if ($billing) {
            $meta = is_array($billing->meta) ? $billing->meta : [];
            return $meta['email'] ?? '';
        }

        return '';
    }

    /** @return string */
    private function buildFromHeader()
    {
        $name  = get_bloginfo('name');
        $email = get_option('admin_email');

        $fromEmail = apply_filters('pifc/shipping_email_from_address', $email);
        $fromName  = apply_filters('pifc/shipping_email_from_name',    $name);

        return sprintf('%s <%s>', $fromName, $fromEmail);
    }
}
