<?php
/**
 * Enqueue block editor assets (Gutenberg sidebar).
 *
 * @package EditorialWorkflowManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueues editor scripts and localizes checklist data.
 */
class EDIWORMAN_Editor_Assets {

	/**
	 * Register editor enqueue hook.
	 *
	 * @return void
	 */
	public function __construct() {
		// This hook only runs in the block editor.
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueue the sidebar JavaScript and pass checklist data to it.
	 */
	public function enqueue() {
		// Always enqueue the JS in the block editor.
		wp_enqueue_script(
			'ediworman-sidebar',
			EDIWORMAN_URL . 'assets/js/sidebar.js',
			array(
				'wp-plugins',
				'wp-edit-post',
				'wp-element',
				'wp-components',
				'wp-data',
				'wp-core-data',
			),
			EDIWORMAN_VERSION,
			true
		);

		// Try to detect the current post type.
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();

		// We only care about actual post editing screens.
		if ( ! $screen || empty( $screen->post_type ) || 'post' !== $screen->base ) {
			return;
		}

		$post_type = sanitize_key( $screen->post_type );
		if ( ! $post_type || ! post_type_exists( $post_type ) ) {
			return;
		}

		$template_id = null;
		$items       = array();

		$template_id = EDIWORMAN_Settings::get_template_for_post_type( $post_type );

		if ( $template_id ) {
			$template_id  = absint( $template_id );
			$stored_items = get_post_meta( $template_id, '_ediworman_items', true );
			if ( is_array( $stored_items ) ) {
				$items = array_values(
					array_filter(
						array_map( 'sanitize_text_field', $stored_items ),
						'strlen'
					)
				);
			}
		}

		// Pass data to JS (even if items is empty).
		wp_localize_script(
			'ediworman-sidebar',
			'EDIWORMAN_CHECKLIST_DATA',
			array(
				'templateId' => $template_id,
				'postType'   => $post_type,
				'items'      => $items,
			)
		);
	}
}
