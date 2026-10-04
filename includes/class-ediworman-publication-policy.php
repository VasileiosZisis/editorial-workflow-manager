<?php
/**
 * Opt-in, server-authoritative first-publication policy.
 *
 * @package EditorialWorkflowManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Holds publication until the complete saved post satisfies its requirements.
 */
final class EDIWORMAN_Publication_Policy {

	const BLOCKED_META = '_ediworman_publication_blocked';

	/**
	 * Pending saves, including new posts without an ID.
	 *
	 * @var array<int,array>
	 */
	private static $pending = array();
	/**
	 * Core REST save contexts, keyed by request identity.
	 *
	 * @var array<string,array>
	 */
	private static $rest_contexts = array();
	/**
	 * Internal status updates currently in progress.
	 *
	 * @var array<int,bool>
	 */
	private static $promoting = array();
	/**
	 * Posts blocked during this request.
	 *
	 * @var array<int,bool>
	 */
	private static $blocked = array();

	/**
	 * Register global enforcement and scoped admin presentation hooks.
	 *
	 * @return void
	 */
	public static function register_hooks() {
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'hold_publication' ), PHP_INT_MAX, 4 );
		add_action( 'wp_insert_post', array( __CLASS__, 'finish_save' ), PHP_INT_MAX, 3 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_hooks' ) );
		add_filter( 'rest_request_after_callbacks', array( __CLASS__, 'finish_rest_response' ), PHP_INT_MAX, 3 );
		add_action( 'publish_future_post', array( __CLASS__, 'check_scheduled_post' ), 0 );
		add_action( 'transition_post_status', array( __CLASS__, 'guard_direct_publication' ), -1000, 3 );
		if ( is_admin() ) {
			add_filter( 'display_post_states', array( __CLASS__, 'add_post_state' ), 10, 2 );
			add_filter( 'wp_redirect', array( __CLASS__, 'add_redirect_notice' ), 10, 2 );
			add_filter( 'removable_query_args', array( __CLASS__, 'notice_query_args' ) );
			add_action( 'admin_notices', array( __CLASS__, 'render_admin_notice' ) );
		}
	}

	/**
	 * Attach to the core REST controller's post-type-specific save hooks.
	 *
	 * @return void
	 */
	public static function register_rest_hooks() {
		foreach ( get_post_types( array( 'show_in_rest' => true ) ) as $post_type ) {
			add_filter( 'rest_pre_insert_' . $post_type, array( __CLASS__, 'begin_rest_save' ), PHP_INT_MAX, 2 );
			add_action( 'rest_after_insert_' . $post_type, array( __CLASS__, 'finish_rest_save' ), PHP_INT_MAX, 3 );
		}
	}

	/**
	 * Record the specific REST save so nested or unrelated saves are not deferred.
	 *
	 * @param stdClass|WP_Error $prepared Prepared core post fields.
	 * @param WP_REST_Request   $request  Authenticated core REST request.
	 * @return stdClass|WP_Error
	 */
	public static function begin_rest_save( $prepared, $request ) {
		if ( ! is_wp_error( $prepared ) ) {
			self::$rest_contexts[ spl_object_hash( $request ) ] = array(
				'id'        => absint( $prepared->ID ?? 0 ),
				'type'      => $prepared->post_type ?? get_post_type( $prepared->ID ?? 0 ),
				'signature' => self::signature( (array) $prepared ),
				'claimed'   => false,
			);
		}
		return $prepared;
	}

	/**
	 * Replace the requested public status before any database write or publish hook.
	 *
	 * @param array $data        Processed, slashed post fields.
	 * @param array $postarr     Sanitized save input.
	 * @param array $raw_postarr Original save input.
	 * @param bool  $update      Whether this is an update.
	 * @return array
	 */
	public static function hold_publication( $data, $postarr, $raw_postarr, $update ) {
		unset( $raw_postarr, $update );
		$post_id = absint( $postarr['ID'] ?? 0 );
		$type    = $data['post_type'];
		$status  = $data['post_status'];
		$before  = $post_id ? get_post( $post_id ) : null;
		if (
			! empty( self::$promoting[ $post_id ] ) ||
			'block' !== EDIWORMAN_Settings::get_publication_mode( $type ) ||
			! in_array( $status, array( 'publish', 'private', 'future' ), true ) ||
			( $before && in_array( $before->post_status, array( 'publish', 'private' ), true ) ) ||
			( $post_id && ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) )
		) {
			return $data;
		}

		$rest_key = '';
		foreach ( self::$rest_contexts as $key => &$context ) {
			if ( ! $context['claimed'] && ( $post_id ? $post_id === $context['id'] : 0 === $context['id'] && $type === $context['type'] ) ) {
				$rest_key           = $key;
				$context['claimed'] = true;
				break;
			}
		}
		unset( $context );

		$held_status = $before && in_array( $before->post_status, array( 'draft', 'pending' ), true ) ? $before->post_status : 'draft';
		self::$pending[] = array(
			'id'        => $post_id,
			'signature' => self::signature( wp_unslash( $data ) ),
			'status'    => $status,
			'rest'      => $rest_key,
		);
		$data['post_status'] = $held_status;
		return $data;
	}

	/**
	 * Identify new posts and finalize ordinary saves, including disabled after hooks.
	 *
	 * @param int     $post_id Saved post ID.
	 * @param WP_Post $post    Saved post.
	 * @param bool    $update  Whether this was an update.
	 * @return void
	 */
	public static function finish_save( $post_id, $post, $update ) {
		unset( $update );
		if ( ! empty( self::$promoting[ $post_id ] ) ) {
			return;
		}
		foreach ( self::$pending as $key => &$attempt ) {
			if ( $post_id !== $attempt['id'] && ( $attempt['id'] || self::signature( (array) $post ) !== $attempt['signature'] ) ) {
				continue;
			}
			$attempt['id'] = $post_id;
			if ( $attempt['rest'] ) {
				self::$rest_contexts[ $attempt['rest'] ]['id'] = $post_id;
			} else {
				self::finalize( $key );
			}
			unset( $attempt );
			return;
		}
		unset( $attempt );
		self::get_blocked_details( $post_id );
	}

	/**
	 * Finalize only after core REST metadata and additional fields are persisted.
	 *
	 * @param WP_Post         $post     Saved post object used by the core response.
	 * @param WP_REST_Request $request  Core REST request.
	 * @param bool            $creating Whether this was creation.
	 * @return void
	 */
	public static function finish_rest_save( $post, $request, $creating ) {
		unset( $creating );
		$request_key = spl_object_hash( $request );
		foreach ( self::$pending as $key => $attempt ) {
			if ( $request_key === $attempt['rest'] && $post->ID === $attempt['id'] ) {
				$error = self::finalize( $key );
				self::$rest_contexts[ $request_key ]['error'] = $error;
				$fresh = get_post( $post->ID );
				foreach ( get_object_vars( $fresh ) as $field => $value ) {
					$post->$field = $value;
				}
				break;
			}
		}
	}

	/**
	 * Return a meaningful REST failure and discard interrupted request contexts.
	 *
	 * @param WP_REST_Response|WP_Error $response Core response.
	 * @param array                    $handler  Matched handler.
	 * @param WP_REST_Request          $request  Core REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function finish_rest_response( $response, $handler, $request ) {
		unset( $handler );
		$key = spl_object_hash( $request );
		if ( ! isset( self::$rest_contexts[ $key ] ) ) {
			return $response;
		}
		$context = self::$rest_contexts[ $key ];
		unset( self::$rest_contexts[ $key ] );
		foreach ( self::$pending as $pending_key => $attempt ) {
			if ( $key === $attempt['rest'] ) {
				unset( self::$pending[ $pending_key ] );
			}
		}
		return ! empty( $context['error'] ) && ! is_wp_error( $response ) ? $context['error'] : $response;
	}

	/**
	 * Evaluate the complete saved state once, then promote or record the failure.
	 *
	 * @param int $key Pending attempt key.
	 * @return WP_Error|null
	 */
	private static function finalize( $key ) {
		$attempt = self::$pending[ $key ];
		unset( self::$pending[ $key ] );
		$post_id = $attempt['id'];
		$details = self::evaluate_gate( $post_id );
		if ( ! empty( $details['missing'] ) ) {
			self::record_block( $post_id, $details, 'future' === $attempt['status'] );
			return self::blocked_error( $post_id, $details );
		}

		self::$promoting[ $post_id ] = true;
		try {
			$result = wp_update_post( array( 'ID' => $post_id, 'post_status' => $attempt['status'] ), true );
		} finally {
			unset( self::$promoting[ $post_id ] );
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		delete_post_meta( $post_id, self::BLOCKED_META );
		unset( self::$blocked[ $post_id ] );
		EDIWORMAN_Readiness::refresh_cache_for_post( $post_id );
		return null;
	}

	/**
	 * Obtain authoritative gate results without aggregate caches.
	 *
	 * @param int $post_id Content ID.
	 * @return array{reason:string,missing:array}
	 */
	private static function evaluate_gate( $post_id ) {
		if ( 'block' !== EDIWORMAN_Settings::get_publication_mode( get_post_type( $post_id ) ) ) {
			return array( 'reason' => '', 'missing' => array() );
		}
		$evaluation = EDIWORMAN_Readiness::evaluate_readiness_for_post( $post_id );
		if ( null === $evaluation ) {
			return array( 'reason' => 'template', 'missing' => array( __( 'Ask an administrator to assign a valid checklist template or select Advisory publication.', 'editorial-workflow-manager' ) ) );
		}
		return array( 'reason' => 'requirements', 'missing' => $evaluation['missing_required_labels'] );
	}

	/**
	 * Check due posts before WordPress's existing scheduled-publication callback.
	 *
	 * @param int $post_id Scheduled post ID.
	 * @return void
	 */
	public static function check_scheduled_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || 'future' !== $post->post_status || strtotime( $post->post_date_gmt . ' GMT' ) > time() ) {
			return;
		}
		$details = self::evaluate_gate( $post->ID );
		if ( ! empty( $details['missing'] ) ) {
			wp_update_post( array( 'ID' => $post->ID, 'post_status' => 'draft' ) );
			self::record_block( $post->ID, $details, true );
		}
	}

	/**
	 * Roll back lower-level publication that bypassed the guarded save API.
	 *
	 * Core has already written the status on this path; external transition
	 * callbacks and transient visibility cannot be prevented by this fallback.
	 *
	 * @param string  $new_status Requested status.
	 * @param string  $old_status Previous status.
	 * @param WP_Post $post       Post being transitioned.
	 * @return void
	 */
	public static function guard_direct_publication( $new_status, $old_status, $post ) {
		if ( ! empty( self::$promoting[ $post->ID ] ) || ! in_array( $new_status, array( 'publish', 'private', 'future' ), true ) || in_array( $old_status, array( 'publish', 'private' ), true ) ) {
			return;
		}
		$details = self::evaluate_gate( $post->ID );
		if ( empty( $details['missing'] ) ) {
			return;
		}
		$status = in_array( $old_status, array( 'draft', 'pending' ), true ) ? $old_status : 'draft';
		wp_update_post( array( 'ID' => $post->ID, 'post_status' => $status ) );
		$post->post_status = $status;
		self::record_block( $post->ID, $details, 'future' === $old_status || 'future' === $new_status );
	}

	/**
	 * Save only the latest failure marker and clear obsolete cron publication.
	 *
	 * @param int   $post_id Content ID.
	 * @param array $details Current failure.
	 * @param bool  $scheduled Whether a scheduled attempt was held.
	 * @return void
	 */
	private static function record_block( $post_id, $details, $scheduled ) {
		self::$blocked[ $post_id ] = true;
		update_post_meta( $post_id, self::BLOCKED_META, array( 'reason' => $details['reason'], 'scheduled' => $scheduled, 'timestamp' => time() ) );
		wp_clear_scheduled_hook( 'publish_future_post', array( $post_id ) );
	}

	/**
	 * Build a safe, actionable REST error; submitted content remains saved.
	 *
	 * @param int   $post_id Content ID.
	 * @param array $details Current failure.
	 * @return WP_Error
	 */
	private static function blocked_error( $post_id, $details ) {
		return new WP_Error(
			'ediworman_publication_blocked',
			__( 'Publication blocked. Your changes were saved without publishing. Complete the requirements and try again:', 'editorial-workflow-manager' ) . ' ' . implode( '; ', $details['missing'] ),
			array( 'status' => 409, 'post_id' => $post_id, 'post_status' => get_post_status( $post_id ), 'missing_required' => $details['missing'] )
		);
	}

	/**
	 * Return current recovery details and remove resolved markers lazily.
	 *
	 * @param int $post_id Content ID.
	 * @return array|null
	 */
	public static function get_blocked_details( $post_id ) {
		$marker = get_post_meta( $post_id, self::BLOCKED_META, true );
		if ( ! is_array( $marker ) ) {
			return null;
		}
		$details = self::evaluate_gate( $post_id );
		if ( empty( $details['missing'] ) || in_array( get_post_status( $post_id ), array( 'publish', 'private' ), true ) ) {
			delete_post_meta( $post_id, self::BLOCKED_META );
			return null;
		}
		$details['scheduled'] = ! empty( $marker['scheduled'] );
		return $details;
	}

	/**
	 * Expose the blocked state in ordinary and Quick Edit list rows.
	 *
	 * @param array   $states Existing state labels.
	 * @param WP_Post $post   Row post.
	 * @return array
	 */
	public static function add_post_state( $states, $post ) {
		if ( current_user_can( 'edit_post', $post->ID ) && self::get_blocked_details( $post->ID ) ) {
			$states['ediworman_publication_blocked'] = __( 'Publication blocked — open the checklist', 'editorial-workflow-manager' );
		}
		return $states;
	}

	/**
	 * Carry this request's blocked count into native post-list/editor redirects.
	 *
	 * @param string $location Redirect URL.
	 * @param int    $status   HTTP redirect status.
	 * @return string
	 */
	public static function add_redirect_notice( $location, $status ) {
		unset( $status );
		$path = wp_parse_url( $location, PHP_URL_PATH );
		if ( self::$blocked && in_array( basename( (string) $path ), array( 'edit.php', 'post.php' ), true ) ) {
			$location = add_query_arg( 'ediworman_publication_blocked', count( self::$blocked ), $location );
		}
		return $location;
	}

	/**
	 * Prevent one-time bulk notices from following list navigation.
	 *
	 * @param array $args Removable query arguments.
	 * @return array
	 */
	public static function notice_query_args( $args ) {
		$args[] = 'ediworman_publication_blocked';
		return $args;
	}

	/**
	 * Show contextual recovery information on post editors and native bulk redirects.
	 *
	 * @return void
	 */
	public static function render_admin_notice() {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->base, array( 'post', 'edit' ), true ) ) {
			return;
		}
		global $post;
		$details = 'post' === $screen->base && $post instanceof WP_Post && current_user_can( 'edit_post', $post->ID ) ? self::get_blocked_details( $post->ID ) : null;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice count from a native redirect.
		$count = isset( $_GET['ediworman_publication_blocked'] ) ? absint( sanitize_text_field( wp_unslash( $_GET['ediworman_publication_blocked'] ) ) ) : 0;
		if ( ! $details && ! $count ) {
			return;
		}
		echo '<div class="notice notice-warning is-dismissible"><p>';
		if ( $details ) {
			echo esc_html( $details['scheduled'] ? __( 'Scheduled publication was held. This post is a draft. Complete its requirements and publish or schedule it again.', 'editorial-workflow-manager' ) : __( 'Publication was blocked. Your changes were saved without publishing. Complete the requirements and try again.', 'editorial-workflow-manager' ) );
		} else {
			printf( /* translators: %d: number of posts held. */ esc_html__( 'Publication blocked for %d post(s). Changes were saved without publishing. Open their editorial checklists to resolve the missing requirements.', 'editorial-workflow-manager' ), (int) $count );
		}
		echo '</p>';
		if ( $details ) {
			echo '<ul>';
			foreach ( $details['missing'] as $label ) {
				echo '<li>' . esc_html( $label ) . '</li>';
			}
			echo '</ul>';
		}
		echo '</div>';
	}

	/**
	 * Match a new held save without relying on title alone.
	 *
	 * @param array $fields Unslashed post fields.
	 * @return string
	 */
	private static function signature( $fields ) {
		return md5( wp_json_encode( array( $fields['post_type'] ?? '', $fields['post_title'] ?? '', $fields['post_content'] ?? '' ) ) );
	}
}
