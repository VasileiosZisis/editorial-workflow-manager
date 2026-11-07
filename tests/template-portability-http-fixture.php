<?php
/**
 * Prepare/clean isolated local HTTP test credentials and template fixtures.
 *
 * @package EditorialWorkflowManager
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}

/**
 * Manage only local test-owned templates and an expiring login session.
 *
 * @param array $arguments CLI mode and optional temporary state-file path.
 * @return void
 */
function ediworman_portability_http_fixture( $arguments ) {
	if ( ! preg_match( '/(?:\.local|\.test)$|^localhost$/D', wp_parse_url( home_url(), PHP_URL_HOST ) ) ) {
		WP_CLI::error( 'Run only on a local development site.' );
	}
	if ( 'cleanup' === ( $arguments[0] ?? '' ) ) {
		$state_path = realpath( $arguments[1] ?? '' );
		if ( ! $state_path || dirname( $state_path ) !== realpath( sys_get_temp_dir() ) || 0 !== strpos( basename( $state_path ), 'ediworman-transfer-http-' ) ) {
			WP_CLI::error( 'Invalid temporary state path.' );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Validated local temporary state, never a URL.
		$state = json_decode( file_get_contents( $state_path ), true );
		wp_set_current_user( $state['user'] );
		$source = get_post( $state['ids'][0] );
		foreach ( (array) _get_cron_array() as $when => $hooks ) {
			foreach ( $hooks[ EDIWORMAN_Template_Portability::CLEANUP_HOOK ] ?? array() as $event ) {
				$record = get_transient( $event['args'][0] );
				$document = is_array( $record ) ? json_decode( $record['json'] ) : null;
				if ( $source && $document && $document->template->title === $source->post_title ) {
					EDIWORMAN_Template_Portability::cleanup( ...$event['args'] );
					wp_unschedule_event( $when, EDIWORMAN_Template_Portability::CLEANUP_HOOK, $event['args'] );
				}
			}
		}
		foreach ( $state['pending_tokens'] as $token ) {
			EDIWORMAN_Template_Portability::cancel( $token );
		}
		foreach ( array_reverse( $state['ids'] ) as $post_id ) {
			$post = get_post( $post_id );
			if ( $post && 'ediworman_template' === $post->post_type && 0 === strpos( $post->post_title, 'HTTP portability ' ) ) {
				wp_delete_post( $post_id, true );
			}
		}
		WP_Session_Tokens::get_instance( $state['user'] )->destroy( $state['session'] );
		wp_delete_file( $state_path );
		WP_CLI::success( 'Removed HTTP fixtures and temporary test login session.' );
		return;
	}
	if ( 'prepare' !== ( $arguments[0] ?? '' ) ) {
		WP_CLI::error( 'Use prepare or cleanup with the temporary state-file path.' );
	}
	$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
	if ( ! $admins ) {
		WP_CLI::error( 'An administrator is required.' );
	}
	wp_set_current_user( $admins[0] );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local fixture.
	$document = json_decode( file_get_contents( __DIR__ . '/template-portability-fixtures.json' ) );
	$document->template->title = 'HTTP portability ' . wp_generate_uuid4();
	$preview = EDIWORMAN_Template_Portability::stage( wp_json_encode( $document ) );
	if ( is_wp_error( $preview ) ) {
		WP_CLI::error( $preview->get_error_message() );
	}
	$post_id = EDIWORMAN_Template_Portability::confirm( $preview );
	if ( is_wp_error( $post_id ) ) {
		EDIWORMAN_Template_Portability::cancel( $preview );
		WP_CLI::error( $post_id->get_error_message() );
	}
	$expires = time() + 1200;
	$token = WP_Session_Tokens::get_instance( $admins[0] )->create( $expires );
	$cookie = AUTH_COOKIE . '=' . wp_generate_auth_cookie( $admins[0], $expires, 'auth', $token ) . '; ' . LOGGED_IN_COOKIE . '=' . wp_generate_auth_cookie( $admins[0], $expires, 'logged_in', $token );
	$state_path = rtrim( sys_get_temp_dir(), '/\\' ) . DIRECTORY_SEPARATOR . 'ediworman-transfer-http-' . wp_generate_uuid4() . '.json';
	$state = array( 'base' => home_url(), 'cookie' => $cookie, 'user' => $admins[0], 'session' => $token, 'ids' => array( $post_id ), 'pending_tokens' => array() );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- CLI-only temporary credential file outside the plugin/web root; never print its contents.
	if ( false === file_put_contents( $state_path, wp_json_encode( $state ) ) ) {
		WP_Session_Tokens::get_instance( $admins[0] )->destroy( $token );
		wp_delete_post( $post_id, true );
		WP_CLI::error( 'Could not write temporary state.' );
	}
	WP_CLI::line( $state_path );
}

ediworman_portability_http_fixture( $args );
