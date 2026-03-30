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

		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || empty( $screen->post_type ) || 'post' !== $screen->base ) {
			return;
		}

		$post_type = sanitize_key( $screen->post_type );
		if ( ! $post_type || ! post_type_exists( $post_type ) ) {
			return;
		}

		$template_id   = null;
		$items         = array();
		$template_mode = 'legacy';

		$template_id = EDIWORMAN_Settings::get_template_for_post_type( $post_type );

		if ( $template_id ) {
			$template_id     = absint( $template_id );
			$stored_items_v2 = get_post_meta( $template_id, '_ediworman_items_v2', true );
			$items_v2        = $this->normalize_v2_items( $stored_items_v2 );

			if ( ! empty( $items_v2 ) ) {
				$template_mode = 'v2';
				$items         = $items_v2;
			} else {
				$stored_items = get_post_meta( $template_id, '_ediworman_items', true );
				$items        = $this->normalize_legacy_items( $stored_items );
			}
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

	/**
	 * Normalize legacy label-based items.
	 *
	 * @param mixed $stored_items Raw legacy template items.
	 * @return array<int, array{id:string,label:string,required:bool}>
	 */
	private function normalize_legacy_items( $stored_items ) {
		if ( ! is_array( $stored_items ) ) {
			return array();
		}

		$items = array();
		foreach ( $stored_items as $item ) {
			if ( ! is_scalar( $item ) ) {
				continue;
			}

			$label = sanitize_text_field( (string) $item );
			if ( '' === $label ) {
				continue;
			}

			$items[] = array(
				'id'       => '',
				'label'    => $label,
				'required' => true,
			);
		}

		return $items;
	}

	/**
	 * Normalize v2 object-based template items.
	 *
	 * @param mixed $stored_items Raw v2 template items.
	 * @return array<int, array{id:string,label:string,required:bool}>
	 */
	private function normalize_v2_items( $stored_items ) {
		if ( ! is_array( $stored_items ) ) {
			return array();
		}

		$items = array();
		foreach ( $stored_items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$id = isset( $item['id'] ) ? $this->sanitize_uuid( $item['id'] ) : '';
			if ( '' === $id ) {
				continue;
			}

			$label = isset( $item['label'] ) ? sanitize_text_field( (string) $item['label'] ) : '';
			if ( '' === $label ) {
				continue;
			}

			$items[] = array(
				'id'       => $id,
				'label'    => $label,
				'required' => $this->normalize_required_flag( $item['required'] ?? true ),
			);
		}

		return $items;
	}

	/**
	 * Normalize the required flag to a strict boolean.
	 *
	 * @param mixed $value Raw required value.
	 * @return bool
	 */
	private function normalize_required_flag( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_numeric( $value ) ) {
			return 1 === (int) $value;
		}

		if ( is_string( $value ) ) {
			$value = strtolower( trim( $value ) );
			if ( in_array( $value, array( '1', 'true', 'yes', 'on' ), true ) ) {
				return true;
			}

			if ( in_array( $value, array( '0', 'false', 'no', 'off', '' ), true ) ) {
				return false;
			}
		}

		return (bool) $value;
	}

	/**
	 * Validate and normalize a UUID string.
	 *
	 * @param mixed $value Raw ID value.
	 * @return string
	 */
	private function sanitize_uuid( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$uuid = strtolower( sanitize_text_field( (string) $value ) );
		if ( '' === $uuid ) {
			return '';
		}

		if ( function_exists( 'wp_is_uuid' ) && wp_is_uuid( $uuid ) ) {
			return $uuid;
		}

		return '';
	}
}
