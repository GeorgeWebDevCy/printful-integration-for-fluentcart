<?php

/**
 * Handles the lifecycle for pushing FluentCart orders to Printful.
 *
 * @package Printful_Integration_For_Fluentcart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Printful_Integration_For_Fluentcart_Order_Sync {

	/**
	 * @var Printful_Integration_For_Fluentcart_Api
	 */
	protected $api;

	/**
	 * Cached settings.
	 *
	 * @var array
	 */
	protected $settings = array();

	/**
	 * Constructor.
	 *
	 * @param Printful_Integration_For_Fluentcart_Api $api API client instance.
	 * @param array                                   $settings Settings array.
	 */
	public function __construct( Printful_Integration_For_Fluentcart_Api $api, array $settings ) {
		$this->api       = $api;
		$this->settings  = $settings;
	}

	/**
	 * Register relevant FluentCart hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'fluent_cart/order_paid_done', array( $this, 'handle_paid_order' ), 20, 1 );
	}

	/**
	 * Manually push a FluentCart order to Printful.
	 *
	 * @param \FluentCart\App\Models\Order $order Order model.
	 *
	 * @return true|\WP_Error
	 */
	public function send_order( $order ) {
		if ( ! $order || ! $this->has_printful_items( $order ) ) {
			return new WP_Error(
				'printful_no_items',
				__( 'No Printful-enabled items were found in this order.', 'printful-integration-for-fluentcart' )
			);
		}

		$already_synced = $order->getMeta( '_printful_order_id' );
		if ( $already_synced ) {
			return new WP_Error(
				'printful_already_synced',
				__( 'Order already sent to Printful.', 'printful-integration-for-fluentcart' )
			);
		}

		$payload = $this->build_order_payload( $order );

		if ( is_wp_error( $payload ) ) {
			$this->log_order_error( $order, $payload->get_error_message() );

			return $payload;
		}

		$response = $this->api->post( 'orders', $payload );

		if ( is_wp_error( $response ) ) {
			$this->log_order_error( $order, $response->get_error_message() );

			return $response;
		}

		$result   = isset( $response['result'] ) ? $response['result'] : $response;
		$order_id = isset( $result['id'] ) ? $result['id'] : null;

		if ( $order_id ) {
			$order->updateMeta( '_printful_order_id', $order_id );
			$this->log_order_info( $order, 'Order queued for Printful fulfilment', array( 'printful_order_id' => $order_id ) );
		}

		if ( isset( $result['status'] ) ) {
			$order->updateMeta( '_printful_last_status', $result['status'] );
		}

		Printful_Integration_For_Fluentcart_Sync_Manager::enqueue_order( $order->id );

		return true;
	}

	/**
	 * Handle paid FluentCart orders and send them to Printful if auto-fulfilment is enabled.
	 *
	 * @param array $event_data Event payload (order, transaction, customer, subscription).
	 *
	 * @return void
	*/
	public function handle_paid_order( $event_data ) {
		if ( empty( $this->settings['auto_fulfill_paid'] ) ) {
			return;
		}

		$order = isset( $event_data['order'] ) ? $event_data['order'] : null;

		if ( ! $order ) {
			return;
		}

		$this->send_order( $order );
	}

	/**
	 * Compose Printful order payload from FluentCart order model.
	 *
	 * @param \FluentCart\App\Models\Order $order Order model.
	 *
	 * @return array|\WP_Error
	 */
	public function build_order_payload( $order ) {
		$relations = array( 'order_items', 'order_items.product', 'order_items.variants', 'shipping_address', 'customer' );
		if ( method_exists( $order, 'loadMissing' ) ) {
			$order->loadMissing( $relations );
		} elseif ( method_exists( $order, 'load' ) ) {
			$order->load( $relations );
		}

		$recipient = $this->format_recipient( $order );

		if ( empty( $recipient ) ) {
			return new WP_Error(
				'printful_missing_recipient',
				__( 'Shipping address is required before the order can be sent to Printful.', 'printful-integration-for-fluentcart' )
			);
		}

		$items = $this->format_items( $order );

		if ( empty( $items ) ) {
			return new WP_Error(
				'printful_missing_items',
				__( 'No Printful-enabled items were found in this order.', 'printful-integration-for-fluentcart' )
			);
		}

		$payload = array(
			'external_id' => (string) $order->id,
			'customer_email' => $order->customer ? $order->customer->email : '',
			'recipient'   => $recipient,
			'items'       => $items,
			'retail_costs' => array(
				'currency' => $order->currency,
				'subtotal' => $this->to_money( $order->subtotal ),
				'shipping' => $this->to_money( $order->shipping_total ),
				'discount' => $this->to_money( (float) $order->manual_discount_total + (float) $order->coupon_discount_total ),
				'tax'      => $this->to_money( $order->tax_total ),
				'total'    => $this->to_money( $order->total_amount ),
			),
			'packing_slip' => array(
				'email' => get_option( 'admin_email' ),
				'phone' => get_option( 'printful_fluentcart_packingslip_phone', '' ),
				'message' => apply_filters( 'printful_fluentcart/packing_slip_message', '', $order ),
			),
		);

		if ( ! empty( $this->settings['enable_printful_tax'] ) ) {
			$payload['external_taxes'] = true;
			$payload['retail_costs']['taxes_included'] = ! empty( $this->settings['tax_inclusive_prices'] );
		}

		$shipping_method = $this->settings['default_shipping_method'];
		if ( $shipping_method ) {
			$payload['shipping'] = $shipping_method;
		}

		return $payload;
	}

	/**
	 * Determine if the current order has any items linked to Printful.
	 *
	 * @param \FluentCart\App\Models\Order $order Order model.
	 *
	 * @return bool
	 */
	protected function has_printful_items( $order ) {
		if ( method_exists( $order, 'loadMissing' ) ) {
			$order->loadMissing( array( 'order_items' ) );
		} elseif ( method_exists( $order, 'load' ) ) {
			$order->load( array( 'order_items' ) );
		}

		foreach ( $order->order_items as $item ) {
			if ( Printful_Integration_For_Fluentcart_Product_Mapping::get_variation_mapping( $item->object_id ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Transform FluentCart order items to Printful line items.
	 *
	 * @param \FluentCart\App\Models\Order $order Order model.
	 *
	 * @return array
	 */
	protected function format_items( $order ) {
		$items = array();

		foreach ( $order->order_items as $item ) {
			$variant_id = Printful_Integration_For_Fluentcart_Product_Mapping::get_variation_mapping( $item->object_id );

			if ( ! $variant_id ) {
				continue;
			}

			// Skip if product-level fulfilment is disabled.
			$product_id = isset( $item->post_id ) ? (int) $item->post_id : 0;
			if ( ! $product_id && class_exists( '\FluentCart\App\Models\ProductVariation' ) ) {
				$variation = \FluentCart\App\Models\ProductVariation::find( $item->object_id );
				$product_id = $variation ? (int) $variation->post_id : 0;
			}
			if ( $product_id && Printful_Integration_For_Fluentcart_Product_Mapping::is_product_disabled( $product_id ) ) {
				continue;
			}

			$service = $product_id ? Printful_Integration_For_Fluentcart_Product_Mapping::get_product_service( $product_id ) : null;

			$items[] = array(
				'external_variant_id' => $variant_id,
				'quantity'            => (int) $item->quantity,
				'price'               => $this->to_money( $item->unit_price ),
				'name'                => $item->post_title ? $item->post_title . ' - ' . $item->title : $item->title,
				'shipping'            => $service ? $service : null,
			);
		}

		return $items;
	}

	/**
	 * Format recipient data for Printful.
	 *
	 * @param \FluentCart\App\Models\Order $order Order model.
	 *
	 * @return array
	 */
	protected function format_recipient( $order ) {
		$address = $order->shipping_address;

		if ( ! $address ) {
			return array();
		}

		$email = $order->customer ? $order->customer->email : '';

		return array(
			'name'         => $address->name,
			'company'      => apply_filters( 'printful_fluentcart/recipient_company', '', $order ),
			'address1'     => $address->address_1,
			'address2'     => $address->address_2,
			'city'         => $address->city,
			'state_code'   => $address->state,
			'country_code' => $address->country,
			'zip'          => $address->postcode,
			'phone'        => apply_filters( 'printful_fluentcart/recipient_phone', '', $order ),
			'email'        => $email,
		);
	}

	/**
	 * Convert numeric totals to Printful compatible string.
	 *
	 * @param float|int|string $amount Monetary amount.
	 *
	 * @return string
	 */
	protected function to_money( $amount ) {
		return number_format( (float) $amount, 2, '.', '' );
	}

	/**
	 * Helper for writing informative log message.
	 *
	 * @param \FluentCart\App\Models\Order $order Order model.
	 * @param string                        $message Message body.
	 * @param array                         $context Extra context.
	 *
	 * @return void
	 */
	protected function log_order_info( $order, $message, $context = array() ) {
		if ( ! function_exists( 'fluent_cart_add_log' ) ) {
			return;
		}

		$context = array_merge(
			array(
				'order_id' => $order->id,
			),
			$context
		);

		fluent_cart_add_log(
			'Printful order sync',
			wp_json_encode( $context ),
			'info',
			array(
				'module_type' => 'PrintfulOrderSync',
				'module_id'   => $order->id,
				'module_name' => 'Order',
			)
		);
	}

	/**
	 * Helper for writing error log.
	 *
	 * @param \FluentCart\App\Models\Order $order Order model.
	 * @param string                        $error Error message.
	 *
	 * @return void
	 */
	protected function log_order_error( $order, $error ) {
		if ( ! function_exists( 'fluent_cart_error_log' ) ) {
			return;
		}

		fluent_cart_error_log(
			'Printful order sync failed',
			$error,
			array(
				'order_id' => $order->id,
			)
		);
	}
}
