<?php
/**
 * Create default checklist templates on plugin activation.
 *
 * @package EditorialWorkflowManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates default template posts and default post type mappings.
 */
class EDIWORMAN_Default_Templates {

	/**
	 * Create default templates and map Blog Post SOP to "post".
	 */
	public static function create_on_activation() {
		// Ensure the CPT is registered so the admin will see these nicely.
		$cpt = new EDIWORMAN_Templates_CPT();
		$cpt->register_cpt();

		// 1) Define the templates and their items.
		$definitions = array(
			'Blog Post SOP'            => array(
				'Set featured image',
				'Write excerpt / meta description',
				'Add at least 2 internal links',
				'Check external links (open in new tab if needed)',
				'Spellcheck and grammar check',
				'Confirm category and tags',
			),
			'Landing Page QA'          => array(
				'Check layout on mobile',
				'Test primary CTA button/link',
				'Test form submission (if any)',
				'Confirm thank-you page or message',
				'Confirm analytics / pixel tracking',
				'Check page speed (basic)',
			),
			'Announcement / News Post' => array(
				'Verify dates, names, and key facts',
				'Add internal link to relevant product/service page',
				'Add featured image or banner',
				'Check tone and brand voice',
				'Confirm any required disclaimer',
				'Prepare or schedule social share copy',
			),
		);

		$created_ids = array();

		foreach ( $definitions as $title => $items ) {
			// Try to find an existing template with this title.
			$existing = get_posts(
				array(
					'post_type'      => 'ediworman_template',
					'title'          => $title,
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);

			if ( ! empty( $existing ) ) {
				$template_id = (int) $existing[0];
			} else {
				// Create a new template.
				$template_id = wp_insert_post(
					array(
						'post_type'   => 'ediworman_template',
						'post_status' => 'publish',
						'post_title'  => $title,
					)
				);
			}

			if ( $template_id && ! is_wp_error( $template_id ) ) {
				// Store / update the checklist items meta.
				update_post_meta( $template_id, '_ediworman_items', $items );
				$created_ids[ $title ] = (int) $template_id;
			}
		}

		// 2) Map "Blog Post SOP" to the "post" post type, if nothing set yet.
		if ( isset( $created_ids['Blog Post SOP'] ) ) {
			$settings = get_option( EDIWORMAN_Settings::OPTION_NAME, array() );

			if ( ! isset( $settings['post_type_templates'] ) || ! is_array( $settings['post_type_templates'] ) ) {
				$settings['post_type_templates'] = array();
			}

			// Only set if there is no mapping yet (don't overwrite user choices).
			if ( empty( $settings['post_type_templates']['post'] ) ) {
				$settings['post_type_templates']['post'] = $created_ids['Blog Post SOP'];
				update_option( EDIWORMAN_Settings::OPTION_NAME, $settings );
			}
		}
	}
}
