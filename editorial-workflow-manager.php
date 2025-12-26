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

        /**
         * @var EWM_Plugin|null
         */
        private static $instance = null;

        /**
         * @var EWM_Templates_CPT
         */
        private $templates_cpt;

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

            // Load dependencies.
            require_once EWM_PATH . 'includes/class-ewm-templates-cpt.php';

            // Instantiate classes.
            $this->templates_cpt = new EWM_Templates_CPT();

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
            // translations later
        }

        public function on_init()
        {
            // Anything else on init later (settings, meta, etc).
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

// Bootstrap the plugin.
ewm();
