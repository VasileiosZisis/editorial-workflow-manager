<?php
/**
 * Plugin settings: map post types to checklist templates.
 *
 * @package EditorialWorkflowManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders plugin settings.
 */
class EDIWORMAN_Settings {

	/**
	 * Option name.
	 */
	const OPTION_NAME = 'ediworman_settings';

	/**
	 * Register admin menu and settings hooks.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add a settings page under "Settings".
	 *
	 * @return void
	 */
	public function register_menu() {
		add_options_page(
			__( 'Editorial Workflow', 'editorial-workflow-manager' ),
			__( 'Editorial Workflow', 'editorial-workflow-manager' ),
			'manage_options',
			'ediworman-settings',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register the settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'ediworman_settings_group',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Sanitize settings on save.
	 *
	 * @param array $input Raw settings input.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		if ( ! is_array( $input ) ) {
			return array(
				'post_type_templates' => array(),
			);
		}

		$raw_mappings = isset( $input['post_type_templates'] ) && is_array( $input['post_type_templates'] )
			? $input['post_type_templates']
			: array();

		return array(
			'post_type_templates' => self::sanitize_post_type_template_mappings(
				$raw_mappings,
				array_keys( self::get_settings_post_types() )
			),
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$mappings   = isset( $settings['post_type_templates'] ) ? $settings['post_type_templates'] : array();
		$post_types = self::get_settings_post_types();
		$templates  = self::get_templates();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Editorial Workflow Settings', 'editorial-workflow-manager' ); ?></h1>

			<p>
				<?php esc_html_e( 'Use checklist templates to enforce a consistent review process before publishing content.', 'editorial-workflow-manager' ); ?>
			</p>

			<div class="notice notice-info">
				<p><strong><?php esc_html_e( 'Getting started', 'editorial-workflow-manager' ); ?></strong></p>
				<ol>
					<li>
						<?php
						printf(
							/* translators: %s: menu label */
							esc_html__( 'Go to %s and create or edit checklist templates (or use the defaults).', 'editorial-workflow-manager' ),
							'<em>' . esc_html__( 'Checklist Templates -> Add New', 'editorial-workflow-manager' ) . '</em>'
						);
						?>
					</li>
					<li>
						<?php esc_html_e( 'Return to this page and map each post type to a checklist template.', 'editorial-workflow-manager' ); ?>
					</li>
					<li>
						<?php esc_html_e( 'Edit a post or page and open the "Editorial Checklist" sidebar in the block editor to see and tick off the checklist items.', 'editorial-workflow-manager' ); ?>
					</li>
					<li>
						<?php esc_html_e( 'If you delete a checklist template, come back here to assign a new template to any post types that were using it.', 'editorial-workflow-manager' ); ?>
					</li>
				</ol>
			</div>

			<form method="post" action="options.php">
				<?php settings_fields( 'ediworman_settings_group' ); ?>

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label><?php esc_html_e( 'Template per post type', 'editorial-workflow-manager' ); ?></label>
							</th>
							<td>
								<p class="description">
									<?php esc_html_e( 'Choose which checklist template should be used by default for each post type.', 'editorial-workflow-manager' ); ?>
								</p>

								<table>
									<thead>
										<tr>
											<th style="text-align:left;"><?php esc_html_e( 'Post type', 'editorial-workflow-manager' ); ?></th>
											<th style="text-align:left;"><?php esc_html_e( 'Checklist template', 'editorial-workflow-manager' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( $post_types as $post_type => $post_type_object ) : ?>
											<tr>
												<td>
													<?php echo esc_html( $post_type_object->labels->singular_name ); ?>
													<br>
													<code><?php echo esc_html( $post_type ); ?></code>
												</td>
												<td>
													<select name="ediworman_settings[post_type_templates][<?php echo esc_attr( $post_type ); ?>]">
														<option value="0"><?php esc_html_e( 'None', 'editorial-workflow-manager' ); ?></option>
														<?php foreach ( $templates as $template ) : ?>
															<option value="<?php echo esc_attr( $template->ID ); ?>" <?php selected( (int) ( $mappings[ $post_type ] ?? 0 ), (int) $template->ID ); ?>>
																<?php echo esc_html( $template->post_title ); ?>
															</option>
														<?php endforeach; ?>
													</select>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</td>
						</tr>
					</tbody>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Helper: get template ID for a given post type.
	 *
	 * Returns null if no valid template exists anymore (e.g. deleted or trashed).
	 *
	 * @param string $post_type Post type slug.
	 * @return int|null
	 */
	public static function get_template_for_post_type( $post_type ) {
		$post_type = sanitize_key( $post_type );
		if ( ! $post_type || ! post_type_exists( $post_type ) ) {
			return null;
		}

		$settings = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		if ( empty( $settings['post_type_templates'][ $post_type ] ) ) {
			return null;
		}

		$template_id = (int) $settings['post_type_templates'][ $post_type ];
		if ( $template_id <= 0 ) {
			return null;
		}

		$template = get_post( $template_id );
		if ( ! $template || 'ediworman_template' !== $template->post_type ) {
			return null;
		}

		if ( 'trash' === $template->post_status ) {
			return null;
		}

		return $template_id;
	}

	/**
	 * Return post types that can be configured on the settings page.
	 *
	 * @return array<string, WP_Post_Type>
	 */
	public static function get_settings_post_types() {
		$post_types = get_post_types(
			array(
				'show_ui' => true,
				'public'  => true,
			),
			'objects'
		);

		unset( $post_types['ediworman_template'] );
		unset( $post_types['attachment'] );

		return $post_types;
	}

	/**
	 * Return post types eligible for the quickstart wizard.
	 *
	 * @return array<string, WP_Post_Type>
	 */
	public static function get_quickstart_post_types() {
		$post_types = get_post_types(
			array(
				'show_ui'      => true,
				'show_in_rest' => true,
			),
			'objects'
		);

		foreach ( $post_types as $post_type => $post_type_object ) {
			if ( 'ediworman_template' === $post_type || 'attachment' === $post_type ) {
				unset( $post_types[ $post_type ] );
				continue;
			}

			if ( ! post_type_supports( $post_type, 'editor' ) ) {
				unset( $post_types[ $post_type ] );
			}
		}

		return $post_types;
	}

	/**
	 * Return checklist templates ordered by title.
	 *
	 * @return array<int, WP_Post>
	 */
	public static function get_templates() {
		return get_posts(
			array(
				'post_type'      => 'ediworman_template',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
	}

	/**
	 * Sanitize post type to checklist template mappings.
	 *
	 * @param array              $raw_mappings       Raw mappings keyed by post type.
	 * @param array<int, string> $allowed_post_types Allowed post type slugs.
	 * @return array<string, int>
	 */
	public static function sanitize_post_type_template_mappings( $raw_mappings, $allowed_post_types = array() ) {
		if ( ! is_array( $raw_mappings ) ) {
			return array();
		}

		$sanitized          = array();
		$allowed_post_types = array_map( 'sanitize_key', $allowed_post_types );
		$allowed_lookup     = ! empty( $allowed_post_types ) ? array_fill_keys( $allowed_post_types, true ) : array();

		foreach ( $raw_mappings as $post_type => $template_id ) {
			$post_type   = sanitize_key( $post_type );
			$template_id = absint( $template_id );

			if ( ! $post_type || ! post_type_exists( $post_type ) ) {
				continue;
			}

			if ( ! empty( $allowed_lookup ) && empty( $allowed_lookup[ $post_type ] ) ) {
				continue;
			}

			if ( 'ediworman_template' === $post_type || 'attachment' === $post_type ) {
				continue;
			}

			if ( $template_id <= 0 ) {
				continue;
			}

			$template = get_post( $template_id );
			if ( ! $template || 'ediworman_template' !== $template->post_type ) {
				continue;
			}

			if ( 'trash' === $template->post_status ) {
				continue;
			}

			$sanitized[ $post_type ] = (int) $template_id;
		}

		return $sanitized;
	}

	/**
	 * Count checklist items stored on a template.
	 *
	 * @param int $template_id Checklist template ID.
	 * @return int
	 */
	public static function get_template_item_count( $template_id ) {
		$template_id = absint( $template_id );
		if ( $template_id <= 0 ) {
			return 0;
		}

		$items_v2 = get_post_meta( $template_id, '_ediworman_items_v2', true );
		if ( is_array( $items_v2 ) ) {
			return count( $items_v2 );
		}

		$legacy_items = get_post_meta( $template_id, '_ediworman_items', true );
		if ( is_array( $legacy_items ) ) {
			return count( $legacy_items );
		}

		return 0;
	}
}
