<?php
/**
 * Feedback links and the per-user review prompt.
 *
 * @package EditorialWorkflowManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers feedback links, tracks checklist completions, and renders review prompts.
 */
class EDIWORMAN_Feedback {

	const SUPPORT_URL = 'https://wordpress.org/support/plugin/editorial-workflow-manager/';
	const REVIEW_URL  = 'https://wordpress.org/support/plugin/editorial-workflow-manager/reviews/#new-post';

	const COMPLETED_POST_IDS_USER_META = 'ediworman_review_completed_post_ids';
	const SNOOZED_UNTIL_USER_META       = 'ediworman_review_prompt_snoozed_until';
	const CLOSED_USER_META              = 'ediworman_review_prompt_closed';

	const COMPLETION_THRESHOLD = 5;
	const SNOOZE_SECONDS        = 30 * DAY_IN_SECONDS;

	const ADMIN_ACTION = 'ediworman_review_prompt';
	const AJAX_ACTION  = 'ediworman_update_review_prompt';
	const NONCE_ACTION = 'ediworman_review_prompt_action';
	const NONCE_NAME   = '_ediworman_review_nonce';

	/**
	 * Register tracking and admin hooks.
	 *
	 * Tracking hooks must run for REST requests as well as normal wp-admin requests.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'added_post_meta', array( $this, 'maybe_track_ready_transition' ), 5, 4 );
		add_action( 'updated_post_meta', array( $this, 'maybe_track_ready_transition' ), 5, 4 );
		add_action( 'deleted_post_meta', array( $this, 'maybe_track_ready_transition' ), 5, 4 );

		if ( ! is_admin() ) {
			return;
		}

		add_filter( 'plugin_action_links_' . plugin_basename( EDIWORMAN_FILE ), array( $this, 'add_plugin_action_link' ) );
		add_action( 'ediworman_after_settings_form', array( $this, 'render_settings_feedback' ) );
		add_action( 'admin_notices', array( $this, 'maybe_render_admin_review_prompt' ) );
		add_action( 'admin_post_' . self::ADMIN_ACTION, array( $this, 'handle_admin_action' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'handle_ajax_action' ) );
	}

	/**
	 * Count a unique post when the current user changes it from Incomplete to Ready.
	 *
	 * This runs before the readiness cache update at priority 10, allowing the old
	 * cached state to be compared with the newly calculated live state.
	 *
	 * @param int|array<int> $meta_id    Meta ID or IDs.
	 * @param int            $post_id    Post ID.
	 * @param string         $meta_key   Meta key.
	 * @param mixed          $meta_value Meta value.
	 * @return void
	 */
	public function maybe_track_ready_transition( $meta_id, $post_id, $meta_key, $meta_value ) {
		unset( $meta_id, $meta_value );

		if ( ! in_array( $meta_key, array( '_ediworman_checked_items', '_ediworman_checked_item_ids' ), true ) ) {
			return;
		}

		$post_id = absint( $post_id );
		$user_id = get_current_user_id();
		if (
			$post_id <= 0 ||
			$user_id <= 0 ||
			wp_is_post_autosave( $post_id ) ||
			wp_is_post_revision( $post_id ) ||
			! current_user_can( 'edit_post', $post_id )
		) {
			return;
		}

		$completed_post_ids = self::get_completed_post_ids( $user_id );
		if ( count( $completed_post_ids ) >= self::COMPLETION_THRESHOLD || in_array( $post_id, $completed_post_ids, true ) ) {
			return;
		}

		$cached_readiness = EDIWORMAN_Readiness::get_readiness_for_post( $post_id, true, false );
		$live_readiness   = EDIWORMAN_Readiness::get_readiness_for_post( $post_id, false, false );

		if (
			! is_array( $cached_readiness ) ||
			! is_array( $live_readiness ) ||
			EDIWORMAN_Readiness::READINESS_INCOMPLETE !== $cached_readiness['readiness'] ||
			EDIWORMAN_Readiness::READINESS_READY !== $live_readiness['readiness'] ||
			empty( $live_readiness['required_total'] )
		) {
			return;
		}

		$completed_post_ids[] = $post_id;
		update_user_meta(
			$user_id,
			self::COMPLETED_POST_IDS_USER_META,
			array_slice( array_values( array_unique( $completed_post_ids ) ), 0, self::COMPLETION_THRESHOLD )
		);
	}

	/**
	 * Add a Feedback link to the Installed Plugins row.
	 *
	 * @param array<string, string> $links Existing plugin action links.
	 * @return array<string, string>
	 */
	public function add_plugin_action_link( $links ) {
		$feedback_link = sprintf(
			'<a href="%1$s" target="_blank" rel="noopener noreferrer" aria-label="%2$s">%3$s</a>',
			esc_url( self::SUPPORT_URL ),
			esc_attr__( 'Send feedback about Editorial Workflow Manager (opens in a new tab)', 'editorial-workflow-manager' ),
			esc_html__( 'Feedback', 'editorial-workflow-manager' )
		);

		$links['ediworman_feedback'] = $feedback_link;
		return $links;
	}

	/**
	 * Render the passive feedback section below the settings form.
	 *
	 * @return void
	 */
	public function render_settings_feedback() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<hr>
		<h2><?php esc_html_e( 'Feedback and support', 'editorial-workflow-manager' ); ?></h2>
		<p>
			<?php esc_html_e( 'Have an idea or need help? Share it in the official WordPress.org support forum.', 'editorial-workflow-manager' ); ?>
			<a href="<?php echo esc_url( self::SUPPORT_URL ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Send feedback (opens in a new tab)', 'editorial-workflow-manager' ); ?>
			</a>
		</p>
		<?php
	}

	/**
	 * Render a review prompt on plugin-owned admin screens.
	 *
	 * @return void
	 */
	public function maybe_render_admin_review_prompt() {
		if ( ! self::is_prompt_eligible() || ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'settings_page_ediworman-settings', 'edit-ediworman_template', 'ediworman_template' ), true ) ) {
			return;
		}

		$review_url = self::get_admin_action_url( 'review' );
		$snooze_url = self::get_admin_action_url( 'snooze' );
		$dismiss_url = self::get_admin_action_url( 'dismiss' );
		?>
		<div class="notice notice-info">
			<p><strong><?php esc_html_e( 'Enjoying Editorial Workflow Manager?', 'editorial-workflow-manager' ); ?></strong></p>
			<p><?php esc_html_e( 'You have completed several editorial checklists. A WordPress.org review would help other teams discover the plugin.', 'editorial-workflow-manager' ); ?></p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( $review_url ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Leave a review (opens in a new tab)', 'editorial-workflow-manager' ); ?>
				</a>
				<a class="button button-secondary" href="<?php echo esc_url( $snooze_url ); ?>">
					<?php esc_html_e( 'Maybe later', 'editorial-workflow-manager' ); ?>
				</a>
				<a class="button-link" href="<?php echo esc_url( $dismiss_url ); ?>">
					<?php esc_html_e( 'Do not ask again', 'editorial-workflow-manager' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Return review-prompt data for the block-editor sidebar.
	 *
	 * @return array<string, bool|string>
	 */
	public static function get_editor_data() {
		return array(
			'eligible'  => self::is_prompt_eligible(),
			'reviewUrl' => self::get_admin_action_url( 'review' ),
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'ajaxAction' => self::AJAX_ACTION,
			'nonce'     => wp_create_nonce( self::NONCE_ACTION ),
		);
	}

	/**
	 * Process a nonce-protected admin action and redirect safely.
	 *
	 * @return void
	 */
	public function handle_admin_action() {
		if ( ! current_user_can( 'read' ) ) {
			wp_die( esc_html__( 'You are not allowed to update this review prompt.', 'editorial-workflow-manager' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

		$prompt_action = isset( $_GET['ediworman_prompt_action'] )
			? sanitize_key( wp_unslash( $_GET['ediworman_prompt_action'] ) )
			: '';

		if ( ! in_array( $prompt_action, array( 'review', 'snooze', 'dismiss' ), true ) ) {
			wp_die( esc_html__( 'Invalid review prompt action.', 'editorial-workflow-manager' ), '', array( 'response' => 400 ) );
		}

		self::apply_prompt_action( $prompt_action );

		if ( 'review' === $prompt_action ) {
			$review_host = strtolower( (string) wp_parse_url( self::REVIEW_URL, PHP_URL_HOST ) );
			if ( 'wordpress.org' !== $review_host ) {
				wp_die( esc_html__( 'The review destination is not allowed.', 'editorial-workflow-manager' ), '', array( 'response' => 500 ) );
			}

			// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Hardcoded URL is explicitly host-allow-listed above.
			wp_redirect( self::REVIEW_URL );
			exit;
		}

		$redirect_url = wp_get_referer();
		if ( ! $redirect_url ) {
			$redirect_url = admin_url();
		}

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Process sidebar snooze and dismissal requests.
	 *
	 * @return void
	 */
	public function handle_ajax_action() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'read' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You are not allowed to update this review prompt.', 'editorial-workflow-manager' ) ), 403 );
		}

		$prompt_action = isset( $_POST['prompt_action'] )
			? sanitize_key( wp_unslash( $_POST['prompt_action'] ) )
			: '';

		if ( ! in_array( $prompt_action, array( 'snooze', 'dismiss' ), true ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid review prompt action.', 'editorial-workflow-manager' ) ), 400 );
		}

		self::apply_prompt_action( $prompt_action );
		wp_send_json_success();
	}

	/**
	 * Return whether the current user should see the review prompt.
	 *
	 * @return bool
	 */
	public static function is_prompt_eligible() {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 || ! current_user_can( 'read' ) ) {
			return false;
		}

		if ( '' !== (string) get_user_meta( $user_id, self::CLOSED_USER_META, true ) ) {
			return false;
		}

		$snoozed_until = absint( get_user_meta( $user_id, self::SNOOZED_UNTIL_USER_META, true ) );
		if ( $snoozed_until > time() ) {
			return false;
		}

		return count( self::get_completed_post_ids( $user_id ) ) >= self::COMPLETION_THRESHOLD;
	}

	/**
	 * Return sanitized, bounded completed post IDs for a user.
	 *
	 * @param int $user_id User ID.
	 * @return array<int, int>
	 */
	private static function get_completed_post_ids( $user_id ) {
		$post_ids = get_user_meta( absint( $user_id ), self::COMPLETED_POST_IDS_USER_META, true );
		if ( ! is_array( $post_ids ) ) {
			return array();
		}

		$post_ids = array_values( array_unique( array_filter( array_map( 'absint', $post_ids ) ) ) );
		return array_slice( $post_ids, 0, self::COMPLETION_THRESHOLD );
	}

	/**
	 * Build a nonce-protected admin-post action URL.
	 *
	 * @param string $prompt_action Review, snooze, or dismiss.
	 * @return string
	 */
	private static function get_admin_action_url( $prompt_action ) {
		$url = add_query_arg(
			array(
				'action'                   => self::ADMIN_ACTION,
				'ediworman_prompt_action' => sanitize_key( $prompt_action ),
			),
			admin_url( 'admin-post.php' )
		);

		return add_query_arg(
			self::NONCE_NAME,
			wp_create_nonce( self::NONCE_ACTION ),
			$url
		);
	}

	/**
	 * Persist a validated prompt action for the current user.
	 *
	 * @param string $prompt_action Review, snooze, or dismiss.
	 * @return void
	 */
	private static function apply_prompt_action( $prompt_action ) {
		$user_id = get_current_user_id();
		if ( 'snooze' === $prompt_action ) {
			update_user_meta( $user_id, self::SNOOZED_UNTIL_USER_META, time() + self::SNOOZE_SECONDS );
			return;
		}

		delete_user_meta( $user_id, self::SNOOZED_UNTIL_USER_META );
		update_user_meta( $user_id, self::CLOSED_USER_META, 'review' === $prompt_action ? 'review' : 'dismissed' );
	}
}
