<?php
/**
 * Reversible integration checks: wp eval-file tests/wordpress-integration.php allow-local-fixtures
 *
 * @package EditorialWorkflowManager
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}
$ediworman_host = wp_parse_url( home_url(), PHP_URL_HOST );
if ( ! in_array( 'allow-local-fixtures', $args, true ) || ! preg_match( '/(?:\.local|\.test)$|^localhost$/D', $ediworman_host ) ) {
	WP_CLI::error( 'Run only on a local development site with allow-local-fixtures.' );
}

/**
 * Assert behavior without relying on PHP's optional assert configuration.
 *
 * @param bool   $condition Expected truth.
 * @param string $message  Failure description.
 * @return void
 * @throws RuntimeException When an integration expectation fails.
 */
function ediworman_test_expect( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
}

$ediworman_ids = array();
$ediworman_term_ids = array();
$ediworman_saved_options = array();
foreach ( array_merge( array( EDIWORMAN_Settings::OPTION_NAME ), EDIWORMAN_Readiness::LINK_CONTEXT_OPTIONS ) as $ediworman_option ) {
	$ediworman_saved_options[ $ediworman_option ] = get_option( $ediworman_option, null );
}
$ediworman_original_user = get_current_user_id();
$ediworman_admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
ediworman_test_expect( ! empty( $ediworman_admins ), 'An administrator is required.' );
wp_set_current_user( $ediworman_admins[0] );
$ediworman_checks = 0;
// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Preserve and restore the synthetic CLI request fixture.
$ediworman_original_post = $_POST;

try {
	global $wp_rewrite, $wpdb;
	update_option( 'permalink_structure', '/%postname%/' );
	$wp_rewrite->init();
	update_option( 'show_on_front', 'posts' );
	$ediworman_template = wp_insert_post( array( 'post_type' => 'ediworman_template', 'post_status' => 'publish', 'post_title' => 'Internal links integration fixture' ), true );
	ediworman_test_expect( ! is_wp_error( $ediworman_template ), 'Template creation failed.' );
	$ediworman_ids[] = $ediworman_template;
	$ediworman_rule = EDIWORMAN_Automatic_Requirements::MINIMUM_INTERNAL_LINK_COUNT;
	$ediworman_config = EDIWORMAN_Automatic_Requirements::get_template_config( $ediworman_template );
	ediworman_test_expect( false === $ediworman_config[ $ediworman_rule ]['enabled'] && 1 === $ediworman_config[ $ediworman_rule ]['minimum'], 'New rule defaults changed.' );
	foreach ( array( '0', '-1', '1.5', '100001', 'abc', array(), null ) as $ediworman_invalid ) {
		ediworman_test_expect( 'invalid' === EDIWORMAN_Automatic_Requirements::validate_limit_group( array( $ediworman_rule => array( 'enabled' => true, 'minimum' => $ediworman_invalid ) ), 'internal_links' ), 'Invalid threshold accepted.' );
	}
	foreach ( array( 1, '100000' ) as $ediworman_valid ) {
		ediworman_test_expect( '' === EDIWORMAN_Automatic_Requirements::validate_limit_group( array( $ediworman_rule => array( 'enabled' => true, 'minimum' => $ediworman_valid ) ), 'internal_links' ), 'Valid boundary rejected.' );
	}
	$ediworman_config[ $ediworman_rule ] = array( 'enabled' => true, 'minimum' => 1 );
	$ediworman_config['fixture/unavailable'] = array( 'enabled' => true );
	update_post_meta( $ediworman_template, EDIWORMAN_Automatic_Requirements::META_KEY, $ediworman_config );
	update_option( EDIWORMAN_Settings::OPTION_NAME, array( 'post_type_templates' => array( 'post' => $ediworman_template, 'page' => $ediworman_template ), 'publication_modes' => array( 'post' => 'block', 'page' => 'advisory' ) ) );
	++$ediworman_checks;

	// Prospective draft URLs depend on other rows reserving the same slug.
	$ediworman_collision_slug = 'ediworman-collision-' . wp_generate_password( 8, false, false );
	$ediworman_collision = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_name' => $ediworman_collision_slug, 'post_title' => $ediworman_collision_slug ), true );
	$ediworman_ids[] = $ediworman_collision;
	wp_update_post( array( 'ID' => $ediworman_collision, 'post_content' => '<a href="' . esc_url( EDIWORMAN_Internal_Links::get_post_url( $ediworman_collision ) ) . '">Self</a>' ) );
	ediworman_test_expect( 'incomplete' === EDIWORMAN_Readiness::get_readiness_for_post( $ediworman_collision )['readiness'], 'Initial prospective self link counted.' );
	$ediworman_conflict = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_name' => $ediworman_collision_slug, 'post_title' => $ediworman_collision_slug ), true );
	$ediworman_ids[] = $ediworman_conflict;
	ediworman_test_expect( 'ready' === EDIWORMAN_Readiness::get_readiness_for_post( $ediworman_collision )['readiness'], 'New duplicate draft slug left stale readiness.' );
	wp_delete_post( $ediworman_conflict, true );
	ediworman_test_expect( 'incomplete' === EDIWORMAN_Readiness::get_readiness_for_post( $ediworman_collision )['readiness'], 'Deleted conflicting slug left stale readiness.' );
	++$ediworman_checks;

	// Exercise the actual nonce/capability-gated template save handler.
	$ediworman_handler = ( new ReflectionClass( EDIWORMAN_Templates_CPT::class ) )->newInstanceWithoutConstructor();
	$_POST = array(
		'ediworman_template_items_nonce' => wp_create_nonce( 'ediworman_save_template_items' ),
		'ediworman_template_items_present' => '1',
		'ediworman_automatic_requirements_present' => '1',
		'ediworman_automatic_requirements' => array( $ediworman_rule => array( 'enabled' => '1', 'minimum' => '-5' ), 'excerpt' => array( 'enabled' => '1' ) ),
	);
	$ediworman_handler->save_template_meta( $ediworman_template, get_post( $ediworman_template ) );
	$ediworman_saved = EDIWORMAN_Automatic_Requirements::get_template_config( $ediworman_template );
	ediworman_test_expect( $ediworman_saved[ $ediworman_rule ] === $ediworman_config[ $ediworman_rule ] && $ediworman_saved['excerpt']['enabled'] && $ediworman_saved['fixture/unavailable']['enabled'], 'Invalid template edit lost previous settings or unrelated valid edits.' );
	$_POST['ediworman_automatic_requirements'][ $ediworman_rule ]['minimum'] = '2';
	unset( $_POST['ediworman_template_items_nonce'] );
	$ediworman_handler->save_template_meta( $ediworman_template, get_post( $ediworman_template ) );
	ediworman_test_expect( 1 === EDIWORMAN_Automatic_Requirements::get_template_config( $ediworman_template )[ $ediworman_rule ]['minimum'], 'Missing nonce changed configuration.' );
	wp_set_current_user( 0 );
	$_POST['ediworman_template_items_nonce'] = wp_create_nonce( 'ediworman_save_template_items' );
	$ediworman_handler->save_template_meta( $ediworman_template, get_post( $ediworman_template ) );
	ediworman_test_expect( 1 === EDIWORMAN_Automatic_Requirements::get_template_config( $ediworman_template )[ $ediworman_rule ]['minimum'], 'Unauthorized user changed configuration.' );
	wp_set_current_user( $ediworman_admins[0] );
	$_POST = array();
	update_post_meta( $ediworman_template, EDIWORMAN_Automatic_Requirements::META_KEY, $ediworman_config );
	++$ediworman_checks;

	$ediworman_slug = 'ediworman-link-test-' . wp_generate_password( 8, false, false );
	$ediworman_post = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_name' => $ediworman_slug, 'post_title' => $ediworman_slug, 'post_content' => 'Saved draft body' ), true );
	$ediworman_ids[] = $ediworman_post;
	$ediworman_url = EDIWORMAN_Internal_Links::get_post_url( $ediworman_post );
	ediworman_test_expect( false === strpos( $ediworman_url, '?p=' ) && 'draft' === get_post_status( $ediworman_post ), 'Prospective permalink mutated status or used the draft query URL.' );
	$ediworman_self_html = '<a href="' . esc_url( $ediworman_url ) . '">Self</a>';
	wp_update_post( array( 'ID' => $ediworman_post, 'post_content' => $ediworman_self_html, 'post_status' => 'publish' ) );
	ediworman_test_expect( 'draft' === get_post_status( $ediworman_post ) && get_post( $ediworman_post )->post_content === $ediworman_self_html, 'Self-only publication passed or lost work.' );
	wp_update_post( array( 'ID' => $ediworman_post, 'post_status' => 'private' ) );
	ediworman_test_expect( 'draft' === get_post_status( $ediworman_post ), 'Self-only private publication passed.' );
	$ediworman_result = EDIWORMAN_Readiness::get_readiness_for_post( $ediworman_post, false );
	ediworman_test_expect( 1 === $ediworman_result['required_total'] && 0 === $ediworman_result['required_done'], 'Readiness did not include the link rule or included unavailable rules.' );
	++$ediworman_checks;

	$ediworman_request = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $ediworman_post );
	$ediworman_request->set_body_params( array( 'status' => 'publish', 'content' => $ediworman_self_html . ' REST work' ) );
	$ediworman_response = rest_do_request( $ediworman_request );
	ediworman_test_expect( 409 === $ediworman_response->get_status() && 'draft' === get_post_status( $ediworman_post ) && false !== strpos( get_post( $ediworman_post )->post_content, 'REST work' ), 'REST Block did not preserve work with HTTP 409.' );
	$ediworman_content = '<a href="' . esc_url( home_url( '/ediworman-other-destination/' ) ) . '">Other</a>';
	wp_update_post( array( 'ID' => $ediworman_post, 'post_content' => $ediworman_content, 'post_status' => 'publish' ) );
	ediworman_test_expect( 'publish' === get_post_status( $ediworman_post ), 'Valid first publication failed.' );
	wp_update_post( array( 'ID' => $ediworman_post, 'post_content' => 'Published update remains editable' ) );
	ediworman_test_expect( 'publish' === get_post_status( $ediworman_post ), 'Published updates were blocked.' );
	++$ediworman_checks;

	// Schedule passing content, then make its requirement fail before the due event.
	$ediworman_future = wp_insert_post( array( 'post_type' => 'post', 'post_title' => $ediworman_slug . '-scheduled', 'post_name' => $ediworman_slug . '-scheduled', 'post_status' => 'future', 'post_date' => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ), 'post_date_gmt' => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ), 'post_content' => $ediworman_content ), true );
	$ediworman_ids[] = $ediworman_future;
	ediworman_test_expect( 'future' === get_post_status( $ediworman_future ), 'Valid scheduling failed.' );
	$ediworman_future_self = '<a href="' . esc_url( EDIWORMAN_Internal_Links::get_post_url( $ediworman_future ) ) . '">Self</a>';
	$ediworman_future_url = EDIWORMAN_Internal_Links::get_post_url( $ediworman_future );
	$ediworman_future_conflict = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_name' => get_post( $ediworman_future )->post_name, 'post_title' => 'Scheduled slug conflict fixture' ), true );
	$ediworman_ids[] = $ediworman_future_conflict;
	ediworman_test_expect( EDIWORMAN_Internal_Links::get_post_url( $ediworman_future ) === $ediworman_future_url, 'Scheduled slug ownership changed because of a later draft.' );
	// Change only test-owned rows to simulate a due post whose saved content is no longer valid.
	$wpdb->update( $wpdb->posts, array( 'post_content' => $ediworman_future_self, 'post_date' => gmdate( 'Y-m-d H:i:s', time() - 60 ), 'post_date_gmt' => gmdate( 'Y-m-d H:i:s', time() - 60 ) ), array( 'ID' => $ediworman_future ) );
	clean_post_cache( $ediworman_future );
	do_action( 'publish_future_post', $ediworman_future );
	ediworman_test_expect( 'draft' === get_post_status( $ediworman_future ), 'Due scheduled self-only publication passed.' );
	++$ediworman_checks;

	// Advisory allows publication; private updates remain editable under Block.
	$ediworman_settings = get_option( EDIWORMAN_Settings::OPTION_NAME );
	$ediworman_settings['publication_modes']['post'] = 'advisory';
	update_option( EDIWORMAN_Settings::OPTION_NAME, $ediworman_settings );
	$ediworman_private = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'private', 'post_title' => 'Internal links private fixture', 'post_content' => '' ), true );
	$ediworman_ids[] = $ediworman_private;
	ediworman_test_expect( 'private' === get_post_status( $ediworman_private ), 'Advisory unexpectedly blocked.' );
	$ediworman_settings['publication_modes']['post'] = 'block';
	update_option( EDIWORMAN_Settings::OPTION_NAME, $ediworman_settings );
	wp_update_post( array( 'ID' => $ediworman_private, 'post_content' => 'Private update' ) );
	ediworman_test_expect( 'private' === get_post_status( $ediworman_private ), 'Private updates were blocked.' );
	++$ediworman_checks;

	// Ancestor slug changes must invalidate a child's cached readiness.
	$ediworman_parent = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $ediworman_slug . '-parent', 'post_name' => $ediworman_slug . '-parent' ), true );
	$ediworman_ids[] = $ediworman_parent;
	$ediworman_child = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Child fixture', 'post_name' => 'child', 'post_parent' => $ediworman_parent ), true );
	$ediworman_ids[] = $ediworman_child;
	$ediworman_child_url = EDIWORMAN_Internal_Links::get_post_url( $ediworman_child );
	wp_update_post( array( 'ID' => $ediworman_child, 'post_content' => '<a href="' . esc_url( $ediworman_child_url ) . '">Self before rename</a>' ) );
	ediworman_test_expect( 'incomplete' === EDIWORMAN_Readiness::get_readiness_for_post( $ediworman_child )['readiness'], 'Child self link incorrectly counted.' );
	wp_update_post( array( 'ID' => $ediworman_parent, 'post_name' => $ediworman_slug . '-renamed' ) );
	ediworman_test_expect( 'ready' === EDIWORMAN_Readiness::get_readiness_for_post( $ediworman_child )['readiness'], 'Ancestor rename left stale child readiness.' );
	++$ediworman_checks;

	// A page becoming the homepage turns its root anchor into a self link.
	wp_update_post( array( 'ID' => $ediworman_child, 'post_parent' => 0, 'post_content' => '<a href="' . esc_url( home_url( '/' ) ) . '">Home</a>' ) );
	ediworman_test_expect( 'ready' === EDIWORMAN_Readiness::get_readiness_for_post( $ediworman_child )['readiness'], 'Ordinary page home link failed.' );
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $ediworman_child );
	ediworman_test_expect( 'incomplete' === EDIWORMAN_Readiness::get_readiness_for_post( $ediworman_child )['readiness'], 'Reading settings left stale homepage readiness.' );
	++$ediworman_checks;

	WP_CLI::success( $ediworman_checks . ' integration groups passed (configuration, slug collisions, permissions, publication, REST, scheduled posts, Advisory/private updates, ancestor/front-page invalidation).' );
} finally {
	$_POST = $ediworman_original_post;
	foreach ( $ediworman_saved_options as $ediworman_option => $ediworman_value ) {
		if ( null === $ediworman_value ) {
			delete_option( $ediworman_option );
		} else {
			update_option( $ediworman_option, $ediworman_value );
		}
	}
	$wp_rewrite->init();
	foreach ( array_reverse( $ediworman_ids ) as $ediworman_id ) {
		wp_delete_post( $ediworman_id, true );
	}
	wp_set_current_user( $ediworman_original_user );
}
