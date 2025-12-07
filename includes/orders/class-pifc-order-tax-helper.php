<?php
/**
 * Order tax helper utilities.
 *
 * @package Printful_Integration_For_Fluentcart
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Normalize order tax behaviour when pushing to Printful.
 */
class PIFC_Order_Tax_Helper {

        /**
         * Resolve the most appropriate address for taxes/recipient data.
         *
         * @param object $order     FluentCart order model.
         * @param array  $settings  Plugin settings.
         * @return object|array|null Address object/array or null when unavailable.
         */
        public static function resolve_address( $order, array $settings ) {
                $address = isset( $order->shipping_address ) ? $order->shipping_address : null;

                if ( $address || empty( $settings['sync_printful_tax_addresses'] ) ) {
                        return $address;
                }

                if ( isset( $order->billing_address ) && $order->billing_address ) {
                        return $order->billing_address;
                }

                $origin = isset( $settings['origin_address'] ) ? $settings['origin_address'] : array();
                if ( self::is_valid_origin( $origin ) ) {
                        return (object) array(
                                'name'      => isset( $origin['name'] ) ? $origin['name'] : '',
                                'address_1' => isset( $origin['address_1'] ) ? $origin['address_1'] : '',
                                'address_2' => isset( $origin['address_2'] ) ? $origin['address_2'] : '',
                                'city'      => isset( $origin['city'] ) ? $origin['city'] : '',
                                'state'     => isset( $origin['state'] ) ? $origin['state'] : '',
                                'postcode'  => isset( $origin['postcode'] ) ? $origin['postcode'] : '',
                                'country'   => isset( $origin['country'] ) ? $origin['country'] : '',
                                'phone'     => isset( $origin['phone'] ) ? $origin['phone'] : '',
                        );
                }

                return null;
        }

        /**
         * Determine if origin data is usable for address fallback.
         *
         * @param array $origin Address data.
         * @return bool
         */
        protected static function is_valid_origin( $origin ) {
                if ( ! is_array( $origin ) ) {
                        return false;
                }

                $fields = array( 'address_1', 'city', 'country' );
                foreach ( $fields as $field ) {
                        if ( empty( $origin[ $field ] ) ) {
                                return false;
                        }
                }

                return true;
        }

        /**
         * Apply tax-related payload adjustments.
         *
         * @param array          $payload   Existing payload.
         * @param object         $order     FluentCart order.
         * @param array          $settings  Settings array.
         * @param callable|null  $money_cb  Callback to normalise money values.
         * @return array
         */
        public static function apply_tax_flags( array $payload, $order, array $settings, $money_cb = null ) {
                $sync_rules   = ! empty( $settings['sync_printful_tax_rules'] );
                $tax_enabled  = ! empty( $settings['enable_printful_tax'] ) || $sync_rules;
                $money_cb     = $money_cb ? $money_cb : function ( $amount ) {
                        return number_format( (float) $amount, 2, '.', '' );
                };

                if ( $tax_enabled ) {
                        $payload['external_taxes']                 = true;
                        $payload['retail_costs']['taxes_included'] = ! empty( $settings['tax_inclusive_prices'] );
                }

                if ( $sync_rules ) {
                        $tax_total                                = isset( $order->tax_total ) ? $order->tax_total : 0;
                        $payload['retail_costs']['tax']           = call_user_func( $money_cb, $tax_total );
                        $payload['retail_costs']['total']         = isset( $payload['retail_costs']['total'] ) ? $payload['retail_costs']['total'] : call_user_func( $money_cb, isset( $order->total_amount ) ? $order->total_amount : 0 );
                }

                return $payload;
        }
}
