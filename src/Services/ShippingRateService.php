<?php

namespace PrintfulForFluentCart\Services;

use FluentCart\App\Models\ProductMeta;
use PrintfulForFluentCart\Api\PrintfulClient;

defined('ABSPATH') || exit;

/**
 * Fetches live shipping rates from Printful and injects them into the
 * FluentCart checkout flow.
 *
 * Integration point
 * ──────────────────
 * FluentCart fires the filter `fluent_cart/checkout/before_patch_checkout_data`
 * (ShippingModule, priority 9) when the customer changes their shipping address
 * or selects a shipping method. We hook at priority 10 and, if the cart contains
 * Printful-linked physical items, replace the shipping charge with the cheapest
 * Printful rate.
 *
 * The full rates list is also stored in the cart session so the checkout JS
 * can display them; currently FluentCart does not expose a native "select rate"
 * UI, so we default to the cheapest option. A future enhancement can surface
 * all rates via a filter on shipping methods.
 */
class ShippingRateService
{
    /** @var PrintfulClient */
    private $client;

    /** Transient TTL in seconds (30 minutes). */
    const CACHE_TTL = 1800;

    public function __construct()
    {
        $this->client = new PrintfulClient();
    }

    // ─── Hook handler ─────────────────────────────────────────────────────────

    /**
     * Filter: fluent_cart/checkout/before_patch_checkout_data
     *
     * @param  array $fillData  Checkout data being patched.
     * @param  array $data      Raw submitted data.
     * @return array
     */
    public function injectShippingRates(array $fillData, array $data)
    {
        $cartItems = $fillData['cart_items'] ?? $fillData['checkout_data']['cart_items'] ?? [];
        $shipping  = $fillData['checkout_data']['shipping_data'] ?? [];

        $address = [
            'country'  => $shipping['country']  ?? $data['country']  ?? '',
            'state'    => $shipping['state']     ?? $data['state']    ?? '',
            'city'     => $shipping['city']      ?? $data['city']     ?? '',
            'postcode' => $shipping['postcode']  ?? $data['postcode'] ?? '',
            'address_1'=> $shipping['address_1'] ?? $data['address_1']?? '',
        ];

        // Only fetch rates when we have enough address data
        if (empty($address['country']) || empty($address['postcode'])) {
            return $fillData;
        }

        $rates = $this->getRatesForCart($cartItems, $address);

        if (is_wp_error($rates) || empty($rates)) {
            return $fillData;
        }

        // Build a lookup map: service_code → rate object
        $rateByCode = [];
        foreach ($rates as $r) {
            $rateByCode[$r['id'] ?? ''] = $r;
        }

        // If the customer has selected a specific Printful-managed shipping method,
        // use that service code's rate. Otherwise fall back to the cheapest.
        $selectedMethodMeta  = $fillData['checkout_data']['shipping_data']['selected_method_meta'] ?? [];
        $selectedServiceCode = $selectedMethodMeta['printful_service_code'] ?? '';

        if ($selectedServiceCode && isset($rateByCode[$selectedServiceCode])) {
            $chosen = $rateByCode[$selectedServiceCode];
        } else {
            usort($rates, function ($a, $b) {
                return (float) ($a['rate'] ?? 0) <=> (float) ($b['rate'] ?? 0);
            });
            $chosen = $rates[0];
        }

        $rateInCents = (int) round((float) ($chosen['rate'] ?? 0) * 100);

        // Inject into the checkout data path FluentCart reads
        $fillData['checkout_data']['shipping_data']['shipping_charge']    = $rateInCents;
        $fillData['checkout_data']['shipping_data']['shipping_rate_id']   = $chosen['id']   ?? '';
        $fillData['checkout_data']['shipping_data']['shipping_rate_name'] = $chosen['name'] ?? '';

        // Store full rates list for potential front-end use
        $fillData['checkout_data']['shipping_data']['printful_rates'] = array_map(
            function ($r) {
                return [
                    'id'             => $r['id']              ?? '',
                    'name'           => $r['name']            ?? '',
                    'rate'           => $r['rate']            ?? '0.00',
                    'currency'       => $r['currency']        ?? 'USD',
                    'minDeliveryDays'=> $r['minDeliveryDays'] ?? null,
                    'maxDeliveryDays'=> $r['maxDeliveryDays'] ?? null,
                ];
            },
            $rates
        );

        return $fillData;
    }

    // ─── Rate fetching ────────────────────────────────────────────────────────

    /**
     * Fetch shipping rates from Printful for the given cart items and address.
     *
     * Results are cached per unique cart+address combination for 30 minutes.
     *
     * @param  array $cartItems  Cart item rows from FluentCart checkout data.
     * @param  array $address    Keys: country, state, city, postcode, address_1
     * @return array|\WP_Error
     */
    public function getRatesForCart(array $cartItems, array $address)
    {
        $printfulItems = $this->buildItemsForRating($cartItems);

        if (empty($printfulItems)) {
            return [];
        }

        $cacheKey = 'pifc_rates_' . md5(serialize($printfulItems) . serialize($address));
        $cached   = get_transient($cacheKey);

        if ($cached !== false) {
            return $cached;
        }

        $payload = [
            'recipient' => [
                'address1'     => sanitize_text_field($address['address_1'] ?? ''),
                'city'         => sanitize_text_field($address['city']      ?? ''),
                'state_code'   => sanitize_text_field($address['state']     ?? ''),
                'country_code' => strtoupper(sanitize_text_field($address['country'] ?? '')),
                'zip'          => sanitize_text_field($address['postcode']  ?? ''),
            ],
            'items'     => $printfulItems,
            'currency'  => strtoupper(get_woocommerce_currency() !== '' ? get_woocommerce_currency() : 'USD'),
            'locale'    => str_replace('_', '-', get_locale()),
        ];

        $rates = $this->client->getShippingRates($payload);

        if (is_wp_error($rates)) {
            return $rates;
        }

        set_transient($cacheKey, $rates, self::CACHE_TTL);

        return $rates;
    }

    // ─── Item mapping ─────────────────────────────────────────────────────────

    /**
     * Extract Printful-linked physical items from the cart items array.
     *
     * @param  array $cartItems
     * @return array  Printful items array.
     */
    private function buildItemsForRating(array $cartItems)
    {
        $items = [];

        foreach ($cartItems as $item) {
            // Support both keyed and nested structures
            $variationId     = (int) ($item['object_id'] ?? $item['variation_id'] ?? 0);
            $fulfillmentType = $item['fulfillment_type'] ?? '';
            $quantity        = (int) ($item['quantity'] ?? 1);

            if (!$variationId || $fulfillmentType !== 'physical') {
                continue;
            }

            $meta = ProductMeta::where('object_type', 'variation')
                ->where('object_id', $variationId)
                ->where('meta_key', '_printful_sync_variant_id')
                ->first();

            if (!$meta) {
                continue;
            }

            $items[] = [
                'quantity'        => $quantity,
                'sync_variant_id' => (int) $meta->meta_value,
            ];
        }

        return $items;
    }
}
