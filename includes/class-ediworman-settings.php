<?php

/**
 * Plugin settings: map post types to checklist templates.
 */

if (! defined('ABSPATH')) {
    exit;
}

class EDIWORMAN_Settings
{
    /**
     * Option name.
     */
    const OPTION_NAME = 'ediworman_settings';

    public function __construct()
    {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    /**
     * Add a settings page under "Settings".
     */
    public function register_menu()
    {
        add_options_page(
            __('Editorial Workflow', 'editorial-workflow-manager'),
            __('Editorial Workflow', 'editorial-workflow-manager'),
            'manage_options',
            'ediworman-settings',
            [$this, 'render_page']
        );
    }

    /**
     * Register the settings.
     */
    public function register_settings()
    {
        register_setting(
            'ediworman_settings_group',
            self::OPTION_NAME,
            [
                'type'              => 'array',
                'sanitize_callback' => [$this, 'sanitize_settings'],
                'default'           => [],
            ]
        );
    }

    /**
     * Sanitize settings on save.
     *
     * @param array $input
     * @return array
     */
    public function sanitize_settings($input)
    {
        $output = [];

        $output['post_type_templates'] = [];

        if (! empty($input['post_type_templates']) && is_array($input['post_type_templates'])) {
            foreach ($input['post_type_templates'] as $post_type => $template_id) {
                $post_type = sanitize_key($post_type);
                $template_id = (int) $template_id;

                if ($template_id > 0) {
                    $output['post_type_templates'][$post_type] = $template_id;
                }
            }
        }

        return $output;
    }

    /**
     * Render the settings page.
     */
    public function render_page()
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $settings   = get_option(self::OPTION_NAME, []);
        $mappings   = isset($settings['post_type_templates']) ? $settings['post_type_templates'] : [];

        // Get public post types that have UI.
        $post_types = get_post_types(
            [
                'show_ui' => true,
                'public'  => true,
            ],
            'objects'
        );

        // Exclude our own CPT.
        unset($post_types['ediworman_template']);

        // Exclude Media (attachments) – checklists for media items don't make sense.
        unset($post_types['attachment']);

        // Get all checklist templates.
        $templates = get_posts(
            [
                'post_type'      => 'ediworman_template',
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'orderby'        => 'title',
                'order'          => 'ASC',
            ]
        );
?>
        <div class="wrap">
            <h1><?php esc_html_e('Editorial Workflow Settings', 'editorial-workflow-manager'); ?></h1>

            <p>
                <?php esc_html_e('Use checklist templates to enforce a consistent review process before publishing content.', 'editorial-workflow-manager'); ?>
            </p>

            <div class="notice notice-info">
                <p><strong><?php esc_html_e('Getting started', 'editorial-workflow-manager'); ?></strong></p>
                <ol>
                    <li>
                        <?php
                        printf(
                            /* translators: %s: menu label "Checklist Templates" */
                            esc_html__('Go to %s and create or edit checklist templates (or use the defaults).', 'editorial-workflow-manager'),
                            '<em>' . esc_html__('Checklist Templates → Add New', 'editorial-workflow-manager') . '</em>'
                        );
                        ?>
                    </li>
                    <li>
                        <?php esc_html_e('Return to this page and map each post type to a checklist template.', 'editorial-workflow-manager'); ?>
                    </li>
                    <li>
                        <?php esc_html_e('Edit a post or page and open the “Editorial Checklist” sidebar in the block editor to see and tick off the checklist items.', 'editorial-workflow-manager'); ?>
                    </li>
                    <li>
                        <?php esc_html_e('If you delete a checklist template, come back here to assign a new template to any post types that were using it.', 'editorial-workflow-manager'); ?>
                    </li>
                </ol>
            </div>

            <form method="post" action="options.php">
                <?php
                settings_fields('ediworman_settings_group');
                ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label><?php esc_html_e('Template per post type', 'editorial-workflow-manager'); ?></label>
                            </th>
                            <td>
                                <p class="description">
                                    <?php esc_html_e('Choose which checklist template should be used by default for each post type.', 'editorial-workflow-manager'); ?>
                                </p>

                                <table>
                                    <thead>
                                        <tr>
                                            <th style="text-align:left;"><?php esc_html_e('Post type', 'editorial-workflow-manager'); ?></th>
                                            <th style="text-align:left;"><?php esc_html_e('Checklist template', 'editorial-workflow-manager'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($post_types as $post_type => $obj) : ?>
                                            <tr>
                                                <td>
                                                    <?php echo esc_html($obj->labels->singular_name); ?>
                                                    <br>
                                                    <code><?php echo esc_html($post_type); ?></code>
                                                </td>
                                                <td>
                                                    <select name="ediworman_settings[post_type_templates][<?php echo esc_attr($post_type); ?>]">
                                                        <option value="0">
                                                            <?php esc_html_e('None', 'editorial-workflow-manager'); ?>
                                                        </option>
                                                        <?php foreach ($templates as $template) : ?>
                                                            <?php
                                                            $selected = isset($mappings[$post_type]) && (int) $mappings[$post_type] === (int) $template->ID
                                                                ? 'selected'
                                                                : '';
                                                            ?>
                                                            <option value="<?php echo esc_attr($template->ID); ?>" <?php selected((int) ($mappings[$post_type] ?? 0), (int) $template->ID); ?>>
                                                                <?php echo esc_html($template->post_title); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>

                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
<?php
    }

    /**
     * Helper: get template ID for a given post type.
     *
     * Returns null if no valid template exists anymore (e.g. deleted or trashed).
     *
     * @param string $post_type
     * @return int|null
     */
    public static function get_template_for_post_type($post_type)
    {
        $settings = get_option(self::OPTION_NAME, []);

        if (empty($settings['post_type_templates'][$post_type])) {
            return null;
        }

        $template_id = (int) $settings['post_type_templates'][$post_type];
        if ($template_id <= 0) {
            return null;
        }

        $template = get_post($template_id);

        // If the template is gone or not our CPT, treat as no template.
        if (! $template || $template->post_type !== 'ediworman_template') {
            return null;
        }

        // If it's in trash, also treat as no template.
        if ($template->post_status === 'trash') {
            return null;
        }

        return $template_id;
    }
}
