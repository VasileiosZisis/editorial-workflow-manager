<?php
/**
 * Checklist Templates CPT + meta box for checklist items.
 *
 * @package EditorialWorkflowManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the checklist template post type and meta box persistence.
 */
class EDIWORMAN_Templates_CPT {

	/**
	 * Register CPT and meta box hooks.
	 *
	 * @return void
	 */
	public function __construct() {
		// Register CPT on init.
		add_action( 'init', array( $this, 'register_cpt' ) );

		// Meta box for checklist items.
		add_action( 'add_meta_boxes', array( $this, 'register_metaboxes' ) );

		// Admin assets for row editor UX.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Save checklist items.
		add_action( 'save_post_ediworman_template', array( $this, 'save_template_meta' ), 10, 2 );
	}

	/**
	 * Register the "Checklist Template" custom post type.
	 */
	public function register_cpt() {
		$labels = array(
			'name'               => __( 'Checklist Templates', 'editorial-workflow-manager' ),
			'singular_name'      => __( 'Checklist Template', 'editorial-workflow-manager' ),
			'add_new'            => __( 'Add New', 'editorial-workflow-manager' ),
			'add_new_item'       => __( 'Add New Checklist Template', 'editorial-workflow-manager' ),
			'edit_item'          => __( 'Edit Checklist Template', 'editorial-workflow-manager' ),
			'new_item'           => __( 'New Checklist Template', 'editorial-workflow-manager' ),
			'view_item'          => __( 'View Checklist Template', 'editorial-workflow-manager' ),
			'search_items'       => __( 'Search Checklist Templates', 'editorial-workflow-manager' ),
			'not_found'          => __( 'No checklist templates found.', 'editorial-workflow-manager' ),
			'not_found_in_trash' => __( 'No checklist templates found in Trash.', 'editorial-workflow-manager' ),
			'menu_name'          => __( 'Checklist Templates', 'editorial-workflow-manager' ),
		);

		$args = array(
			'labels'              => $labels,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => false,
			'show_in_admin_bar'   => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'has_archive'         => false,
			'rewrite'             => false,
			'supports'            => array( 'title' ),
			'capability_type'     => 'page',
			'map_meta_cap'        => true,
		);

		register_post_type( 'ediworman_template', $args );
	}

	/**
	 * Register meta boxes for checklist templates.
	 *
	 * @return void
	 */
	public function register_metaboxes() {
		add_meta_box(
			'ediworman_template_items',
			__( 'Checklist Items', 'editorial-workflow-manager' ),
			array( $this, 'render_items_metabox' ),
			'ediworman_template',
			'normal',
			'high'
		);
	}

	/**
	 * Enqueue template editor assets on checklist template edit screens.
	 *
	 * @param string $hook_suffix Current admin hook suffix.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'ediworman_template' !== $screen->post_type ) {
			return;
		}

		if ( 'post.php' === $hook_suffix ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only post ID for capability gating on an edit screen.
			$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;
			if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}
		} else {
			$post_type_object = get_post_type_object( 'ediworman_template' );
			if ( ! $post_type_object || empty( $post_type_object->cap->edit_posts ) || ! current_user_can( $post_type_object->cap->edit_posts ) ) {
				return;
			}
		}

		wp_enqueue_script(
			'ediworman-template-editor',
			EDIWORMAN_URL . 'assets/js/template-editor.js',
			array(),
			EDIWORMAN_VERSION,
			true
		);

		wp_localize_script(
			'ediworman-template-editor',
			'EDIWORMAN_TEMPLATE_EDITOR_DATA',
			array(
				'emptyLabelMessage' => __( 'Item label is required.', 'editorial-workflow-manager' ),
			)
		);
	}

	/**
	 * Render the checklist items meta box.
	 *
	 * @param WP_Post $post Current checklist template post.
	 * @return void
	 */
	public function render_items_metabox( $post ) {
		// Security nonce.
		wp_nonce_field( 'ediworman_save_template_items', 'ediworman_template_items_nonce' );
		?>
		<input type="hidden" name="ediworman_template_items_present" value="1" />
		<?php

		$items = $this->get_items_for_editor( $post->ID );
		if ( empty( $items ) ) {
			$items = array(
				array(
					'id'       => '',
					'label'    => '',
					'required' => true,
				),
			);
		}
		?>
		<div id="ediworman-template-items-editor">
			<p>
				<?php esc_html_e( 'Add checklist items, mark each as required or optional, and reorder items with Up/Down buttons.', 'editorial-workflow-manager' ); ?>
			</p>

			<table class="widefat striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Item', 'editorial-workflow-manager' ); ?></th>
						<th scope="col" style="width: 170px;"><?php esc_html_e( 'Type', 'editorial-workflow-manager' ); ?></th>
						<th scope="col" style="width: 240px;"><?php esc_html_e( 'Actions', 'editorial-workflow-manager' ); ?></th>
					</tr>
				</thead>
				<tbody class="ediworman-template-items-rows">
					<?php foreach ( $items as $index => $item ) : ?>
						<?php $this->render_item_row( $item, (string) $index ); ?>
					<?php endforeach; ?>
				</tbody>
			</table>

			<p>
				<button type="button" class="button ediworman-template-item-add">
					<?php esc_html_e( 'Add item', 'editorial-workflow-manager' ); ?>
				</button>
			</p>
		</div>

		<template id="ediworman-template-item-row-template">
			<?php
			$this->render_item_row(
				array(
					'id'       => '',
					'label'    => '',
					'required' => true,
				),
				'__INDEX__'
			);
			?>
		</template>
		<?php
	}

	/**
	 * Render one checklist item row for the editor.
	 *
	 * @param array  $item      Item data.
	 * @param string $row_index Row index token.
	 * @return void
	 */
	private function render_item_row( $item, $row_index ) {
		$item_id           = isset( $item['id'] ) ? $this->sanitize_uuid( $item['id'] ) : '';
		$item_label        = isset( $item['label'] ) ? sanitize_text_field( (string) $item['label'] ) : '';
		$item_is_required  = $this->normalize_required_flag( $item['required'] ?? true );
		$label_input_id    = 'ediworman-template-item-label-' . $row_index;
		$required_input_id = 'ediworman-template-item-required-' . $row_index;
		$row_actions_aria  = sprintf(
			/* translators: %s: row number token */
			__( 'Checklist item row actions %s', 'editorial-workflow-manager' ),
			$row_index
		);
		?>
		<tr class="ediworman-template-item-row">
			<td>
				<label class="screen-reader-text ediworman-template-item-label-label" for="<?php echo esc_attr( $label_input_id ); ?>">
					<?php esc_html_e( 'Checklist item label', 'editorial-workflow-manager' ); ?>
				</label>
				<input
					type="hidden"
					class="ediworman-template-item-id"
					name="ediworman_template_items[id][]"
					value="<?php echo esc_attr( $item_id ); ?>"
				/>
				<input
					type="text"
					class="widefat ediworman-template-item-label"
					id="<?php echo esc_attr( $label_input_id ); ?>"
					name="ediworman_template_items[label][]"
					value="<?php echo esc_attr( $item_label ); ?>"
				/>
				<p class="description ediworman-template-item-error" hidden></p>
			</td>
			<td>
				<label class="screen-reader-text ediworman-template-item-required-label" for="<?php echo esc_attr( $required_input_id ); ?>">
					<?php esc_html_e( 'Checklist item type', 'editorial-workflow-manager' ); ?>
				</label>
				<select
					class="ediworman-template-item-required"
					id="<?php echo esc_attr( $required_input_id ); ?>"
					name="ediworman_template_items[required][]"
				>
					<option value="1" <?php selected( $item_is_required ); ?>>
						<?php esc_html_e( 'Required', 'editorial-workflow-manager' ); ?>
					</option>
					<option value="0" <?php selected( ! $item_is_required ); ?>>
						<?php esc_html_e( 'Optional', 'editorial-workflow-manager' ); ?>
					</option>
				</select>
			</td>
			<td>
				<div class="button-group" role="group" aria-label="<?php echo esc_attr( $row_actions_aria ); ?>">
					<button
						type="button"
						class="button button-small ediworman-template-item-up"
						aria-label="<?php esc_attr_e( 'Move item up', 'editorial-workflow-manager' ); ?>"
						title="<?php esc_attr_e( 'Move item up', 'editorial-workflow-manager' ); ?>"
					>
						<span aria-hidden="true">&uarr;</span>
					</button>
					<button
						type="button"
						class="button button-small ediworman-template-item-down"
						aria-label="<?php esc_attr_e( 'Move item down', 'editorial-workflow-manager' ); ?>"
						title="<?php esc_attr_e( 'Move item down', 'editorial-workflow-manager' ); ?>"
					>
						<span aria-hidden="true">&darr;</span>
					</button>
					<button type="button" class="button button-small button-link-delete ediworman-template-item-remove">
						<?php esc_html_e( 'Remove', 'editorial-workflow-manager' ); ?>
					</button>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save the checklist items when the template is saved.
	 *
	 * @param int     $post_id Post ID being saved.
	 * @param WP_Post $post    Post object being saved.
	 * @return void
	 */
	public function save_template_meta( $post_id, $post ) {
		// Autosave? Bail.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Right post type?
		if ( 'ediworman_template' !== $post->post_type ) {
			return;
		}

		// Nonce check.
		$nonce = isset( $_POST['ediworman_template_items_nonce'] )
			? sanitize_text_field( wp_unslash( $_POST['ediworman_template_items_nonce'] ) )
			: '';

		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'ediworman_save_template_items' ) ) {
			return;
		}

		// Capability check.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['ediworman_template_items_present'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated as array shape, then unslashed and sanitized via map_deep below.
		if ( ! isset( $_POST['ediworman_template_items'] ) || ! is_array( $_POST['ediworman_template_items'] ) ) {
			return;
		}

		$raw_items = map_deep(
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized immediately by map_deep callback.
			wp_unslash( $_POST['ediworman_template_items'] ),
			static function ( $value ) {
				return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : $value;
			}
		);
		if ( ! $this->is_items_request_shape_valid( $raw_items ) ) {
			return;
		}

		$items_v2 = $this->parse_items_v2_from_request( $raw_items );

		if ( empty( $items_v2 ) ) {
			return;
		}

		$legacy_labels = array_map(
			static function ( $item ) {
				return $item['label'];
			},
			$items_v2
		);

		update_post_meta( $post_id, '_ediworman_items_v2', $items_v2 );
		update_post_meta( $post_id, '_ediworman_items', $legacy_labels );
	}

	/**
	 * Validate the expected metabox payload shape.
	 *
	 * @param mixed $raw_items Raw request payload.
	 * @return bool
	 */
	private function is_items_request_shape_valid( $raw_items ) {
		if ( ! is_array( $raw_items ) ) {
			return false;
		}

		$required_keys = array( 'id', 'label', 'required' );
		foreach ( $required_keys as $required_key ) {
			if ( ! array_key_exists( $required_key, $raw_items ) ) {
				return false;
			}

			if ( ! is_array( $raw_items[ $required_key ] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Parse normalized v2 items from metabox request payload.
	 *
	 * @param array $raw_items Raw request payload.
	 * @return array<int, array{id:string,label:string,required:bool}>
	 */
	private function parse_items_v2_from_request( $raw_items ) {
		$ids      = isset( $raw_items['id'] ) && is_array( $raw_items['id'] ) ? $raw_items['id'] : array();
		$labels   = isset( $raw_items['label'] ) && is_array( $raw_items['label'] ) ? $raw_items['label'] : array();
		$required = isset( $raw_items['required'] ) && is_array( $raw_items['required'] ) ? $raw_items['required'] : array();
		$row_max  = max( count( $ids ), count( $labels ), count( $required ) );

		$items_v2 = array();
		$used_ids = array();

		for ( $index = 0; $index < $row_max; $index++ ) {
			$label_value = isset( $labels[ $index ] ) ? sanitize_text_field( trim( (string) $labels[ $index ] ) ) : '';
			if ( '' === $label_value ) {
				continue;
			}

			$item_id = isset( $ids[ $index ] ) ? $this->sanitize_uuid( $ids[ $index ] ) : '';
			if ( '' === $item_id || in_array( $item_id, $used_ids, true ) ) {
				$item_id = $this->generate_unique_uuid( $used_ids );
			}

			$used_ids[] = $item_id;

			$item_required = $this->normalize_required_flag( $required[ $index ] ?? '1' );

			$items_v2[] = array(
				'id'       => $item_id,
				'label'    => $label_value,
				'required' => $item_required,
			);
		}

		return $items_v2;
	}

	/**
	 * Return normalized item rows for the metabox editor.
	 *
	 * @param int $post_id Template post ID.
	 * @return array<int, array{id:string,label:string,required:bool}>
	 */
	private function get_items_for_editor( $post_id ) {
		$items_v2 = $this->get_existing_items_v2( $post_id );
		if ( ! empty( $items_v2 ) ) {
			return $items_v2;
		}

		$legacy_items = get_post_meta( $post_id, '_ediworman_items', true );
		return $this->normalize_legacy_items( $legacy_items );
	}

	/**
	 * Normalize legacy label items for row editor usage.
	 *
	 * @param mixed $legacy_items Legacy label array from _ediworman_items.
	 * @return array<int, array{id:string,label:string,required:bool}>
	 */
	private function normalize_legacy_items( $legacy_items ) {
		if ( ! is_array( $legacy_items ) ) {
			return array();
		}

		$items = array();
		foreach ( $legacy_items as $legacy_item ) {
			if ( ! is_scalar( $legacy_item ) ) {
				continue;
			}

			$parsed = $this->parse_text_line_to_item( (string) $legacy_item );
			if ( null === $parsed ) {
				continue;
			}

			$items[] = array(
				'id'       => '',
				'label'    => $parsed['label'],
				'required' => $parsed['required'],
			);
		}

		return $items;
	}

	/**
	 * Parse a single line into a normalized checklist item.
	 *
	 * Supported optional marker: [optional] My label.
	 *
	 * @param string $line Raw line value.
	 * @return array{label:string,required:bool}|null
	 */
	private function parse_text_line_to_item( $line ) {
		$line = sanitize_text_field( trim( $line ) );
		if ( '' === $line ) {
			return null;
		}

		$required = true;
		if ( 0 === stripos( $line, '[optional]' ) ) {
			$required = false;
			$line     = sanitize_text_field( trim( substr( $line, 10 ) ) );
		}

		if ( '' === $line ) {
			return null;
		}

		return array(
			'label'    => $line,
			'required' => $required,
		);
	}

	/**
	 * Return normalized existing v2 items.
	 *
	 * @param int $post_id Template post ID.
	 * @return array<int, array{id:string,label:string,required:bool}>
	 */
	private function get_existing_items_v2( $post_id ) {
		$stored = get_post_meta( $post_id, '_ediworman_items_v2', true );
		if ( ! is_array( $stored ) ) {
			return array();
		}

		$items = array();
		foreach ( $stored as $item ) {
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
	 * Validate and normalize a UUID value.
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

	/**
	 * Generate a UUID that is unique in the current payload.
	 *
	 * @param array<int, string> $used_ids Already used IDs.
	 * @return string
	 */
	private function generate_unique_uuid( $used_ids ) {
		do {
			$uuid = $this->sanitize_uuid( wp_generate_uuid4() );
		} while ( '' === $uuid || in_array( $uuid, $used_ids, true ) );

		return $uuid;
	}
}
