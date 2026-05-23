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
		// Ensure the CPT exists during activation, when init may have already passed.
		if ( ! post_type_exists( 'ediworman_template' ) ) {
			$cpt = new EDIWORMAN_Templates_CPT();
			$cpt->register_cpt();
		}

		$created_ids = array();

		foreach ( self::get_template_definitions() as $title => $definition ) {
			$template_id = self::find_template_id_by_title( $title );

			if ( $template_id <= 0 ) {
				$template_id = self::create_template( $title, $definition );
			}

			if ( $template_id > 0 ) {
				$created_ids[ $title ] = $template_id;
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

	/**
	 * Return starter checklist template definitions.
	 *
	 * @return array<string, array{mode:string, items:array<int, string|array{label:string, description:string, required:bool}>}>
	 */
	private static function get_template_definitions() {
		return array(
			'Blog Post SOP'            => array(
				'mode'  => 'legacy',
				'items' => array(
					'Set featured image',
					'Write excerpt / meta description',
					'Add at least 2 internal links',
					'Check external links (open in new tab if needed)',
					'Spellcheck and grammar check',
					'Confirm category and tags',
				),
			),
			'Landing Page QA'          => array(
				'mode'  => 'legacy',
				'items' => array(
					'Check layout on mobile',
					'Test primary CTA button/link',
					'Test form submission (if any)',
					'Confirm thank-you page or message',
					'Confirm analytics / pixel tracking',
					'Check page speed (basic)',
				),
			),
			'Announcement / News Post' => array(
				'mode'  => 'legacy',
				'items' => array(
					'Verify dates, names, and key facts',
					'Add internal link to relevant product/service page',
					'Add featured image or banner',
					'Check tone and brand voice',
					'Confirm any required disclaimer',
					'Prepare or schedule social share copy',
				),
			),
			'Blog SEO'                 => array(
				'mode'  => 'v2',
				'items' => array(
					array(
						'label'       => 'Confirm target keyword and search intent',
						'description' => 'Identify the primary query this post should satisfy and make sure the draft answers it clearly.',
						'required'    => true,
					),
					array(
						'label'       => 'Write SEO title and meta description',
						'description' => 'Keep the title and description specific, useful, and aligned with the post content.',
						'required'    => true,
					),
					array(
						'label'       => 'Use a clear heading structure',
						'description' => 'Check that headings are descriptive and nested in a logical order.',
						'required'    => true,
					),
					array(
						'label'       => 'Add internal links',
						'description' => 'Link to relevant existing content where it genuinely helps the reader.',
						'required'    => true,
					),
					array(
						'label'       => 'Review image alt text',
						'description' => 'Add useful alt text for informative images and leave decorative images empty when appropriate.',
						'required'    => false,
					),
				),
			),
			'News Fact-Check'          => array(
				'mode'  => 'v2',
				'items' => array(
					array(
						'label'       => 'Verify names, dates, and titles',
						'description' => 'Check proper nouns, dates, job titles, organization names, and locations against reliable sources.',
						'required'    => true,
					),
					array(
						'label'       => 'Confirm source attribution',
						'description' => 'Make sure claims are attributed clearly and quotes are matched to the right source.',
						'required'    => true,
					),
					array(
						'label'       => 'Check links and cited references',
						'description' => 'Open referenced links and confirm they support the surrounding statement.',
						'required'    => true,
					),
					array(
						'label'       => 'Review headline accuracy',
						'description' => 'Confirm the headline is specific and does not overstate the reporting.',
						'required'    => true,
					),
					array(
						'label'       => 'Flag legal or sensitive claims',
						'description' => 'Escalate allegations, medical/legal claims, or other high-risk assertions before publishing.',
						'required'    => false,
					),
				),
			),
			'Accessibility Review'     => array(
				'mode'  => 'v2',
				'items' => array(
					array(
						'label'       => 'Review heading order',
						'description' => 'Check that headings describe the content and do not skip levels for visual styling alone.',
						'required'    => true,
					),
					array(
						'label'       => 'Check link text',
						'description' => 'Use link text that makes sense out of context. Avoid vague labels such as "click here".',
						'required'    => true,
					),
					array(
						'label'       => 'Add meaningful alt text',
						'description' => 'Describe informative images concisely and mark decorative images appropriately.',
						'required'    => true,
					),
					array(
						'label'       => 'Check color-dependent meaning',
						'description' => 'Make sure instructions and status indicators do not rely only on color.',
						'required'    => true,
					),
					array(
						'label'       => 'Review embeds and media',
						'description' => 'Confirm videos, audio, and embeds have accessible labels, captions, transcripts, or fallback text where needed.',
						'required'    => false,
					),
				),
			),
			'Client Approval'          => array(
				'mode'  => 'v2',
				'items' => array(
					array(
						'label'       => 'Confirm client brief requirements',
						'description' => 'Compare the draft against the agreed scope, audience, message, and required deliverables.',
						'required'    => true,
					),
					array(
						'label'       => 'Check brand voice and terminology',
						'description' => 'Review names, product terms, claims, and phrasing against client preferences.',
						'required'    => true,
					),
					array(
						'label'       => 'Send preview to client stakeholder',
						'description' => 'Share the draft or preview through the agreed review channel.',
						'required'    => true,
					),
					array(
						'label'       => 'Record approval or requested changes',
						'description' => 'Capture the client response before marking the draft ready.',
						'required'    => true,
					),
					array(
						'label'       => 'Confirm final publish timing',
						'description' => 'Verify the launch date, time zone, and any dependencies before scheduling.',
						'required'    => false,
					),
				),
			),
		);
	}

	/**
	 * Return an existing template ID for an exact title match.
	 *
	 * @param string $title Template title.
	 * @return int
	 */
	private static function find_template_id_by_title( $title ) {
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

		if ( empty( $existing ) ) {
			return 0;
		}

		return (int) $existing[0];
	}

	/**
	 * Create a checklist template from a starter definition.
	 *
	 * @param string $title      Template title.
	 * @param array  $definition Template definition.
	 * @return int
	 */
	private static function create_template( $title, $definition ) {
		$template_id = wp_insert_post(
			array(
				'post_type'   => 'ediworman_template',
				'post_status' => 'publish',
				'post_title'  => $title,
			)
		);

		if ( ! $template_id || is_wp_error( $template_id ) ) {
			return 0;
		}

		$template_id = (int) $template_id;
		$items       = isset( $definition['items'] ) && is_array( $definition['items'] ) ? $definition['items'] : array();
		$mode        = isset( $definition['mode'] ) ? (string) $definition['mode'] : 'legacy';

		if ( 'v2' === $mode ) {
			$items_v2 = self::normalize_v2_items( $items );
			if ( ! empty( $items_v2 ) ) {
				update_post_meta( $template_id, '_ediworman_items_v2', $items_v2 );
				update_post_meta( $template_id, '_ediworman_items', wp_list_pluck( $items_v2, 'label' ) );
			}
		} else {
			update_post_meta( $template_id, '_ediworman_items', array_map( 'sanitize_text_field', $items ) );
		}

		return $template_id;
	}

	/**
	 * Normalize curated v2 starter items.
	 *
	 * @param array<int, array{label:string, description:string, required:bool}> $items Raw v2 starter items.
	 * @return array<int, array{id:string,label:string,description:string,url:string,required:bool}>
	 */
	private static function normalize_v2_items( $items ) {
		$normalized = array();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$label = isset( $item['label'] ) ? sanitize_text_field( (string) $item['label'] ) : '';
			if ( '' === $label ) {
				continue;
			}

			$normalized[] = array(
				'id'          => wp_generate_uuid4(),
				'label'       => $label,
				'description' => isset( $item['description'] ) ? sanitize_textarea_field( (string) $item['description'] ) : '',
				'url'         => '',
				'required'    => isset( $item['required'] ) ? (bool) $item['required'] : true,
			);
		}

		return $normalized;
	}
}
