<?php
/**
 * Public automatic-requirement rule registry.
 *
 * @package EditorialWorkflowManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and evaluates built-in and third-party automatic requirements.
 */
final class EDIWORMAN_Rule_Registry {

	const API_VERSION                = 1;
	const RESULT_SCHEMA_VERSION      = 1;
	const FINGERPRINT_OPTION         = 'ediworman_rule_registry_fingerprint';
	const REST_NAMESPACE             = 'editorial-workflow-manager/v1';
	const REST_ROUTE                 = '/posts/(?P<post_id>\d+)/rules';
	const STATUS_PASS                = 'pass';
	const STATUS_FAIL                = 'fail';
	const STATUS_NOT_APPLICABLE      = 'not_applicable';

	/**
	 * Registered rules.
	 *
	 * @var array<string,array>
	 */
	private static $rules = array();

	/**
	 * Whether registration has completed for this request.
	 *
	 * @var bool
	 */
	private static $registration_complete = false;

	/**
	 * Register lifecycle and REST hooks.
	 *
	 * @return void
	 */
	public static function register_hooks() {
		add_action( 'init', array( __CLASS__, 'register_rules' ), 5 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_invalidate_registry_cache' ), 5 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
	}

	/**
	 * Register built-ins, then allow extensions to register their rules.
	 *
	 * @return void
	 */
	public static function register_rules() {
		if ( self::$registration_complete ) {
			return;
		}

		EDIWORMAN_Automatic_Requirements::register_builtin_rules();

		/**
		 * Fires when automatic requirement rules should be registered.
		 *
		 * @since 1.2.0
		 */
		do_action( 'ediworman_register_rules' );

		self::$registration_complete = true;
	}

	/**
	 * Register a built-in rule with a legacy-compatible ID.
	 *
	 * @param string $rule_id Rule ID.
	 * @param array  $args    Registration arguments.
	 * @return true|WP_Error
	 */
	public static function register_builtin_rule( $rule_id, $args ) {
		return self::register( $rule_id, $args, true );
	}

	/**
	 * Register a third-party rule.
	 *
	 * @param string $rule_id Namespaced rule ID.
	 * @param array  $args    Registration arguments.
	 * @return true|WP_Error
	 */
	public static function register_rule( $rule_id, $args ) {
		return self::register( $rule_id, $args, false );
	}

	/**
	 * Return all registered rule definitions.
	 *
	 * @return array<string,array>
	 */
	public static function get_rules() {
		self::ensure_registered();
		return self::$rules;
	}

	/**
	 * Return one registered rule.
	 *
	 * @param string $rule_id Rule ID.
	 * @return array|null
	 */
	public static function get_rule( $rule_id ) {
		self::ensure_registered();
		return isset( self::$rules[ $rule_id ] ) ? self::$rules[ $rule_id ] : null;
	}

	/**
	 * Return rules supported by a post type.
	 *
	 * @param string $post_type Post type slug.
	 * @return array<string,array>
	 */
	public static function get_rules_for_post_type( $post_type ) {
		$post_type = sanitize_key( $post_type );
		$rules     = array();

		foreach ( self::get_rules() as $rule_id => $rule ) {
			if ( ! empty( $rule['post_types'] ) && ! in_array( $post_type, $rule['post_types'], true ) ) {
				continue;
			}

			$rules[ $rule_id ] = $rule;
		}

		return $rules;
	}

	/**
	 * Evaluate one rule and normalize its result.
	 *
	 * @param string $rule_id Rule ID.
	 * @param int    $post_id Post ID.
	 * @param array  $config  Rule configuration.
	 * @return array{schemaVersion:int,id:string,label:string,status:string,passed:bool,message:string}
	 */
	public static function evaluate( $rule_id, $post_id, $config = array() ) {
		$rule    = self::get_rule( $rule_id );
		$post_id = absint( $post_id );

		if ( ! $rule || $post_id <= 0 ) {
			return self::error_result( (string) $rule_id, $rule );
		}

		$post_type = get_post_type( $post_id );
		if ( ! $post_type || ( ! empty( $rule['post_types'] ) && ! in_array( $post_type, $rule['post_types'], true ) ) ) {
			return self::normalize_result(
				$rule_id,
				$rule,
				array(
					'status'  => self::STATUS_NOT_APPLICABLE,
					'message' => '',
				)
			);
		}

		try {
			$result = call_user_func( $rule['evaluate_callback'], $post_id, $config, $rule );
		} catch ( Throwable $error ) {
			self::emit_evaluation_error( $rule_id, $post_id, 'exception', $error );
			return self::error_result( $rule_id, $rule );
		}

		if ( ! self::is_valid_result( $result ) ) {
			self::emit_evaluation_error( $rule_id, $post_id, 'invalid_result' );
			return self::error_result( $rule_id, $rule );
		}

		return self::normalize_result( $rule_id, $rule, $result );
	}

	/**
	 * Return the registry fingerprint used to invalidate aggregate caches.
	 *
	 * @return string
	 */
	public static function get_fingerprint() {
		$fingerprint_data = array();

		foreach ( self::get_rules() as $rule_id => $rule ) {
			$fingerprint_data[ $rule_id ] = array(
				'rule_version' => $rule['rule_version'],
				'post_types'   => $rule['post_types'],
				'dependencies' => $rule['dependencies'],
			);
		}

		return md5( wp_json_encode( $fingerprint_data ) );
	}

	/**
	 * Clear stale readiness caches when the active registry changes.
	 *
	 * @return void
	 */
	public static function maybe_invalidate_registry_cache() {
		$current = self::get_fingerprint();
		$stored  = get_option( self::FINGERPRINT_OPTION, '' );

		if ( hash_equals( (string) $stored, $current ) ) {
			return;
		}

		EDIWORMAN_Readiness::clear_all_caches();
		update_option( self::FINGERPRINT_OPTION, $current );
	}

	/**
	 * Register the read-only saved-rule-results endpoint.
	 *
	 * @return void
	 */
	public static function register_rest_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_rest_rule_results' ),
				'permission_callback' => array( __CLASS__, 'can_read_rest_rule_results' ),
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
						'validate_callback' => static function ( $value ) {
							return absint( $value ) > 0;
						},
					),
				),
			)
		);
	}

	/**
	 * Check whether the current user may read saved rule results.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return bool|WP_Error
	 */
	public static function can_read_rest_rule_results( $request ) {
		$post_id = absint( $request->get_param( 'post_id' ) );
		if ( $post_id <= 0 || ! get_post( $post_id ) ) {
			return new WP_Error( 'ediworman_rule_post_not_found', __( 'The requested post was not found.', 'editorial-workflow-manager' ), array( 'status' => 404 ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'ediworman_rule_forbidden', __( 'You are not allowed to view these rule results.', 'editorial-workflow-manager' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Return authoritative saved rule results for a post.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public static function get_rest_rule_results( $request ) {
		$post_id = absint( $request->get_param( 'post_id' ) );
		$results = EDIWORMAN_Automatic_Requirements::evaluate_post( $post_id );

		EDIWORMAN_Readiness::refresh_cache_for_post( $post_id );

		return rest_ensure_response(
			array(
				'schemaVersion' => self::RESULT_SCHEMA_VERSION,
				'postId'        => $post_id,
				'results'       => array_values( $results ),
			)
		);
	}

	/**
	 * Return whether any registered rule declares a post-meta dependency.
	 *
	 * @param string $meta_key Meta key.
	 * @return bool
	 */
	public static function depends_on_post_meta( $meta_key ) {
		foreach ( self::get_rules() as $rule ) {
			if ( in_array( $meta_key, $rule['dependencies']['post_meta'], true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Return whether any registered rule declares a taxonomy dependency.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 * @return bool
	 */
	public static function depends_on_taxonomy( $taxonomy ) {
		foreach ( self::get_rules() as $rule ) {
			if ( in_array( $taxonomy, $rule['dependencies']['taxonomies'], true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Return whether any registered rule declares attachment ALT dependency.
	 *
	 * @return bool
	 */
	public static function depends_on_attachment_alt() {
		foreach ( self::get_rules() as $rule ) {
			if ( ! empty( $rule['dependencies']['attachment_alt'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Register and validate a rule.
	 *
	 * @param string $rule_id Rule ID.
	 * @param array  $args    Registration arguments.
	 * @param bool   $builtin Whether this is a reserved built-in rule.
	 * @return true|WP_Error
	 */
	private static function register( $rule_id, $args, $builtin ) {
		$rule_id = is_string( $rule_id ) ? trim( $rule_id ) : '';
		$args    = is_array( $args ) ? $args : array();

		if ( ! self::is_valid_rule_id( $rule_id, $builtin ) ) {
			return new WP_Error( 'ediworman_invalid_rule_id', __( 'The automatic requirement rule ID is invalid.', 'editorial-workflow-manager' ) );
		}

		if ( isset( self::$rules[ $rule_id ] ) ) {
			return new WP_Error( 'ediworman_duplicate_rule_id', __( 'The automatic requirement rule ID is already registered.', 'editorial-workflow-manager' ) );
		}

		$api_version = absint( $args['api_version'] ?? 0 );
		if ( self::API_VERSION !== $api_version ) {
			return new WP_Error( 'ediworman_unsupported_rule_api', __( 'The automatic requirement uses an unsupported API version.', 'editorial-workflow-manager' ) );
		}

		$label       = isset( $args['label'] ) && is_scalar( $args['label'] ) ? sanitize_text_field( (string) $args['label'] ) : '';
		$description = isset( $args['description'] ) && is_scalar( $args['description'] ) ? sanitize_text_field( (string) $args['description'] ) : '';
		$callback    = $args['evaluate_callback'] ?? null;

		if ( '' === $label || ! is_callable( $callback ) ) {
			return new WP_Error( 'ediworman_invalid_rule_definition', __( 'The automatic requirement needs a label and callable server evaluator.', 'editorial-workflow-manager' ) );
		}

		$dependencies = self::sanitize_dependencies( $args['dependencies'] ?? array() );
		if ( is_wp_error( $dependencies ) ) {
			return $dependencies;
		}

		$post_types = array();
		if ( isset( $args['post_types'] ) && is_array( $args['post_types'] ) ) {
			$post_types = array_values( array_unique( array_filter( array_map( 'sanitize_key', $args['post_types'] ) ) ) );
		}

		$editor_script = isset( $args['editor_script'] ) && is_scalar( $args['editor_script'] )
			? sanitize_key( (string) $args['editor_script'] )
			: '';

		self::$rules[ $rule_id ] = array(
			'id'                => $rule_id,
			'api_version'       => self::API_VERSION,
			'rule_version'      => self::sanitize_version( $args['rule_version'] ?? '1.0.0' ),
			'label'             => $label,
			'description'       => $description,
			'post_types'        => $post_types,
			'dependencies'      => $dependencies,
			'evaluate_callback' => $callback,
			'editor_script'     => $editor_script,
			'builtin'           => (bool) $builtin,
		);

		return true;
	}

	/**
	 * Ensure rules are available for callbacks that run after init.
	 *
	 * @return void
	 */
	private static function ensure_registered() {
		if ( ! self::$registration_complete && did_action( 'init' ) ) {
			self::register_rules();
		}
	}

	/**
	 * Validate a rule ID.
	 *
	 * @param string $rule_id Rule ID.
	 * @param bool   $builtin Whether built-in legacy IDs are allowed.
	 * @return bool
	 */
	private static function is_valid_rule_id( $rule_id, $builtin ) {
		if ( $builtin ) {
			return in_array( $rule_id, EDIWORMAN_Automatic_Requirements::get_builtin_rule_keys(), true );
		}

		return 1 === preg_match( '/^[a-z][a-z0-9_-]{1,31}\/[a-z][a-z0-9_-]{1,63}$/', $rule_id );
	}

	/**
	 * Sanitize dependency declarations.
	 *
	 * @param mixed $raw Raw dependencies.
	 * @return array|WP_Error
	 */
	private static function sanitize_dependencies( $raw ) {
		$raw = is_array( $raw ) ? $raw : array();
		$allowed_post_fields = array( 'post_title', 'post_content', 'post_excerpt', 'post_date', 'post_author', 'post_status', 'featured_media' );
		$post_fields = isset( $raw['post_fields'] ) && is_array( $raw['post_fields'] )
			? array_values( array_unique( array_map( 'sanitize_key', $raw['post_fields'] ) ) )
			: array();

		foreach ( $post_fields as $post_field ) {
			if ( ! in_array( $post_field, $allowed_post_fields, true ) ) {
				return new WP_Error( 'ediworman_invalid_rule_dependency', __( 'The automatic requirement declares an unsupported post-field dependency.', 'editorial-workflow-manager' ) );
			}
		}

		$post_meta = array();
		if ( isset( $raw['post_meta'] ) && is_array( $raw['post_meta'] ) ) {
			foreach ( $raw['post_meta'] as $meta_key ) {
				if ( ! is_scalar( $meta_key ) ) {
					continue;
				}

				$meta_key = sanitize_text_field( (string) $meta_key );
				if ( '' !== $meta_key && 1 === preg_match( '/^[A-Za-z0-9_-]+$/', $meta_key ) ) {
					$post_meta[] = $meta_key;
				}
			}
		}

		$taxonomies = isset( $raw['taxonomies'] ) && is_array( $raw['taxonomies'] )
			? array_values( array_unique( array_filter( array_map( 'sanitize_key', $raw['taxonomies'] ) ) ) )
			: array();

		return array(
			'post_fields'   => $post_fields,
			'post_meta'     => array_values( array_unique( $post_meta ) ),
			'taxonomies'    => $taxonomies,
			'attachment_alt' => ! empty( $raw['attachment_alt'] ),
		);
	}

	/**
	 * Normalize a semantic-looking version value.
	 *
	 * @param mixed $version Raw version.
	 * @return string
	 */
	private static function sanitize_version( $version ) {
		$version = is_scalar( $version ) ? sanitize_text_field( (string) $version ) : '';
		return 1 === preg_match( '/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version ) ? $version : '1.0.0';
	}

	/**
	 * Return whether an evaluator result follows schema version 1.
	 *
	 * @param mixed $result Raw result.
	 * @return bool
	 */
	private static function is_valid_result( $result ) {
		if ( ! is_array( $result ) || ! isset( $result['status'] ) || ! is_string( $result['status'] ) ) {
			return false;
		}

		if ( ! in_array( $result['status'], array( self::STATUS_PASS, self::STATUS_FAIL, self::STATUS_NOT_APPLICABLE ), true ) ) {
			return false;
		}

		return ! isset( $result['message'] ) || is_scalar( $result['message'] );
	}

	/**
	 * Normalize a successful evaluator result.
	 *
	 * @param string $rule_id Rule ID.
	 * @param array  $rule    Rule definition.
	 * @param array  $result  Evaluator result.
	 * @return array
	 */
	private static function normalize_result( $rule_id, $rule, $result ) {
		$status = $result['status'];
		return array(
			'schemaVersion' => self::RESULT_SCHEMA_VERSION,
			'id'            => $rule_id,
			'key'           => $rule_id,
			'label'         => $rule['label'],
			'status'        => $status,
			'passed'        => self::STATUS_PASS === $status,
			'message'       => isset( $result['message'] ) ? sanitize_text_field( (string) $result['message'] ) : '',
		);
	}

	/**
	 * Return a safe failed result after an evaluator error.
	 *
	 * @param string     $rule_id Rule ID.
	 * @param array|null $rule    Rule definition.
	 * @return array
	 */
	private static function error_result( $rule_id, $rule ) {
		$rule = is_array( $rule ) ? $rule : array( 'label' => sanitize_text_field( $rule_id ) );
		return self::normalize_result(
			$rule_id,
			$rule,
			array(
				'status'  => self::STATUS_FAIL,
				'message' => __( 'This requirement could not be evaluated. Contact a site administrator.', 'editorial-workflow-manager' ),
			)
		);
	}

	/**
	 * Emit a diagnostic hook without exposing exception details to authors.
	 *
	 * @param string         $rule_id Rule ID.
	 * @param int            $post_id Post ID.
	 * @param string         $reason  Error reason.
	 * @param Throwable|null $error   Optional caught error.
	 * @return void
	 */
	private static function emit_evaluation_error( $rule_id, $post_id, $reason, $error = null ) {
		/**
		 * Fires when an automatic-requirement evaluator fails.
		 *
		 * @since 1.2.0
		 */
		do_action( 'ediworman_rule_evaluation_error', $rule_id, $post_id, $reason, $error );
	}
}
