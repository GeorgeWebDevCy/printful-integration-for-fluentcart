<?php

/**
 * Admin order actions (manual send/refresh/cancel) and widget display.
 *
 * @package Printful_Integration_For_Fluentcart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use FluentCart\App\Models\Order;
use FluentCart\Framework\Support\Arr;

class Printful_Integration_For_Fluentcart_Order_Actions {

	/**
	 * @var Printful_Integration_For_Fluentcart_Api
	 */
	protected $api;

	/**
	 * @var Printful_Integration_For_Fluentcart_Order_Sync
	 */
	protected $order_sync;

	/**
	 * @var Printful_Integration_For_Fluentcart_Sync_Manager
	 */
	protected $sync_manager;

	/**
	 * @var array
	 */
	protected $settings = array();

	/**
	 * Constructor.
	 *
	 * @param Printful_Integration_For_Fluentcart_Api          $api API client.
	 * @param Printful_Integration_For_Fluentcart_Order_Sync   $order_sync Order sync helper.
	 * @param Printful_Integration_For_Fluentcart_Sync_Manager $sync_manager Sync manager.
	 * @param array                                            $settings Settings.
	 */
	public function __construct( $api, $order_sync, $sync_manager, array $settings ) {
		$this->api          = $api;
		$this->order_sync   = $order_sync;
		$this->sync_manager = $sync_manager;
		$this->settings     = $settings;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'fluent_cart/widgets/single_order', array( $this, 'inject_widget' ), 20, 2 );
		add_action( 'admin_post_printful_fluentcart_order_action', array( $this, 'handle_order_action' ) );
	}

	/**
	 * Add Printful widget to order details.
	 *
	 * @param array $widgets Existing widgets.
	 * @param mixed $order   Order model.
	 *
	 * @return array
	 */
	public function inject_widget( $widgets, $order ) {
		if ( ! $order || ! method_exists( $order, 'getMeta' ) ) {
			return $widgets;
		}

		$printful_id = $order->getMeta( '_printful_order_id' );
		$status      = $order->getMeta( '_printful_last_status' );
		$tracking    = $order->getMeta( '_printful_tracking' );
		$queue       = Printful_Integration_For_Fluentcart_Sync_Queue::all();
		$is_queued   = in_array( $order->id, $queue, true );
		$nonce       = wp_create_nonce( 'printful_fluentcart_order_action' );

		$content  = '<div class="printful-fc-widget">';
		$content .= '<div class="printful-fc-grid">';
		$content .= '<div><span class="printful-fc-label">' . esc_html__( 'Printful order ID', 'printful-integration-for-fluentcart' ) . '</span><span>' . esc_html( $printful_id ? $printful_id : '—' ) . '</span></div>';
		$content .= '<div><span class="printful-fc-label">' . esc_html__( 'Fulfilment status', 'printful-integration-for-fluentcart' ) . '</span><span>' . esc_html( $status ? $status : '—' ) . '</span></div>';
		$content .= '<div><span class="printful-fc-label">' . esc_html__( 'Queued for polling', 'printful-integration-for-fluentcart' ) . '</span><span>' . ( $is_queued ? esc_html__( 'Yes', 'printful-integration-for-fluentcart' ) : esc_html__( 'No', 'printful-integration-for-fluentcart' ) ) . '</span></div>';

		if ( is_array( $tracking ) && ! empty( $tracking ) ) {
			$content .= '<h4>' . esc_html__( 'Tracking', 'printful-integration-for-fluentcart' ) . '</h4>';
			$content .= '<ul>';
			foreach ( $tracking as $track ) {
				$carrier = isset( $track['carrier'] ) ? $track['carrier'] : '';
				$number  = isset( $track['tracking_number'] ) ? $track['tracking_number'] : '';
				$url     = isset( $track['tracking_url'] ) ? $track['tracking_url'] : '';

				$content .= '<li>';
				if ( $url ) {
					$content .= '<a href="' . esc_url( $url ) . '" target="_blank" rel="noreferrer">' . esc_html( $number ) . '</a>';
				} else {
					$content .= esc_html( $number );
				}
				if ( $carrier ) {
					$content .= ' <span style="opacity:0.7">' . esc_html( $carrier ) . '</span>';
				}
				$content .= '</li>';
			}
			$content .= '</ul>';
		}

		$content .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="printful-fc-actions">';
		$content .= '<input type="hidden" name="action" value="printful_fluentcart_order_action" />';
		$content .= '<input type="hidden" name="order_id" value="' . esc_attr( $order->id ) . '" />';
		$content .= '<input type="hidden" name="_wpnonce" value="' . esc_attr( $nonce ) . '" />';

		$disabled_send    = $printful_id ? ' disabled' : '';
		$disabled_refresh = $printful_id ? '' : ' disabled';

		$content .= '<button class="button button-primary" name="operation" value="send"' . $disabled_send . '>' . esc_html__( 'Send to Printful', 'printful-integration-for-fluentcart' ) . '</button>';
		$content .= '<button class="button" name="operation" value="refresh"' . $disabled_refresh . '>' . esc_html__( 'Refresh status', 'printful-integration-for-fluentcart' ) . '</button>';
		$content .= '<button class="button button-secondary" name="operation" value="cancel"' . $disabled_refresh . ' onclick="return confirm(\'' . esc_js( __( 'Cancel this order in Printful?', 'printful-integration-for-fluentcart' ) ) . '\');">' . esc_html__( 'Cancel in Printful', 'printful-integration-for-fluentcart' ) . '</button>';
		$content .= '</form>';

		$content .= '</div>';

		$widgets[] = array(
			'title'     => __( 'Printful Fulfilment', 'printful-integration-for-fluentcart' ),
			'sub_title' => __( 'Sync status & actions', 'printful-integration-for-fluentcart' ),
			'type'      => 'html',
			'content'   => $content,
		);

		return $widgets;
	}

	/**
	 * Handle order actions posted from the widget.
	 *
	 * @return void
	 */
	public function handle_order_action() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'printful-integration-for-fluentcart' ) );
		}

		check_admin_referer( 'printful_fluentcart_order_action' );

		$order_id  = isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$operation = isset( $_POST['operation'] ) ? sanitize_text_field( wp_unslash( $_POST['operation'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$order = Order::find( $order_id );

		if ( ! $order ) {
			wp_die( esc_html__( 'Order not found.', 'printful-integration-for-fluentcart' ) );
		}

		$message = '';
		$error   = null;

		if ( 'send' === $operation ) {
			$result = $this->order_sync->send_order( $order );
			if ( is_wp_error( $result ) ) {
				$error = $result->get_error_message();
			} else {
				$message = __( 'Order sent to Printful.', 'printful-integration-for-fluentcart' );
				$order->addLog( 'Printful', $message, 'activity' );
			}
		} elseif ( 'refresh' === $operation ) {
			$result = $this->sync_manager->sync_single_order( $order_id );
			if ( ! $result ) {
				$error = __( 'Unable to refresh order.', 'printful-integration-for-fluentcart' );
			} else {
				$message = __( 'Printful status refreshed.', 'printful-integration-for-fluentcart' );
				$order->addLog( 'Printful', $message, 'activity' );
			}
		} elseif ( 'cancel' === $operation ) {
			$result = $this->cancel_order( $order );
			if ( is_wp_error( $result ) ) {
				$error = $result->get_error_message();
			} else {
				$message = __( 'Printful order cancellation requested.', 'printful-integration-for-fluentcart' );
				$order->addLog( 'Printful', $message, 'activity' );
			}
		}

		if ( $error ) {
			add_settings_error( 'printful_fluentcart_order', 'printful_fluentcart_order_error', $error, 'error' );
		} elseif ( $message ) {
			add_settings_error( 'printful_fluentcart_order', 'printful_fluentcart_order_message', $message, 'updated' );
		}

		$redirect = wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=fluent-cart#/orders/' . $order_id . '/view' );
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Cancel remote Printful order.
	 *
	 * @param Order $order Order model.
	 *
	 * @return true|\WP_Error
	 */
	protected function cancel_order( $order ) {
		$printful_id = $order->getMeta( '_printful_order_id' );

		if ( ! $printful_id ) {
			return new WP_Error(
				'printful_missing_id',
				__( 'Order has not been sent to Printful yet.', 'printful-integration-for-fluentcart' )
			);
		}

		$response = $this->api->delete( 'orders/' . $printful_id );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$order->updateMeta( '_printful_last_status', 'canceled' );
		Printful_Integration_For_Fluentcart_Sync_Queue::remove( $order->id );

		if ( function_exists( 'fluent_cart_add_log' ) ) {
			fluent_cart_add_log(
				'Printful order cancelled',
				wp_json_encode(
					array(
						'order_id'      => $order->id,
						'printful_id'   => $printful_id,
						'response_code' => Arr::get( $response, 'code', '' ),
					)
				),
				'info',
				array(
					'module_type' => __CLASS__,
					'module_id'   => $order->id,
					'module_name' => 'Order',
				)
			);
		}

		return true;
	}
}
