<?php

/**
 * Coordinates webhook + cron driven synchronisation of Printful fulfilment data.
 *
 * @package Printful_Integration_For_Fluentcart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use FluentCart\App\Helpers\Status;
use FluentCart\App\Models\Order;

class Printful_Integration_For_Fluentcart_Sync_Manager {

	/**
	 * Action hook fired by WP-Cron.
	 */
	const CRON_HOOK = 'printful_fluentcart_sync_orders';

	/**
	 * Cron schedule identifier.
	 */
	const CRON_INTERVAL = 'printful_fluentcart_ten_minutes';

	/**
	 * Number of orders to process per cron run.
	 */
	const CRON_BATCH_LIMIT = 5;

	/**
	 * Printful API client.
	 *
	 * @var Printful_Integration_For_Fluentcart_Api
	 */
	protected $api;

	/**
	 * Plugin settings array.
	 *
	 * @var array
	 */
	protected $settings = array();

	/**
	 * Singleton-like pointer for static helpers.
	 *
	 * @var self|null
	 */
	protected static $instance = null;

	/**
	 * Constructor.
	 *
	 * @param Printful_Integration_For_Fluentcart_Api $api API client.
	 * @param array                                   $settings Plugin settings.
	 */
	public function __construct( Printful_Integration_For_Fluentcart_Api $api, array $settings ) {
		$this->api      = $api;
		$this->settings = $settings;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		self::$instance = $this;

		add_filter( 'cron_schedules', array( $this, 'register_cron_schedule' ) );
		add_action( self::CRON_HOOK, array( $this, 'run_scheduled_sync' ) );

		if ( $this->is_polling_enabled() ) {
			$this->ensure_schedule();
		} else {
			$this->clear_schedule();
		}
	}

	/**
	 * Determine whether background polling is enabled.
	 *
	 * @return bool
	 */
	protected function is_polling_enabled() {
		return ! empty( $this->settings['enable_polling'] );
	}

	/**
	 * Public helper to expose current polling state.
	 *
	 * @return bool
	 */
	public function is_polling_active() {
		return $this->is_polling_enabled();
	}

	/**
	 * Determine whether webhooks are enabled.
	 *
	 * @return bool
	 */
	protected function is_webhook_enabled() {
		return ! empty( $this->settings['enable_webhooks'] ) && ! empty( $this->settings['webhook_secret'] );
	}

	/**
	 * Register a custom cron schedule if required.
	 *
	 * @param array $schedules Existing schedules.
	 *
	 * @return array
	 */
	public function register_cron_schedule( $schedules ) {
		if ( isset( $schedules[ self::CRON_INTERVAL ] ) ) {
			return $schedules;
		}

		$interval = isset( $this->settings['poll_interval_minutes'] ) ? (int) $this->settings['poll_interval_minutes'] : 10;
		$interval = max( 5, $interval ); // avoid very aggressive polling.

		$schedules[ self::CRON_INTERVAL ] = array(
			'interval' => $interval * MINUTE_IN_SECONDS,
			'display'  => __( 'Printful integration background sync', 'printful-integration-for-fluentcart' ),
		);

		return $schedules;
	}

	/**
	 * Ensure a cron event exists when polling is enabled.
	 *
	 * @return void
	 */
	public function ensure_schedule() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, self::CRON_INTERVAL, self::CRON_HOOK );
		}
	}

	/**
	 * Cancel scheduled polling.
	 *
	 * @return void
	 */
	public function clear_schedule() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
			$timestamp = wp_next_scheduled( self::CRON_HOOK );
		}
	}

	/**
	 * Entry point for cron-based sync.
	 *
	 * @return void
	 */
	public function run_scheduled_sync() {
		if ( ! $this->is_polling_enabled() ) {
			return;
		}

		$order_ids = Printful_Integration_For_Fluentcart_Sync_Queue::pop_batch( self::CRON_BATCH_LIMIT );

		if ( empty( $order_ids ) ) {
			return;
		}

		foreach ( $order_ids as $order_id ) {
			$success = $this->sync_single_order( $order_id );

			if ( ! $success ) {
				// push back to queue for retry.
				Printful_Integration_For_Fluentcart_Sync_Queue::add( $order_id );
			}
		}
	}

	/**
	 * Process webhook payload using shared logic.
	 *
	 * @param array $payload Webhook payload.
	 *
	 * @return bool
	 */
	public function process_webhook_payload( array $payload ) {
		if ( ! $this->is_webhook_enabled() ) {
			return false;
		}

		$data = isset( $payload['data'] ) ? $payload['data'] : $payload;
		$order_payload = isset( $data['order'] ) && is_array( $data['order'] ) ? $data['order'] : $data;

		if ( empty( $order_payload ) || ! is_array( $order_payload ) ) {
			return false;
		}

		$order = $this->locate_order_from_payload( $order_payload );

		if ( ! $order ) {
			return false;
		}

		$this->apply_remote_state( $order, $order_payload );

		// If fully resolved, remove from polling queue.
		if ( $this->is_terminal_status( isset( $order_payload['status'] ) ? $order_payload['status'] : '' ) ) {
			Printful_Integration_For_Fluentcart_Sync_Queue::remove( $order->id );
		}

		return true;
	}

	/**
	 * Sync a single order from the Printful API.
	 *
	 * @param int $order_id FluentCart order ID.
	 *
	 * @return bool
	 */
	protected function sync_single_order( $order_id ) {
		$order = Order::find( (int) $order_id );

		if ( ! $order ) {
			Printful_Integration_For_Fluentcart_Sync_Queue::remove( $order_id );
			return false;
		}

		$printful_id = $order->getMeta( '_printful_order_id' );

		if ( ! $printful_id ) {
			Printful_Integration_For_Fluentcart_Sync_Queue::remove( $order_id );
			return false;
		}

		$response = $this->api->get( 'orders/' . $printful_id );

		if ( is_wp_error( $response ) ) {
			$this->log_error(
				$order,
				sprintf(
					/* translators: %s: error message */
					__( 'Unable to refresh Printful status: %s', 'printful-integration-for-fluentcart' ),
					$response->get_error_message()
				)
			);
			return false;
		}

		$result = isset( $response['result'] ) ? $response['result'] : $response;

		$this->apply_remote_state( $order, $result );

		if ( $this->is_terminal_status( isset( $result['status'] ) ? $result['status'] : '' ) ) {
			Printful_Integration_For_Fluentcart_Sync_Queue::remove( $order->id );
		} else {
			// keep in queue for future follow-up.
			Printful_Integration_For_Fluentcart_Sync_Queue::add( $order->id );
		}

		return true;
	}

	/**
	 * Apply remote state to a given order.
	 *
	 * @param Order $order FluentCart order.
	 * @param array $remote Remote order payload.
	 *
	 * @return void
	 */
	protected function apply_remote_state( Order $order, array $remote ) {
		$current_status = $order->getMeta( '_printful_last_status' );
		$new_status     = isset( $remote['status'] ) ? $remote['status'] : '';

		if ( $new_status ) {
			$order->updateMeta( '_printful_last_status', $new_status );
		}

		$shipping_status = $this->map_shipping_status( $remote );

		if ( $shipping_status && $order->shipping_status !== $shipping_status ) {
			$order->updateStatus( 'shipping_status', $shipping_status );
			$this->log_info(
				$order,
				sprintf(
					/* translators: %s: status */
					__( 'Shipping status updated to %s via Printful', 'printful-integration-for-fluentcart' ),
					$shipping_status
				)
			);
		}

		$tracking = $this->extract_tracking_info( $remote );
		if ( ! empty( $tracking ) ) {
			$order->updateMeta( '_printful_tracking', $tracking );
		}

		if ( $new_status && $current_status !== $new_status ) {
			$this->log_info(
				$order,
				sprintf(
					/* translators: %s: Printful fulfilment status */
					__( 'Printful fulfilment status is now "%s".', 'printful-integration-for-fluentcart' ),
					$new_status
				)
			);
		}
	}

	/**
	 * Map Printful status to FluentCart shipping status.
	 *
	 * @param array $remote Remote payload.
	 *
	 * @return string|null
	 */
	protected function map_shipping_status( array $remote ) {
		$status     = isset( $remote['status'] ) ? strtolower( $remote['status'] ) : '';
		$shipments  = isset( $remote['shipments'] ) && is_array( $remote['shipments'] ) ? $remote['shipments'] : array();
		$has_shipped = false;
		$delivered   = false;

		foreach ( $shipments as $shipment ) {
			$state = isset( $shipment['status'] ) ? strtolower( $shipment['status'] ) : '';
			if ( in_array( $state, array( 'shipped', 'delivered' ), true ) ) {
				$has_shipped = true;
			}
			if ( 'delivered' === $state || ! empty( $shipment['delivered_at'] ) ) {
				$delivered = true;
			}
		}

		if ( $delivered ) {
			return Status::SHIPPING_DELIVERED;
		}

		if ( $has_shipped || in_array( $status, array( 'fulfilled', 'partial', 'intransit' ), true ) ) {
			return Status::SHIPPING_SHIPPED;
		}

		if ( in_array( $status, array( 'failed', 'canceled' ), true ) ) {
			return Status::SHIPPING_UNSHIPPABLE;
		}

		return Status::SHIPPING_UNSHIPPED;
	}

	/**
	 * Extract tracking info array.
	 *
	 * @param array $remote Remote payload.
	 *
	 * @return array
	 */
	protected function extract_tracking_info( array $remote ) {
		if ( empty( $remote['shipments'] ) || ! is_array( $remote['shipments'] ) ) {
			return array();
		}

		$tracking = array();

		foreach ( $remote['shipments'] as $shipment ) {
			$tracking[] = array(
				'id'             => isset( $shipment['id'] ) ? $shipment['id'] : '',
				'carrier'        => isset( $shipment['carrier'] ) ? $shipment['carrier'] : '',
				'tracking_number'=> isset( $shipment['tracking_number'] ) ? $shipment['tracking_number'] : '',
				'tracking_url'   => isset( $shipment['tracking_url'] ) ? $shipment['tracking_url'] : '',
				'status'         => isset( $shipment['status'] ) ? $shipment['status'] : '',
				'shipped_at'     => isset( $shipment['created_at'] ) ? $shipment['created_at'] : '',
				'delivered_at'   => isset( $shipment['delivered_at'] ) ? $shipment['delivered_at'] : '',
			);
		}

		return $tracking;
	}

	/**
	 * Determine if Printful status is terminal.
	 *
	 * @param string $status Status string.
	 *
	 * @return bool
	 */
	protected function is_terminal_status( $status ) {
		return in_array( strtolower( $status ), array( 'fulfilled', 'failed', 'canceled' ), true );
	}

	/**
	 * Locate order from webhook payload.
	 *
	 * @param array $payload Webhook payload.
	 *
	 * @return Order|null
	 */
	protected function locate_order_from_payload( array $data ) {
		$external_id = isset( $data['external_id'] ) ? $data['external_id'] : '';
		if ( $external_id && is_numeric( $external_id ) ) {
			$order = Order::find( (int) $external_id );
			if ( $order ) {
				return $order;
			}
		}

		$printful_id = isset( $data['id'] ) ? $data['id'] : '';
		if ( $printful_id ) {
			$meta = \FluentCart\App\Models\OrderMeta::query()
				->where( 'meta_key', '_printful_order_id' )
				->where( 'meta_value', $printful_id )
				->first();
			if ( $meta ) {
				return Order::find( $meta->order_id );
			}
		}

		return null;
	}

	/**
	 * Helper for logging informational messages.
	 *
	 * @param Order  $order Order instance.
	 * @param string $message Message body.
	 *
	 * @return void
	 */
	protected function log_info( Order $order, $message ) {
		if ( function_exists( 'fluent_cart_add_log' ) ) {
			fluent_cart_add_log(
				'Printful fulfilment sync',
				$message,
				'info',
				array(
					'module_type' => __CLASS__,
					'module_id'   => $order->id,
					'module_name' => 'Order',
				)
			);
		}
	}

	/**
	 * Helper for logging error messages.
	 *
	 * @param Order  $order Order instance.
	 * @param string $message Message body.
	 *
	 * @return void
	 */
	protected function log_error( Order $order, $message ) {
		if ( function_exists( 'fluent_cart_error_log' ) ) {
			fluent_cart_error_log(
				'Printful fulfilment sync error',
				$message,
				array(
					'order_id' => $order->id,
				)
			);
		}
	}

	/**
	 * Static helper: enqueue a FluentCart order for polling.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return void
	 */
	public static function enqueue_order( $order_id ) {
		Printful_Integration_For_Fluentcart_Sync_Queue::add( $order_id );

		if ( self::$instance && self::$instance->is_polling_active() ) {
			self::$instance->ensure_schedule();
		}
	}
}
