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
	 * Fixed feature announcement identity; routine updates must not reset it.
	 */
	const PUBLICATION_ANNOUNCEMENT_VERSION = '1.3.0';
	const PUBLICATION_ANNOUNCEMENT_USER_OPTION = 'ediworman_publication_announcement_dismissed';
	const PUBLICATION_ANNOUNCEMENT_ACTION = 'ediworman_dismiss_publication_announcement';
	const PUBLICATION_ANNOUNCEMENT_NONCE_NAME = '_ediworman_announcement_nonce';

	/**
	 * Register admin menu and settings hooks.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_publication_announcement' ) );
		add_action( 'wp_ajax_' . self::PUBLICATION_ANNOUNCEMENT_ACTION, array( $this, 'dismiss_publication_announcement_ajax' ) );
		add_action( 'admin_post_' . self::PUBLICATION_ANNOUNCEMENT_ACTION, array( $this, 'dismiss_publication_announcement_post' ) );
	}

	/**
	 * Determine whether this administrator has seen this site's announcement.
	 *
	 * @return bool
	 */
	private function show_publication_announcement() {
		return current_user_can( 'manage_options' ) && self::PUBLICATION_ANNOUNCEMENT_VERSION !== get_user_option( self::PUBLICATION_ANNOUNCEMENT_USER_OPTION );
	}

	/**
	 * Load dismissal behavior only on the settings page while the notice is shown.
	 *
	 * @param string $hook_suffix Current admin screen hook.
	 * @return void
	 */
	public function enqueue_publication_announcement( $hook_suffix ) {
		if ( 'settings_page_ediworman-settings' !== $hook_suffix || ! $this->show_publication_announcement() ) {
			return;
		}
		wp_enqueue_script( 'ediworman-publication-announcement', EDIWORMAN_URL . 'assets/js/publication-announcement.js', array(), EDIWORMAN_VERSION, true );
		wp_localize_script(
			'ediworman-publication-announcement',
			'EDIWORMAN_PUBLICATION_ANNOUNCEMENT',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'error'   => __( 'The announcement could not be dismissed. Please try again.', 'editorial-workflow-manager' ),
			)
		);
	}

	/**
	 * Persist only this user's site-specific dismissal, never publication settings.
	 *
	 * @return bool
	 */
	private function store_publication_announcement_dismissal() {
		return update_user_option( get_current_user_id(), self::PUBLICATION_ANNOUNCEMENT_USER_OPTION, self::PUBLICATION_ANNOUNCEMENT_VERSION, false ) || ! $this->show_publication_announcement();
	}

	/**
	 * Dismiss without navigating away from unsaved settings.
	 *
	 * @return void
	 */
	public function dismiss_publication_announcement_ajax() {
		check_ajax_referer( self::PUBLICATION_ANNOUNCEMENT_ACTION, self::PUBLICATION_ANNOUNCEMENT_NONCE_NAME );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to dismiss this announcement.', 'editorial-workflow-manager' ) ), 403 );
		}
		if ( ! $this->store_publication_announcement_dismissal() ) {
			wp_send_json_error( array( 'message' => __( 'The announcement could not be dismissed. Please try again.', 'editorial-workflow-manager' ) ), 500 );
		}
		wp_send_json_success();
	}

	/**
	 * Accessible form fallback when JavaScript is unavailable.
	 *
	 * @return void
	 */
	public function dismiss_publication_announcement_post() {
		check_admin_referer( self::PUBLICATION_ANNOUNCEMENT_ACTION, self::PUBLICATION_ANNOUNCEMENT_NONCE_NAME );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to dismiss this announcement.', 'editorial-workflow-manager' ), '', array( 'response' => 403 ) );
		}
		if ( ! $this->store_publication_announcement_dismissal() ) {
			wp_die( esc_html__( 'The announcement could not be dismissed. Please try again.', 'editorial-workflow-manager' ), '', array( 'response' => 500, 'back_link' => true ) );
		}
		wp_safe_redirect( admin_url( 'options-general.php?page=ediworman-settings' ) );
		exit;
	}

	/**
	 * Render feature discovery outside the independent Settings API form.
	 *
	 * @return void
	 */
	private function render_publication_announcement() {
		if ( ! $this->show_publication_announcement() ) {
			return;
		}
		?>
		<div id="ediworman-publication-announcement" class="notice notice-info">
			<p><strong><?php esc_html_e( 'New in 1.3.0: Optional publication blocking', 'editorial-workflow-manager' ); ?></strong></p>
			<p><?php esc_html_e( 'No action is required after updating. Advisory remains the default. To require checklist readiness before first publication or scheduling, choose Block for a post type below and save changes. Published and private posts remain editable; scheduled posts are checked again when due.', 'editorial-workflow-manager' ); ?></p>
			<form id="ediworman-publication-announcement-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::PUBLICATION_ANNOUNCEMENT_ACTION ); ?>">
				<?php wp_nonce_field( self::PUBLICATION_ANNOUNCEMENT_ACTION, self::PUBLICATION_ANNOUNCEMENT_NONCE_NAME, false ); ?>
				<p><button type="submit" class="button-link"><?php esc_html_e( 'Dismiss this announcement', 'editorial-workflow-manager' ); ?></button></p>
				<p id="ediworman-publication-announcement-status" role="status" aria-live="polite" aria-atomic="true"></p>
			</form>
		</div>
		<?php
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
		$existing = get_option( self::OPTION_NAME, array() );
		$existing = is_array( $existing ) ? $existing : array();
		if ( ! is_array( $input ) ) {
			return $existing;
		}

		$raw_mappings = isset( $input['post_type_templates'] ) && is_array( $input['post_type_templates'] )
			? $input['post_type_templates']
			: array();

		$mappings = self::sanitize_post_type_template_mappings( $raw_mappings, array_keys( self::get_settings_post_types() ) );
		$modes    = isset( $input['publication_modes'] ) && is_array( $input['publication_modes'] ) ? $input['publication_modes'] : ( $existing['publication_modes'] ?? array() );
		$policies = array();
		foreach ( self::get_settings_post_types() as $post_type => $post_type_object ) {
			$mode = isset( $modes[ $post_type ] ) && 'block' === $modes[ $post_type ] ? 'block' : 'advisory';
			if ( 'block' === $mode && empty( $mappings[ $post_type ] ) && 'block' !== ( $existing['publication_modes'][ $post_type ] ?? '' ) ) {
				$mode = 'advisory';
				add_settings_error( self::OPTION_NAME, 'ediworman_publication_template_' . $post_type, __( 'Assign a valid checklist template before enabling Block publication.', 'editorial-workflow-manager' ) );
			}
			$policies[ $post_type ] = $mode;
		}

		return array( 'post_type_templates' => $mappings, 'publication_modes' => $policies );
	}

	/**
	 * Return the effective publication mode, including orphaned Block policies.
	 *
	 * @param string $post_type Post type slug.
	 * @return string
	 */
	public static function get_publication_mode( $post_type ) {
		if ( ! EDIWORMAN_Readiness::is_cacheable_post_type( $post_type ) || 'revision' === $post_type ) {
			return 'advisory';
		}
		$settings = get_option( self::OPTION_NAME, array() );
		return is_array( $settings ) && 'block' === ( $settings['publication_modes'][ $post_type ] ?? '' ) ? 'block' : 'advisory';
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
			<?php $this->render_publication_announcement(); ?>

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
								<?php esc_html_e( 'Template per post type', 'editorial-workflow-manager' ); ?>
							</th>
							<td>
								<p class="description">
									<?php esc_html_e( 'Choose which checklist template should be used by default for each post type.', 'editorial-workflow-manager' ); ?>
								</p>

								<table>
									<caption class="screen-reader-text">
										<?php esc_html_e( 'Checklist template mappings by post type', 'editorial-workflow-manager' ); ?>
									</caption>
									<thead>
										<tr>
											<th style="text-align:left;"><?php esc_html_e( 'Post type', 'editorial-workflow-manager' ); ?></th>
											<th style="text-align:left;"><?php esc_html_e( 'Checklist template', 'editorial-workflow-manager' ); ?></th>
											<th style="text-align:left;"><?php esc_html_e( 'Publication policy', 'editorial-workflow-manager' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( $post_types as $post_type => $post_type_object ) : ?>
											<?php $select_id = 'ediworman-template-mapping-' . $post_type; ?>
											<tr>
												<th scope="row">
													<label for="<?php echo esc_attr( $select_id ); ?>">
														<?php echo esc_html( $post_type_object->labels->singular_name ); ?>
													</label>
													<br>
													<code><?php echo esc_html( $post_type ); ?></code>
												</th>
												<td>
													<select
														id="<?php echo esc_attr( $select_id ); ?>"
														name="ediworman_settings[post_type_templates][<?php echo esc_attr( $post_type ); ?>]"
													>
														<option value="0"><?php esc_html_e( 'None', 'editorial-workflow-manager' ); ?></option>
														<?php foreach ( $templates as $template ) : ?>
															<option value="<?php echo esc_attr( $template->ID ); ?>" <?php selected( (int) ( $mappings[ $post_type ] ?? 0 ), (int) $template->ID ); ?>>
																<?php echo esc_html( $template->post_title ); ?>
															</option>
														<?php endforeach; ?>
													</select>
												</td>
												<td>
													<label class="screen-reader-text" for="ediworman-publication-<?php echo esc_attr( $post_type ); ?>">
														<?php printf( /* translators: %s: post type name. */ esc_html__( 'Publication policy for %s', 'editorial-workflow-manager' ), esc_html( $post_type_object->labels->singular_name ) ); ?>
													</label>
													<select id="ediworman-publication-<?php echo esc_attr( $post_type ); ?>" name="ediworman_settings[publication_modes][<?php echo esc_attr( $post_type ); ?>]" aria-describedby="ediworman-publication-help">
														<option value="advisory" <?php selected( self::get_publication_mode( $post_type ), 'advisory' ); ?>><?php esc_html_e( 'Advisory', 'editorial-workflow-manager' ); ?></option>
														<option value="block" <?php selected( self::get_publication_mode( $post_type ), 'block' ); ?>><?php esc_html_e( 'Block', 'editorial-workflow-manager' ); ?></option>
													</select>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
								<p id="ediworman-publication-help" class="description"><?php esc_html_e( 'Advisory shows warnings. Block requires completion before first publication or scheduling, and checks scheduled posts again when due. Published and private posts remain editable. If a template is removed, assign a replacement or choose Advisory.', 'editorial-workflow-manager' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>

				<?php submit_button(); ?>
			</form>

			<?php do_action( 'ediworman_after_settings_form' ); ?>
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
