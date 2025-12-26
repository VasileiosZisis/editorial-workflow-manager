<?php

/**
 * Post meta for storing checklist state per post.
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! class_exists('EWM_Meta')) {

    class EWM_Meta
    {

        public function __construct()
        {
            add_action('init', [$this, 'register_meta']);
        }

        /**
         * Register post meta used to store checked checklist items.
         *
         * We keep it simple:
         * - meta key: _ewm_checked_items
         * - value:    array of strings (the checklist item labels that are checked)
         *
         * This meta is exposed in the REST API so the Gutenberg editor sidebar
         * can read/write it.
         */
        public function register_meta()
        {

            register_post_meta(
                '', // empty string => applies to all post types.
                '_ewm_checked_items',
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
                        // Only users who can edit posts should be able to modify this.
                        return current_user_can('edit_posts');
                    },
                ]
            );
        }
    }
}
