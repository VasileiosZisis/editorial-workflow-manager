<?php
/**
 * Plugin uninstall handler.
 *
 * Runs when the plugin is deleted from WordPress.
 */

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Delete plugin data for the current site.
 *
 * Note: uninstall.php is loaded without loading the plugin bootstrap file,
 * so do not rely on plugin constants/classes here.
 *
 * @return void
 */
function ediworman_uninstall_cleanup_site()
{
    // Delete settings.
    delete_option('ediworman_settings');

    // Remove post meta stored on edited content.
    delete_post_meta_by_key('_ediworman_checked_items');
    delete_post_meta_by_key('_ediworman_last_editor');

    // Remove checklist template item meta (in case templates are left behind).
    delete_post_meta_by_key('_ediworman_items');

    // Delete checklist templates CPT posts.
    $limit = 100;

    do {
        $template_ids = get_posts(
            [
                'post_type'      => 'ediworman_template',
                'post_status'    => 'any',
                'posts_per_page' => $limit,
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'orderby'        => 'ID',
                'order'          => 'ASC',
            ]
        );

        foreach ($template_ids as $template_id) {
            wp_delete_post((int) $template_id, true);
        }
    } while (! empty($template_ids));
}

/**
 * Run uninstall for single-site or multisite.
 *
 * @return void
 */
function ediworman_uninstall()
{
    if (is_multisite()) {
        $ediworman_site_ids = get_sites(
            [
                'fields' => 'ids',
            ]
        );

        foreach ($ediworman_site_ids as $ediworman_site_id) {
            switch_to_blog((int) $ediworman_site_id);
            ediworman_uninstall_cleanup_site();
            restore_current_blog();
        }

        // In case anything was stored at network level in the future.
        delete_site_option('ediworman_settings');
        return;
    }

    ediworman_uninstall_cleanup_site();
}

ediworman_uninstall();
