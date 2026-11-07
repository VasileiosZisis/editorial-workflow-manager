<?php
/**
 * Reversible local tests: wp eval-file tests/template-portability-integration.php allow-local-fixtures
 *
 * @package EditorialWorkflowManager
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}
if ( ! in_array( 'allow-local-fixtures', $args, true ) || ! preg_match( '/(?:\.local|\.test)$|^localhost$/D', wp_parse_url( home_url(), PHP_URL_HOST ) ) ) {
	WP_CLI::error( 'Run only on a local development site with allow-local-fixtures.' );
}

/**
 * Assert an integration expectation.
 *
 * @param bool   $condition Expected truth.
 * @param string $message   Failure description.
 * @return void
 * @throws RuntimeException When an expectation fails.
 */
function ediworman_portability_expect( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
}

$ediworman_ids = array();
$ediworman_tokens = array();
$ediworman_user_id = get_current_user_id();
$ediworman_admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
wp_set_current_user( $ediworman_admins[0] );
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local fixture.
$ediworman_json = file_get_contents( __DIR__ . '/template-portability-fixtures.json' );
$ediworman_document = json_decode( $ediworman_json );
$ediworman_original_settings = get_option( EDIWORMAN_Settings::OPTION_NAME );
$ediworman_checks = 0;

try {
	$ediworman_valid = EDIWORMAN_Template_Portability::validate_json( $ediworman_json );
	ediworman_portability_expect( ! is_wp_error( $ediworman_valid ) && empty( $ediworman_valid['warnings'] ), 'Valid v2 fixture changed during validation.' );
	ediworman_portability_expect( 1 === count( EDIWORMAN_Template_Portability::provider_warnings( $ediworman_valid['document'] ) ), 'Unavailable provider warning missing.' );
	$ediworman_invalid_files = array( '', 'null', '[]', '{}', '{', str_repeat( ' ', EDIWORMAN_Template_Portability::MAX_BYTES + 1 ), str_replace( '"schema_version": 1', '"schema_version": 2', $ediworman_json ), str_replace( '"required": true', '"required": "true"', $ediworman_json ), str_replace( '"minimum": 2', '"minimum": 2.0', $ediworman_json ), str_replace( '"maximum": 0', '"maximum": -1', $ediworman_json ), str_replace( '6b97e11a-b186-475d-b610-0b4edfc27812', 'd452be02-2455-4c21-aac7-efac7e9dd35c', $ediworman_json ) );
	foreach ( $ediworman_invalid_files as $ediworman_invalid ) {
		ediworman_portability_expect( is_wp_error( EDIWORMAN_Template_Portability::validate_json( $ediworman_invalid ) ), 'Invalid JSON/types/size/UUID accepted.' );
		++$ediworman_checks;
	}
	foreach ( array( 'title', 'item_format', 'items', 'automatic_requirements' ) as $ediworman_field ) {
		$ediworman_bad = json_decode( $ediworman_json );
		unset( $ediworman_bad->template->$ediworman_field );
		ediworman_portability_expect( is_wp_error( EDIWORMAN_Template_Portability::validate_json( wp_json_encode( $ediworman_bad ) ) ), 'Missing template field accepted.' );
		++$ediworman_checks;
	}
	foreach ( array( 'post_type_templates', 'publication_modes', 'author', 'post_id', 'checked_items' ) as $ediworman_field ) {
		$ediworman_bad = json_decode( $ediworman_json );
		$ediworman_bad->template->$ediworman_field = 1;
		ediworman_portability_expect( is_wp_error( EDIWORMAN_Template_Portability::validate_json( wp_json_encode( $ediworman_bad ) ) ), 'Unexpected template field accepted.' );
		++$ediworman_checks;
	}
	foreach ( array( 'future_builtin', 'x/y', '../bad' ) as $ediworman_bad_id ) {
		$ediworman_bad = json_decode( $ediworman_json );
		$ediworman_bad->template->automatic_requirements->$ediworman_bad_id = (object) array( 'enabled' => true );
		ediworman_portability_expect( is_wp_error( EDIWORMAN_Template_Portability::validate_json( wp_json_encode( $ediworman_bad ) ) ), 'Unknown built-in/invalid provider ID accepted.' );
		++$ediworman_checks;
	}
	$ediworman_bad = json_decode( $ediworman_json );
	$ediworman_bad->template->automatic_requirements->minimum_word_count->minimum = 1000;
	$ediworman_bad->template->automatic_requirements->maximum_word_count->maximum = 5;
	ediworman_portability_expect( is_wp_error( EDIWORMAN_Template_Portability::validate_json( wp_json_encode( $ediworman_bad ) ) ), 'Contradictory range accepted.' );
	$ediworman_bad = json_decode( $ediworman_json );
	$ediworman_bad->template->automatic_requirements->{'fixture/unavailable'}->callback = 'phpinfo';
	ediworman_portability_expect( is_wp_error( EDIWORMAN_Template_Portability::validate_json( wp_json_encode( $ediworman_bad ) ) ), 'Unexpected provider configuration accepted.' );
	$ediworman_bad = json_decode( $ediworman_json );
	$ediworman_bad->template->items[0]->label = '<script>bad()</script>Safe label';
	$ediworman_sanitized = EDIWORMAN_Template_Portability::validate_json( wp_json_encode( $ediworman_bad ) );
	ediworman_portability_expect( ! is_wp_error( $ediworman_sanitized ) && ! empty( $ediworman_sanitized['warnings'] ), 'Sanitization changes not disclosed.' );
	$ediworman_bad->template->items[0]->url = 'javascript:alert(1)';
	ediworman_portability_expect( is_wp_error( EDIWORMAN_Template_Portability::validate_json( wp_json_encode( $ediworman_bad ) ) ), 'Invalid URL silently dropped.' );
	++$ediworman_checks;

	// Old v2 templates lack helper fields, but remain supported without migration.
	$ediworman_old_id = wp_insert_post( array( 'post_type' => 'ediworman_template', 'post_status' => 'publish', 'post_title' => 'Old v2 portability fixture' ), true );
	$ediworman_ids[] = $ediworman_old_id;
	$ediworman_old_item = array( 'id' => wp_generate_uuid4(), 'label' => 'Old item', 'required' => false );
	update_post_meta( $ediworman_old_id, '_ediworman_items_v2', array( $ediworman_old_item ) );
	$ediworman_old_before = get_post_meta( $ediworman_old_id );
	$ediworman_old_export = EDIWORMAN_Template_Portability::export_template( $ediworman_old_id );
	ediworman_portability_expect( ! is_wp_error( $ediworman_old_export ) && '' === $ediworman_old_export['document']->template->items[0]->description && '' === $ediworman_old_export['document']->template->items[0]->url && get_post_meta( $ediworman_old_id ) === $ediworman_old_before, 'Old v2 export failed or mutated source.' );
	++$ediworman_checks;

	// Raw UTF-8 stays within the limit through staging, including CJK text.
	$ediworman_unicode = json_decode( $ediworman_json );
	$ediworman_unicode->template->items[0]->description = str_repeat( '中', 180000 );
	$ediworman_unicode_json = wp_json_encode( $ediworman_unicode, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	$ediworman_unicode_token = EDIWORMAN_Template_Portability::stage( $ediworman_unicode_json );
	ediworman_portability_expect( is_string( $ediworman_unicode_token ), 'Valid large Unicode file was rejected.' );
	$ediworman_tokens[] = $ediworman_unicode_token;
	ediworman_portability_expect( ! is_wp_error( EDIWORMAN_Template_Portability::load_preview( $ediworman_unicode_token ) ), 'Unicode escaping made the staged file too large.' );
	EDIWORMAN_Template_Portability::cancel( $ediworman_unicode_token );
	++$ediworman_checks;

	// Interleave two claim acquisitions after their cache checks, before SQL executes.
	$ediworman_acquire = new ReflectionMethod( EDIWORMAN_Template_Portability::class, 'acquire_claim' );
	$ediworman_acquire->setAccessible( true );
	$ediworman_release = new ReflectionMethod( EDIWORMAN_Template_Portability::class, 'release_claim' );
	$ediworman_release->setAccessible( true );
	$ediworman_race_key = 'ediworman_import_claim_' . hash( 'sha256', wp_generate_uuid4() );
	$ediworman_inner_won = false;
	$ediworman_interleave = null;
	$ediworman_interleave = static function ( $sql ) use ( &$ediworman_interleave, &$ediworman_inner_won, $ediworman_acquire, $ediworman_race_key ) {
		if ( false !== strpos( $sql, 'INSERT IGNORE' ) && false !== strpos( $sql, $ediworman_race_key ) ) {
			remove_filter( 'query', $ediworman_interleave );
			$ediworman_inner_won = $ediworman_acquire->invoke( null, $ediworman_race_key, 'winner' );
		}
		return $sql;
	};
	add_filter( 'query', $ediworman_interleave );
	try {
		$ediworman_outer_won = $ediworman_acquire->invoke( null, $ediworman_race_key, 'loser' );
		ediworman_portability_expect( $ediworman_inner_won && ! $ediworman_outer_won && 'winner' === get_option( $ediworman_race_key ), 'Interleaved claim overwrote a winner.' );
		$ediworman_release->invoke( null, $ediworman_race_key, 'loser' );
		ediworman_portability_expect( 'winner' === get_option( $ediworman_race_key ), 'Non-owner released a claim.' );
	} finally {
		remove_filter( 'query', $ediworman_interleave );
		delete_option( $ediworman_race_key );
	}
	++$ediworman_checks;

	// Round trips preserve UUIDs, literal backslashes, text, order, and all rules.
	$ediworman_v2_source = 0;
	foreach ( array( 'v2', 'legacy', 'automatic-only' ) as $ediworman_mode ) {
		$ediworman_payload = json_decode( $ediworman_json );
		$ediworman_payload->template->title .= ' ' . wp_generate_password( 8, false, false ) . ' ' . $ediworman_mode;
		if ( 'legacy' === $ediworman_mode ) {
			$ediworman_payload->template->item_format = 'legacy';
			$ediworman_payload->template->items = array( 'First label', '[optional] Literal legacy marker', 'C:\\drafts and café' );
		} elseif ( 'automatic-only' === $ediworman_mode ) {
			$ediworman_payload->template->items = array();
		}
		$ediworman_token = EDIWORMAN_Template_Portability::stage( wp_json_encode( $ediworman_payload ) );
		ediworman_portability_expect( is_string( $ediworman_token ), 'Could not stage valid document.' );
		$ediworman_tokens[] = $ediworman_token;
		$ediworman_id = EDIWORMAN_Template_Portability::confirm( $ediworman_token );
		ediworman_portability_expect( is_int( $ediworman_id ), 'Valid import failed.' );
		$ediworman_ids[] = $ediworman_id;
		if ( 'v2' === $ediworman_mode ) {
			$ediworman_v2_source = $ediworman_id;
		}
		$ediworman_before = get_post_meta( $ediworman_id );
		$ediworman_export = EDIWORMAN_Template_Portability::export_template( $ediworman_id );
		ediworman_portability_expect( ! is_wp_error( $ediworman_export ) && wp_json_encode( $ediworman_export['document'] ) === wp_json_encode( EDIWORMAN_Template_Portability::validate_json( wp_json_encode( $ediworman_payload ) )['document'] ), 'Round-trip data changed.' );
		ediworman_portability_expect( get_post_meta( $ediworman_id ) === $ediworman_before, 'Export wrote to source metadata.' );
		ediworman_portability_expect( get_option( EDIWORMAN_Settings::OPTION_NAME ) === $ediworman_original_settings, 'Import modified site settings.' );
		ediworman_portability_expect( is_wp_error( EDIWORMAN_Template_Portability::confirm( $ediworman_token ) ), 'Consumed preview could be replayed.' );
		ediworman_portability_expect( is_wp_error( EDIWORMAN_Template_Portability::load_preview( $ediworman_token ) ), 'Completed staging data survived.' );
		++$ediworman_checks;
	}

	// Title conflict creates a copy, never updates the original.
	$ediworman_copy_source = $ediworman_v2_source;
	$ediworman_copy = EDIWORMAN_Template_Portability::export_template( $ediworman_copy_source );
	$ediworman_copy_token = EDIWORMAN_Template_Portability::stage( wp_json_encode( $ediworman_copy['document'] ) );
	$ediworman_tokens[] = $ediworman_copy_token;
	$ediworman_copy_id = EDIWORMAN_Template_Portability::confirm( $ediworman_copy_token );
	ediworman_portability_expect( is_int( $ediworman_copy_id ) && $ediworman_copy_id !== $ediworman_copy_source && get_post( $ediworman_copy_id )->post_title === get_post( $ediworman_copy_source )->post_title . ' (Imported)', 'Title conflict did not create a distinct named copy.' );
	$ediworman_ids[] = $ediworman_copy_id;
	++$ediworman_checks;

	$ediworman_token = EDIWORMAN_Template_Portability::stage( $ediworman_json );
	$ediworman_tokens[] = $ediworman_token;
	wp_set_current_user( 0 );
	ediworman_portability_expect( is_wp_error( EDIWORMAN_Template_Portability::stage( $ediworman_json ) ) && is_wp_error( EDIWORMAN_Template_Portability::confirm( $ediworman_token ) ) && is_wp_error( EDIWORMAN_Template_Portability::export_template( $ediworman_copy_source ) ), 'Unauthorized operation succeeded.' );
	wp_set_current_user( $ediworman_admins[0] );
	global $blog_id;
	$ediworman_blog_id = $blog_id;
	// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test only the site-bound key without switching the real development database.
	$blog_id += 10000;
	try {
		ediworman_portability_expect( is_wp_error( EDIWORMAN_Template_Portability::load_preview( $ediworman_token ) ), 'Token crossed site scope.' );
	} finally {
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore the test's temporary site-ID fixture.
		$blog_id = $ediworman_blog_id;
	}
	++$ediworman_checks;

	// Expiry, claims, provider changes, cancellation, and failure cleanup.
	$ediworman_key = 'ediworman_import_' . get_current_blog_id() . '_' . get_current_user_id() . '_' . $ediworman_token;
	$ediworman_claim = 'ediworman_import_claim_' . hash( 'sha256', $ediworman_key );
	add_option( $ediworman_claim, time(), '', false );
	ediworman_portability_expect( 'ediworman_transfer_busy' === EDIWORMAN_Template_Portability::confirm( $ediworman_token )->get_error_code() && is_wp_error( EDIWORMAN_Template_Portability::cancel( $ediworman_token ) ), 'Concurrent claim was bypassed.' );
	delete_option( $ediworman_claim );
	$ediworman_record = get_transient( $ediworman_key );
	$ediworman_record['fingerprint'] = 'previous-registry';
	set_transient( $ediworman_key, $ediworman_record, 900 );
	ediworman_portability_expect( 'ediworman_transfer_changed' === EDIWORMAN_Template_Portability::confirm( $ediworman_token )->get_error_code(), 'Changed registry did not require a new review.' );
	$ediworman_block_write = static function ( $check, $object_id, $meta_key ) {
		return EDIWORMAN_Automatic_Requirements::META_KEY === $meta_key ? false : $check;
	};
	$ediworman_count_before = wp_count_posts( 'ediworman_template' )->publish;
	add_filter( 'add_post_metadata', $ediworman_block_write, 10, 3 );
	try {
		ediworman_portability_expect( is_wp_error( EDIWORMAN_Template_Portability::confirm( $ediworman_token ) ), 'Persistence failure was ignored.' );
	} finally {
		remove_filter( 'add_post_metadata', $ediworman_block_write, 10 );
	}
	ediworman_portability_expect( wp_count_posts( 'ediworman_template' )->publish === $ediworman_count_before && false === get_option( $ediworman_claim ) && false === get_option( EDIWORMAN_Template_Portability::TITLE_CLAIM ), 'Failed write left a partial template or claim.' );
	$ediworman_record = get_transient( $ediworman_key );
	$ediworman_record['expires'] = time() - 1;
	set_transient( $ediworman_key, $ediworman_record, 900 );
	ediworman_portability_expect( is_wp_error( EDIWORMAN_Template_Portability::confirm( $ediworman_token ) ), 'Expired preview accepted.' );
	ediworman_portability_expect( true === EDIWORMAN_Template_Portability::cancel( $ediworman_token ) && false === get_transient( $ediworman_key ) && false === wp_next_scheduled( EDIWORMAN_Template_Portability::CLEANUP_HOOK, array( $ediworman_key, $ediworman_claim ) ), 'Cancellation did not clean staging/events.' );
	++$ediworman_checks;

	// Deliberate mapping uses the existing readiness pipeline and ignores inactive providers.
	$ediworman_settings = $ediworman_original_settings;
	$ediworman_settings['post_type_templates']['post'] = $ediworman_copy_source;
	$ediworman_settings['publication_modes']['post'] = 'advisory';
	update_option( EDIWORMAN_Settings::OPTION_NAME, $ediworman_settings );
	$ediworman_post = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'Portability readiness fixture', 'post_content' => 'A word' ), true );
	$ediworman_ids[] = $ediworman_post;
	$ediworman_readiness = EDIWORMAN_Readiness::get_readiness_for_post( $ediworman_post, false );
	ediworman_portability_expect( 5 === $ediworman_readiness['required_total'] && 'incomplete' === $ediworman_readiness['readiness'], 'Imported required/optional/rule aggregation changed.' );
	++$ediworman_checks;
	WP_CLI::success( $ediworman_checks . ' portability checks passed.' );
} finally {
	wp_set_current_user( $ediworman_admins[0] );
	foreach ( $ediworman_tokens as $ediworman_token ) {
		EDIWORMAN_Template_Portability::cancel( $ediworman_token );
	}
	update_option( EDIWORMAN_Settings::OPTION_NAME, $ediworman_original_settings );
	foreach ( array_reverse( $ediworman_ids ) as $ediworman_id ) {
		wp_delete_post( $ediworman_id, true );
	}
	wp_set_current_user( $ediworman_user_id );
}
