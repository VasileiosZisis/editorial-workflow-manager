<?php

/**
 * Enqueue block editor assets (Gutenberg sidebar).
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! class_exists('EWM_Editor_Assets')) {

    class EWM_Editor_Assets
    {

        public function __construct()
        {
            // This hook only runs in the block editor.
            add_action('enqueue_block_editor_assets', [$this, 'enqueue']);
        }

        /**
         * Enqueue the sidebar JavaScript.
         */
        public function enqueue()
        {
            // Path to assets/js/sidebar.js
            wp_enqueue_script(
                'ewm-sidebar',
                EWM_URL . 'assets/js/sidebar.js',
                [
                    'wp-plugins',
                    'wp-edit-post',
                    'wp-element',
                    'wp-components',
                    'wp-data',
                ],
                EWM_VERSION,
                true
            );
        }
    }
}
