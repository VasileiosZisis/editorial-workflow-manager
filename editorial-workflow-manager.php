<?php
/**
 * Plugin Name: Editorial Workflow Manager
 * Description: Add editorial checklists and approvals to the WordPress editor.
 * Version:     0.6.0
 * Author:      Vasileios Zisis
 * Author URI:  https://profiles.wordpress.org/vzisis/
 * Text Domain: editorial-workflow-manager
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package EditorialWorkflowManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Plugin bootstrap and service wiring.
 */
final class EDIWORMAN_Plugin {

	const VERSION = '0.6.0';

	/**
	 * Stored plugin version option name.
	 */
	const VERSION_OPTION = 'ediworman_version';

	/**
	 * Version that introduced the template capability change notice.
	 */
	const TEMPLATE_CAPABILITY_NOTICE_VERSION = '0.5.0';

	/**
	 * Pending template capability notice option name.
	 */
	const TEMPLATE_CAPABILITY_NOTICE_OPTION = 'ediworman_template_capability_notice_version';

	/**
	 * Per-user dismissed template capability notice meta key.
	 */
	const TEMPLATE_CAPABILITY_NOTICE_USER_META = 'ediworman_dismissed_template_capability_notice_version';

	/**
	 * Query arg value used to dismiss the template capability notice.
	 */
	const TEMPLATE_CAPABILITY_NOTICE_SLUG = 'template-capability-update';

	/**
	 * Singleton plugin instance.
	 *
	 * @var EDIWORMAN_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Checklist template CPT handler.
	 *
	 * @var EDIWORMAN_Templates_CPT
	 */
	private $templates_cpt;

	/**
	 * Plugin settings handler.
	 *
	 * @var EDIWORMAN_Settings
	 */
	private $settings;

	/**
	 * Post meta registration and save hooks.
	 *
	 * @var EDIWORMAN_Meta
	 */
	private $meta;

	/**
	 * Block editor asset loader.
	 *
	 * @var EDIWORMAN_Editor_Assets
	 */
	private $editor_assets;

	/**
	 * Quickstart onboarding flow handler.
	 *
	 * @var EDIWORMAN_Onboarding
	 */
	private $onboarding;

	/**
	 * Get plugin singleton instance.
	 *
	 * @return EDIWORMAN_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Construct plugin services and hooks.
	 */
	private function __construct() {
		$this->define_constants();

		// Load classes.
		require_once EDIWORMAN_PATH . 'includes/class-ediworman-templates-cpt.php';
		require_once EDIWORMAN_PATH . 'includes/class-ediworman-settings.php';
		require_once EDIWORMAN_PATH . 'includes/class-ediworman-meta.php';
		require_once EDIWORMAN_PATH . 'includes/class-ediworman-editor-assets.php';
		require_once EDIWORMAN_PATH . 'includes/class-ediworman-onboarding.php';
		require_once EDIWORMAN_PATH . 'includes/class-ediworman-default-templates.php';

		// Instantiate.
		$this->templates_cpt = new EDIWORMAN_Templates_CPT();
		$this->settings      = new EDIWORMAN_Settings();
		$this->meta          = new EDIWORMAN_Meta();
		$this->editor_assets = new EDIWORMAN_Editor_Assets();
		$this->onboarding    = new EDIWORMAN_Onboarding();

		// Hooks.
		add_action( 'init', array( $this, 'on_init' ) );
		add_action( 'admin_init', array( $this, 'maybe_run_upgrade_tasks' ) );
		add_action( 'admin_init', array( $this, 'handle_notice_dismissal' ) );
		add_action( 'admin_notices', array( $this, 'maybe_render_template_capability_notice' ) );
	}

	/**
	 * Define plugin constants used across the codebase.
	 *
	 * @return void
	 */
	private function define_constants() {
		define( 'EDIWORMAN_VERSION', self::VERSION );
		define( 'EDIWORMAN_FILE', __FILE__ );
		define( 'EDIWORMAN_PATH', plugin_dir_path( __FILE__ ) );
		define( 'EDIWORMAN_URL', plugin_dir_url( __FILE__ ) );
	}

	/**
	 * Run init-time plugin behavior.
	 *
	 * @return void
	 */
	public function on_init() {
		// Reserved for future init-time hooks.
	}

	/**
	 * Run versioned upgrade tasks for existing installs.
	 *
	 * @return void
	 */
	public function maybe_run_upgrade_tasks() {
		$stored_version = get_option( self::VERSION_OPTION, '' );
		if ( ! is_string( $stored_version ) ) {
			$stored_version = '';
		}

		if ( '' === $stored_version ) {
			update_option( self::VERSION_OPTION, self::VERSION );
			return;
		}

		if ( version_compare( $stored_version, self::VERSION, '>=' ) ) {
			return;
		}

		if (
			version_compare( $stored_version, self::TEMPLATE_CAPABILITY_NOTICE_VERSION, '<' ) &&
			version_compare( self::VERSION, self::TEMPLATE_CAPABILITY_NOTICE_VERSION, '>=' )
		) {
			update_option( self::TEMPLATE_CAPABILITY_NOTICE_OPTION, self::TEMPLATE_CAPABILITY_NOTICE_VERSION );
		}

		update_option( self::VERSION_OPTION, self::VERSION );
	}

	/**
	 * Persist dismissal of the template capability notice for the current admin user.
	 *
	 * @return void
	 */
	public function handle_notice_dismissal() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$notice_slug = isset( $_GET['ediworman_dismiss_notice'] )
			? sanitize_key( wp_unslash( $_GET['ediworman_dismiss_notice'] ) )
			: '';

		if ( self::TEMPLATE_CAPABILITY_NOTICE_SLUG !== $notice_slug ) {
			return;
		}

		$nonce = isset( $_GET['_ediworman_notice_nonce'] )
			? sanitize_text_field( wp_unslash( $_GET['_ediworman_notice_nonce'] ) )
			: '';

		if ( ! wp_verify_nonce( $nonce, 'ediworman_dismiss_notice_' . self::TEMPLATE_CAPABILITY_NOTICE_SLUG ) ) {
			return;
		}

		update_user_meta( get_current_user_id(), self::TEMPLATE_CAPABILITY_NOTICE_USER_META, self::TEMPLATE_CAPABILITY_NOTICE_VERSION );

		$redirect_url = remove_query_arg(
			array(
				'ediworman_dismiss_notice',
				'_ediworman_notice_nonce',
			)
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Render the one-time admin notice about stricter template permissions.
	 *
	 * @return void
	 */
	public function maybe_render_template_capability_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$pending_notice_version = get_option( self::TEMPLATE_CAPABILITY_NOTICE_OPTION, '' );
		if ( self::TEMPLATE_CAPABILITY_NOTICE_VERSION !== $pending_notice_version ) {
			return;
		}

		$dismissed_version = get_user_meta( get_current_user_id(), self::TEMPLATE_CAPABILITY_NOTICE_USER_META, true );
		if ( self::TEMPLATE_CAPABILITY_NOTICE_VERSION === $dismissed_version ) {
			return;
		}

		$review_url  = admin_url( 'edit.php?post_type=ediworman_template' );
		$dismiss_url = wp_nonce_url(
			add_query_arg(
				'ediworman_dismiss_notice',
				self::TEMPLATE_CAPABILITY_NOTICE_SLUG
			),
			'ediworman_dismiss_notice_' . self::TEMPLATE_CAPABILITY_NOTICE_SLUG,
			'_ediworman_notice_nonce'
		);
		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'Editorial Workflow Manager update:', 'editorial-workflow-manager' ); ?></strong>
				<?php esc_html_e( 'Checklist Template permissions now follow WordPress page permissions.', 'editorial-workflow-manager' ); ?>
				<?php esc_html_e( 'Only Editors and Administrators can create, edit, or delete Checklist Templates.', 'editorial-workflow-manager' ); ?>
				<?php esc_html_e( 'Authors and Contributors may lose access after this update.', 'editorial-workflow-manager' ); ?>
			</p>
			<p>
				<?php esc_html_e( 'If your site previously relied on Authors managing templates, review your workflow and user roles before making further template changes.', 'editorial-workflow-manager' ); ?>
			</p>
			<p>
				<a class="button button-secondary" href="<?php echo esc_url( $review_url ); ?>">
					<?php esc_html_e( 'Review Checklist Templates', 'editorial-workflow-manager' ); ?>
				</a>
				<a class="button-link" href="<?php echo esc_url( $dismiss_url ); ?>">
					<?php esc_html_e( 'Dismiss', 'editorial-workflow-manager' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Activation callback.
	 *
	 * @return void
	 */
	public static function activate() {
		$stored_version = get_option( self::VERSION_OPTION, '' );
		if ( ! is_string( $stored_version ) ) {
			$stored_version = '';
		}

		EDIWORMAN_Default_Templates::create_on_activation();

		if ( '' === $stored_version ) {
			update_option( EDIWORMAN_Onboarding::QUICKSTART_PENDING_OPTION, self::VERSION );
		}

		if (
			'' !== $stored_version &&
			version_compare( $stored_version, self::TEMPLATE_CAPABILITY_NOTICE_VERSION, '<' ) &&
			version_compare( self::VERSION, self::TEMPLATE_CAPABILITY_NOTICE_VERSION, '>=' )
		) {
			update_option( self::TEMPLATE_CAPABILITY_NOTICE_OPTION, self::TEMPLATE_CAPABILITY_NOTICE_VERSION );
		}

		update_option( self::VERSION_OPTION, self::VERSION );
	}
}

register_activation_hook( __FILE__, array( 'EDIWORMAN_Plugin', 'activate' ) );

// Bootstrap the plugin.
EDIWORMAN_Plugin::instance();
