<?php

/**
 * Size guide shortcode helper.
 *
 * @package Printful_Integration_For_Fluentcart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Printful_Integration_For_Fluentcart_Size_Guide {

	/**
	 * Register shortcode.
	 *
	 * @return void
	 */
	public static function register() {
		add_shortcode( 'printful_size_guide', array( __CLASS__, 'render' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_metabox' ) );
		add_action( 'save_post', array( __CLASS__, 'save_metabox' ) );
	}

	/**
	 * Render size guide based on product meta payload (best effort).
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @return string
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'product_id' => get_the_ID(),
			),
			$atts,
			'printful_size_guide'
		);

		$product_id = (int) $atts['product_id'];

		if ( ! $product_id ) {
			return '';
		}

		$guide = get_post_meta( $product_id, '_printful_size_guide', true );

		if ( empty( $guide ) || ! is_array( $guide ) ) {
			return '';
		}

		$output = '<div class="printful-size-guide"><table><thead><tr><th>' . esc_html__( 'Size', 'printful-integration-for-fluentcart' ) . '</th><th>' . esc_html__( 'Label', 'printful-integration-for-fluentcart' ) . '</th></tr></thead><tbody>';

		foreach ( $guide as $row ) {
			$output .= '<tr><td>' . esc_html( isset( $row['size'] ) ? $row['size'] : '' ) . '</td><td>' . esc_html( isset( $row['label'] ) ? $row['label'] : '' ) . '</td></tr>';
		}

		$output .= '</tbody></table></div>';

		return $output;
	}

	/**
	 * Register size guide metabox on Fluent products.
	 *
	 * @return void
	 */
	public static function register_metabox() {
		add_meta_box(
			'printful_size_guide',
			__( 'Printful Size Guide', 'printful-integration-for-fluentcart' ),
			array( __CLASS__, 'render_metabox' ),
			'fluent-products',
			'side',
			'default'
		);
	}

	/**
	 * Render metabox UI.
	 *
	 * @param WP_Post $post Post object.
	 *
	 * @return void
	 */
	public static function render_metabox( $post ) {
		$guide = get_post_meta( $post->ID, '_printful_size_guide', true );
		if ( ! is_array( $guide ) ) {
			$guide = array();
		}

		wp_nonce_field( 'printful_size_guide_save', 'printful_size_guide_nonce' );

		echo '<p>' . esc_html__( 'Add size label rows (e.g. S, M, L).', 'printful-integration-for-fluentcart' ) . '</p>';
		echo '<table class="widefat"><thead><tr><th>' . esc_html__( 'Size', 'printful-integration-for-fluentcart' ) . '</th><th>' . esc_html__( 'Label', 'printful-integration-for-fluentcart' ) . '</th></tr></thead><tbody id="printful-size-guide-rows">';

		if ( empty( $guide ) ) {
			$guide[] = array( 'size' => '', 'label' => '' );
		}

		foreach ( $guide as $index => $row ) {
			$size  = isset( $row['size'] ) ? $row['size'] : '';
			$label = isset( $row['label'] ) ? $row['label'] : '';
			echo '<tr><td><input type="text" name="printful_size_guide[' . esc_attr( $index ) . '][size]" value="' . esc_attr( $size ) . '" class="small-text" /></td>';
			echo '<td><input type="text" name="printful_size_guide[' . esc_attr( $index ) . '][label]" value="' . esc_attr( $label ) . '" class="regular-text" /></td></tr>';
		}

		echo '</tbody></table>';
		echo '<p><button type="button" class="button" id="printful-size-guide-add-row">' . esc_html__( 'Add row', 'printful-integration-for-fluentcart' ) . '</button></p>';

		// Simple JS to add rows.
		echo '<script>
			(function($){
				var $tbody = $("#printful-size-guide-rows");
				$("#printful-size-guide-add-row").on("click", function(e){
					e.preventDefault();
					var index = $tbody.find("tr").length;
					$tbody.append(\'<tr><td><input type="text" name="printful_size_guide[\'+index+\'][size]" class="small-text" /></td><td><input type="text" name="printful_size_guide[\'+index+\'][label]" class="regular-text" /></td></tr>\');
				});
			})(jQuery);
		</script>';
	}

	/**
	 * Save metabox data.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	public static function save_metabox( $post_id ) {
		if ( ! isset( $_POST['printful_size_guide_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['printful_size_guide_nonce'] ) ), 'printful_size_guide_save' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( get_post_type( $post_id ) !== 'fluent-products' ) {
			return;
		}

		$guide = isset( $_POST['printful_size_guide'] ) ? (array) $_POST['printful_size_guide'] : array();
		$clean = array();

		foreach ( $guide as $row ) {
			$size  = isset( $row['size'] ) ? sanitize_text_field( $row['size'] ) : '';
			$label = isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '';
			if ( '' === $size && '' === $label ) {
				continue;
			}
			$clean[] = array(
				'size'  => $size,
				'label' => $label,
			);
		}

		if ( $clean ) {
			update_post_meta( $post_id, '_printful_size_guide', $clean );
		} else {
			delete_post_meta( $post_id, '_printful_size_guide' );
		}
	}
}
