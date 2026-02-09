<?php

/**
 * Post meta for storing checklist state per post.
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! class_exists('EDIWORMAN')) {

    class EDIWORMAN_Meta
    {

        public function __construct()
        {
            add_action('init', [$this, 'register_meta']);
            add_action('save_post', [$this, 'capture_last_editor'], 10, 3);
        }

        /**
         * Sanitize the checked items meta.
         *
         * @param mixed  $value
         * @param string $meta_key
         * @param string $object_type
         * @return array
         */
        public function sanitize_checked_items($value, $meta_key, $object_type)
        {
            if (! is_array($value)) {
                if (is_string($value) && $value !== '') {
                    $value = [$value];
                } else {
                    return [];
                }
            }

            $sanitized = [];

            foreach ($value as $item) {
                if (! is_scalar($item)) {
                    continue;
                }

                $label = sanitize_text_field(wp_unslash((string) $item));
                if ($label === '') {
                    continue;
                }

                $sanitized[] = $label;
            }

            return array_values(array_unique($sanitized));
        }

        /**
         * Register post meta used by the plugin.
         *
         * - _ediworman_checked_items: array of checklist item labels that are checked.
         * - _ediworman_last_editor:   user ID of the last editor (for display only).
         */
        public function register_meta()
        {

            // Checklist state.
            register_post_meta(
                '',
                '_ediworman_checked_items',
                [
                    'single'       => true,
                    'type'         => 'array',
                    'default'      => [],
                    'show_in_rest' => [
                        'schema' => [
                            'type'  => 'array',
                            'items' => [
                                'type' => 'string',
                            ],
                        ],
                    ],
                    'sanitize_callback' => [$this, 'sanitize_checked_items'],
                    'auth_callback' => static function ($allowed, $meta_key, $post_id, $user_id = 0, $cap = '', $caps = []) {
                        $post_id = (int) $post_id;
                        if ($post_id <= 0) {
                            return false;
                        }

                        return current_user_can('edit_post', $post_id);
                    },
                ]
            );

            // Last editor (user ID).
            register_post_meta(
                '',
                '_ediworman_last_editor',
                [
                    'single'       => true,
                    'type'         => 'integer',
                    'default'      => 0,
                    'show_in_rest' => true,
                    'sanitize_callback' => 'absint',
                    'auth_callback' => static function ($allowed, $meta_key, $post_id, $user_id = 0, $cap = '', $caps = []) {
                        $post_id = (int) $post_id;
                        if ($post_id <= 0) {
                            return false;
                        }

                        return current_user_can('edit_post', $post_id);
                    },
                ]
            );
        }

        /**
         * Store the last editor's user ID when a post is saved.
         *
         * @param int     $post_id
         * @param WP_Post $post
         * @param bool    $update
         */
        public function capture_last_editor($post_id, $post, $update)
        {
            // Don't run on autosave / revisions.
            if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
                return;
            }

            // Only for real posts/pages/etc (not our template CPT if you prefer to exclude).
            // If you want to skip templates, uncomment:
            // if ( $post->post_type === 'ediworman_template' ) {
            //     return;
            // }

            // Permission check.
            if (! current_user_can('edit_post', $post_id)) {
                return;
            }

            $user_id = get_current_user_id();
            if ($user_id) {
                update_post_meta($post_id, '_ediworman_last_editor', (int) $user_id);
            }
        }
    }
}
