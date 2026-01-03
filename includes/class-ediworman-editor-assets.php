<?php

/**
 * Enqueue block editor assets (Gutenberg sidebar).
 */

if (! defined('ABSPATH')) {
    exit;
}

class EDIWORMAN_Editor_Assets
{

    public function __construct()
    {
        // This hook only runs in the block editor.
        add_action('enqueue_block_editor_assets', [$this, 'enqueue']);
    }

    /**
     * Enqueue the sidebar JavaScript and pass checklist data to it.
     */
    public function enqueue()
    {
        // Always enqueue the JS in the block editor.
        wp_enqueue_script(
            'ediworman-sidebar',
            EDIWORMAN_URL . 'assets/js/sidebar.js',
            [
                'wp-plugins',
                'wp-edit-post',
                'wp-element',
                'wp-components',
                'wp-data',
            ],
            EDIWORMAN_VERSION,
            true
        );

        // Try to detect the current post type.
        if (! function_exists('get_current_screen')) {
            return;
        }

        $screen = get_current_screen();

        // We only care about actual post editing screens.
        if (! $screen || empty($screen->post_type) || $screen->base !== 'post') {
            return;
        }

        $post_type   = $screen->post_type;
        $template_id = null;
        $items       = [];

        if (class_exists('EDIWORMAN_Settings')) {
            $template_id = EDIWORMAN_Settings::get_template_for_post_type($post_type);
        }

        if ($template_id) {
            $stored_items = get_post_meta($template_id, '_ediworman_items', true);
            if (is_array($stored_items)) {
                $items = array_values($stored_items);
            }
        }

        // Pass data to JS (even if items is empty).
        wp_localize_script(
            'ediworman-sidebar',
            'EDIWORMAN_CHECKLIST_DATA',
            [
                'templateId' => $template_id,
                'postType'   => $post_type,
                'items'      => $items,
            ]
        );
    }
}
