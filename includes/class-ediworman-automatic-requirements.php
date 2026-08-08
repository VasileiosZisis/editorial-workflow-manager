<?php
/**
 * Built-in automatic editorial requirements.
 *
 * @package EditorialWorkflowManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores, exposes, and evaluates the Lite automatic requirements.
 */
class EDIWORMAN_Automatic_Requirements {

	const META_KEY               = '_ediworman_automatic_requirements';
	const DEFAULT_MINIMUM_WORDS  = 300;
	const MAXIMUM_WORDS          = 100000;
	const FEATURED_IMAGE         = 'featured_image';
	const EXCERPT                = 'excerpt';
	const MINIMUM_WORD_COUNT     = 'minimum_word_count';
	const TAXONOMY_PRESENCE      = 'taxonomy_presence';
	const IMAGE_ALT_TEXT         = 'image_alt_text';

	/**
	 * Return every supported automatic requirement key.
	 *
	 * @return array<int, string>
	 */
	public static function get_rule_keys() {
		return array(
			self::FEATURED_IMAGE,
			self::EXCERPT,
			self::MINIMUM_WORD_COUNT,
			self::TAXONOMY_PRESENCE,
			self::IMAGE_ALT_TEXT,
		);
	}

	/**
	 * Return normalized automatic requirement configuration for a template.
	 *
	 * @param int $template_id Checklist template ID.
	 * @return array<string, array{enabled:bool,minimum?:int}>
	 */
	public static function get_template_config( $template_id ) {
		$template_id = absint( $template_id );
		if ( $template_id <= 0 ) {
			return self::sanitize_config( array() );
		}

		return self::sanitize_config( get_post_meta( $template_id, self::META_KEY, true ) );
	}

	/**
	 * Sanitize automatic requirement configuration against the fixed Lite rules.
	 *
	 * @param mixed $raw_config Raw configuration.
	 * @return array<string, array{enabled:bool,minimum?:int}>
	 */
	public static function sanitize_config( $raw_config ) {
		$raw_config = is_array( $raw_config ) ? $raw_config : array();
		$config     = array();

		foreach ( self::get_rule_keys() as $rule_key ) {
			$raw_rule = isset( $raw_config[ $rule_key ] ) && is_array( $raw_config[ $rule_key ] )
				? $raw_config[ $rule_key ]
				: array();

			$config[ $rule_key ] = array(
				'enabled' => self::normalize_enabled( $raw_rule['enabled'] ?? false ),
			);

			if ( self::MINIMUM_WORD_COUNT === $rule_key ) {
				$minimum = isset( $raw_rule['minimum'] ) && is_scalar( $raw_rule['minimum'] )
					? absint( $raw_rule['minimum'] )
					: self::DEFAULT_MINIMUM_WORDS;

				$config[ $rule_key ]['minimum'] = min(
					self::MAXIMUM_WORDS,
					max( 1, $minimum )
				);
			}
		}

		return $config;
	}

	/**
	 * Return translated labels and descriptions for template administration.
	 *
	 * @return array<string, array{label:string,description:string}>
	 */
	public static function get_rule_definitions() {
		return array(
			self::FEATURED_IMAGE     => array(
				'label'       => __( 'Featured image present', 'editorial-workflow-manager' ),
				'description' => __( 'Passes when the post has a featured image. Ignored for post types without featured-image support.', 'editorial-workflow-manager' ),
			),
			self::EXCERPT            => array(
				'label'       => __( 'Excerpt present', 'editorial-workflow-manager' ),
				'description' => __( 'Passes when the manual excerpt contains text. Ignored for post types without excerpt support.', 'editorial-workflow-manager' ),
			),
			self::MINIMUM_WORD_COUNT => array(
				'label'       => __( 'Minimum word count', 'editorial-workflow-manager' ),
				'description' => __( 'Counts readable words in the post content.', 'editorial-workflow-manager' ),
			),
			self::TAXONOMY_PRESENCE  => array(
				'label'       => __( 'Category or tag present', 'editorial-workflow-manager' ),
				'description' => __( 'Passes when at least one category or tag is assigned. Ignored when neither taxonomy is available.', 'editorial-workflow-manager' ),
			),
			self::IMAGE_ALT_TEXT     => array(
				'label'       => __( 'Image alternative-text coverage', 'editorial-workflow-manager' ),
				'description' => __( 'Passes when the featured image and content images have non-empty alternative text.', 'editorial-workflow-manager' ),
			),
		);
	}

	/**
	 * Return enabled, applicable rules for live block-editor evaluation.
	 *
	 * @param int    $template_id Checklist template ID.
	 * @param string $post_type   Content post type.
	 * @return array{rules:array<int,array{key:string,label:string,minimum?:int}>,taxonomyRestBases:array<int,string>}
	 */
	public static function get_editor_data( $template_id, $post_type ) {
		$post_type  = sanitize_key( $post_type );
		$config     = self::get_template_config( $template_id );
		$rules      = array();
		$taxonomies = self::get_supported_taxonomies( $post_type );

		foreach ( self::get_rule_keys() as $rule_key ) {
			if ( empty( $config[ $rule_key ]['enabled'] ) || ! self::is_rule_applicable( $rule_key, $post_type, $taxonomies ) ) {
				continue;
			}

			$rule = array(
				'key'   => $rule_key,
				'label' => self::get_readiness_label( $rule_key, $config[ $rule_key ] ),
			);

			if ( self::MINIMUM_WORD_COUNT === $rule_key ) {
				$rule['minimum'] = (int) $config[ $rule_key ]['minimum'];
			}

			$rules[] = $rule;
		}

		$rest_bases = array();
		foreach ( $taxonomies as $taxonomy ) {
			$taxonomy_object = get_taxonomy( $taxonomy );
			if ( ! $taxonomy_object || ! $taxonomy_object->show_in_rest ) {
				continue;
			}

			$rest_bases[] = $taxonomy_object->rest_base ? $taxonomy_object->rest_base : $taxonomy_object->name;
		}

		return array(
			'rules'             => $rules,
			'taxonomyRestBases' => array_values( array_unique( array_map( 'sanitize_key', $rest_bases ) ) ),
		);
	}

	/**
	 * Evaluate enabled automatic requirements for a post.
	 *
	 * @param int   $post_id Content post ID.
	 * @param array $config  Optional normalized template configuration.
	 * @return array<int, array{key:string,label:string,passed:bool,message:string}>
	 */
	public static function evaluate_post( $post_id, $config = array() ) {
		$post_id = absint( $post_id );
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return array();
		}

		$config     = self::sanitize_config( $config );
		$post_type  = sanitize_key( $post->post_type );
		$taxonomies = self::get_supported_taxonomies( $post_type );
		$results    = array();

		foreach ( self::get_rule_keys() as $rule_key ) {
			if ( empty( $config[ $rule_key ]['enabled'] ) || ! self::is_rule_applicable( $rule_key, $post_type, $taxonomies ) ) {
				continue;
			}

			$passed  = false;
			$message = '';

			switch ( $rule_key ) {
				case self::FEATURED_IMAGE:
					$thumbnail_id = get_post_thumbnail_id( $post_id );
					$thumbnail    = $thumbnail_id > 0 ? get_post( $thumbnail_id ) : null;
					$passed       = $thumbnail && 'attachment' === $thumbnail->post_type;
					$message      = $passed
						? __( 'Featured image detected.', 'editorial-workflow-manager' )
						: __( 'Add a featured image.', 'editorial-workflow-manager' );
					break;

				case self::EXCERPT:
					$passed  = '' !== trim( wp_strip_all_tags( (string) $post->post_excerpt ) );
					$message = $passed
						? __( 'Excerpt detected.', 'editorial-workflow-manager' )
						: __( 'Add a manual excerpt.', 'editorial-workflow-manager' );
					break;

				case self::MINIMUM_WORD_COUNT:
					$minimum    = (int) $config[ $rule_key ]['minimum'];
					$word_count = self::count_words( $post->post_content );
					$passed     = $word_count >= $minimum;
					$message    = sprintf(
						/* translators: 1: current word count, 2: required minimum word count. */
						__( '%1$d of %2$d required words.', 'editorial-workflow-manager' ),
						$word_count,
						$minimum
					);
					break;

				case self::TAXONOMY_PRESENCE:
					$term_ids = wp_get_object_terms(
						$post_id,
						$taxonomies,
						array( 'fields' => 'ids' )
					);
					$passed   = ! is_wp_error( $term_ids ) && ! empty( $term_ids );
					$message  = $passed
						? __( 'Category or tag detected.', 'editorial-workflow-manager' )
						: __( 'Assign at least one category or tag.', 'editorial-workflow-manager' );
					break;

				case self::IMAGE_ALT_TEXT:
					$image_summary = self::get_image_alt_summary( $post );
					$passed        = 0 === $image_summary['missing'];
					$message       = $passed
						? sprintf(
							/* translators: %d: number of images checked. */
							__( '%d image(s) checked.', 'editorial-workflow-manager' ),
							$image_summary['total']
						)
						: sprintf(
							/* translators: %s: comma-separated image locations missing alternative text. */
							__( 'Add alternative text to: %s.', 'editorial-workflow-manager' ),
							implode( ', ', $image_summary['missing_labels'] )
						);
					break;
			}

			$results[] = array(
				'key'     => $rule_key,
				'label'   => self::get_readiness_label( $rule_key, $config[ $rule_key ] ),
				'passed'  => $passed,
				'message' => $message,
			);
		}

		return $results;
	}

	/**
	 * Return the readiness label for an automatic rule.
	 *
	 * @param string $rule_key Rule key.
	 * @param array  $config   Rule configuration.
	 * @return string
	 */
	private static function get_readiness_label( $rule_key, $config ) {
		if ( self::MINIMUM_WORD_COUNT === $rule_key ) {
			return sprintf(
				/* translators: %d: minimum required word count. */
				__( 'At least %d words', 'editorial-workflow-manager' ),
				(int) ( $config['minimum'] ?? self::DEFAULT_MINIMUM_WORDS )
			);
		}

		$labels = array(
			self::FEATURED_IMAGE    => __( 'Featured image present', 'editorial-workflow-manager' ),
			self::EXCERPT           => __( 'Excerpt present', 'editorial-workflow-manager' ),
			self::TAXONOMY_PRESENCE => __( 'Category or tag present', 'editorial-workflow-manager' ),
			self::IMAGE_ALT_TEXT    => __( 'All images have alternative text', 'editorial-workflow-manager' ),
		);

		return isset( $labels[ $rule_key ] ) ? $labels[ $rule_key ] : '';
	}

	/**
	 * Return supported core editorial taxonomies for a post type.
	 *
	 * @param string $post_type Post type slug.
	 * @return array<int, string>
	 */
	private static function get_supported_taxonomies( $post_type ) {
		$post_type = sanitize_key( $post_type );
		$supported = array();

		foreach ( array( 'category', 'post_tag' ) as $taxonomy ) {
			if ( taxonomy_exists( $taxonomy ) && is_object_in_taxonomy( $post_type, $taxonomy ) ) {
				$supported[] = $taxonomy;
			}
		}

		return $supported;
	}

	/**
	 * Return whether a rule can be evaluated for a post type.
	 *
	 * @param string            $rule_key  Rule key.
	 * @param string            $post_type Post type slug.
	 * @param array<int,string> $taxonomies Supported taxonomies.
	 * @return bool
	 */
	private static function is_rule_applicable( $rule_key, $post_type, $taxonomies ) {
		switch ( $rule_key ) {
			case self::FEATURED_IMAGE:
				return post_type_supports( $post_type, 'thumbnail' ) && current_theme_supports( 'post-thumbnails' );
			case self::EXCERPT:
				return post_type_supports( $post_type, 'excerpt' );
			case self::TAXONOMY_PRESENCE:
				return ! empty( $taxonomies );
			case self::MINIMUM_WORD_COUNT:
			case self::IMAGE_ALT_TEXT:
				return post_type_supports( $post_type, 'editor' );
		}

		return false;
	}

	/**
	 * Count Unicode words in stored post content.
	 *
	 * @param string $content Post content.
	 * @return int
	 */
	private static function count_words( $content ) {
		$content_without_shortcodes = preg_replace( '/\[[^\]]*\]/u', ' ', (string) $content );
		$text = html_entity_decode( wp_strip_all_tags( (string) $content_without_shortcodes ), ENT_QUOTES, get_bloginfo( 'charset' ) );
		$count = preg_match_all( "/[\\p{L}\\p{N}]+(?:[\\x{2019}'-][\\p{L}\\p{N}]+)*/u", $text, $matches );
		unset( $matches );

		return false === $count ? 0 : (int) $count;
	}

	/**
	 * Return total and missing-alt counts for featured and content images.
	 *
	 * @param WP_Post $post Content post.
	 * An empty alternative-text value is treated as incomplete. This keeps the
	 * Lite rule deterministic; templates that intentionally use decorative
	 * images can leave this rule disabled.
	 *
	 * @return array{total:int,missing:int,missing_labels:array<int,string>}
	 */
	private static function get_image_alt_summary( $post ) {
		$images       = array();
		$thumbnail_id = get_post_thumbnail_id( $post->ID );

		if ( $thumbnail_id > 0 ) {
			$images[] = array(
				'id'     => $thumbnail_id,
				'alt'    => '',
				'source' => __( 'Featured image', 'editorial-workflow-manager' ),
			);
		}

		self::collect_images_from_blocks( parse_blocks( (string) $post->post_content ), $images );

		$missing        = 0;
		$missing_labels = array();
		$content_image  = 0;
		foreach ( $images as $image ) {
			$alt = isset( $image['alt'] ) ? trim( wp_strip_all_tags( (string) $image['alt'] ) ) : '';
			$id  = isset( $image['id'] ) ? absint( $image['id'] ) : 0;
			$source = isset( $image['source'] ) ? sanitize_text_field( (string) $image['source'] ) : '';

			if ( '' === $source ) {
				++$content_image;
				$source = sprintf(
					/* translators: %d: image position in post content. */
					__( 'Content image %d', 'editorial-workflow-manager' ),
					$content_image
				);
			}

			if ( '' === $alt && $id > 0 ) {
				$alt = trim( wp_strip_all_tags( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ) );
			}

			if ( '' === $alt ) {
				++$missing;
				$missing_labels[] = $source;
			}
		}

		return array(
			'total'   => count( $images ),
			'missing' => $missing,
			'missing_labels' => $missing_labels,
		);
	}

	/**
	 * Collect image references from parsed blocks.
	 *
	 * @param array $blocks Parsed blocks.
	 * @param array $images Collected image references.
	 * @return void
	 */
	private static function collect_images_from_blocks( $blocks, &$images ) {
		if ( ! is_array( $blocks ) ) {
			return;
		}

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$block_name = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
			$attrs      = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();

			if ( 'core/image' === $block_name ) {
				$html_image = self::get_first_image_from_html( (string) ( $block['innerHTML'] ?? '' ) );
				$images[]   = array(
					'id'  => isset( $attrs['id'] ) ? absint( $attrs['id'] ) : $html_image['id'],
					'alt' => isset( $attrs['alt'] ) && is_scalar( $attrs['alt'] ) ? (string) $attrs['alt'] : $html_image['alt'],
				);
			} else {
				self::collect_images_from_html( (string) ( $block['innerHTML'] ?? '' ), $images );

				if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
					self::collect_images_from_blocks( $block['innerBlocks'], $images );
				}
			}
		}
	}

	/**
	 * Collect every image reference found in an HTML fragment.
	 *
	 * @param string $html   HTML fragment.
	 * @param array  $images Collected image references.
	 * @return void
	 */
	private static function collect_images_from_html( $html, &$images ) {
		if ( ! preg_match_all( '/<img\\b[^>]*>/i', $html, $matches ) ) {
			return;
		}

		foreach ( $matches[0] as $image_html ) {
			$images[] = self::get_first_image_from_html( $image_html );
		}
	}

	/**
	 * Extract attachment ID and alternative text from the first image in HTML.
	 *
	 * @param string $html HTML fragment.
	 * @return array{id:int,alt:string}
	 */
	private static function get_first_image_from_html( $html ) {
		$id  = 0;
		$alt = '';

		if ( preg_match( '/\\bwp-image-(\\d+)\\b/i', $html, $id_match ) ) {
			$id = absint( $id_match[1] );
		}

		if ( preg_match( '/\\balt\\s*=\\s*(["\'])(.*?)\\1/is', $html, $alt_match ) ) {
			$alt = html_entity_decode( wp_strip_all_tags( $alt_match[2] ), ENT_QUOTES, get_bloginfo( 'charset' ) );
		}

		return array(
			'id'  => $id,
			'alt' => $alt,
		);
	}

	/**
	 * Normalize checkbox-like input.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	private static function normalize_enabled( $value ) {
		return true === $value || 1 === $value || '1' === $value || 'true' === $value || 'on' === $value;
	}
}
