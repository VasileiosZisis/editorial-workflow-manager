<?php

/**
 * Checklist Templates CPT + meta box for checklist items.
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! class_exists('EWM_Templates_CPT')) {

    class EWM_Templates_CPT
    {

        public function __construct()
        {
            // Register CPT on init.
            add_action('init', [$this, 'register_cpt']);

            // Meta box for checklist items.
            add_action('add_meta_boxes', [$this, 'register_metaboxes']);

            // Save checklist items.
            add_action('save_post_ewm_template', [$this, 'save_template_meta'], 10, 2);
        }

        /**
         * Register the "Checklist Template" custom post type.
         */
        public function register_cpt()
        {
            $labels = [
                'name'               => __('Checklist Templates', 'editorial-workflow-manager'),
                'singular_name'      => __('Checklist Template', 'editorial-workflow-manager'),
                'add_new'            => __('Add New', 'editorial-workflow-manager'),
                'add_new_item'       => __('Add New Checklist Template', 'editorial-workflow-manager'),
                'edit_item'          => __('Edit Checklist Template', 'editorial-workflow-manager'),
                'new_item'           => __('New Checklist Template', 'editorial-workflow-manager'),
                'view_item'          => __('View Checklist Template', 'editorial-workflow-manager'),
                'search_items'       => __('Search Checklist Templates', 'editorial-workflow-manager'),
                'not_found'          => __('No checklist templates found.', 'editorial-workflow-manager'),
                'not_found_in_trash' => __('No checklist templates found in Trash.', 'editorial-workflow-manager'),
                'menu_name'          => __('Checklist Templates', 'editorial-workflow-manager'),
            ];

            $args = [
                'labels'             => $labels,
                'public'             => false,
                'show_ui'            => true,
                'show_in_menu'       => true,
                'show_in_nav_menus'  => false,
                'show_in_admin_bar'  => false,
                'exclude_from_search' => true,
                'publicly_queryable' => false,
                'has_archive'        => false,
                'rewrite'            => false,
                'supports'           => ['title'],
                'capability_type'    => 'post',
            ];

            register_post_type('ewm_template', $args);
        }

        /**
         * Register meta boxes for checklist templates.
         */
        public function register_metaboxes()
        {
            add_meta_box(
                'ewm_template_items',
                __('Checklist Items', 'editorial-workflow-manager'),
                [$this, 'render_items_metabox'],
                'ewm_template',
                'normal',
                'high'
            );
        }

        /**
         * Render the checklist items meta box.
         *
         * @param WP_Post $post
         */
        public function render_items_metabox($post)
        {
            // Security nonce.
            wp_nonce_field('ewm_save_template_items', 'ewm_template_items_nonce');

            $items = get_post_meta($post->ID, '_ewm_items', true);
            if (! is_array($items)) {
                $items = [];
            }

            $value = implode("\n", $items);
?>
            <p>
                <?php esc_html_e('Enter one checklist item per line. These items will appear in the editor sidebar for posts using this template.', 'editorial-workflow-manager'); ?>
            </p>
            <textarea
                name="ewm_template_items"
                id="ewm_template_items"
                style="width: 100%; min-height: 200px;"><?php echo esc_textarea($value); ?></textarea>
<?php
        }

        /**
         * Save the checklist items when the template is saved.
         *
         * @param int     $post_id
         * @param WP_Post $post
         */
        public function save_template_meta($post_id, $post)
        {
            // Autosave? Bail.
            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                return;
            }

            // Right post type?
            if ($post->post_type !== 'ewm_template') {
                return;
            }

            // Nonce check.
            $nonce = isset($_POST['ewm_template_items_nonce'])
                ? sanitize_text_field(wp_unslash($_POST['ewm_template_items_nonce']))
                : '';

            if (! $nonce || ! wp_verify_nonce($nonce, 'ewm_save_template_items')) {
                return;
            }

            // Capability check.
            if (! current_user_can('edit_post', $post_id)) {
                return;
            }

            // If field missing, delete meta (or keep existing - your choice).
            if (! isset($_POST['ewm_template_items'])) {
                delete_post_meta($post_id, '_ewm_items');
                return;
            }

            $raw = sanitize_textarea_field(wp_unslash($_POST['ewm_template_items']));

            $lines = preg_split('/\R/', $raw); // split on any newline type
            $items = array_filter(
                array_map(
                    static function ($line) {
                        return sanitize_text_field(trim($line));
                    },
                    $lines
                )
            );

            update_post_meta($post_id, '_ewm_items', $items);
        }
    }
}
