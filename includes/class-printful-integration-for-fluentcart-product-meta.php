<?php

/**
 * Product-level meta UI (fulfilment overrides, service hints).
 *
 * @package Printful_Integration_For_Fluentcart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Printful_Integration_For_Fluentcart_Product_Meta {

	const META_FULFILMENT = '_printful_fulfilment_mode';
	const META_SERVICE    = '_printful_service_code';
	const META_ORIGIN     = '_printful_origin_index';
	const META_MOCKUP     = '_printful_mockup_url';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'metabox' ) );
		add_action( 'save_post', array( __CLASS__, 'save' ) );
	}

	/**
	 * Register metabox for Fluent products.
	 *
	 * @return void
	 */
	public static function metabox() {
		add_meta_box(
			'printful_product_meta',
			__( 'Printful Fulfilment', 'printful-integration-for-fluentcart' ),
			array( __CLASS__, 'render' ),
			'fluent-products',
			'side',
			'default'
		);
	}

	/**
	 * Render fields.
	 *
	 * @param WP_Post $post Post object.
	 *
	 * @return void
	 */
	public static function render( $post ) {
		wp_nonce_field( 'printful_product_meta', 'printful_product_meta_nonce' );
		$mode    = get_post_meta( $post->ID, self::META_FULFILMENT, true );
		$service = get_post_meta( $post->ID, self::META_SERVICE, true );
		$printful_id = Printful_Integration_For_Fluentcart_Product_Mapping::get_product_mapping( $post->ID );
		$origin_index = get_post_meta( $post->ID, self::META_ORIGIN, true );
		$mockup_url = get_post_meta( $post->ID, self::META_MOCKUP, true );
		$origins = Printful_Integration_For_Fluentcart_Settings::all( array() );
		$origin_overrides = isset( $origins['origin_overrides'] ) && is_array( $origins['origin_overrides'] ) ? $origins['origin_overrides'] : array();

		?>
		<p><strong><?php esc_html_e( 'Fulfilment', 'printful-integration-for-fluentcart' ); ?></strong></p>
		<p>
			<label><input type="radio" name="printful_fulfilment_mode" value="" <?php checked( $mode, '' ); ?> /> <?php esc_html_e( 'Auto (default)', 'printful-integration-for-fluentcart' ); ?></label><br/>
			<label><input type="radio" name="printful_fulfilment_mode" value="disabled" <?php checked( $mode, 'disabled' ); ?> /> <?php esc_html_e( 'Disabled (do not send to Printful)', 'printful-integration-for-fluentcart' ); ?></label>
		</p>
		<p><label for="printful_service_code"><strong><?php esc_html_e( 'Preferred Printful service', 'printful-integration-for-fluentcart' ); ?></strong></label><br/>
			<input type="text" id="printful_service_code" name="printful_service_code" class="regular-text" value="<?php echo esc_attr( $service ); ?>" placeholder="<?php esc_attr_e( 'e.g. STANDARD', 'printful-integration-for-fluentcart' ); ?>" />
		</p>
		<p class="description"><?php esc_html_e( 'Preferred service is passed during order send. Disabled prevents this product’s items from being sent to Printful.', 'printful-integration-for-fluentcart' ); ?></p>
		<p><label for="printful_origin_index"><strong><?php esc_html_e( 'Origin profile', 'printful-integration-for-fluentcart' ); ?></strong></label><br/>
			<select name="printful_origin_index" id="printful_origin_index">
				<option value=""><?php esc_html_e( 'Default', 'printful-integration-for-fluentcart' ); ?></option>
				<?php foreach ( $origin_overrides as $idx => $entry ) : ?>
					<?php
					$label = isset( $entry['countries'] ) ? implode( ',', (array) $entry['countries'] ) : ( isset( $entry['country'] ) ? $entry['country'] : '' );
					?>
					<option value="<?php echo esc_attr( $idx ); ?>" <?php selected( (string) $origin_index, (string) $idx ); ?>><?php echo esc_html( $label ? $label : sprintf( __( 'Origin %d', 'printful-integration-for-fluentcart' ), $idx + 1 ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php if ( $printful_id ) : ?>
			<p>
				<a class="button" href="<?php echo esc_url( 'https://www.printful.com/dashboard/store/products/' . rawurlencode( $printful_id ) ); ?>" target="_blank" rel="noreferrer"><?php esc_html_e( 'Open in Printful', 'printful-integration-for-fluentcart' ); ?></a>
				<?php
				$designer_url = 'https://www.printful.com/dashboard/designer?product=' . rawurlencode( $printful_id );
				$embed        = ! empty( $origins['enable_designer_embed'] );
				?>
				<?php if ( $embed ) : ?>
					<button type="button" class="button button-secondary printful-open-designer" data-designer-url="<?php echo esc_attr( $designer_url ); ?>"><?php esc_html_e( 'Design / Mockup', 'printful-integration-for-fluentcart' ); ?></button>
				<?php else : ?>
					<a class="button button-secondary" href="<?php echo esc_url( $designer_url ); ?>" target="_blank" rel="noreferrer"><?php esc_html_e( 'Design / Mockup', 'printful-integration-for-fluentcart' ); ?></a>
				<?php endif; ?>
			</p>
		<?php endif; ?>
		<p><label for="printful_mockup_url"><strong><?php esc_html_e( 'Mockup preview URL', 'printful-integration-for-fluentcart' ); ?></strong></label><br/>
			<input type="url" id="printful_mockup_url" name="printful_mockup_url" class="widefat" value="<?php echo esc_attr( $mockup_url ); ?>" placeholder="https://..." />
		</p>
		<?php if ( $mockup_url ) : ?>
			<p><img src="<?php echo esc_url( $mockup_url ); ?>" alt="<?php esc_attr_e( 'Mockup preview', 'printful-integration-for-fluentcart' ); ?>" style="max-width:100%;height:auto;border:1px solid #e2e8f0;padding:4px;" /></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Save meta.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	public static function save( $post_id ) {
		if ( ! isset( $_POST['printful_product_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['printful_product_meta_nonce'] ) ), 'printful_product_meta' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( get_post_type( $post_id ) !== 'fluent-products' ) {
			return;
		}

		$mode    = isset( $_POST['printful_fulfilment_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['printful_fulfilment_mode'] ) ) : '';
		$service = isset( $_POST['printful_service_code'] ) ? sanitize_text_field( wp_unslash( $_POST['printful_service_code'] ) ) : '';
		$origin  = isset( $_POST['printful_origin_index'] ) ? sanitize_text_field( wp_unslash( $_POST['printful_origin_index'] ) ) : '';
		$mockup  = isset( $_POST['printful_mockup_url'] ) ? esc_url_raw( wp_unslash( $_POST['printful_mockup_url'] ) ) : '';

		if ( $mode ) {
			update_post_meta( $post_id, self::META_FULFILMENT, $mode );
		} else {
			delete_post_meta( $post_id, self::META_FULFILMENT );
		}

		if ( $service ) {
			update_post_meta( $post_id, self::META_SERVICE, $service );
		} else {
			delete_post_meta( $post_id, self::META_SERVICE );
		}

		if ( $origin !== '' ) {
			update_post_meta( $post_id, self::META_ORIGIN, $origin );
		} else {
			delete_post_meta( $post_id, self::META_ORIGIN );
		}

		if ( $mockup ) {
			update_post_meta( $post_id, self::META_MOCKUP, $mockup );
		} else {
			delete_post_meta( $post_id, self::META_MOCKUP );
		}
	}
}
