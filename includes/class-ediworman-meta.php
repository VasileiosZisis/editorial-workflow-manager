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
                    'auth_callback' => function () {
                        return current_user_can('edit_posts');
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
                    'auth_callback' => function () {
                        return current_user_can('edit_posts');
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
