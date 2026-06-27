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
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueue the sidebar JavaScript and pass checklist data to it.
	 *
	 * @return void
	 */
	public function enqueue() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || empty( $screen->post_type ) || 'post' !== $screen->base ) {
			return;
		}

		$post_type = sanitize_key( $screen->post_type );
		if (
			! $post_type ||
			! post_type_exists( $post_type ) ||
			! post_type_supports( $post_type, 'editor' ) ||
			! use_block_editor_for_post_type( $post_type )
		) {
			return;
		}

		wp_enqueue_script(
			'ediworman-sidebar',
			EDIWORMAN_URL . 'assets/js/sidebar.js',
			array(
				'wp-plugins',
				'wp-edit-post',
				'wp-element',
				'wp-i18n',
				'wp-components',
				'wp-data',
				'wp-core-data',
			),
			EDIWORMAN_VERSION,
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'ediworman-sidebar', 'editorial-workflow-manager', EDIWORMAN_PATH . 'languages' );
		}

		wp_enqueue_style(
			'ediworman-sidebar',
			EDIWORMAN_URL . 'assets/css/sidebar.css',
			array(),
			EDIWORMAN_VERSION
		);

		$template_id   = null;
		$items         = array();
		$template_mode = 'legacy';
		$template_data = EDIWORMAN_Readiness::get_template_data_for_post_type( $post_type );

		if ( null !== $template_data ) {
			$template_id   = $template_data['template_id'];
			$template_mode = $template_data['template_mode'];
			$items         = $template_data['items'];
		}

		wp_localize_script(
			'ediworman-sidebar',
			'EDIWORMAN_CHECKLIST_DATA',
			array(
				'templateId'   => $template_id,
				'postType'     => $post_type,
				'templateMode' => $template_mode,
				'items'        => $items,
			)
		);

		if ( ! $template_id || ! $this->is_editor_tour_request() || EDIWORMAN_Onboarding::has_user_dismissed_editor_tour() ) {
			return;
		}

		wp_enqueue_style(
			'ediworman-sidebar-tour',
			EDIWORMAN_URL . 'assets/css/sidebar-tour.css',
			array(),
			EDIWORMAN_VERSION
		);

		wp_enqueue_script(
			'ediworman-sidebar-tour',
			EDIWORMAN_URL . 'assets/js/sidebar-tour.js',
			array(
				'ediworman-sidebar',
				'wp-plugins',
				'wp-edit-post',
				'wp-element',
				'wp-i18n',
				'wp-components',
				'wp-data',
			),
			EDIWORMAN_VERSION,
			true
		);

		wp_localize_script(
			'ediworman-sidebar-tour',
			'EDIWORMAN_EDITOR_TOUR_DATA',
			EDIWORMAN_Onboarding::get_editor_tour_script_data()
		);
	}

	/**
	 * Return whether the current request explicitly asks for the onboarding tour.
	 *
	 * @return bool
	 */
	private function is_editor_tour_request() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only query arg that controls whether tour assets load.
		$tour_flag = isset( $_GET['ediworman_tour'] )
			? sanitize_text_field(
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only query arg that controls whether tour assets load.
				wp_unslash( $_GET['ediworman_tour'] )
			)
			: '';

		return '1' === $tour_flag;
	}
}
