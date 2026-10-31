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
	const DEFAULT_MAXIMUM_WORDS  = 1000;
	const MAXIMUM_WORDS          = 100000;
	const FEATURED_IMAGE         = 'featured_image';
	const EXCERPT                = 'excerpt';
	const MINIMUM_WORD_COUNT     = 'minimum_word_count';
	const MAXIMUM_WORD_COUNT     = 'maximum_word_count';
	const MINIMUM_INTERNAL_LINK_COUNT = 'minimum_internal_link_count';
	const TAXONOMY_PRESENCE      = 'taxonomy_presence';
	const MINIMUM_CATEGORY_COUNT = 'minimum_category_count';
	const MAXIMUM_CATEGORY_COUNT = 'maximum_category_count';
	const MINIMUM_TAG_COUNT      = 'minimum_tag_count';
	const MAXIMUM_TAG_COUNT      = 'maximum_tag_count';
	const IMAGE_ALT_TEXT         = 'image_alt_text';

	/**
	 * Register the built-in rules with the shared registry.
	 *
	 * @return void
	 */
	public static function register_builtin_rules() {
		$definitions = self::get_builtin_rule_definitions();
		$dependencies = array(
			self::FEATURED_IMAGE     => array( 'post_fields' => array( 'featured_media' ) ),
			self::EXCERPT            => array( 'post_fields' => array( 'post_excerpt' ) ),
			self::MINIMUM_WORD_COUNT => array( 'post_fields' => array( 'post_content' ) ),
			self::MAXIMUM_WORD_COUNT => array( 'post_fields' => array( 'post_content' ) ),
			self::MINIMUM_INTERNAL_LINK_COUNT => array( 'post_fields' => array( 'post_content' ), 'taxonomies' => array( 'category' ) ),
			self::TAXONOMY_PRESENCE  => array( 'taxonomies' => array( 'category', 'post_tag' ) ),
			self::MINIMUM_CATEGORY_COUNT => array( 'taxonomies' => array( 'category' ) ),
			self::MAXIMUM_CATEGORY_COUNT => array( 'taxonomies' => array( 'category' ) ),
			self::MINIMUM_TAG_COUNT => array( 'taxonomies' => array( 'post_tag' ) ),
			self::MAXIMUM_TAG_COUNT => array( 'taxonomies' => array( 'post_tag' ) ),
			self::IMAGE_ALT_TEXT     => array(
				'post_fields'    => array( 'post_content', 'featured_media' ),
				'attachment_alt' => true,
			),
		);

		foreach ( self::get_builtin_rule_keys() as $rule_key ) {
			EDIWORMAN_Rule_Registry::register_builtin_rule(
				$rule_key,
				array(
					'api_version'       => EDIWORMAN_Rule_Registry::API_VERSION,
					'rule_version'      => '1.0.0',
					'label'             => $definitions[ $rule_key ]['label'],
					'description'       => $definitions[ $rule_key ]['description'],
					'dependencies'      => $dependencies[ $rule_key ],
					'evaluate_callback' => array( __CLASS__, 'evaluate_builtin_rule' ),
				)
			);
		}
	}

	/**
	 * Return every supported automatic requirement key.
	 *
	 * @return array<int, string>
	 */
	public static function get_builtin_rule_keys() {
		return array(
			self::FEATURED_IMAGE,
			self::EXCERPT,
			self::MINIMUM_WORD_COUNT,
			self::MAXIMUM_WORD_COUNT,
			self::MINIMUM_INTERNAL_LINK_COUNT,
			self::TAXONOMY_PRESENCE,
			self::MINIMUM_CATEGORY_COUNT,
			self::MAXIMUM_CATEGORY_COUNT,
			self::MINIMUM_TAG_COUNT,
			self::MAXIMUM_TAG_COUNT,
			self::IMAGE_ALT_TEXT,
		);
	}

	/**
	 * Return every currently registered automatic requirement key.
	 *
	 * @return array<int,string>
	 */
	public static function get_rule_keys() {
		return array_keys( EDIWORMAN_Rule_Registry::get_rules() );
	}

	/**
	 * Numeric settings shared by template validation and administration.
	 *
	 * @return array<string,array{group:string,bound:string,default:int,min:int}>
	 */
	public static function get_limit_settings() {
		return array(
			self::MINIMUM_WORD_COUNT => array( 'group' => 'words', 'bound' => 'minimum', 'default' => self::DEFAULT_MINIMUM_WORDS, 'min' => 1 ),
			self::MAXIMUM_WORD_COUNT => array( 'group' => 'words', 'bound' => 'maximum', 'default' => self::DEFAULT_MAXIMUM_WORDS, 'min' => 1 ),
			self::MINIMUM_INTERNAL_LINK_COUNT => array( 'group' => 'internal_links', 'bound' => 'minimum', 'default' => 1, 'min' => 1 ),
			self::MINIMUM_CATEGORY_COUNT => array( 'group' => 'categories', 'bound' => 'minimum', 'default' => 1, 'min' => 1 ),
			self::MAXIMUM_CATEGORY_COUNT => array( 'group' => 'categories', 'bound' => 'maximum', 'default' => 5, 'min' => 0 ),
			self::MINIMUM_TAG_COUNT => array( 'group' => 'tags', 'bound' => 'minimum', 'default' => 1, 'min' => 1 ),
			self::MAXIMUM_TAG_COUNT => array( 'group' => 'tags', 'bound' => 'maximum', 'default' => 5, 'min' => 0 ),
		);
	}

	/**
	 * Return the core taxonomy for a count rule, or an empty string.
	 *
	 * @param string $rule_id Rule ID.
	 * @return string
	 */
	public static function get_count_rule_taxonomy( $rule_id ) {
		if ( in_array( $rule_id, array( self::MINIMUM_CATEGORY_COUNT, self::MAXIMUM_CATEGORY_COUNT ), true ) ) {
			return 'category';
		}
		return in_array( $rule_id, array( self::MINIMUM_TAG_COUNT, self::MAXIMUM_TAG_COUNT ), true ) ? 'post_tag' : '';
	}

	/**
	 * Return normalized automatic requirement configuration for a template.
	 *
	 * @param int $template_id Checklist template ID.
	 * @return array<string, array{enabled:bool,minimum?:int,maximum?:int}>
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
	 * @return array<string, array{enabled:bool,minimum?:int,maximum?:int}>
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
			if ( self::MAXIMUM_WORD_COUNT === $rule_key ) {
				$maximum = isset( $raw_rule['maximum'] ) && is_scalar( $raw_rule['maximum'] )
					? absint( $raw_rule['maximum'] )
					: self::DEFAULT_MAXIMUM_WORDS;
				$config[ $rule_key ]['maximum'] = min( self::MAXIMUM_WORDS, max( 1, $maximum ) );
			}
			if ( self::get_count_rule_taxonomy( $rule_key ) || self::MINIMUM_INTERNAL_LINK_COUNT === $rule_key ) {
				$setting = self::get_limit_settings()[ $rule_key ];
				$bound = $setting['bound'];
				$value = $raw_rule[ $bound ] ?? $setting['default'];
				$config[ $rule_key ][ $bound ] = self::is_valid_limit( $value, $setting['min'] ) ? (int) $value : $setting['default'];
			}
		}

		// Keep unavailable custom registrations so their enablement returns when
		// the provider plugin is reactivated. Unknown built-in-looking keys are
		// deliberately discarded.
		foreach ( $raw_config as $rule_key => $raw_rule ) {
			if ( isset( $config[ $rule_key ] ) || ! is_string( $rule_key ) || false === strpos( $rule_key, '/' ) || ! is_array( $raw_rule ) ) {
				continue;
			}

			$config[ $rule_key ] = array(
				'enabled' => self::normalize_enabled( $raw_rule['enabled'] ?? false ),
			);
		}

		return $config;
	}

	/**
	 * Validate enabled word bounds before normalizing a template submission.
	 *
	 * @param array $raw_config Unslashed submitted automatic requirements.
	 * @return string Empty on success, otherwise an allow-listed error code.
	 */
	public static function validate_word_limits( $raw_config ) {
		return self::validate_limit_group( $raw_config, 'words' );
	}

	/**
	 * Strictly validate one related pair without altering other rule settings.
	 *
	 * @param array  $raw_config Unslashed configuration.
	 * @param string $group      Words, categories, or tags.
	 * @return string Empty on success; otherwise invalid or range.
	 */
	public static function validate_limit_group( $raw_config, $group ) {
		$bounds = array();
		foreach ( self::get_limit_settings() as $rule_id => $setting ) {
			if ( $group !== $setting['group'] ) {
				continue;
			}
			$field = $setting['bound'];
			$rule = isset( $raw_config[ $rule_id ] ) && is_array( $raw_config[ $rule_id ] ) ? $raw_config[ $rule_id ] : array();
			// Match stored checkbox normalization, but validate thresholds unmodified.
			$enabled = $rule['enabled'] ?? false;
			$enabled = is_scalar( $enabled ) ? sanitize_text_field( (string) $enabled ) : $enabled;
			if ( ! self::normalize_enabled( $enabled ) ) {
				continue;
			}
			$value = $rule[ $field ] ?? null;
			if ( ! self::is_valid_limit( $value, $setting['min'] ) ) {
				return 'invalid';
			}
			$bounds[ $field ] = (int) $value;
		}
		return isset( $bounds['minimum'], $bounds['maximum'] ) && $bounds['minimum'] > $bounds['maximum'] ? 'range' : '';
	}

	/**
	 * Check a submitted whole-number threshold without coercing malformed input.
	 *
	 * @param mixed $value Value to validate.
	 * @param int   $floor Smallest allowed value.
	 * @return bool
	 */
	public static function is_valid_limit( $value, $floor ) {
		return ( is_int( $value ) || is_string( $value ) ) && preg_match( '/^[0-9]+$/D', (string) $value ) && (float) $value >= $floor && (float) $value <= self::MAXIMUM_WORDS;
	}

	/**
	 * Share PHP's HTML 4 entity vocabulary with the provisional editor counter.
	 *
	 * @return array<string,string>
	 */
	public static function get_word_count_entities() {
		return array_flip( get_html_translation_table( HTML_ENTITIES, ENT_QUOTES, get_bloginfo( 'charset' ) ) );
	}

	/**
	 * Return translated labels and descriptions for template administration.
	 *
	 * @return array<string, array{label:string,description:string}>
	 */
	private static function get_builtin_rule_definitions() {
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
			self::MAXIMUM_WORD_COUNT => array(
				'label'       => __( 'Maximum word count', 'editorial-workflow-manager' ),
				'description' => __( 'Limits readable words in the post content. Empty content passes this check; enable a minimum separately if needed.', 'editorial-workflow-manager' ),
			),
			self::MINIMUM_INTERNAL_LINK_COUNT => array(
				'label' => __( 'Minimum internal links', 'editorial-workflow-manager' ),
				'description' => __( 'Counts unique link destinations within this site’s home URL, including files and images. Repeated destinations count once; fragments and links to this post do not count. Checks literal content without loading linked pages or rendering dynamic content.', 'editorial-workflow-manager' ),
			),
			self::TAXONOMY_PRESENCE  => array(
				'label'       => __( 'Category or tag present', 'editorial-workflow-manager' ),
				'description' => __( 'Passes when at least one category or tag is assigned. Ignored when neither taxonomy is available.', 'editorial-workflow-manager' ),
			),
			self::MINIMUM_CATEGORY_COUNT => array(
				'label' => __( 'Minimum categories', 'editorial-workflow-manager' ),
				'description' => __( 'Counts directly assigned categories, including the default category WordPress may assign when saving. Unassigned ancestors do not count. Ignored when categories are unsupported.', 'editorial-workflow-manager' ),
			),
			self::MAXIMUM_CATEGORY_COUNT => array(
				'label' => __( 'Maximum categories', 'editorial-workflow-manager' ),
				'description' => __( 'Limits directly assigned categories. Zero requires no categories, but WordPress may assign a default category when saving. Ignored when categories are unsupported.', 'editorial-workflow-manager' ),
			),
			self::MINIMUM_TAG_COUNT => array(
				'label' => __( 'Minimum tags', 'editorial-workflow-manager' ),
				'description' => __( 'Counts distinct tags assigned to the post. Ignored when tags are unsupported.', 'editorial-workflow-manager' ),
			),
			self::MAXIMUM_TAG_COUNT => array(
				'label' => __( 'Maximum tags', 'editorial-workflow-manager' ),
				'description' => __( 'Limits distinct tags assigned to the post. Zero requires no tags. Ignored when tags are unsupported.', 'editorial-workflow-manager' ),
			),
			self::IMAGE_ALT_TEXT     => array(
				'label'       => __( 'Image alternative-text coverage', 'editorial-workflow-manager' ),
				'description' => __( 'Passes when the featured image and content images have non-empty alternative text.', 'editorial-workflow-manager' ),
			),
		);
	}

	/**
	 * Return registered labels and descriptions for template administration.
	 *
	 * @return array<string,array{label:string,description:string,builtin:bool}>
	 */
	public static function get_rule_definitions() {
		$definitions = array();
		foreach ( EDIWORMAN_Rule_Registry::get_rules() as $rule_id => $rule ) {
			$definitions[ $rule_id ] = array(
				'label'       => $rule['label'],
				'description' => $rule['description'],
				'builtin'     => $rule['builtin'],
			);
		}

		return $definitions;
	}

	/**
	 * Return enabled, applicable rules for live block-editor evaluation.
	 *
	 * @param int    $template_id Checklist template ID.
	 * @param string $post_type   Content post type.
	 * @return array{rules:array<int,array>,taxonomyRestBases:array<int,string>,taxonomyRestFields:array<string,string|null>,editorScripts:array<int,string>}
	 */
	public static function get_editor_data( $template_id, $post_type ) {
		$post_type  = sanitize_key( $post_type );
		$config     = self::get_template_config( $template_id );
		$rules      = array();
		$editor_scripts = array();
		$taxonomies = self::get_supported_taxonomies( $post_type );
		$taxonomy_rest_fields = array();
		foreach ( $taxonomies as $taxonomy ) {
			$object = get_taxonomy( $taxonomy );
			$taxonomy_rest_fields[ $taxonomy ] = $object && $object->show_in_rest ? ( $object->rest_base ? $object->rest_base : $object->name ) : null;
		}

		foreach ( self::get_rule_keys() as $rule_key ) {
			if ( empty( $config[ $rule_key ]['enabled'] ) || ! self::is_rule_applicable( $rule_key, $post_type, $taxonomies ) ) {
				continue;
			}

			$definition = EDIWORMAN_Rule_Registry::get_rule( $rule_key );
			if ( ! $definition ) {
				continue;
			}

			$rule = array(
				'id'    => $rule_key,
				'key'   => $rule_key,
				'label' => self::get_readiness_label( $rule_key, $config[ $rule_key ] ),
				'builtin' => ! empty( $definition['builtin'] ),
				'hasClientEvaluator' => ! empty( $definition['builtin'] ) || '' !== $definition['editor_script'],
				'config' => $config[ $rule_key ],
			);

			if ( self::MINIMUM_WORD_COUNT === $rule_key || self::MINIMUM_INTERNAL_LINK_COUNT === $rule_key ) {
				$rule['minimum'] = (int) $config[ $rule_key ]['minimum'];
			}
			if ( self::MAXIMUM_WORD_COUNT === $rule_key ) {
				$rule['maximum'] = (int) $config[ $rule_key ]['maximum'];
			}
			$taxonomy = self::get_count_rule_taxonomy( $rule_key );
			if ( $taxonomy ) {
				$setting = self::get_limit_settings()[ $rule_key ];
				$rule[ $setting['bound'] ] = (int) $config[ $rule_key ][ $setting['bound'] ];
				$rule['taxonomy'] = $taxonomy;
				$rule['hasClientEvaluator'] = ! empty( $taxonomy_rest_fields[ $taxonomy ] );
			}

			$rules[] = $rule;
			if ( ! empty( $definition['editor_script'] ) ) {
				$editor_scripts[] = $definition['editor_script'];
			}
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
			'taxonomyRestFields' => $taxonomy_rest_fields,
			'editorScripts'     => array_values( array_unique( $editor_scripts ) ),
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

		if ( empty( $config ) ) {
			$template_id = EDIWORMAN_Settings::get_template_for_post_type( $post->post_type );
			$config      = $template_id ? self::get_template_config( $template_id ) : array();
		} else {
			$config = self::sanitize_config( $config );
		}

		$post_type  = sanitize_key( $post->post_type );
		$taxonomies = self::get_supported_taxonomies( $post_type );
		$results    = array();

		foreach ( self::get_rule_keys() as $rule_key ) {
			if ( empty( $config[ $rule_key ]['enabled'] ) || ! self::is_rule_applicable( $rule_key, $post_type, $taxonomies ) ) {
				continue;
			}

			$result = EDIWORMAN_Rule_Registry::evaluate( $rule_key, $post_id, $config[ $rule_key ] );
			if ( EDIWORMAN_Rule_Registry::STATUS_NOT_APPLICABLE === $result['status'] ) {
				continue;
			}

			$result['label'] = self::get_readiness_label( $rule_key, $config[ $rule_key ] );
			$results[]       = $result;
		}

		return $results;
	}

	/**
	 * Evaluate one built-in requirement through the public result schema.
	 *
	 * @param int   $post_id Content post ID.
	 * @param array $config  Rule configuration.
	 * @param array $rule    Registered rule definition.
	 * @return array{status:string,message:string}
	 */
	public static function evaluate_builtin_rule( $post_id, $config, $rule ) {
		$post       = get_post( $post_id );
		$rule_key   = $rule['id'];
		$post_type  = $post ? sanitize_key( $post->post_type ) : '';
		$taxonomies = self::get_supported_taxonomies( $post_type );

		if ( ! $post || ! self::is_rule_applicable( $rule_key, $post_type, $taxonomies ) ) {
			return array( 'status' => EDIWORMAN_Rule_Registry::STATUS_NOT_APPLICABLE, 'message' => '' );
		}

		$passed  = false;
		$message = '';
		$taxonomy = self::get_count_rule_taxonomy( $rule_key );
		if ( $taxonomy ) {
			$term_ids = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
			if ( is_wp_error( $term_ids ) ) {
				return array( 'status' => EDIWORMAN_Rule_Registry::STATUS_FAIL, 'message' => __( 'Assigned terms could not be checked. Save again or contact a site administrator.', 'editorial-workflow-manager' ) );
			}
			$count = count( array_unique( array_map( 'absint', $term_ids ) ) );
			$setting = self::get_limit_settings()[ $rule_key ];
			$limit = (int) ( $config[ $setting['bound'] ] ?? $setting['default'] );
			$passed = 'minimum' === $setting['bound'] ? $count >= $limit : $count <= $limit;
			return array(
				'status' => $passed ? EDIWORMAN_Rule_Registry::STATUS_PASS : EDIWORMAN_Rule_Registry::STATUS_FAIL,
				'message' => self::get_taxonomy_count_message( $rule_key, $count, $limit, $passed ),
			);
		}

		switch ( $rule_key ) {
			case self::MINIMUM_INTERNAL_LINK_COUNT:
				$minimum = (int) ( $config['minimum'] ?? 1 );
				$count = EDIWORMAN_Internal_Links::count( (string) $post->post_content, home_url( '/' ), EDIWORMAN_Internal_Links::get_post_url( $post_id ) );
				$passed = $count >= $minimum;
				$message = sprintf(
					/* translators: 1: actual unique internal-link count, 2: required minimum count. */
					_n( '%1$d unique internal link; minimum %2$d.', '%1$d unique internal links; minimum %2$d.', $count, 'editorial-workflow-manager' ),
					$count,
					$minimum
				);
				if ( ! $passed ) {
					$message .= ' ' . __( 'Add links to other destinations on this site.', 'editorial-workflow-manager' );
				}
				break;
			case self::FEATURED_IMAGE:
				$thumbnail_id = get_post_thumbnail_id( $post_id );
				$thumbnail    = $thumbnail_id > 0 ? get_post( $thumbnail_id ) : null;
				$passed       = $thumbnail && 'attachment' === $thumbnail->post_type;
				$message      = $passed ? __( 'Featured image detected.', 'editorial-workflow-manager' ) : __( 'Add a featured image.', 'editorial-workflow-manager' );
				break;
			case self::MAXIMUM_WORD_COUNT:
				$maximum    = (int) ( $config['maximum'] ?? self::DEFAULT_MAXIMUM_WORDS );
				$word_count = self::count_words( $post->post_content );
				$passed     = $word_count <= $maximum;
				$message    = $passed
					? sprintf(
						/* translators: 1: current word count, 2: allowed maximum word count. */
						__( '%1$d words; maximum %2$d.', 'editorial-workflow-manager' ),
						$word_count,
						$maximum
					)
					: sprintf(
						/* translators: 1: current word count, 2: allowed maximum word count. */
						__( '%1$d words; maximum %2$d. Shorten the content to meet the limit.', 'editorial-workflow-manager' ),
						$word_count,
						$maximum
					);
				break;
			case self::EXCERPT:
				$passed  = '' !== trim( wp_strip_all_tags( (string) $post->post_excerpt ) );
				$message = $passed ? __( 'Excerpt detected.', 'editorial-workflow-manager' ) : __( 'Add a manual excerpt.', 'editorial-workflow-manager' );
				break;
			case self::MINIMUM_WORD_COUNT:
				$minimum    = (int) ( $config['minimum'] ?? self::DEFAULT_MINIMUM_WORDS );
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
				$term_ids = wp_get_object_terms( $post_id, $taxonomies, array( 'fields' => 'ids' ) );
				$passed   = ! is_wp_error( $term_ids ) && ! empty( $term_ids );
				$message  = $passed ? __( 'Category or tag detected.', 'editorial-workflow-manager' ) : __( 'Assign at least one category or tag.', 'editorial-workflow-manager' );
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

		return array(
			'status'  => $passed ? EDIWORMAN_Rule_Registry::STATUS_PASS : EDIWORMAN_Rule_Registry::STATUS_FAIL,
			'message' => $message,
		);
	}

	/**
	 * Return the readiness label for an automatic rule.
	 *
	 * @param string $rule_key Rule key.
	 * @param array  $config   Rule configuration.
	 * @return string
	 */
	private static function get_readiness_label( $rule_key, $config ) {
		if ( self::MINIMUM_INTERNAL_LINK_COUNT === $rule_key ) {
			$minimum = (int) ( $config['minimum'] ?? 1 );
			return sprintf(
				/* translators: %d: required minimum unique internal-link count. */
				_n( 'At least %d unique internal link', 'At least %d unique internal links', $minimum, 'editorial-workflow-manager' ),
				$minimum
			);
		}
		if ( self::get_count_rule_taxonomy( $rule_key ) ) {
			$setting = self::get_limit_settings()[ $rule_key ];
			$limit = (int) $config[ $setting['bound'] ];
			$labels = array(
				/* translators: %d: minimum category count. */
				self::MINIMUM_CATEGORY_COUNT => _n( 'At least %d category', 'At least %d categories', $limit, 'editorial-workflow-manager' ),
				/* translators: %d: maximum category count. */
				self::MAXIMUM_CATEGORY_COUNT => _n( 'At most %d category', 'At most %d categories', $limit, 'editorial-workflow-manager' ),
				/* translators: %d: minimum tag count. */
				self::MINIMUM_TAG_COUNT => _n( 'At least %d tag', 'At least %d tags', $limit, 'editorial-workflow-manager' ),
				/* translators: %d: maximum tag count. */
				self::MAXIMUM_TAG_COUNT => _n( 'At most %d tag', 'At most %d tags', $limit, 'editorial-workflow-manager' ),
			);
			return sprintf( $labels[ $rule_key ], $limit );
		}
		if ( self::MAXIMUM_WORD_COUNT === $rule_key ) {
			return sprintf(
				/* translators: %d: allowed maximum word count. */
				__( 'At most %d words', 'editorial-workflow-manager' ),
				(int) ( $config['maximum'] ?? self::DEFAULT_MAXIMUM_WORDS )
			);
		}
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

		if ( isset( $labels[ $rule_key ] ) ) {
			return $labels[ $rule_key ];
		}

		$rule = EDIWORMAN_Rule_Registry::get_rule( $rule_key );
		return $rule ? $rule['label'] : '';
	}

	/**
	 * Explain a taxonomy count and the action needed when it fails.
	 *
	 * @param string $rule_key Rule ID.
	 * @param int    $count    Assigned term count.
	 * @param int    $limit    Configured limit.
	 * @param bool   $passed   Whether the limit is satisfied.
	 * @return string
	 */
	private static function get_taxonomy_count_message( $rule_key, $count, $limit, $passed ) {
		$messages = array(
			/* translators: 1: actual category count, 2: minimum category count. */
			self::MINIMUM_CATEGORY_COUNT => _n( '%1$d category; minimum %2$d.', '%1$d categories; minimum %2$d.', $count, 'editorial-workflow-manager' ),
			/* translators: 1: actual category count, 2: maximum category count. */
			self::MAXIMUM_CATEGORY_COUNT => _n( '%1$d category; maximum %2$d.', '%1$d categories; maximum %2$d.', $count, 'editorial-workflow-manager' ),
			/* translators: 1: actual tag count, 2: minimum tag count. */
			self::MINIMUM_TAG_COUNT => _n( '%1$d tag; minimum %2$d.', '%1$d tags; minimum %2$d.', $count, 'editorial-workflow-manager' ),
			/* translators: 1: actual tag count, 2: maximum tag count. */
			self::MAXIMUM_TAG_COUNT => _n( '%1$d tag; maximum %2$d.', '%1$d tags; maximum %2$d.', $count, 'editorial-workflow-manager' ),
		);
		$guidance = array(
			self::MINIMUM_CATEGORY_COUNT => __( 'Assign more categories to meet the minimum.', 'editorial-workflow-manager' ),
			self::MAXIMUM_CATEGORY_COUNT => __( 'Remove categories to meet the maximum.', 'editorial-workflow-manager' ),
			self::MINIMUM_TAG_COUNT => __( 'Assign more tags to meet the minimum.', 'editorial-workflow-manager' ),
			self::MAXIMUM_TAG_COUNT => __( 'Remove tags to meet the maximum.', 'editorial-workflow-manager' ),
		);
		return sprintf( $messages[ $rule_key ], $count, $limit ) . ( $passed ? '' : ' ' . $guidance[ $rule_key ] );
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
		$rule = EDIWORMAN_Rule_Registry::get_rule( $rule_key );
		if ( ! $rule ) {
			return false;
		}

		if ( ! empty( $rule['post_types'] ) && ! in_array( $post_type, $rule['post_types'], true ) ) {
			return false;
		}
		$taxonomy = self::get_count_rule_taxonomy( $rule_key );
		if ( $taxonomy ) {
			return in_array( $taxonomy, $taxonomies, true );
		}

		switch ( $rule_key ) {
			case self::FEATURED_IMAGE:
				return post_type_supports( $post_type, 'thumbnail' ) && current_theme_supports( 'post-thumbnails' );
			case self::EXCERPT:
				return post_type_supports( $post_type, 'excerpt' );
			case self::TAXONOMY_PRESENCE:
				return ! empty( $taxonomies );
			case self::MINIMUM_WORD_COUNT:
			case self::MAXIMUM_WORD_COUNT:
			case self::IMAGE_ALT_TEXT:
			case self::MINIMUM_INTERNAL_LINK_COUNT:
				return post_type_supports( $post_type, 'editor' );
		}

		return true;
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
