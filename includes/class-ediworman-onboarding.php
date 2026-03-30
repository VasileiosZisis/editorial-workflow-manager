<?php
/**
 * Quickstart wizard and editor tour onboarding.
 *
 * @package EditorialWorkflowManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles first-run quickstart and editor tour flows.
 */
class EDIWORMAN_Onboarding {

	/**
	 * Site option tracking whether quickstart is pending.
	 */
	const QUICKSTART_PENDING_OPTION = 'ediworman_quickstart_pending_version';

	/**
	 * Per-user dismissal meta for the quickstart redirect.
	 */
	const QUICKSTART_DISMISSED_USER_META = 'ediworman_quickstart_dismissed_version';

	/**
	 * Per-user dismissal meta for the editor tour.
	 */
	const EDITOR_TOUR_DISMISSED_USER_META = 'ediworman_editor_tour_dismissed_version';

	/**
	 * Hidden admin page slug.
	 */
	const QUICKSTART_PAGE_SLUG = 'ediworman-quickstart';

	/**
	 * Register hooks for onboarding flows.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'current_screen', array( $this, 'maybe_redirect_to_quickstart' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_post_ediworman_quickstart_save', array( $this, 'handle_quickstart_save' ) );
		add_action( 'admin_post_ediworman_quickstart_skip', array( $this, 'handle_quickstart_skip' ) );
		add_action( 'wp_ajax_ediworman_dismiss_editor_tour', array( $this, 'handle_editor_tour_dismissal' ) );
	}

	/**
	 * Register the hidden quickstart page.
	 *
	 * @return void
	 */
	public function register_admin_page() {
		add_submenu_page(
			null,
			__( 'Editorial Workflow Quickstart', 'editorial-workflow-manager' ),
			__( 'Editorial Workflow Quickstart', 'editorial-workflow-manager' ),
			'manage_options',
			self::QUICKSTART_PAGE_SLUG,
			array( $this, 'render_quickstart_page' )
		);
	}

	/**
	 * Redirect eligible admins to the quickstart page when onboarding is pending.
	 *
	 * @param WP_Screen $screen Current admin screen.
	 * @return void
	 */
	public function maybe_redirect_to_quickstart( $screen ) {
		if ( ! $screen instanceof WP_Screen ) {
			return;
		}

		if ( ! $this->should_redirect_to_quickstart( $screen ) ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::QUICKSTART_PAGE_SLUG ) );
		exit;
	}

	/**
	 * Enqueue quickstart page assets.
	 *
	 * @return void
	 */
	public function enqueue_admin_assets() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page check for conditional asset loading.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( self::QUICKSTART_PAGE_SLUG !== $page ) {
			return;
		}

		wp_enqueue_style(
			'ediworman-quickstart',
			EDIWORMAN_URL . 'assets/css/quickstart.css',
			array(),
			EDIWORMAN_VERSION
		);

		wp_enqueue_script(
			'ediworman-quickstart',
			EDIWORMAN_URL . 'assets/js/quickstart.js',
			array(),
			EDIWORMAN_VERSION,
			true
		);

		$defaults = $this->get_quickstart_defaults();
		wp_localize_script(
			'ediworman-quickstart',
			'EDIWORMAN_QUICKSTART_DATA',
			array(
				'selectedPostTypes' => $defaults['selected_post_types'],
				'noSelectionText'   => __( 'Select at least one post type to continue.', 'editorial-workflow-manager' ),
			)
		);
	}

	/**
	 * Render the quickstart page.
	 *
	 * @return void
	 */
	public function render_quickstart_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$defaults      = $this->get_quickstart_defaults();
		$post_types    = $defaults['post_types'];
		$templates     = $defaults['templates'];
		$selected      = $defaults['selected_post_types'];
		$template_map  = $defaults['template_selections'];
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only error code for notice rendering.
		$error_code    = isset( $_GET['ediworman-quickstart-error'] ) ? sanitize_key( wp_unslash( $_GET['ediworman-quickstart-error'] ) ) : '';
		$has_posttypes = ! empty( $post_types );
		?>
		<div class="wrap ediworman-quickstart">
			<div class="ediworman-quickstart__hero">
				<p class="ediworman-quickstart__eyebrow"><?php esc_html_e( 'Editorial Workflow Manager', 'editorial-workflow-manager' ); ?></p>
				<h1><?php esc_html_e( 'Set up your first checklist in 60 seconds', 'editorial-workflow-manager' ); ?></h1>
				<p class="ediworman-quickstart__lead">
					<?php esc_html_e( 'Choose where editorial checklists should appear, assign a starter template, and jump straight into the block editor.', 'editorial-workflow-manager' ); ?>
				</p>
			</div>

			<?php if ( 'no-post-types' === $error_code ) : ?>
				<div class="notice notice-warning">
					<p><?php esc_html_e( 'Choose at least one post type before continuing.', 'editorial-workflow-manager' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( ! $has_posttypes ) : ?>
				<div class="notice notice-warning">
					<p><?php esc_html_e( 'No block-editor post types are currently eligible for quickstart setup. You can configure mappings later in Editorial Workflow settings.', 'editorial-workflow-manager' ); ?></p>
				</div>
			<?php endif; ?>

			<form
				id="ediworman-quickstart-save-form"
				class="ediworman-quickstart__form"
				method="post"
				action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
			>
				<input type="hidden" name="action" value="ediworman_quickstart_save" />
				<?php wp_nonce_field( 'ediworman_quickstart_save', 'ediworman_quickstart_nonce' ); ?>

				<div class="ediworman-quickstart__grid">
					<section class="ediworman-quickstart__panel" aria-labelledby="ediworman-quickstart-step-post-types">
						<div class="ediworman-quickstart__panel-header">
							<span class="ediworman-quickstart__step"><?php esc_html_e( 'Step 1', 'editorial-workflow-manager' ); ?></span>
							<h2 id="ediworman-quickstart-step-post-types"><?php esc_html_e( 'Choose post types', 'editorial-workflow-manager' ); ?></h2>
						</div>
						<p class="description">
							<?php esc_html_e( 'Only post types that support the block editor are shown here.', 'editorial-workflow-manager' ); ?>
						</p>

						<div class="ediworman-quickstart__post-types">
							<?php foreach ( $post_types as $post_type => $post_type_object ) : ?>
								<label class="ediworman-quickstart__choice">
									<input
										type="checkbox"
										name="ediworman_quickstart_post_types[]"
										value="<?php echo esc_attr( $post_type ); ?>"
										data-post-type-checkbox="<?php echo esc_attr( $post_type ); ?>"
										<?php checked( in_array( $post_type, $selected, true ) ); ?>
									/>
									<span>
										<strong><?php echo esc_html( $post_type_object->labels->singular_name ); ?></strong>
										<code><?php echo esc_html( $post_type ); ?></code>
									</span>
								</label>
							<?php endforeach; ?>
						</div>
					</section>

					<section class="ediworman-quickstart__panel" aria-labelledby="ediworman-quickstart-step-templates">
						<div class="ediworman-quickstart__panel-header">
							<span class="ediworman-quickstart__step"><?php esc_html_e( 'Step 2', 'editorial-workflow-manager' ); ?></span>
							<h2 id="ediworman-quickstart-step-templates"><?php esc_html_e( 'Assign starter templates', 'editorial-workflow-manager' ); ?></h2>
						</div>
						<p class="description">
							<?php esc_html_e( 'You can change these mappings later in Settings -> Editorial Workflow.', 'editorial-workflow-manager' ); ?>
						</p>

						<div class="ediworman-quickstart__mappings">
							<?php foreach ( $post_types as $post_type => $post_type_object ) : ?>
								<?php
								$is_selected          = in_array( $post_type, $selected, true );
								$selected_template_id = isset( $template_map[ $post_type ] ) ? (int) $template_map[ $post_type ] : 0;
								?>
								<div
									class="ediworman-quickstart__mapping"
									data-post-type-row="<?php echo esc_attr( $post_type ); ?>"
									<?php echo $is_selected ? '' : 'hidden'; ?>
								>
									<label for="ediworman-quickstart-template-<?php echo esc_attr( $post_type ); ?>">
										<?php
										printf(
											/* translators: %s: post type singular label */
											esc_html__( '%s checklist template', 'editorial-workflow-manager' ),
											esc_html( $post_type_object->labels->singular_name )
										);
										?>
									</label>
									<select
										id="ediworman-quickstart-template-<?php echo esc_attr( $post_type ); ?>"
										name="ediworman_quickstart_templates[<?php echo esc_attr( $post_type ); ?>]"
										data-template-select="<?php echo esc_attr( $post_type ); ?>"
									>
										<option value="0" data-preview="<?php echo esc_attr__( 'No template selected.', 'editorial-workflow-manager' ); ?>">
											<?php esc_html_e( 'None', 'editorial-workflow-manager' ); ?>
										</option>
										<?php foreach ( $templates as $template ) : ?>
											<?php $preview_text = $this->get_template_preview_text( $template ); ?>
											<option
												value="<?php echo esc_attr( $template->ID ); ?>"
												data-preview="<?php echo esc_attr( $preview_text ); ?>"
												<?php selected( $selected_template_id, (int) $template->ID ); ?>
											>
												<?php echo esc_html( $template->post_title ); ?>
											</option>
										<?php endforeach; ?>
									</select>
									<p class="description" data-template-preview-for="<?php echo esc_attr( $post_type ); ?>">
										<?php echo esc_html( $this->get_template_preview_for_selection( $selected_template_id, $templates ) ); ?>
									</p>
								</div>
							<?php endforeach; ?>
						</div>
					</section>
				</div>
			</form>

			<form
				id="ediworman-quickstart-skip-form"
				method="post"
				action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
			>
				<input type="hidden" name="action" value="ediworman_quickstart_skip" />
				<?php wp_nonce_field( 'ediworman_quickstart_skip', 'ediworman_quickstart_skip_nonce' ); ?>
			</form>

			<div class="ediworman-quickstart__actions">
				<p class="ediworman-quickstart__actions-note" data-no-selection-message>
					<?php esc_html_e( 'Select at least one post type to continue.', 'editorial-workflow-manager' ); ?>
				</p>
				<div class="ediworman-quickstart__actions-buttons">
					<button
						type="submit"
						form="ediworman-quickstart-skip-form"
						class="button button-secondary"
					>
						<?php esc_html_e( 'Skip for now', 'editorial-workflow-manager' ); ?>
					</button>
					<button
						type="submit"
						form="ediworman-quickstart-save-form"
						class="button button-primary button-hero"
						<?php disabled( ! $has_posttypes ); ?>
					>
						<?php esc_html_e( 'Finish setup and open editor', 'editorial-workflow-manager' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle quickstart save submissions.
	 *
	 * @return void
	 */
	public function handle_quickstart_save() {
		check_admin_referer( 'ediworman_quickstart_save', 'ediworman_quickstart_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to perform this action.', 'editorial-workflow-manager' ), 403 );
		}

		$eligible_post_types = EDIWORMAN_Settings::get_quickstart_post_types();
		$raw_selected_post_types = filter_input(
			INPUT_POST,
			'ediworman_quickstart_post_types',
			FILTER_DEFAULT,
			array(
				'flags' => FILTER_REQUIRE_ARRAY,
			)
		);
		if ( ! is_array( $raw_selected_post_types ) ) {
			$raw_selected_post_types = array();
		}

		$raw_selected_post_types = map_deep(
			$raw_selected_post_types,
			static function ( $value ) {
				return is_scalar( $value ) ? sanitize_key( (string) $value ) : $value;
			}
		);

		$selected_post_types = $this->sanitize_selected_post_types(
			$raw_selected_post_types,
			array_keys( $eligible_post_types )
		);

		if ( empty( $selected_post_types ) ) {
			wp_safe_redirect(
				add_query_arg(
					'ediworman-quickstart-error',
					'no-post-types',
					admin_url( 'admin.php?page=' . self::QUICKSTART_PAGE_SLUG )
				)
			);
			exit;
		}

		$raw_mappings = filter_input(
			INPUT_POST,
			'ediworman_quickstart_templates',
			FILTER_DEFAULT,
			array(
				'flags' => FILTER_REQUIRE_ARRAY,
			)
		);
		if ( ! is_array( $raw_mappings ) ) {
			$raw_mappings = array();
		}

		$raw_mappings = map_deep(
			$raw_mappings,
			static function ( $value ) {
				return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : $value;
			}
		);

		$sanitized_mappings = EDIWORMAN_Settings::sanitize_post_type_template_mappings( $raw_mappings, $selected_post_types );
		$settings           = get_option( EDIWORMAN_Settings::OPTION_NAME, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		if ( ! isset( $settings['post_type_templates'] ) || ! is_array( $settings['post_type_templates'] ) ) {
			$settings['post_type_templates'] = array();
		}

		foreach ( array_keys( $eligible_post_types ) as $post_type ) {
			unset( $settings['post_type_templates'][ $post_type ] );
		}

		foreach ( $sanitized_mappings as $post_type => $template_id ) {
			$settings['post_type_templates'][ $post_type ] = (int) $template_id;
		}

		update_option( EDIWORMAN_Settings::OPTION_NAME, $settings );
		delete_option( self::QUICKSTART_PENDING_OPTION );

		$redirect_post_type = reset( $selected_post_types );
		$redirect_url       = add_query_arg(
			array(
				'post_type'      => $redirect_post_type,
				'ediworman_tour' => '1',
			),
			admin_url( 'post-new.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Handle quickstart skip submissions.
	 *
	 * @return void
	 */
	public function handle_quickstart_skip() {
		check_admin_referer( 'ediworman_quickstart_skip', 'ediworman_quickstart_skip_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to perform this action.', 'editorial-workflow-manager' ), 403 );
		}

		update_user_meta( get_current_user_id(), self::QUICKSTART_DISMISSED_USER_META, EDIWORMAN_VERSION );

		wp_safe_redirect( admin_url( 'options-general.php?page=ediworman-settings' ) );
		exit;
	}

	/**
	 * Handle AJAX tour dismissal.
	 *
	 * @return void
	 */
	public function handle_editor_tour_dismissal() {
		check_ajax_referer( 'ediworman_dismiss_editor_tour', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You are not allowed to perform this action.', 'editorial-workflow-manager' ),
				),
				403
			);
		}

		update_user_meta( get_current_user_id(), self::EDITOR_TOUR_DISMISSED_USER_META, EDIWORMAN_VERSION );
		wp_send_json_success();
	}

	/**
	 * Return whether the current user has dismissed the quickstart for this version.
	 *
	 * @return bool
	 */
	public static function has_user_dismissed_quickstart() {
		return EDIWORMAN_VERSION === get_user_meta( get_current_user_id(), self::QUICKSTART_DISMISSED_USER_META, true );
	}

	/**
	 * Return whether the current user has dismissed the editor tour for this version.
	 *
	 * @return bool
	 */
	public static function has_user_dismissed_editor_tour() {
		return EDIWORMAN_VERSION === get_user_meta( get_current_user_id(), self::EDITOR_TOUR_DISMISSED_USER_META, true );
	}

	/**
	 * Return AJAX data for the editor tour script.
	 *
	 * @return array<string, string|bool>
	 */
	public static function get_editor_tour_script_data() {
		return array(
			'isActive'      => true,
			'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
			'nonce'         => wp_create_nonce( 'ediworman_dismiss_editor_tour' ),
			'dismissAction' => 'ediworman_dismiss_editor_tour',
			'sidebarTarget' => 'ediworman-checklist-plugin/ediworman-checklist-sidebar',
		);
	}

	/**
	 * Determine whether the current admin request should redirect to quickstart.
	 *
	 * @param WP_Screen $screen Current screen.
	 * @return bool
	 */
	private function should_redirect_to_quickstart( $screen ) {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		if ( is_network_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return false;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only query arg used to skip redirect during bulk activation.
		if ( isset( $_GET['activate-multi'] ) ) {
			return false;
		}

		$pending_version = get_option( self::QUICKSTART_PENDING_OPTION, '' );
		if ( EDIWORMAN_VERSION !== $pending_version ) {
			return false;
		}

		if ( self::has_user_dismissed_quickstart() ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page check for redirect exclusions.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( self::QUICKSTART_PAGE_SLUG === $page ) {
			return false;
		}

		if ( 'settings_page_ediworman-settings' === $screen->id ) {
			return false;
		}

		if ( ! empty( $screen->post_type ) && 'ediworman_template' === $screen->post_type ) {
			return false;
		}

		return true;
	}

	/**
	 * Return derived defaults used for quickstart rendering.
	 *
	 * @return array<string, mixed>
	 */
	private function get_quickstart_defaults() {
		$post_types       = EDIWORMAN_Settings::get_quickstart_post_types();
		$templates        = EDIWORMAN_Settings::get_templates();
		$current_settings = get_option( EDIWORMAN_Settings::OPTION_NAME, array() );
		$current_mappings = isset( $current_settings['post_type_templates'] ) && is_array( $current_settings['post_type_templates'] )
			? $current_settings['post_type_templates']
			: array();

		$default_template_ids = $this->get_default_template_ids( $templates );
		$selected_post_types  = array();
		$template_selections  = array();

		foreach ( $post_types as $post_type => $post_type_object ) {
			$current_template_id = isset( $current_mappings[ $post_type ] ) ? (int) $current_mappings[ $post_type ] : 0;
			if ( $current_template_id > 0 ) {
				$selected_post_types[] = $post_type;
			}

			if ( $current_template_id > 0 ) {
				$template_selections[ $post_type ] = $current_template_id;
				continue;
			}

			if ( 'post' === $post_type && ! empty( $default_template_ids['blog'] ) ) {
				$template_selections[ $post_type ] = $default_template_ids['blog'];
				continue;
			}

			if ( 'page' === $post_type && ! empty( $default_template_ids['page'] ) ) {
				$template_selections[ $post_type ] = $default_template_ids['page'];
				continue;
			}

			$template_selections[ $post_type ] = 0;
		}

		if ( empty( $selected_post_types ) ) {
			if ( isset( $post_types['post'] ) ) {
				$selected_post_types[] = 'post';
			}

			if ( isset( $post_types['page'] ) ) {
				$selected_post_types[] = 'page';
			}
		}

		return array(
			'post_types'          => $post_types,
			'templates'           => $templates,
			'selected_post_types' => $selected_post_types,
			'template_selections' => $template_selections,
		);
	}

	/**
	 * Return default template IDs keyed by quickstart role.
	 *
	 * @param array<int, WP_Post> $templates Templates list.
	 * @return array<string, int>
	 */
	private function get_default_template_ids( $templates ) {
		$defaults = array(
			'blog' => 0,
			'page' => 0,
		);

		foreach ( $templates as $template ) {
			if ( 'Blog Post SOP' === $template->post_title ) {
				$defaults['blog'] = (int) $template->ID;
			}

			if ( 'Landing Page QA' === $template->post_title ) {
				$defaults['page'] = (int) $template->ID;
			}
		}

		return $defaults;
	}

	/**
	 * Return preview copy for a template.
	 *
	 * @param WP_Post $template Checklist template.
	 * @return string
	 */
	private function get_template_preview_text( $template ) {
		$count = EDIWORMAN_Settings::get_template_item_count( $template->ID );

		if ( $count <= 0 ) {
			return __( 'No checklist items yet.', 'editorial-workflow-manager' );
		}

		return sprintf(
			/* translators: %d: checklist item count */
			_n( '%d item', '%d items', $count, 'editorial-workflow-manager' ),
			$count
		);
	}

	/**
	 * Return the preview text for the selected template.
	 *
	 * @param int                $selected_template_id Selected template ID.
	 * @param array<int, WP_Post> $templates           Available templates.
	 * @return string
	 */
	private function get_template_preview_for_selection( $selected_template_id, $templates ) {
		$selected_template_id = absint( $selected_template_id );
		if ( $selected_template_id <= 0 ) {
			return __( 'No template selected.', 'editorial-workflow-manager' );
		}

		foreach ( $templates as $template ) {
			if ( (int) $template->ID === $selected_template_id ) {
				return $this->get_template_preview_text( $template );
			}
		}

		return __( 'No template selected.', 'editorial-workflow-manager' );
	}

	/**
	 * Sanitize selected post type slugs against an allow list.
	 *
	 * @param mixed              $raw_values         Raw request values.
	 * @param array<int, string> $allowed_post_types Allowed post type slugs.
	 * @return array<int, string>
	 */
	private function sanitize_selected_post_types( $raw_values, $allowed_post_types ) {
		if ( ! is_array( $raw_values ) ) {
			return array();
		}

		$allowed_lookup = array_fill_keys( array_map( 'sanitize_key', $allowed_post_types ), true );
		$selected       = array();

		foreach ( $raw_values as $raw_value ) {
			$post_type = sanitize_key( $raw_value );
			if ( ! $post_type || empty( $allowed_lookup[ $post_type ] ) ) {
				continue;
			}

			$selected[] = $post_type;
		}

		return array_values( array_unique( $selected ) );
	}
}
