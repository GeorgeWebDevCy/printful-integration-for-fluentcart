<?php

/**
 * Live Printful shipping rate integration for FluentCart.
 *
 * @package Printful_Integration_For_Fluentcart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use FluentCart\App\Helpers\CartHelper;
use FluentCart\App\Models\Cart;
use FluentCart\Framework\Support\Arr;
use FluentCart\Framework\Support\Collection;

class Printful_Integration_For_Fluentcart_Shipping {

	/**
	 * @var Printful_Integration_For_Fluentcart_Api
	 */
	protected $api;

	/**
	 * @var array
	 */
	protected $settings = array();

	/**
	 * In-request cache of computed rate sets keyed by cache key.
	 *
	 * @var array
	 */
	protected $runtime_cache = array();

	/**
	 * Constructor.
	 *
	 * @param Printful_Integration_For_Fluentcart_Api $api      API client.
	 * @param array                                   $settings Plugin settings.
	 */
	public function __construct( Printful_Integration_For_Fluentcart_Api $api, array $settings ) {
		$this->api      = $api;
		$this->settings = $settings;
	}

	/**
	 * Register filters/actions if live rates are enabled.
	 *
	 * @return void
	 */
	public function register() {
		if ( empty( $this->settings['enable_live_rates'] ) ) {
			return;
		}

		add_filter( 'fluent_cart/shipping/available_methods', array( $this, 'filter_available_collection' ), 20, 4 );
		add_filter( 'fluent_cart/shipping/methods', array( $this, 'filter_methods' ), 20, 4 );
		add_filter( 'fluent_cart/shipping/resolve_method', array( $this, 'resolve_method' ), 10, 3 );
		add_action( 'fluent_cart/cart/cart_data_items_updated', array( $this, 'flush_cart_cache' ), 20, 1 );
	}

	/**
	 * Replace available shipping methods collection with Printful rates.
	 *
	 * @param mixed $methods   Original collection (Illuminate collection).
	 * @param mixed $country   Country code.
	 * @param mixed $state     State code.
	 * @param mixed $payload   Additional payload.
	 *
	 * @return mixed
	 */
	public function filter_available_collection( $methods, $country, $state, $payload ) {
		$result = $this->get_printful_methods( $country, $state, Arr::get( (array) $payload, 'timezone' ) );

		if ( is_wp_error( $result ) ) {
			return $methods;
		}

		return new Collection( $result );
	}

	/**
	 * Replace raw shipping method arrays.
	 *
	 * @param array       $methods Original methods.
	 * @param string|null $country Country code.
	 * @param string|null $state   State code.
	 * @param string|null $timezone Timezone.
	 *
	 * @return array|WP_Error
	 */
	public function filter_methods( $methods, $country = null, $state = null, $timezone = null ) {
		$result = $this->get_printful_methods( $country, $state, $timezone );

		if ( is_wp_error( $result ) ) {
			return $methods;
		}

		return $result;
	}

	/**
	 * Resolve custom shipping method by ID when not persisted in DB.
	 *
	 * @param mixed $shipping_method Current shipping method instance (or null).
	 * @param mixed $method_id       Requested method ID.
	 * @param Cart  $cart            Active cart.
	 *
	 * @return mixed
	 */
	public function resolve_method( $shipping_method, $method_id, $cart ) {
		if ( $shipping_method || ! is_string( $method_id ) || strpos( $method_id, 'printful:' ) !== 0 ) {
			return $shipping_method;
		}

		$address = $this->determine_address( $cart );
		if ( is_wp_error( $address ) ) {
			return $shipping_method;
		}

		$methods = $this->get_printful_methods( $address['country'], $address['state'], Arr::get( $address, 'timezone' ) );
		if ( is_wp_error( $methods ) ) {
			return $shipping_method;
		}

		foreach ( $methods as $method ) {
			if ( isset( $method->id ) && $method->id === $method_id ) {
				return $method;
			}
		}

		return $shipping_method;
	}

	/**
	 * Flush cached rates when cart items mutate.
	 *
	 * @param array $payload Action payload.
	 *
	 * @return void
	 */
	public function flush_cart_cache( $payload ) {
		$cart = Arr::get( $payload, 'cart' );
		if ( $cart instanceof Cart ) {
			$cache_key = $cart->cart_hash ?? null;
		} else {
			$current   = $this->get_active_cart();
			$cache_key = $current ? $current->cart_hash : null;
		}

		if ( $cache_key ) {
			$transient_key = 'printful_fluentcart_rates_' . $cache_key;
			delete_transient( $transient_key );
			unset( $this->runtime_cache[ $transient_key ] );
		}
	}

	/**
	 * Retrieve Printful methods for given address.
	 *
	 * @param string|null $country  Country code.
	 * @param string|null $state    State code.
	 * @param string|null $timezone Timezone.
	 *
	 * @return array|WP_Error
	 */
	protected function get_printful_methods( $country = null, $state = null, $timezone = null ) {
		$cart = $this->get_active_cart();
		if ( ! $cart ) {
			return new WP_Error( 'printful_cart_missing', __( 'Cart could not be loaded for rate calculation.', 'printful-integration-for-fluentcart' ) );
		}

		$address = $this->determine_address( $cart, $country, $state, $timezone );
		if ( is_wp_error( $address ) ) {
			return $address;
		}

		$items = $this->build_line_items( $cart );
		if ( is_wp_error( $items ) ) {
			return $items;
		}

		if ( empty( $items ) ) {
			// No fulfilment items – fall back to existing methods.
			return new WP_Error( 'printful_no_items', __( 'No Printful enabled items found for shipping.', 'printful-integration-for-fluentcart' ) );
		}

		$cache_key     = $this->build_cache_key( $cart, $address, $items );
		$transient_key = 'printful_fluentcart_rates_' . $cache_key;

		if ( isset( $this->runtime_cache[ $transient_key ] ) ) {
			return $this->runtime_cache[ $transient_key ];
		}

		$cached = get_transient( $transient_key );
		if ( is_array( $cached ) ) {
			$this->runtime_cache[ $transient_key ] = $cached;
			return $cached;
		}

                $request = array(
                        'recipient' => array(
				'name'         => $address['full_name'],
				'address1'     => $address['address_1'],
				'address2'     => $address['address_2'],
				'city'         => $address['city'],
				'state_code'   => $address['state'],
				'country_code' => $address['country'],
				'zip'          => $address['postcode'],
				'phone'        => Arr::get( $address, 'phone', '' ),
			),
			'items'     => $items,
                        'currency'  => $cart->currency ? $cart->currency : $this->get_store_currency(),
			'locale'    => get_locale(),
		);


		$origin = $this->get_origin_for_cart( $cart, $address['country'] );
		if ( $origin ) {
			$request['sender'] = $origin;
		}

		$response = $this->api->post( 'shipping/rates', $request );

		if ( is_wp_error( $response ) ) {
			return $this->maybe_fallback_rate( $response, $cart );
		}

		$result = isset( $response['result'] ) ? $response['result'] : $response;
		if ( empty( $result ) || ! is_array( $result ) ) {
			return new WP_Error( 'printful_invalid_response', __( 'Unexpected response from Printful while fetching rates.', 'printful-integration-for-fluentcart' ) );
		}

		$methods = $this->transform_rates_to_methods( $result, $cart );

		if ( empty( $methods ) ) {
			return $this->maybe_fallback_rate( new WP_Error( 'printful_no_rates', __( 'Printful did not provide any shipping rates for this address.', 'printful-integration-for-fluentcart' ) ), $cart );
		}

		set_transient( $transient_key, $methods, MINUTE_IN_SECONDS * 10 );
		$this->runtime_cache[ $transient_key ] = $methods;

		return $methods;
	}

	/**
	 * Return fallback rate if configured.
	 *
	 * @param WP_Error $error Error from API/rates.
	 * @param Cart     $cart  Cart.
	 *
	 * @return array|WP_Error
	 */
	protected function maybe_fallback_rate( $error, Cart $cart ) {
		$fallback = isset( $this->settings['fallback_rate'] ) && is_array( $this->settings['fallback_rate'] ) ? $this->settings['fallback_rate'] : array();
		$amount   = isset( $fallback['amount'] ) ? floatval( $fallback['amount'] ) : 0;
		$label    = isset( $fallback['label'] ) ? $fallback['label'] : __( 'Standard shipping', 'printful-integration-for-fluentcart' );

		if ( $amount <= 0 ) {
			return $error;
		}

		$currency = $cart->currency ? $cart->currency : $this->get_store_currency();

		$method                = (object) array();
		$method->id            = 'printful:fallback';
		$method->title         = sprintf( '%s (%s)', $label, $currency );
		$method->amount        = round( $amount, 2 );
		$method->charge_amount = intval( round( $amount * 100 ) );
		$method->settings      = array(
			'configure_rate'    => 'per_order',
			'class_aggregation' => 'sum_all',
		);
		$method->states     = array();
		$method->is_enabled = true;
		$method->meta       = array(
			'description'   => __( 'Fallback rate (Printful unavailable).', 'printful-integration-for-fluentcart' ),
			'printful_rate' => array(
				'id'       => 'fallback',
				'name'     => $label,
				'amount'   => round( $amount, 2 ),
				'currency' => $currency,
			),
		);

		return array( $method );
	}

	/**
	 * Build cache key unique to cart, address, and items.
	 *
	 * @param Cart  $cart    Cart instance.
	 * @param array $address Address array.
	 * @param array $items   Items descriptor.
	 *
	 * @return string
	 */
	protected function build_cache_key( Cart $cart, array $address, array $items ) {
		$hash_parts = array(
			$cart->cart_hash,
			wp_json_encode( $address ),
			wp_json_encode( $items ),
		);

		return md5( implode( '|', $hash_parts ) );
	}

	/**
	 * Transform Printful rates to pseudo shipping method objects.
	 *
	 * @param array $rates Printful rate response.
	 * @param Cart  $cart  Cart instance.
	 *
	 * @return array
	 */
	protected function transform_rates_to_methods( array $rates, Cart $cart ) {
                $methods        = array();
                $currency       = $cart->currency ? $cart->currency : $this->get_store_currency();
		$markup_percent = isset( $this->settings['shipping_markup_percent'] ) ? floatval( $this->settings['shipping_markup_percent'] ) : 0;
		$allowed        = isset( $this->settings['allowed_carriers'] ) && is_array( $this->settings['allowed_carriers'] ) ? array_filter( array_map( 'strtolower', $this->settings['allowed_carriers'] ) ) : array();
		$allowed_services = isset( $this->settings['allowed_services'] ) && is_array( $this->settings['allowed_services'] ) ? array_filter( array_map( 'strtolower', $this->settings['allowed_services'] ) ) : array();

		foreach ( $rates as $rate ) {
			$rate_id = Arr::get( $rate, 'id' );
			$name    = Arr::get( $rate, 'name', $rate_id );
			$amount  = floatval( Arr::get( $rate, 'rate', 0 ) );
			$carrier = strtolower( Arr::get( $rate, 'carrier', '' ) );
			$service = strtolower( $rate_id );

			if ( $allowed && $carrier && ! in_array( $carrier, $allowed, true ) ) {
				continue;
			}

			if ( $allowed_services && $service && ! in_array( $service, $allowed_services, true ) ) {
				continue;
			}

			if ( $amount < 0 ) {
				$amount = 0;
			}

			if ( $markup_percent !== 0.0 ) {
				$amount += ( $amount * ( $markup_percent / 100 ) );
			}

			$method                     = (object) array();
			$method->id                 = 'printful:' . $rate_id;
			$method->title              = sprintf( '%s (%s)', $name, strtoupper( Arr::get( $rate, 'currency', $currency ) ) );
			$method->amount             = round( $amount, 2 );
			$method->charge_amount      = intval( round( $amount * 100 ) );
			$method->states             = array();
			$method->is_enabled         = true;
			$method->settings           = array(
				'configure_rate'   => 'per_order',
				'class_aggregation'=> 'sum_all',
			);
			$method->meta               = array(
				'description'    => $this->format_description( $rate ),
				'printful_rate'  => array(
					'id'            => $rate_id,
					'name'          => $name,
					'amount'        => round( $amount, 2 ),
					'currency'      => Arr::get( $rate, 'currency', $currency ),
					'min_days'      => Arr::get( $rate, 'minDeliveryDays' ),
					'max_days'      => Arr::get( $rate, 'maxDeliveryDays' ),
					'carrier'       => Arr::get( $rate, 'carrier' ),
				),
			);

			$methods[ $method->id ] = $method;
		}

		return array_values( $methods );
	}

	/**
	 * Format description based on Printful rate info.
	 *
	 * @param array $rate Rate payload.
	 *
	 * @return string
	 */
	protected function format_description( array $rate ) {
		$min = Arr::get( $rate, 'minDeliveryDays' );
		$max = Arr::get( $rate, 'maxDeliveryDays' );

		if ( $min && $max ) {
			return sprintf(
				/* translators: 1: minimum days, 2: maximum days */
				__( '%1$s - %2$s business days', 'printful-integration-for-fluentcart' ),
				intval( $min ),
				intval( $max )
			);
		}

		if ( $min ) {
			return sprintf(
				/* translators: %s: delivery days */
				__( 'Up to %s business days', 'printful-integration-for-fluentcart' ),
				intval( $min )
			);
		}

		return Arr::get( $rate, 'carrier', '' );
	}

	/**
	 * Build Printful line items.
	 *
	 * @param Cart $cart Cart object.
	 *
	 * @return array|WP_Error
	 */
	protected function build_line_items( Cart $cart ) {
		$items          = array();
		$cart_items     = $cart->cart_data ?? array();
		$has_unmapped   = false;
		$has_physical   = false;

		foreach ( $cart_items as $item ) {
			$fulfillment = Arr::get( $item, 'fulfillment_type', 'physical' );
			if ( $fulfillment !== 'physical' ) {
				continue;
			}

			$has_physical = true;

			$variation_id = Arr::get( $item, 'object_id' );
			if ( ! $variation_id ) {
				continue;
			}

			$product_id = Arr::get( $item, 'post_id' );
			if ( ! $product_id && class_exists( '\FluentCart\App\Models\ProductVariation' ) ) {
				$variation_model = \FluentCart\App\Models\ProductVariation::find( $variation_id );
				$product_id      = $variation_model ? $variation_model->post_id : 0;
			}
			if ( $product_id && Printful_Integration_For_Fluentcart_Product_Mapping::is_product_disabled( $product_id ) ) {
				continue;
			}

			$printful_variant = Printful_Integration_For_Fluentcart_Product_Mapping::get_variation_mapping( $variation_id );
			if ( ! $printful_variant ) {
				$has_unmapped = true;
				continue;
			}

			$quantity = max( 1, intval( Arr::get( $item, 'quantity', 1 ) ) );
			$value    = floatval( Arr::get( $item, 'unit_price', Arr::get( $item, 'price', 0 ) ) );

			$items[] = array(
				'external_variant_id' => (string) $printful_variant,
				'quantity'            => $quantity,
				'value'               => $value,
			);
		}

		if ( $has_unmapped && ! empty( $items ) ) {
			// Mixed cart – fallback to default shipping methods instead of partial.
			return new WP_Error(
				'printful_partial_mapping',
				__( 'Some items are missing Printful mappings; default shipping rates will be used instead.', 'printful-integration-for-fluentcart' )
			);
		}

		if ( ! $has_physical ) {
			return array();
		}

		return $items;
	}

	/**
	 * Determine shipping address for the current cart.
	 *
	 * @param Cart        $cart    Cart instance.
	 * @param string|null $country Country code optional override.
	 * @param string|null $state   State code optional override.
	 * @param string|null $timezone Timezone optional override.
	 *
	 * @return array|WP_Error
	 */
	protected function determine_address( Cart $cart, $country = null, $state = null, $timezone = null ) {
		$address = $cart->getShippingAddress();

		if ( empty( Arr::get( $address, 'country' ) ) && $country ) {
			$address['country'] = $country;
		}

		if ( empty( Arr::get( $address, 'state' ) ) && $state ) {
			$address['state'] = $state;
		}

                if ( empty( Arr::get( $address, 'country' ) ) && $timezone && class_exists( '\\FluentCart\\App\\Services\\LocalizationManager' ) ) {
                        $address['country'] = \FluentCart\App\Services\LocalizationManager::guessCountryFromTimezone( $timezone );
                }

		$required = array( 'address_1', 'city', 'state', 'postcode', 'country' );
		foreach ( $required as $field ) {
			if ( empty( Arr::get( $address, $field ) ) ) {
				return new WP_Error(
					'printful_incomplete_address',
					__( 'Enter full shipping address to calculate Printful live rates.', 'printful-integration-for-fluentcart' )
				);
			}
		}

		$address['full_name'] = Arr::get( $address, 'full_name', $cart->first_name . ' ' . $cart->last_name );
		$address['timezone']  = $timezone;

		return $address;
	}

	/**
	 * Retrieve active cart for the current session.
	 *
	 * @return Cart|null
	 */
        protected function get_active_cart() {
                $cart = CartHelper::getCart();

                return $cart instanceof Cart ? $cart : null;
        }

        /**
         * Retrieve store currency with graceful fallbacks.
         *
         * @return string
         */
	protected function get_store_currency() {
                if ( class_exists( '\\FluentCart\\App\\Services\\Helper' ) && method_exists( '\\FluentCart\\App\\Services\\Helper', 'shopConfig' ) ) {
                        $currency = \FluentCart\App\Services\Helper::shopConfig( 'currency' );
                        if ( $currency ) {
                                return $currency;
                        }
                }

                $currency = get_option( 'woocommerce_currency' );
                if ( $currency ) {
                        return $currency;
                }

                return 'USD';
        }

	/**
	 * Choose origin/sender address based on recipient country.
	 *
	 * @param string $country Country code.
	 *
	 * @return array|null
	 */
	protected function get_origin_for_country( $country ) {
		$country = strtoupper( (string) $country );

		$overrides = isset( $this->settings['origin_overrides'] ) && is_array( $this->settings['origin_overrides'] ) ? $this->settings['origin_overrides'] : array();

		foreach ( $overrides as $entry ) {
			$countries = isset( $entry['countries'] ) ? array_filter( array_map( 'strtoupper', (array) $entry['countries'] ) ) : array();
			if ( $countries && in_array( $country, $countries, true ) ) {
				$origin = $this->format_origin( $entry );
				if ( $origin ) {
					return $origin;
				}
			}
		}

		if ( ! empty( $this->settings['origin_address'] ) ) {
			return $this->format_origin( $this->settings['origin_address'] );
		}

		return null;
	}

	/**
	 * Format origin block.
	 *
	 * @param array $origin Raw origin.
	 *
	 * @return array|null
	 */
	protected function format_origin( array $origin ) {
		if ( empty( $origin['country'] ) ) {
			return null;
		}

		return array(
			'name'         => Arr::get( $origin, 'name', get_bloginfo( 'name' ) ),
			'company'      => Arr::get( $origin, 'company', '' ),
			'address1'     => Arr::get( $origin, 'address_1', '' ),
			'address2'     => Arr::get( $origin, 'address_2', '' ),
			'city'         => Arr::get( $origin, 'city', '' ),
			'state_code'   => Arr::get( $origin, 'state', '' ),
			'country_code' => Arr::get( $origin, 'country', '' ),
			'zip'          => Arr::get( $origin, 'postcode', '' ),
			'phone'        => Arr::get( $origin, 'phone', '' ),
		);
	}

	/**
	 * Determine origin for cart, considering per-product override.
	 *
	 * @param Cart   $cart    Cart.
	 * @param string $country Destination country.
	 *
	 * @return array|null
	 */
	protected function get_origin_for_cart( Cart $cart, $country ) {
		$cart_items = $cart->cart_data ?? array();
		$origin_index = null;

		foreach ( $cart_items as $item ) {
			$product_id = Arr::get( $item, 'post_id' );
			if ( ! $product_id && class_exists( '\FluentCart\App\Models\ProductVariation' ) ) {
				$variation = \FluentCart\App\Models\ProductVariation::find( Arr::get( $item, 'object_id' ) );
				$product_id = $variation ? $variation->post_id : 0;
			}

			if ( $product_id ) {
				$origin_index = Printful_Integration_For_Fluentcart_Product_Mapping::get_product_origin( $product_id );
				if ( null !== $origin_index ) {
					break;
				}
			}
		}

		// If per-product override is set, use that profile directly if available.
		if ( null !== $origin_index ) {
			$overrides = isset( $this->settings['origin_overrides'] ) ? $this->settings['origin_overrides'] : array();
			if ( isset( $overrides[ $origin_index ] ) ) {
				$origin = $this->format_origin( $overrides[ $origin_index ] );
				if ( $origin ) {
					return $origin;
				}
			}
		}

		return $this->get_origin_for_country( $country );
	}
}

