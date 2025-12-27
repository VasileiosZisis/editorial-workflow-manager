<?php

/**
 * Plugin Name: Editorial Workflow Manager
 * Plugin URI:  https://example.com/   // optional, you can change later
 * Description: Add editorial checklists and approvals to the WordPress editor.
 * Version:     0.1.0
 * Author:      Your Name
 * Author URI:  https://example.com/   // optional
 * Text Domain: editorial-workflow-manager
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (! class_exists('EWM_Plugin')) {

    final class EWM_Plugin
    {

        const VERSION = '0.1.0';

        private static $instance = null;

        /** @var EWM_Templates_CPT */
        private $templates_cpt;

        /** @var EWM_Settings */
        private $settings;

        /** @var EWM_Meta */
        private $meta;

        /** @var EWM_Editor_Assets */
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
            require_once EWM_PATH . 'includes/class-ewm-templates-cpt.php';
            require_once EWM_PATH . 'includes/class-ewm-settings.php';
            require_once EWM_PATH . 'includes/class-ewm-meta.php';
            require_once EWM_PATH . 'includes/class-ewm-editor-assets.php';
            require_once EWM_PATH . 'includes/class-ewm-default-templates.php';

            // Instantiate.
            $this->templates_cpt = new EWM_Templates_CPT();
            $this->settings      = new EWM_Settings();
            $this->meta          = new EWM_Meta();
            $this->editor_assets = new EWM_Editor_Assets();

            // Hooks.
            add_action('plugins_loaded', [$this, 'on_plugins_loaded']);
            add_action('init', [$this, 'on_init']);
        }

        private function define_constants()
        {
            define('EWM_VERSION', self::VERSION);
            define('EWM_FILE', __FILE__);
            define('EWM_PATH', plugin_dir_path(__FILE__));
            define('EWM_URL', plugin_dir_url(__FILE__));
        }

        public function on_plugins_loaded()
        {
            load_plugin_textdomain(
                'editorial-workflow-manager',
                false,
                dirname(plugin_basename(__FILE__)) . '/languages'
            );
        }


        public function on_init()
        {
            // other init stuff later (if needed)
        }
    }
}

/**
 * Helper function to get the plugin instance.
 *
 * @return EWM_Plugin
 */
function ewm()
{
    return EWM_Plugin::instance();
}

/**
 * Run on plugin activation: create default templates, etc.
 */
function ewm_activate()
{
    EWM_Default_Templates::create_on_activation();
}

register_activation_hook(__FILE__, 'ewm_activate');


// Bootstrap the plugin.
ewm();
