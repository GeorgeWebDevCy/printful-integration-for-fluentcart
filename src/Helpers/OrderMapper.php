<?php

namespace PrintfulForFluentCart\Helpers;

use FluentCart\App\Models\Order;

defined('ABSPATH') || exit;

/**
 * Converts a FluentCart Order into the data structures expected by Printful.
 */
class OrderMapper
{
    /**
     * Build a Printful recipient object from a FluentCart order.
     *
     * Prefers the shipping address; falls back to billing address.
     * All fields are sanitized before being returned.
     *
     * @param  Order $order  Must have shipping_address / billing_address loaded.
     * @return array|\WP_Error
     */
    public static function buildRecipient(Order $order)
    {
        $address = $order->shipping_address ?? $order->billing_address ?? null;

        if (!$address) {
            return new \WP_Error(
                'pifc_no_address',
                __('Order has no shipping or billing address.', 'printful-for-fluentcart')
            );
        }

        // Resolve full name
        $name = trim($address->name ?? $address->full_name ?? '');
        if ($name === '' && $order->customer) {
            $name = trim(
                ($order->customer->first_name ?? '') . ' ' .
                ($order->customer->last_name  ?? '')
            );
        }

        // Email lives on the customer record
        $email = '';
        if ($order->customer) {
            $email = $order->customer->email ?? '';
        }

        // Phone is stored in address meta
        $meta  = is_array($address->meta) ? $address->meta : [];
        $phone = $meta['phone'] ?? '';

        $recipient = [
            'name'         => sanitize_text_field($name),
            'address1'     => sanitize_text_field($address->address_1 ?? ''),
            'address2'     => sanitize_text_field($address->address_2 ?? ''),
            'city'         => sanitize_text_field($address->city      ?? ''),
            'state_code'   => sanitize_text_field($address->state     ?? ''),
            'country_code' => sanitize_text_field($address->country   ?? ''),
            'zip'          => sanitize_text_field($address->postcode  ?? ''),
            'email'        => sanitize_email($email),
            'phone'        => sanitize_text_field($phone),
        ];

        // Strip empty optional fields so Printful doesn't complain
        foreach (['address2', 'phone'] as $optional) {
            if ($recipient[$optional] === '') {
                unset($recipient[$optional]);
            }
        }

        return $recipient;
    }

    /**
     * Convert FluentCart money (stored in cents as int) to a decimal string.
     *
     * @param  int    $cents
     * @return string  e.g. "24.95"
     */
    public static function centsToDecimal($cents)
    {
        return number_format((int) $cents / 100, 2, '.', '');
    }
}
