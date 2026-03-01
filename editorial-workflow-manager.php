<?php
/**
 * Plugin Name: Editorial Workflow Manager
 * Description: Add editorial checklists and approvals to the WordPress editor.
 * Version:     0.3.5
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

	const VERSION = '0.3.5';

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
		require_once EDIWORMAN_PATH . 'includes/class-ediworman-default-templates.php';

		// Instantiate.
		$this->templates_cpt = new EDIWORMAN_Templates_CPT();
		$this->settings      = new EDIWORMAN_Settings();
		$this->meta          = new EDIWORMAN_Meta();
		$this->editor_assets = new EDIWORMAN_Editor_Assets();

		// Hooks.
		add_action( 'init', array( $this, 'on_init' ) );
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
	 * Activation callback.
	 *
	 * @return void
	 */
	public static function activate() {
		EDIWORMAN_Default_Templates::create_on_activation();
	}
}

register_activation_hook( __FILE__, array( 'EDIWORMAN_Plugin', 'activate' ) );

// Bootstrap the plugin.
EDIWORMAN_Plugin::instance();
