<?php
/**
 * Local, versioned checklist template transfer.
 *
 * @package EditorialWorkflowManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates portable data and coordinates isolated template imports.
 */
final class EDIWORMAN_Template_Portability {

	const FORMAT = 'editorial-workflow-manager/template';
	const SCHEMA_VERSION = 1;
	const MAX_BYTES = 1048576;
	const PREVIEW_TTL = 900;
	const PAGE = 'ediworman-template-portability';
	const CLEANUP_HOOK = 'ediworman_template_import_cleanup';
	const TITLE_CLAIM = 'ediworman_import_title_claim';

	/**
	 * Register scoped admin actions and expiring-data cleanup.
	 *
	 * @return void
	 */
	public static function register_hooks() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_action( 'admin_post_ediworman_template_export', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_ediworman_template_preview', array( __CLASS__, 'handle_preview' ) );
		add_action( 'admin_post_ediworman_template_confirm', array( __CLASS__, 'handle_confirm' ) );
		add_action( 'admin_post_ediworman_template_cancel', array( __CLASS__, 'handle_cancel' ) );
		add_action( self::CLEANUP_HOOK, array( __CLASS__, 'cleanup' ), 10, 2 );
	}

	/**
	 * Whether the current user can create usable template records.
	 *
	 * @return bool
	 */
	public static function can_import() {
		$type = get_post_type_object( 'ediworman_template' );
		return get_current_user_id() > 0 && $type && current_user_can( $type->cap->create_posts ) && current_user_can( $type->cap->publish_posts );
	}

	/**
	 * Return a translated validation error with an actionable field path.
	 *
	 * @param string $path Invalid field path.
	 * @return WP_Error
	 */
	private static function invalid( $path ) {
		return new WP_Error(
			'ediworman_invalid_transfer',
			sprintf(
				/* translators: %s: JSON field path. */
				__( 'Invalid or unsupported data at %s. Correct the file or export it again from a supported plugin version.', 'editorial-workflow-manager' ),
				$path
			)
		);
	}

	/**
	 * Check an exact object shape without confusing JSON arrays and objects.
	 *
	 * @param mixed $value    Decoded JSON value.
	 * @param array $allowed  Permitted keys.
	 * @param array $required Required keys.
	 * @return bool
	 */
	private static function fields( $value, $allowed, $required ) {
		if ( ! $value instanceof stdClass ) {
			return false;
		}
		$keys = array_keys( get_object_vars( $value ) );
		return ! array_diff( $keys, $allowed ) && ! array_diff( $required, $keys );
	}

	/**
	 * Sanitize one text value and record visible normalization feedback.
	 *
	 * @param string $value    Input text.
	 * @param string $kind     text, textarea, or url.
	 * @param string $path     Field path.
	 * @param array  $warnings Normalization warnings, by reference.
	 * @return string
	 */
	private static function clean_text( $value, $kind, $path, &$warnings ) {
		if ( 'url' === $kind ) {
			$clean = esc_url_raw( $value );
		} else {
			$clean = 'textarea' === $kind ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
		}
		if ( $clean !== $value ) {
			$warnings[] = sprintf(
				/* translators: %s: JSON field path. */
				__( 'Text or URL formatting was normalized at %s. Review the resulting value below.', 'editorial-workflow-manager' ),
				$path
			);
		}
		return $clean;
	}

	/**
	 * Strictly validate a portable JSON file before invoking permissive sanitizers.
	 *
	 * @param string $json JSON bytes.
	 * @return array|WP_Error Document and normalization warnings, or an error.
	 */
	public static function validate_json( $json ) {
		if ( ! is_string( $json ) || strlen( $json ) > self::MAX_BYTES ) {
			return new WP_Error( 'ediworman_transfer_size', __( 'Choose a JSON file no larger than 1 MiB.', 'editorial-workflow-manager' ) );
		}
		$document = json_decode( preg_replace( '/^\xEF\xBB\xBF/', '', $json ), false, 16 );
		if ( JSON_ERROR_NONE !== json_last_error() || ! self::fields( $document, array( 'format', 'schema_version', 'template' ), array( 'format', 'schema_version', 'template' ) ) ) {
			return self::invalid( 'document' );
		}
		if ( self::FORMAT !== $document->format || self::SCHEMA_VERSION !== $document->schema_version ) {
			return self::invalid( 'format/schema_version' );
		}
		$template = $document->template;
		$keys = array( 'title', 'item_format', 'items', 'automatic_requirements' );
		if ( ! self::fields( $template, $keys, $keys ) || ! is_string( $template->title ) || ! in_array( $template->item_format, array( 'legacy', 'v2' ), true ) || ! is_array( $template->items ) || ! $template->automatic_requirements instanceof stdClass ) {
			return self::invalid( 'template' );
		}
		$warnings = array();
		$template->title = self::clean_text( $template->title, 'text', 'template.title', $warnings );
		if ( '' === trim( $template->title ) ) {
			return self::invalid( 'template.title' );
		}
		$ids = array();
		foreach ( $template->items as $index => $item ) {
			$path = 'template.items.' . $index;
			if ( 'legacy' === $template->item_format ) {
				if ( ! is_string( $item ) ) {
					return self::invalid( $path );
				}
				$template->items[ $index ] = self::clean_text( $item, 'text', $path, $warnings );
				if ( '' === trim( $template->items[ $index ] ) ) {
					return self::invalid( $path );
				}
				continue;
			}
			$item_keys = array( 'id', 'label', 'required', 'description', 'url' );
			if ( ! self::fields( $item, $item_keys, $item_keys ) || ! is_bool( $item->required ) ) {
				return self::invalid( $path );
			}
			foreach ( array( 'id', 'label', 'description', 'url' ) as $field ) {
				if ( ! is_string( $item->$field ) ) {
					return self::invalid( $path . '.' . $field );
				}
			}
			$id = strtolower( $item->id );
			if ( ! preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $id ) || isset( $ids[ $id ] ) ) {
				return self::invalid( $path . '.id' );
			}
			$ids[ $id ] = true;
			$item->id = $id;
			$item->label = self::clean_text( $item->label, 'text', $path . '.label', $warnings );
			$item->description = self::clean_text( $item->description, 'textarea', $path . '.description', $warnings );
			$original_url = $item->url;
			$item->url = self::clean_text( $item->url, 'url', $path . '.url', $warnings );
			if ( '' === trim( $item->label ) || ( '' !== $original_url && '' === $item->url ) ) {
				return self::invalid( $path . '.label/url' );
			}
		}
		$builtins = EDIWORMAN_Automatic_Requirements::get_builtin_rule_keys();
		$limits = EDIWORMAN_Automatic_Requirements::get_limit_settings();
		$config = array();
		foreach ( get_object_vars( $template->automatic_requirements ) as $id => $rule ) {
			$is_builtin = in_array( $id, $builtins, true );
			if ( ! $is_builtin && ! preg_match( '/^[a-z][a-z0-9_-]{1,31}\/[a-z][a-z0-9_-]{1,63}$/D', $id ) ) {
				return self::invalid( 'automatic_requirements.' . $id );
			}
			$rule_keys = isset( $limits[ $id ] ) ? array( 'enabled', $limits[ $id ]['bound'] ) : array( 'enabled' );
			if ( ! self::fields( $rule, $rule_keys, $rule_keys ) || ! is_bool( $rule->enabled ) ) {
				return self::invalid( 'automatic_requirements.' . $id );
			}
			if ( isset( $limits[ $id ] ) ) {
				$setting = $limits[ $id ];
				$value = $rule->{$setting['bound']};
				if ( ! is_int( $value ) || ! EDIWORMAN_Automatic_Requirements::is_valid_limit( $value, $setting['min'] ) ) {
					return self::invalid( 'automatic_requirements.' . $id . '.' . $setting['bound'] );
				}
			}
			$config[ $id ] = get_object_vars( $rule );
		}
		foreach ( array( 'words', 'categories', 'tags', 'internal_links' ) as $group ) {
			if ( EDIWORMAN_Automatic_Requirements::validate_limit_group( $config, $group ) ) {
				return self::invalid( 'automatic_requirements.' . $group );
			}
		}
		$defaults = array_intersect_key( EDIWORMAN_Automatic_Requirements::sanitize_config( array() ), array_flip( $builtins ) );
		$template->automatic_requirements = (object) array_merge( $defaults, $config );
		return array( 'document' => $document, 'warnings' => $warnings );
	}

	/**
	 * Export only portable metadata, without writing to the source.
	 *
	 * @param int $template_id Source template ID.
	 * @return array|WP_Error Validated document and warnings.
	 */
	public static function export_template( $template_id ) {
		$post = get_post( $template_id );
		if ( ! $post || 'ediworman_template' !== $post->post_type || 'trash' === $post->post_status || ! current_user_can( 'edit_post', $template_id ) ) {
			return new WP_Error( 'ediworman_transfer_forbidden', __( 'You cannot export this checklist template.', 'editorial-workflow-manager' ) );
		}
		$v2 = get_post_meta( $template_id, '_ediworman_items_v2', true );
		$items = is_array( $v2 ) ? $v2 : get_post_meta( $template_id, '_ediworman_items', true );
		if ( is_array( $v2 ) ) {
			foreach ( $items as &$item ) {
				if ( is_array( $item ) ) {
					// Earlier v2 templates predate optional helper fields.
					$item += array( 'description' => '', 'url' => '' );
				}
			}
			unset( $item );
		}
		$config = get_post_meta( $template_id, EDIWORMAN_Automatic_Requirements::META_KEY, true );
		return self::validate_json(
			wp_json_encode(
				array(
					'format' => self::FORMAT,
					'schema_version' => self::SCHEMA_VERSION,
					'template' => array(
						'title' => $post->post_title,
						'item_format' => is_array( $v2 ) ? 'v2' : 'legacy',
						'items' => '' === $items ? array() : $items,
						'automatic_requirements' => (object) ( '' === $config ? array() : $config ),
					),
				),
				JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
			)
		);
	}

	/**
	 * Return unavailable-provider warnings independently of normalization.
	 *
	 * @param stdClass $document Validated document.
	 * @return array
	 */
	public static function provider_warnings( $document ) {
		$warnings = array();
		foreach ( $document->template->automatic_requirements as $id => $rule ) {
			$rule = (array) $rule;
			if ( ! EDIWORMAN_Rule_Registry::get_rule( $id ) ) {
				$warnings[] = sprintf(
					/* translators: %s: unavailable automatic rule ID. */
					__( 'The provider for %s is unavailable. Its settings will be preserved, but it will not affect readiness until its provider is active.', 'editorial-workflow-manager' ),
					$id
				);
			}
		}
		return $warnings;
	}

	/**
	 * Derive staging identifiers from the authenticated user and current site.
	 *
	 * @param string $token Opaque import token.
	 * @return array|false Transient key and atomic option claim key.
	 */
	private static function keys( $token ) {
		if ( ! is_string( $token ) || ! preg_match( '/^[a-zA-Z0-9]{48}$/D', $token ) || ! get_current_user_id() ) {
			return false;
		}
		$key = 'ediworman_import_' . get_current_blog_id() . '_' . get_current_user_id() . '_' . $token;
		return array( $key, 'ediworman_import_claim_' . hash( 'sha256', $key ) );
	}

	/**
	 * Stage validated bytes with a guaranteed cleanup event before showing preview.
	 *
	 * @param string $json Raw uploaded JSON.
	 * @return string|WP_Error Opaque token.
	 */
	public static function stage( $json ) {
		if ( ! self::can_import() ) {
			return new WP_Error( 'ediworman_transfer_forbidden', __( 'You cannot import checklist templates.', 'editorial-workflow-manager' ) );
		}
		$result = self::validate_json( $json );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$token = wp_generate_password( 48, false, false );
		$keys = self::keys( $token );
		$expires = time() + self::PREVIEW_TTL;
		$event = wp_schedule_single_event( $expires, self::CLEANUP_HOOK, $keys, true );
		if ( is_wp_error( $event ) || ! $event ) {
			return new WP_Error( 'ediworman_transfer_storage', __( 'The import preview could not be stored. Try uploading the file again.', 'editorial-workflow-manager' ) );
		}
		$record = array(
			'json' => wp_json_encode( $result['document'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			'warnings' => $result['warnings'],
			'fingerprint' => EDIWORMAN_Rule_Registry::get_fingerprint(),
			'expires' => $expires,
		);
		if ( strlen( $record['json'] ) > self::MAX_BYTES ) {
			wp_clear_scheduled_hook( self::CLEANUP_HOOK, $keys );
			return new WP_Error( 'ediworman_transfer_size', __( 'The normalized template exceeds 1 MiB. Reduce its text before importing.', 'editorial-workflow-manager' ) );
		}
		if ( ! set_transient( $keys[0], $record, self::PREVIEW_TTL ) ) {
			wp_clear_scheduled_hook( self::CLEANUP_HOOK, $keys );
			return new WP_Error( 'ediworman_transfer_storage', __( 'The import preview could not be stored. Try uploading the file again.', 'editorial-workflow-manager' ) );
		}
		return $token;
	}

	/**
	 * Load only this user's preview on this site.
	 *
	 * @param string $token Preview token.
	 * @return array|WP_Error Validated preview and staging record.
	 */
	public static function load_preview( $token ) {
		$keys = self::keys( $token );
		$record = $keys && self::can_import() ? get_transient( $keys[0] ) : false;
		if ( ! is_array( $record ) || $record['expires'] <= time() ) {
			return new WP_Error( 'ediworman_transfer_expired', __( 'This import preview is unavailable or expired. Upload the JSON file again.', 'editorial-workflow-manager' ) );
		}
		$result = self::validate_json( $record['json'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$result['warnings'] = array_merge( $record['warnings'], self::provider_warnings( $result['document'] ) );
		$result['record'] = $record;
		return $result;
	}

	/**
	 * Create a title that never selects an existing record as an import target.
	 *
	 * @param string $title Source title.
	 * @return string
	 */
	public static function available_title( $title ) {
		$candidate = $title;
		$number = 1;
		while ( get_posts( array( 'post_type' => 'ediworman_template', 'post_status' => array_values( get_post_stati() ), 'title' => $candidate, 'fields' => 'ids', 'posts_per_page' => 1, 'no_found_rows' => true ) ) ) {
			$candidate = 1 === $number
				? sprintf( /* translators: %s: imported template title. */ __( '%s (Imported)', 'editorial-workflow-manager' ), $title )
				: sprintf( /* translators: 1: imported template title, 2: copy number. */ __( '%1$s (Imported %2$d)', 'editorial-workflow-manager' ), $title, $number );
			++$number;
		}
		return $candidate;
	}

	/**
	 * Insert a claim without overwriting a winner, even with stale option caches.
	 *
	 * @param string $name  Private claim option key.
	 * @param string $owner Ownership value.
	 * @return bool
	 */
	private static function acquire_claim( $name, $owner ) {
		global $wpdb;
		// Options API add_option() uses an upsert and is not an insert-only lock.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic insert-only claim; invalidate both relevant option-cache entries below.
		$result = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $name, $owner ) );
		wp_cache_delete( $name, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		return 1 === $result;
	}

	/**
	 * Release only a claim still owned by this operation.
	 *
	 * @param string $name  Claim option key.
	 * @param string $owner Ownership value.
	 * @return void
	 */
	private static function release_claim( $name, $owner ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Compare-and-delete lock; invalidate the option cache below.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $name, $owner ) );
		wp_cache_delete( $name, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
	}

	/**
	 * Atomically claim and consume a preview; never update an existing template.
	 *
	 * @param string $token Preview token.
	 * @return int|WP_Error New template ID.
	 */
	public static function confirm( $token ) {
		$keys = self::keys( $token );
		if ( ! self::can_import() || ! $keys ) {
			return new WP_Error( 'ediworman_transfer_forbidden', __( 'You cannot confirm this import.', 'editorial-workflow-manager' ) );
		}
		$owner = wp_generate_uuid4();
		if ( ! self::acquire_claim( $keys[1], $owner ) ) {
			return new WP_Error( 'ediworman_transfer_busy', __( 'This import is already being processed. Wait and reload the preview.', 'editorial-workflow-manager' ) );
		}
		// Serialize import title selection across different preview tokens as well.
		if ( ! self::acquire_claim( self::TITLE_CLAIM, $keys[1] ) ) {
			self::release_claim( $keys[1], $owner );
			return new WP_Error( 'ediworman_transfer_busy', __( 'Another template import is being saved. Wait and confirm this preview again.', 'editorial-workflow-manager' ) );
		}
		$id = 0;
		try {
			$preview = self::load_preview( $token );
			if ( is_wp_error( $preview ) ) {
				return $preview;
			}
			if ( EDIWORMAN_Rule_Registry::get_fingerprint() !== $preview['record']['fingerprint'] ) {
				$preview['record']['fingerprint'] = EDIWORMAN_Rule_Registry::get_fingerprint();
				set_transient( $keys[0], $preview['record'], max( 1, $preview['record']['expires'] - time() ) );
				return new WP_Error( 'ediworman_transfer_changed', __( 'Available automatic rules changed. Reload and review the preview before confirming again.', 'editorial-workflow-manager' ) );
			}
			$template = $preview['document']->template;
			$id = wp_insert_post( array( 'post_type' => 'ediworman_template', 'post_status' => 'publish', 'post_title' => wp_slash( self::available_title( $template->title ) ) ), true );
			if ( is_wp_error( $id ) || ! $id ) {
				return new WP_Error( 'ediworman_transfer_write', __( 'The new template could not be created. Existing templates were not changed.', 'editorial-workflow-manager' ) );
			}
			$items = json_decode( wp_json_encode( $template->items ), true );
			$metadata = array(
				'_ediworman_items' => 'v2' === $template->item_format ? wp_list_pluck( $items, 'label' ) : $items,
				EDIWORMAN_Automatic_Requirements::META_KEY => json_decode( wp_json_encode( $template->automatic_requirements ), true ),
			);
			if ( 'v2' === $template->item_format ) {
				$metadata['_ediworman_items_v2'] = $items;
			}
			foreach ( $metadata as $key => $value ) {
				update_post_meta( $id, $key, wp_slash( $value ) );
				if ( get_post_meta( $id, $key, true ) !== $value ) {
					wp_delete_post( $id, true );
					$id = 0;
					return new WP_Error( 'ediworman_transfer_write', __( 'The template could not be saved completely. The new copy was removed; retry the import.', 'editorial-workflow-manager' ) );
				}
			}
			self::cleanup( $keys[0], $keys[1] );
			wp_clear_scheduled_hook( self::CLEANUP_HOOK, $keys );
			return $id;
		} catch ( Throwable $error ) {
			if ( is_int( $id ) && 0 < $id ) {
				wp_delete_post( $id, true );
			}
			return new WP_Error( 'ediworman_transfer_write', __( 'The import could not be completed. Retry the import; existing templates were not changed.', 'editorial-workflow-manager' ) );
		} finally {
			self::release_claim( self::TITLE_CLAIM, $keys[1] );
			self::release_claim( $keys[1], $owner );
		}
	}

	/**
	 * Remove only the exact staging keys supplied by our own cleanup event.
	 *
	 * @param string $key   Transient key.
	 * @param string $claim Atomic claim option key.
	 * @return void
	 */
	public static function cleanup( $key, $claim ) {
		if ( is_string( $key ) && is_string( $claim ) && preg_match( '/^ediworman_import_[0-9]+_[0-9]+_[a-zA-Z0-9]{48}$/D', $key ) && hash_equals( 'ediworman_import_claim_' . hash( 'sha256', $key ), $claim ) ) {
			delete_transient( $key );
			delete_option( $claim );
			self::release_claim( self::TITLE_CLAIM, $claim );
		}
	}

	/**
	 * Cancel under the same claim used for confirmation.
	 *
	 * @param string $token Preview token.
	 * @return true|WP_Error
	 */
	public static function cancel( $token ) {
		$keys = self::keys( $token );
		if ( ! self::can_import() || ! $keys || ! self::acquire_claim( $keys[1], wp_generate_uuid4() ) ) {
			return new WP_Error( 'ediworman_transfer_busy', __( 'The preview cannot be cancelled while it is being processed.', 'editorial-workflow-manager' ) );
		}
		self::cleanup( $keys[0], $keys[1] );
		wp_clear_scheduled_hook( self::CLEANUP_HOOK, $keys );
		return true;
	}

	/**
	 * Site-bound nonce action for forms and read-only preview links.
	 *
	 * @param string $action Operation name.
	 * @param string $scope  Token or source ID.
	 * @return string
	 */
	public static function nonce_action( $action, $scope = '' ) {
		return 'ediworman_transfer_' . get_current_blog_id() . '_' . $action . '_' . $scope;
	}

	/**
	 * Register the CPT-scoped transfer screen.
	 *
	 * @return void
	 */
	public static function register_menu() {
		$type = get_post_type_object( 'ediworman_template' );
		if ( $type ) {
			add_submenu_page( 'edit.php?post_type=ediworman_template', __( 'Template Import/Export', 'editorial-workflow-manager' ), __( 'Import/Export', 'editorial-workflow-manager' ), $type->cap->edit_posts, self::PAGE, array( __CLASS__, 'render_page' ) );
		}
	}

	/**
	 * Add a nonce-protected export shortcut to editable template rows.
	 *
	 * @param array   $actions Existing actions.
	 * @param WP_Post $post    Row post.
	 * @return array
	 */
	public static function row_actions( $actions, $post ) {
		if ( 'ediworman_template' === $post->post_type && 'trash' !== $post->post_status && current_user_can( 'edit_post', $post->ID ) ) {
			$url = wp_nonce_url( add_query_arg( array( 'action' => 'ediworman_template_export', 'template_id' => $post->ID ), admin_url( 'admin-post.php' ) ), self::nonce_action( 'export', (string) $post->ID ) );
			$actions['ediworman_export'] = '<a href="' . esc_url( $url ) . '" aria-label="' . esc_attr( sprintf( /* translators: %s: template title. */ __( 'Export %s as JSON', 'editorial-workflow-manager' ), $post->post_title ) ) . '">' . esc_html__( 'Export JSON', 'editorial-workflow-manager' ) . '</a>';
		}
		return $actions;
	}

	/**
	 * Return the plugin-scoped admin URL.
	 *
	 * @return string
	 */
	private static function page_url() {
		return admin_url( 'edit.php?post_type=ediworman_template&page=' . self::PAGE );
	}

	/**
	 * Render an error inside the normal admin layout for admin-post responses.
	 *
	 * @param WP_Error $error Validation/processing error.
	 * @return void
	 */
	private static function fail( $error ) {
		global $hook_suffix, $title;
		// admin-post.php does not initialize a page hook; admin-header callbacks require a string.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Initialize the admin response context before loading the standard header.
		$hook_suffix = self::PAGE;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Standard WordPress admin page title.
		$title = __( 'Template Import/Export', 'editorial-workflow-manager' );
		set_current_screen( self::PAGE );
		status_header( 400 );
		require ABSPATH . 'wp-admin/admin-header.php';
		self::render_page( $error );
		require ABSPATH . 'wp-admin/admin-footer.php';
		exit;
	}

	/**
	 * Authenticate a transfer POST before using its input.
	 *
	 * @param string $action Operation name.
	 * @return void
	 */
	private static function authorize_import( $action ) {
		if ( ! self::can_import() ) {
			wp_die( esc_html__( 'You cannot import checklist templates.', 'editorial-workflow-manager' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::nonce_action( $action ) );
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( 'POST' !== $method ) {
			wp_die( esc_html__( 'Submit this action using its form.', 'editorial-workflow-manager' ), '', array( 'response' => 405 ) );
		}
	}

	/**
	 * Download a source-scoped JSON attachment.
	 *
	 * @return void
	 */
	public static function handle_export() {
		// IDs are untrusted selectors; export_template enforces the per-object capability.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce is verified below after determining its object scope.
		$id = isset( $_GET['template_id'] ) ? absint( wp_unslash( $_GET['template_id'] ) ) : 0;
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( 'POST' === $method ) {
			check_admin_referer( self::nonce_action( 'export' ) );
			$id = isset( $_POST['template_id'] ) ? absint( wp_unslash( $_POST['template_id'] ) ) : 0;
		} else {
			check_admin_referer( self::nonce_action( 'export', (string) $id ) );
		}
		$result = self::export_template( $id );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 403 ) );
		}
		$bytes = wp_json_encode( $result['document'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( strlen( $bytes ) > self::MAX_BYTES ) {
			$bytes = wp_json_encode( $result['document'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		}
		if ( strlen( $bytes ) > self::MAX_BYTES ) {
			self::fail( new WP_Error( 'ediworman_transfer_size', __( 'The exported template exceeds 1 MiB. Reduce its text before exporting.', 'editorial-workflow-manager' ) ) );
		}
		nocache_headers();
		header( 'Content-Type: application/json; charset=UTF-8' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( 'ediworman-template-' . $id . '.json' ) . '"' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON attachment, never HTML; generated with wp_json_encode after validation.
		echo $bytes;
		exit;
	}

	/**
	 * Read one real PHP upload without moving it into public storage.
	 *
	 * @return void
	 */
	public static function handle_preview() {
		self::authorize_import( 'preview' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- authorize_import verifies the action nonce and capabilities before any upload input is used.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Shape, upload provenance, size and extension checked before reading bytes; JSON is strictly validated by stage().
		$file = $_FILES['ediworman_template_file'] ?? null;
		if ( ! is_array( $file ) || ! isset( $file['error'], $file['name'], $file['tmp_name'], $file['size'] ) || UPLOAD_ERR_OK !== $file['error'] || ! is_string( $file['name'] ) || ! is_string( $file['tmp_name'] ) || 'json' !== strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			self::fail( new WP_Error( 'ediworman_transfer_upload', __( 'Choose one readable .json file. If uploading failed, check the server upload limit and try again.', 'editorial-workflow-manager' ) ) );
		}
		$limit = min( self::MAX_BYTES, wp_max_upload_size() );
		$size = filesize( $file['tmp_name'] );
		if ( false === $size || $size > $limit || $file['size'] > $limit ) {
			self::fail( new WP_Error( 'ediworman_transfer_size', __( 'The JSON file exceeds the upload limit. The maximum is 1 MiB, or your server upload limit if smaller.', 'editorial-workflow-manager' ) ) );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Bounded local temporary upload; never a URL.
		$json = file_get_contents( $file['tmp_name'], false, null, 0, $limit + 1 );
		$token = self::stage( $json );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( is_wp_error( $token ) ) {
			self::fail( $token );
		}
		wp_safe_redirect( add_query_arg( array( 'ediworman_preview' => $token, '_wpnonce' => wp_create_nonce( self::nonce_action( 'view', $token ) ) ), self::page_url() ) );
		exit;
	}

	/**
	 * Confirm a single staged import.
	 *
	 * @return void
	 */
	public static function handle_confirm() {
		self::authorize_import( 'confirm' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by authorize_import above.
		$token = isset( $_POST['ediworman_token'] ) ? sanitize_text_field( wp_unslash( $_POST['ediworman_token'] ) ) : '';
		$id = self::confirm( $token );
		if ( is_wp_error( $id ) ) {
			if ( 'ediworman_transfer_changed' === $id->get_error_code() ) {
				wp_safe_redirect( add_query_arg( array( 'ediworman_preview' => $token, 'ediworman_review_changed' => '1', '_wpnonce' => wp_create_nonce( self::nonce_action( 'view', $token ) ) ), self::page_url() ) );
				exit;
			}
			self::fail( $id );
		}
		wp_safe_redirect( add_query_arg( array( 'ediworman_imported' => $id, '_wpnonce' => wp_create_nonce( self::nonce_action( 'success', (string) $id ) ) ), self::page_url() ) );
		exit;
	}

	/**
	 * Cancel a preview without changing templates.
	 *
	 * @return void
	 */
	public static function handle_cancel() {
		self::authorize_import( 'cancel' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by authorize_import above.
		$token = isset( $_POST['ediworman_token'] ) ? sanitize_text_field( wp_unslash( $_POST['ediworman_token'] ) ) : '';
		$result = self::cancel( $token );
		if ( is_wp_error( $result ) ) {
			self::fail( $result );
		}
		wp_safe_redirect( self::page_url() );
		exit;
	}

	/**
	 * Render forms and readable previews; works without JavaScript.
	 *
	 * @param WP_Error|null $error Optional form error.
	 * @return void
	 */
	public static function render_page( $error = null ) {
		$type = get_post_type_object( 'ediworman_template' );
		if ( ! $type || ! current_user_can( $type->cap->edit_posts ) ) {
			wp_die( esc_html__( 'You cannot manage checklist templates.', 'editorial-workflow-manager' ), '', array( 'response' => 403 ) );
		}
		$preview = null;
		$token = '';
		$imported = 0;
		$page = 1;
		// Read-only navigation selectors; each is used only after its scoped nonce verification.
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		$requested_token = isset( $_GET['ediworman_preview'] ) ? sanitize_text_field( wp_unslash( $_GET['ediworman_preview'] ) ) : '';
		if ( $requested_token && wp_verify_nonce( $nonce, self::nonce_action( 'view', $requested_token ) ) ) {
			$token = $requested_token;
			$preview = self::load_preview( $token );
			if ( is_wp_error( $preview ) ) {
				$error = $preview;
				$preview = null;
			} elseif ( isset( $_GET['ediworman_review_changed'] ) ) {
				$preview['warnings'][] = __( 'Available automatic rules changed. Review the requirements and provider warnings again before creating the template.', 'editorial-workflow-manager' );
			}
		} elseif ( $requested_token ) {
			$error = new WP_Error( 'ediworman_transfer_expired', __( 'This preview link is invalid or expired. Upload the JSON file again.', 'editorial-workflow-manager' ) );
		}
		$requested_id = isset( $_GET['ediworman_imported'] ) ? absint( wp_unslash( $_GET['ediworman_imported'] ) ) : 0;
		if ( $requested_id && wp_verify_nonce( $nonce, self::nonce_action( 'success', (string) $requested_id ) ) && current_user_can( 'edit_post', $requested_id ) && 'ediworman_template' === get_post_type( $requested_id ) ) {
			$imported = $requested_id;
		}
		if ( wp_verify_nonce( $nonce, self::nonce_action( 'page' ) ) ) {
			$page = isset( $_GET['ediworman_export_page'] ) ? max( 1, absint( wp_unslash( $_GET['ediworman_export_page'] ) ) ) : 1;
		}
		$query = new WP_Query( array( 'post_type' => 'ediworman_template', 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ), 'posts_per_page' => 50, 'paged' => $page, 'orderby' => 'title', 'order' => 'ASC' ) );
		$last_page = min( (int) $query->max_num_pages, $page + 2 );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Template Import/Export', 'editorial-workflow-manager' ); ?></h1>
			<?php if ( is_wp_error( $error ) ) : ?>
				<div class="notice notice-error" role="alert"><p><?php echo esc_html( $error->get_error_message() ); ?></p><p><a href="<?php echo esc_url( self::page_url() ); ?>"><?php esc_html_e( 'Start a new import', 'editorial-workflow-manager' ); ?></a></p></div>
			<?php endif; ?>
			<?php if ( $imported ) : ?>
				<div class="notice notice-success" role="status"><p><?php esc_html_e( 'Template imported as a new copy. Assign it to a post type separately when ready. Existing templates, mappings, and publication policies were not changed.', 'editorial-workflow-manager' ); ?> <a href="<?php echo esc_url( get_edit_post_link( $imported, 'raw' ) ); ?>"><?php esc_html_e( 'Edit imported template', 'editorial-workflow-manager' ); ?></a></p></div>
			<?php endif; ?>
			<?php if ( $preview ) : ?>
				<?php self::render_preview( $preview, $token ); ?>
			<?php else : ?>
				<h2><?php esc_html_e( 'Export a template', 'editorial-workflow-manager' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="ediworman_template_export">
					<?php wp_nonce_field( self::nonce_action( 'export' ) ); ?>
					<label for="ediworman-export-template"><?php esc_html_e( 'Checklist template', 'editorial-workflow-manager' ); ?></label>
					<select name="template_id" id="ediworman-export-template" required>
						<option value=""><?php esc_html_e( 'Select a template', 'editorial-workflow-manager' ); ?></option>
						<?php foreach ( $query->posts as $template ) : ?>
							<?php if ( current_user_can( 'edit_post', $template->ID ) ) : ?>
								<option value="<?php echo esc_attr( (string) $template->ID ); ?>"><?php echo esc_html( $template->post_title ); ?></option>
							<?php endif; ?>
						<?php endforeach; ?>
					</select>
					<?php submit_button( __( 'Download JSON', 'editorial-workflow-manager' ), 'secondary', 'ediworman-download' ); ?>
				</form>
				<?php if ( $query->max_num_pages > 1 ) : ?>
					<nav aria-label="<?php esc_attr_e( 'Export template pages', 'editorial-workflow-manager' ); ?>">
					<?php for ( $number = max( 1, $page - 2 ); $number <= $last_page; ++$number ) : ?>
						<a <?php echo $number === $page ? 'aria-current="page"' : ''; ?> href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'ediworman_export_page', $number, self::page_url() ), self::nonce_action( 'page' ) ) ); ?>"><?php echo esc_html( (string) $number ); ?></a>
					<?php endfor; ?>
					</nav>
				<?php endif; ?>
				<?php if ( self::can_import() ) : ?>
					<h2><?php esc_html_e( 'Import a template', 'editorial-workflow-manager' ); ?></h2>
					<p id="ediworman-import-help"><?php esc_html_e( 'Upload one JSON template, review its contents, then create a new unmapped copy. Maximum file size: 1 MiB, or the server upload limit if smaller.', 'editorial-workflow-manager' ); ?></p>
					<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="ediworman_template_preview">
						<?php wp_nonce_field( self::nonce_action( 'preview' ) ); ?>
						<label for="ediworman-template-file"><?php esc_html_e( 'JSON template file', 'editorial-workflow-manager' ); ?></label>
						<input id="ediworman-template-file" type="file" name="ediworman_template_file" accept=".json,application/json" aria-describedby="ediworman-import-help" required>
						<?php submit_button( __( 'Validate and preview', 'editorial-workflow-manager' ), 'primary', 'ediworman-preview' ); ?>
					</form>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Show every imported field as escaped text and current provider warnings.
	 *
	 * @param array  $preview Validated preview.
	 * @param string $token   Scoped confirmation token.
	 * @return void
	 */
	private static function render_preview( $preview, $token ) {
		$template = $preview['document']->template;
		$definitions = EDIWORMAN_Automatic_Requirements::get_rule_definitions();
		?>
		<h2><?php esc_html_e( 'Review import', 'editorial-workflow-manager' ); ?></h2>
		<p><strong><?php esc_html_e( 'New template title:', 'editorial-workflow-manager' ); ?></strong> <?php echo esc_html( self::available_title( $template->title ) ); ?></p>
		<p><?php esc_html_e( 'Item identities and order will be preserved. This preview expires after 15 minutes. No existing template will be replaced.', 'editorial-workflow-manager' ); ?></p>
		<?php foreach ( $preview['warnings'] as $warning ) : ?>
			<div class="notice notice-warning"><p><?php echo esc_html( $warning ); ?></p></div>
		<?php endforeach; ?>
		<h3><?php esc_html_e( 'Checklist items', 'editorial-workflow-manager' ); ?></h3>
		<?php if ( ! $template->items ) : ?>
			<p><?php esc_html_e( 'No manual checklist items.', 'editorial-workflow-manager' ); ?></p>
		<?php endif; ?>
		<ol>
		<?php foreach ( $template->items as $item ) : ?>
			<li>
			<?php if ( 'legacy' === $template->item_format ) : ?>
				<?php echo esc_html( $item ); ?> — <?php esc_html_e( 'Required (legacy format)', 'editorial-workflow-manager' ); ?>
			<?php else : ?>
				<strong><?php echo esc_html( $item->label ); ?></strong> — <?php echo $item->required ? esc_html__( 'Required', 'editorial-workflow-manager' ) : esc_html__( 'Optional', 'editorial-workflow-manager' ); ?>
				<p><?php echo nl2br( esc_html( $item->description ) ); ?></p>
				<p><?php echo esc_html( $item->url ); ?></p>
			<?php endif; ?>
			</li>
		<?php endforeach; ?>
		</ol>
		<h3><?php esc_html_e( 'Automatic requirements', 'editorial-workflow-manager' ); ?></h3>
		<table class="widefat striped"><thead><tr><th scope="col"><?php esc_html_e( 'Rule', 'editorial-workflow-manager' ); ?></th><th scope="col"><?php esc_html_e( 'Settings', 'editorial-workflow-manager' ); ?></th></tr></thead><tbody>
		<?php foreach ( $template->automatic_requirements as $id => $settings ) : ?>
			<?php $settings = (array) $settings; ?>
			<tr><th scope="row"><?php echo esc_html( $definitions[ $id ]['label'] ?? $id ); ?></th><td>
				<?php echo $settings['enabled'] ? esc_html__( 'Enabled', 'editorial-workflow-manager' ) : esc_html__( 'Disabled', 'editorial-workflow-manager' ); ?>
				<?php if ( isset( $settings['minimum'] ) ) : ?>
					<?php echo esc_html( sprintf( /* translators: %d: configured minimum threshold. */ __( '; minimum %d', 'editorial-workflow-manager' ), $settings['minimum'] ) ); ?>
				<?php elseif ( isset( $settings['maximum'] ) ) : ?>
					<?php echo esc_html( sprintf( /* translators: %d: configured maximum threshold. */ __( '; maximum %d', 'editorial-workflow-manager' ), $settings['maximum'] ) ); ?>
				<?php endif; ?>
			</td></tr>
		<?php endforeach; ?>
		</tbody></table>
		<?php foreach ( array( 'confirm' => __( 'Create new template', 'editorial-workflow-manager' ), 'cancel' => __( 'Cancel import', 'editorial-workflow-manager' ) ) as $action => $label ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( 'ediworman_template_' . $action ); ?>">
				<input type="hidden" name="ediworman_token" value="<?php echo esc_attr( $token ); ?>">
				<?php wp_nonce_field( self::nonce_action( $action ) ); ?>
				<?php submit_button( $label, 'confirm' === $action ? 'primary' : 'secondary', 'ediworman-' . $action ); ?>
			</form>
		<?php endforeach; ?>
		<?php
	}
}
