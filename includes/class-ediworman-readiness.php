<?php
/**
 * Shared readiness calculation and cache helpers.
 *
 * @package EditorialWorkflowManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Calculates and caches per-post editorial checklist readiness.
 */
class EDIWORMAN_Readiness {

	const REQUIRED_TOTAL_CACHE_META = '_ediworman_required_total_cache';
	const REQUIRED_DONE_CACHE_META  = '_ediworman_required_done_cache';
	const READINESS_CACHE_META      = '_ediworman_readiness_cache';

	const READINESS_READY      = 'ready';
	const READINESS_INCOMPLETE = 'incomplete';

	const CACHE_SCHEMA_VERSION_OPTION = 'ediworman_readiness_cache_version';
	const CACHE_SCHEMA_VERSION        = '2';

	/**
	 * Register readiness-related hooks.
	 *
	 * @return void
	 */
	public static function register_hooks() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_invalidate_stale_cache_schema' ) );
		add_action( 'save_post', array( __CLASS__, 'handle_content_post_save' ), 20, 1 );
		add_action( 'save_post_ediworman_template', array( __CLASS__, 'handle_template_save' ), 20, 2 );
		add_action( 'added_post_meta', array( __CLASS__, 'handle_checklist_meta_change' ), 10, 4 );
		add_action( 'updated_post_meta', array( __CLASS__, 'handle_checklist_meta_change' ), 10, 4 );
		add_action( 'deleted_post_meta', array( __CLASS__, 'handle_checklist_meta_change' ), 10, 4 );
		add_action( 'updated_option', array( __CLASS__, 'handle_settings_option_updated' ), 10, 3 );
		add_action( 'added_option', array( __CLASS__, 'handle_settings_option_added' ), 10, 2 );
	}

	/**
	 * Return the readiness cache meta keys.
	 *
	 * @return array<int, string>
	 */
	public static function get_cache_meta_keys() {
		return array(
			self::REQUIRED_TOTAL_CACHE_META,
			self::REQUIRED_DONE_CACHE_META,
			self::READINESS_CACHE_META,
		);
	}

	/**
	 * Sanitize the readiness cache state.
	 *
	 * @param mixed $value Raw meta value.
	 * @return string
	 */
	public static function sanitize_readiness_cache_value( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = sanitize_key( (string) $value );
		if ( in_array( $value, array( self::READINESS_READY, self::READINESS_INCOMPLETE ), true ) ) {
			return $value;
		}

		return '';
	}

	/**
	 * Clear older readiness caches once after the cache schema changes.
	 *
	 * @return void
	 */
	public static function maybe_invalidate_stale_cache_schema() {
		$stored_version = get_option( self::CACHE_SCHEMA_VERSION_OPTION, '' );
		if ( self::CACHE_SCHEMA_VERSION === $stored_version ) {
			return;
		}

		foreach ( self::get_cache_meta_keys() as $meta_key ) {
			delete_post_meta_by_key( $meta_key );
		}

		update_option( self::CACHE_SCHEMA_VERSION_OPTION, self::CACHE_SCHEMA_VERSION );
	}

	/**
	 * Refresh readiness cache after a content post is saved.
	 *
	 * @param int $post_id Post ID being saved.
	 * @return void
	 */
	public static function handle_content_post_save( $post_id ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		$post_type = get_post_type( $post_id );
		if ( ! self::is_cacheable_post_type( $post_type ) ) {
			return;
		}

		self::refresh_cache_for_post( $post_id );
	}

	/**
	 * Refresh readiness cache after checklist state meta changes.
	 *
	 * @param int|array<int> $meta_id    Meta ID or IDs.
	 * @param int            $post_id    Post ID.
	 * @param string         $meta_key   Meta key.
	 * @param mixed          $meta_value Meta value.
	 * @return void
	 */
	public static function handle_checklist_meta_change( $meta_id, $post_id, $meta_key, $meta_value ) {
		unset( $meta_id, $meta_value );

		if ( ! in_array( $meta_key, array( '_ediworman_checked_items', '_ediworman_checked_item_ids' ), true ) ) {
			return;
		}

		$post_id = absint( $post_id );
		if ( $post_id <= 0 || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		$post_type = get_post_type( $post_id );
		if ( ! self::is_cacheable_post_type( $post_type ) ) {
			return;
		}

		self::refresh_cache_for_post( $post_id );
	}

	/**
	 * Invalidate mapped content caches after a checklist template is saved.
	 *
	 * @param int     $post_id Template post ID.
	 * @param WP_Post $post    Template post object.
	 * @return void
	 */
	public static function handle_template_save( $post_id, $post ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! $post || 'ediworman_template' !== $post->post_type ) {
			return;
		}

		self::invalidate_caches_for_template( $post_id );
	}

	/**
	 * Invalidate caches when the settings option is updated.
	 *
	 * @param string $option    Option name.
	 * @param mixed  $old_value Previous option value.
	 * @param mixed  $value     New option value.
	 * @return void
	 */
	public static function handle_settings_option_updated( $option, $old_value, $value ) {
		if ( EDIWORMAN_Settings::OPTION_NAME !== $option ) {
			return;
		}

		self::invalidate_caches_for_settings_change( $old_value, $value );
	}

	/**
	 * Invalidate caches when the settings option is first added.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  New option value.
	 * @return void
	 */
	public static function handle_settings_option_added( $option, $value ) {
		if ( EDIWORMAN_Settings::OPTION_NAME !== $option ) {
			return;
		}

		self::invalidate_caches_for_settings_change( array(), $value );
	}

	/**
	 * Return normalized template data for a mapped post type.
	 *
	 * @param string $post_type Post type slug.
	 * @return array{template_id:int,template_mode:string,items:array<int,array{id:string,label:string,description:string,url:string,required:bool}>}|null
	 */
	public static function get_template_data_for_post_type( $post_type ) {
		$post_type = sanitize_key( $post_type );
		if ( ! self::is_cacheable_post_type( $post_type ) ) {
			return null;
		}

		$template_id = EDIWORMAN_Settings::get_template_for_post_type( $post_type );
		if ( ! $template_id ) {
			return null;
		}

		return self::get_template_data( $template_id );
	}

	/**
	 * Return readiness for a post, using cache when present.
	 *
	 * @param int  $post_id     Content post ID.
	 * @param bool $use_cache   Whether to use the stored cache when complete.
	 * @param bool $write_cache Whether to write cache after a live calculation.
	 * @return array{required_total:int,required_done:int,readiness:string}|null
	 */
	public static function get_readiness_for_post( $post_id, $use_cache = true, $write_cache = true ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 ) {
			return null;
		}

		if ( $use_cache ) {
			$cached = self::get_cached_readiness( $post_id );
			if ( null !== $cached ) {
				return $cached;
			}
		}

		$readiness = self::calculate_readiness_for_post( $post_id );
		if ( null === $readiness ) {
			if ( $write_cache ) {
				self::clear_cache_for_post( $post_id );
			}

			return null;
		}

		if ( $write_cache ) {
			self::write_cache_for_post( $post_id, $readiness );
		}

		return $readiness;
	}

	/**
	 * Recompute and store readiness cache for a post.
	 *
	 * @param int $post_id Content post ID.
	 * @return array{required_total:int,required_done:int,readiness:string}|null
	 */
	public static function refresh_cache_for_post( $post_id ) {
		return self::get_readiness_for_post( $post_id, false, true );
	}

	/**
	 * Delete readiness cache for one post.
	 *
	 * @param int $post_id Content post ID.
	 * @return void
	 */
	public static function clear_cache_for_post( $post_id ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 ) {
			return;
		}

		foreach ( self::get_cache_meta_keys() as $meta_key ) {
			delete_post_meta( $post_id, $meta_key );
		}
	}

	/**
	 * Invalidate readiness caches for all post types mapped to a template.
	 *
	 * @param int $template_id Template post ID.
	 * @return void
	 */
	public static function invalidate_caches_for_template( $template_id ) {
		$template_id = absint( $template_id );
		if ( $template_id <= 0 ) {
			return;
		}

		$post_types = self::get_post_types_mapped_to_template( $template_id );
		self::invalidate_caches_for_post_types( $post_types );
	}

	/**
	 * Invalidate readiness caches after settings mappings change.
	 *
	 * @param mixed $old_settings Previous settings value.
	 * @param mixed $new_settings New settings value.
	 * @return void
	 */
	public static function invalidate_caches_for_settings_change( $old_settings, $new_settings ) {
		$old_mappings = self::get_mappings_from_settings_value( $old_settings );
		$new_mappings = self::get_mappings_from_settings_value( $new_settings );
		$post_types   = array_unique( array_merge( array_keys( $old_mappings ), array_keys( $new_mappings ) ) );
		$affected     = array();

		foreach ( $post_types as $post_type ) {
			$old_template_id = isset( $old_mappings[ $post_type ] ) ? (int) $old_mappings[ $post_type ] : 0;
			$new_template_id = isset( $new_mappings[ $post_type ] ) ? (int) $new_mappings[ $post_type ] : 0;

			if ( $old_template_id === $new_template_id ) {
				continue;
			}

			if ( self::is_cacheable_post_type( $post_type ) ) {
				$affected[] = $post_type;
			}
		}

		self::invalidate_caches_for_post_types( $affected );
	}

	/**
	 * Invalidate readiness caches for multiple post types.
	 *
	 * @param array<int, string> $post_types Post type slugs.
	 * @return void
	 */
	public static function invalidate_caches_for_post_types( $post_types ) {
		if ( ! is_array( $post_types ) || empty( $post_types ) ) {
			return;
		}

		$post_types = array_values( array_unique( array_map( 'sanitize_key', $post_types ) ) );
		foreach ( $post_types as $post_type ) {
			self::invalidate_caches_for_post_type( $post_type );
		}
	}

	/**
	 * Invalidate readiness caches for one post type.
	 *
	 * @param string $post_type Post type slug.
	 * @return void
	 */
	public static function invalidate_caches_for_post_type( $post_type ) {
		$post_type = sanitize_key( $post_type );
		if ( ! self::is_cacheable_post_type( $post_type ) ) {
			return;
		}

		$page = 1;
		do {
			$post_ids = get_posts(
				array(
					'post_type'              => $post_type,
					'post_status'            => 'any',
					'posts_per_page'         => 100,
					'paged'                  => $page,
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'orderby'                => 'ID',
					'order'                  => 'ASC',
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);

			foreach ( $post_ids as $post_id ) {
				self::clear_cache_for_post( (int) $post_id );
			}

			++$page;
		} while ( ! empty( $post_ids ) );
	}

	/**
	 * Return whether a post type can store readiness cache.
	 *
	 * @param mixed $post_type Post type slug.
	 * @return bool
	 */
	public static function is_cacheable_post_type( $post_type ) {
		if ( ! is_string( $post_type ) ) {
			return false;
		}

		$post_type = sanitize_key( $post_type );
		if ( ! $post_type || ! post_type_exists( $post_type ) ) {
			return false;
		}

		return 'ediworman_template' !== $post_type && 'attachment' !== $post_type;
	}

	/**
	 * Return whether a post type should show the readiness column.
	 *
	 * @param string $post_type Post type slug.
	 * @return bool
	 */
	public static function should_show_column_for_post_type( $post_type ) {
		return null !== self::get_template_data_for_post_type( $post_type );
	}

	/**
	 * Return the labels of required checklist items that remain incomplete.
	 *
	 * @param int $post_id Content post ID.
	 * @return array<int, string>
	 */
	public static function get_missing_required_item_labels( $post_id ) {
		$evaluation = self::evaluate_readiness_for_post( $post_id );
		if ( null === $evaluation ) {
			return array();
		}

		return $evaluation['missing_required_labels'];
	}

	/**
	 * Calculate readiness for a post without reading cache.
	 *
	 * @param int $post_id Content post ID.
	 * @return array{required_total:int,required_done:int,readiness:string}|null
	 */
	private static function calculate_readiness_for_post( $post_id ) {
		$evaluation = self::evaluate_readiness_for_post( $post_id );
		if ( null === $evaluation ) {
			return null;
		}

		return array(
			'required_total' => $evaluation['required_total'],
			'required_done'  => $evaluation['required_done'],
			'readiness'      => $evaluation['readiness'],
		);
	}

	/**
	 * Evaluate readiness and missing required labels from one shared item walk.
	 *
	 * @param int $post_id Content post ID.
	 * @return array{required_total:int,required_done:int,readiness:string,missing_required_labels:array<int,string>}|null
	 */
	private static function evaluate_readiness_for_post( $post_id ) {
		$post_id   = absint( $post_id );
		$post_type = get_post_type( $post_id );
		if ( ! self::is_cacheable_post_type( $post_type ) ) {
			return null;
		}

		$template_data = self::get_template_data_for_post_type( $post_type );
		if ( null === $template_data ) {
			return null;
		}

		$checked_labels   = self::normalize_checked_labels( get_post_meta( $post_id, '_ediworman_checked_items', true ) );
		$checked_item_ids = self::normalize_checked_item_ids( get_post_meta( $post_id, '_ediworman_checked_item_ids', true ) );
		$required_total   = 0;
		$required_done    = 0;
		$missing_labels   = array();

		foreach ( $template_data['items'] as $item ) {
			if ( false === $item['required'] ) {
				continue;
			}

			++$required_total;

			$is_checked = 'v2' === $template_data['template_mode']
				? isset( $checked_item_ids[ $item['id'] ] )
				: isset( $checked_labels[ $item['label'] ] );

			if ( $is_checked ) {
				++$required_done;
				continue;
			}

			$missing_labels[] = $item['label'];
		}

		$readiness = $required_done >= $required_total ? self::READINESS_READY : self::READINESS_INCOMPLETE;

		return array(
			'required_total' => $required_total,
			'required_done'  => $required_done,
			'readiness'      => $readiness,
			'missing_required_labels' => $missing_labels,
		);
	}

	/**
	 * Return cached readiness when all cache keys are present and valid.
	 *
	 * @param int $post_id Content post ID.
	 * @return array{required_total:int,required_done:int,readiness:string}|null
	 */
	private static function get_cached_readiness( $post_id ) {
		$post_id = absint( $post_id );
		if (
			! metadata_exists( 'post', $post_id, self::REQUIRED_TOTAL_CACHE_META ) ||
			! metadata_exists( 'post', $post_id, self::REQUIRED_DONE_CACHE_META ) ||
			! metadata_exists( 'post', $post_id, self::READINESS_CACHE_META )
		) {
			return null;
		}

		$required_total = absint( get_post_meta( $post_id, self::REQUIRED_TOTAL_CACHE_META, true ) );
		$required_done  = absint( get_post_meta( $post_id, self::REQUIRED_DONE_CACHE_META, true ) );
		$readiness      = self::sanitize_readiness_cache_value( get_post_meta( $post_id, self::READINESS_CACHE_META, true ) );

		if ( '' === $readiness ) {
			return null;
		}

		return array(
			'required_total' => $required_total,
			'required_done'  => min( $required_done, $required_total ),
			'readiness'      => $readiness,
		);
	}

	/**
	 * Write readiness cache for one post.
	 *
	 * @param int   $post_id   Content post ID.
	 * @param array $readiness Readiness summary.
	 * @return void
	 */
	private static function write_cache_for_post( $post_id, $readiness ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 || ! is_array( $readiness ) ) {
			return;
		}

		update_post_meta( $post_id, self::REQUIRED_TOTAL_CACHE_META, absint( $readiness['required_total'] ?? 0 ) );
		update_post_meta( $post_id, self::REQUIRED_DONE_CACHE_META, absint( $readiness['required_done'] ?? 0 ) );
		update_post_meta( $post_id, self::READINESS_CACHE_META, self::sanitize_readiness_cache_value( $readiness['readiness'] ?? '' ) );
	}

	/**
	 * Return normalized template data for a template ID.
	 *
	 * @param int $template_id Template post ID.
	 * @return array{template_id:int,template_mode:string,items:array<int,array{id:string,label:string,description:string,url:string,required:bool}>}|null
	 */
	private static function get_template_data( $template_id ) {
		$template_id = absint( $template_id );
		if ( $template_id <= 0 ) {
			return null;
		}

		$template = get_post( $template_id );
		if ( ! $template || 'ediworman_template' !== $template->post_type || 'trash' === $template->post_status ) {
			return null;
		}

		$items_v2 = get_post_meta( $template_id, '_ediworman_items_v2', true );
		if ( is_array( $items_v2 ) ) {
			return array(
				'template_id'    => $template_id,
				'template_mode'  => 'v2',
				'items'          => self::normalize_v2_items( $items_v2 ),
			);
		}

		$legacy_items = get_post_meta( $template_id, '_ediworman_items', true );

		return array(
			'template_id'    => $template_id,
			'template_mode'  => 'legacy',
			'items'          => self::normalize_legacy_items( $legacy_items ),
		);
	}

	/**
	 * Normalize legacy label-based items.
	 *
	 * @param mixed $stored_items Raw legacy template items.
	 * @return array<int, array{id:string,label:string,description:string,url:string,required:bool}>
	 */
	private static function normalize_legacy_items( $stored_items ) {
		if ( ! is_array( $stored_items ) ) {
			return array();
		}

		$items = array();
		foreach ( $stored_items as $item ) {
			if ( ! is_scalar( $item ) ) {
				continue;
			}

			$label = sanitize_text_field( (string) $item );
			if ( '' === $label ) {
				continue;
			}

			$items[] = array(
				'id'          => '',
				'label'       => $label,
				'description' => '',
				'url'         => '',
				'required'    => true,
			);
		}

		return $items;
	}

	/**
	 * Normalize v2 object-based template items.
	 *
	 * @param mixed $stored_items Raw v2 template items.
	 * @return array<int, array{id:string,label:string,description:string,url:string,required:bool}>
	 */
	private static function normalize_v2_items( $stored_items ) {
		if ( ! is_array( $stored_items ) ) {
			return array();
		}

		$items = array();
		foreach ( $stored_items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$id = isset( $item['id'] ) ? self::sanitize_uuid( $item['id'] ) : '';
			if ( '' === $id ) {
				continue;
			}

			$label = isset( $item['label'] ) ? sanitize_text_field( (string) $item['label'] ) : '';
			if ( '' === $label ) {
				continue;
			}

			$description = isset( $item['description'] ) ? sanitize_textarea_field( (string) $item['description'] ) : '';
			$url         = isset( $item['url'] ) ? esc_url_raw( (string) $item['url'] ) : '';

			$items[] = array(
				'id'          => $id,
				'label'       => $label,
				'description' => $description,
				'url'         => $url,
				'required'    => false !== ( $item['required'] ?? true ),
			);
		}

		return $items;
	}

	/**
	 * Normalize checked legacy labels to a lookup array.
	 *
	 * @param mixed $checked_items Raw checked labels.
	 * @return array<string, bool>
	 */
	private static function normalize_checked_labels( $checked_items ) {
		if ( ! is_array( $checked_items ) ) {
			return array();
		}

		$labels = array();
		foreach ( $checked_items as $checked_item ) {
			if ( ! is_scalar( $checked_item ) ) {
				continue;
			}

			$label = sanitize_text_field( (string) $checked_item );
			if ( '' !== $label ) {
				$labels[ $label ] = true;
			}
		}

		return $labels;
	}

	/**
	 * Normalize checked UUID item IDs to a lookup array.
	 *
	 * @param mixed $checked_item_ids Raw checked UUIDs.
	 * @return array<string, bool>
	 */
	private static function normalize_checked_item_ids( $checked_item_ids ) {
		if ( ! is_array( $checked_item_ids ) ) {
			return array();
		}

		$item_ids = array();
		foreach ( $checked_item_ids as $checked_item_id ) {
			$item_id = self::sanitize_uuid( $checked_item_id );
			if ( '' !== $item_id ) {
				$item_ids[ $item_id ] = true;
			}
		}

		return $item_ids;
	}

	/**
	 * Return post types currently mapped to a template.
	 *
	 * @param int $template_id Template post ID.
	 * @return array<int, string>
	 */
	private static function get_post_types_mapped_to_template( $template_id ) {
		$template_id = absint( $template_id );
		$settings    = get_option( EDIWORMAN_Settings::OPTION_NAME, array() );
		$mappings    = self::get_mappings_from_settings_value( $settings );
		$post_types  = array();

		foreach ( $mappings as $post_type => $mapped_template_id ) {
			if ( $template_id === (int) $mapped_template_id && self::is_cacheable_post_type( $post_type ) ) {
				$post_types[] = $post_type;
			}
		}

		return $post_types;
	}

	/**
	 * Extract sanitized mappings from a settings value.
	 *
	 * @param mixed $settings Raw settings value.
	 * @return array<string, int>
	 */
	private static function get_mappings_from_settings_value( $settings ) {
		if ( ! is_array( $settings ) || empty( $settings['post_type_templates'] ) || ! is_array( $settings['post_type_templates'] ) ) {
			return array();
		}

		$mappings = array();
		foreach ( $settings['post_type_templates'] as $post_type => $template_id ) {
			$post_type   = sanitize_key( $post_type );
			$template_id = absint( $template_id );
			if ( ! $post_type || $template_id <= 0 ) {
				continue;
			}

			$mappings[ $post_type ] = $template_id;
		}

		return $mappings;
	}

	/**
	 * Validate and normalize a UUID value.
	 *
	 * @param mixed $value Raw ID value.
	 * @return string
	 */
	private static function sanitize_uuid( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$uuid = strtolower( sanitize_text_field( (string) $value ) );
		if ( '' === $uuid ) {
			return '';
		}

		if ( function_exists( 'wp_is_uuid' ) && wp_is_uuid( $uuid ) ) {
			return $uuid;
		}

		return '';
	}
}
