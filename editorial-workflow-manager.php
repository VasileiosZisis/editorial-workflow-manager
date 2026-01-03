<?php

/**
 * Plugin Name: Editorial Workflow Manager
 * Description: Add editorial checklists and approvals to the WordPress editor.
 * Version:     0.3.0
 * Author:      Vasileios Zisis
 * Text Domain: editorial-workflow-manager
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
final class EDIWORMAN_Plugin
{

    const VERSION = '0.3.0';

    private static $instance = null;

    /** @var EDIWORMAN_Templates_CPT */
    private $templates_cpt;

    /** @var EDIWORMAN_Settings */
    private $settings;

    /** @var EDIWORMAN_Meta */
    private $meta;

    /** @var EDIWORMAN_Editor_Assets */
    private $editor_assets;

    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
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
        add_action('init', [$this, 'on_init']);
    }

    private function define_constants()
    {
        define('EDIWORMAN_VERSION', self::VERSION);
        define('EDIWORMAN_FILE', __FILE__);
        define('EDIWORMAN_PATH', plugin_dir_path(__FILE__));
        define('EDIWORMAN_URL', plugin_dir_url(__FILE__));
    }

    public function on_init()
    {
        // other init stuff later (if needed)
    }

    public static function activate()
    {
        EDIWORMAN_Default_Templates::create_on_activation();
    }
}

register_activation_hook(__FILE__, ['EDIWORMAN_Plugin', 'activate']);

// Bootstrap the plugin.
EDIWORMAN_Plugin::instance();
