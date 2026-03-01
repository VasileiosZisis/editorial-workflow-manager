<?php
/**
 * Post meta for storing checklist state per post.
 *
 * @package EditorialWorkflowManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and maintains editorial checklist post meta.
 */
class EDIWORMAN_Meta {

	/**
	 * Register meta and save handlers.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_meta' ) );
		add_action( 'save_post', array( $this, 'capture_last_editor' ), 10, 1 );
	}

	/**
	 * Sanitize the checked items meta.
	 *
	 * @param mixed $value Raw meta value.
	 * @return array
	 */
	public function sanitize_checked_items( $value ) {
		if ( ! is_array( $value ) ) {
			if ( is_string( $value ) && '' !== $value ) {
				$value = array( $value );
			} else {
				return array();
			}
		}

		$sanitized = array();

		foreach ( $value as $item ) {
			if ( ! is_scalar( $item ) ) {
				continue;
			}

			$label = sanitize_text_field( wp_unslash( (string) $item ) );
			if ( '' === $label ) {
				continue;
			}

			$sanitized[] = $label;
		}

		return array_values( array_unique( $sanitized ) );
	}

	/**
	 * Register post meta used by the plugin.
	 *
	 * - _ediworman_checked_items: array of checklist item labels that are checked.
	 * - _ediworman_last_editor:   user ID of the last editor (for display only).
	 *
	 * @return void
	 */
	public function register_meta() {

		// Checklist state.
		register_post_meta(
			'',
			'_ediworman_checked_items',
			array(
				'single'            => true,
				'type'              => 'array',
				'default'           => array(),
				'show_in_rest'      => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array(
							'type' => 'string',
						),
					),
				),
				'sanitize_callback' => array( $this, 'sanitize_checked_items' ),
				'auth_callback'     => static function ( $allowed, $meta_key, $post_id ) {
					if ( false === $allowed || '_ediworman_checked_items' !== $meta_key ) {
						return false;
					}

					$post_id = (int) $post_id;
					if ( $post_id <= 0 ) {
						return false;
					}

					return current_user_can( 'edit_post', $post_id );
				},
			)
		);

		// Last editor (user ID).
		register_post_meta(
			'',
			'_ediworman_last_editor',
			array(
				'single'            => true,
				'type'              => 'integer',
				'default'           => 0,
				'show_in_rest'      => true,
				'sanitize_callback' => 'absint',
				'auth_callback'     => static function ( $allowed, $meta_key, $post_id ) {
					if ( false === $allowed || '_ediworman_last_editor' !== $meta_key ) {
						return false;
					}

					$post_id = (int) $post_id;
					if ( $post_id <= 0 ) {
						return false;
					}

					return current_user_can( 'edit_post', $post_id );
				},
			)
		);
	}

	/**
	 * Store the last editor's user ID when a post is saved.
	 *
	 * @param int $post_id Post ID being saved.
	 * @return void
	 */
	public function capture_last_editor( $post_id ) {
		// Don't run on autosave / revisions.
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Only for real posts/pages/etc.

		// Permission check.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$user_id = get_current_user_id();
		if ( $user_id ) {
			update_post_meta( $post_id, '_ediworman_last_editor', (int) $user_id );
		}
	}
}
