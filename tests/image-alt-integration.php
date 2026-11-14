<?php
/**
 * Run with wp eval-file tests/image-alt-integration.php allow-local-fixtures.
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
 * Assert a test expectation independently of PHP assertion settings.
 *
 * @param bool   $condition Expected condition.
 * @param string $message   Failure description.
 * @throws RuntimeException If the expectation is false.
 */
function ediworman_image_test_expect( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
}

$ediworman_options = get_option( EDIWORMAN_Settings::OPTION_NAME, null );
$ediworman_fingerprint = get_option( EDIWORMAN_Rule_Registry::FINGERPRINT_OPTION, null );
$ediworman_previous_user = get_current_user_id();
$ediworman_admin = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
ediworman_image_test_expect( ! empty( $ediworman_admin ), 'A local administrator is required.' );
wp_set_current_user( $ediworman_admin[0] );
$ediworman_created = array();
$ediworman_override = false;
$ediworman_theme_data = static function ( $data ) use ( &$ediworman_override ) {
	return $data->update_with( array( 'version' => 2, 'settings' => array( 'blocks' => array( 'core/image' => array( 'lightbox' => array( 'enabled' => $ediworman_override ) ) ) ) ) );
};
$ediworman_insert = static function ( $data ) use ( &$ediworman_created ) {
	$id = wp_insert_post( wp_slash( $data ), true );
	ediworman_image_test_expect( is_int( $id ) && $id > 0, 'Fixture creation failed.' );
	$ediworman_created[] = $id;
	return $id;
};

try {
	global $wpdb;
	if ( function_exists( 'block_core_image_get_lightbox_settings' ) ) {
		add_filter( 'wp_theme_json_data_user', $ediworman_theme_data );
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Read a checked-in local fixture.
	$ediworman_fixtures = json_decode( file_get_contents( __DIR__ . '/image-alt-fixtures.json' ), true, 512, JSON_THROW_ON_ERROR );
	foreach ( $ediworman_fixtures as $ediworman_fixture ) {
		$ediworman_override = $ediworman_fixture['lightboxDefault'] ?? false;
		if ( $ediworman_override && ! function_exists( 'block_core_image_get_lightbox_settings' ) ) {
			continue;
		}
		if ( function_exists( 'wp_clean_theme_json_cache' ) ) {
			wp_clean_theme_json_cache();
		}
		$ediworman_images = EDIWORMAN_Image_Alt::collect( $ediworman_fixture['content'] );
		ediworman_image_test_expect( array_map( array( 'EDIWORMAN_Image_Alt', 'status' ), $ediworman_images ) === $ediworman_fixture['statuses'], $ediworman_fixture['name'] );
	}
	$ediworman_override = false;
	if ( function_exists( 'wp_clean_theme_json_cache' ) ) {
		wp_clean_theme_json_cache();
	}

	$ediworman_rule = EDIWORMAN_Automatic_Requirements::IMAGE_ALT_TEXT;
	ediworman_image_test_expect( '1.1.0' === EDIWORMAN_Rule_Registry::get_rule( $ediworman_rule )['rule_version'], 'ALT rule cache version was not updated.' );
	$ediworman_template = $ediworman_insert( array( 'post_type' => 'ediworman_template', 'post_title' => 'Decorative image integration template', 'post_status' => 'publish' ) );
	update_post_meta( $ediworman_template, EDIWORMAN_Automatic_Requirements::META_KEY, array( $ediworman_rule => array( 'enabled' => true ) ) );
	update_option( EDIWORMAN_Settings::OPTION_NAME, array( 'post_type_templates' => array( 'post' => $ediworman_template ), 'publication_modes' => array( 'post' => 'block' ) ) );
	$ediworman_attachment = $ediworman_insert( array( 'post_type' => 'attachment', 'post_title' => 'Decorative image attachment fixture', 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit' ) );
	update_post_meta( $ediworman_attachment, '_wp_attachment_image_alt', 'Description from the Media Library' );
	$ediworman_empty = '<img src="/fixture.jpg" class="wp-image-' . $ediworman_attachment . '" alt="" />';
	$ediworman_decorative = '<!-- wp:image {"ediwormanDecorativeImage":true,"lightbox":{"enabled":false}} -->' . $ediworman_empty . '<!-- /wp:image -->';
	$ediworman_id = $ediworman_insert( array( 'post_title' => 'Decorative image integration draft', 'post_status' => 'draft', 'post_content' => $ediworman_decorative . $ediworman_empty ) );
	update_post_meta( $ediworman_id, '_thumbnail_id', $ediworman_attachment );
	$ediworman_results = EDIWORMAN_Automatic_Requirements::evaluate_post( $ediworman_id );
	ediworman_image_test_expect( 'fail' === $ediworman_results[0]['status'] && false !== strpos( $ediworman_results[0]['message'], 'Content image 2' ) && false === strpos( $ediworman_results[0]['message'], 'Featured image' ), 'Readiness conflated a featured image with a content occurrence.' );
	ediworman_image_test_expect( 'incomplete' === EDIWORMAN_Readiness::get_readiness_for_post( $ediworman_id )['readiness'], 'Incomplete image was not reflected in readiness.' );
	wp_update_post( array( 'ID' => $ediworman_id, 'post_status' => 'publish' ) );
	ediworman_image_test_expect( 'draft' === get_post_status( $ediworman_id ), 'Programmatic Block accepted blank content ALT.' );
	$ediworman_request = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $ediworman_id );
	$ediworman_request->set_body_params( array( 'status' => 'publish', 'content' => $ediworman_empty . ' Preserved REST work.' ) );
	$ediworman_response = rest_do_request( $ediworman_request );
	ediworman_image_test_expect( 409 === $ediworman_response->get_status() && false !== strpos( get_post( $ediworman_id )->post_content, 'Preserved REST work.' ), 'REST Block did not preserve the draft content.' );

	wp_update_post( wp_slash( array( 'ID' => $ediworman_id, 'post_content' => $ediworman_decorative ) ) );
	ediworman_image_test_expect( 'ready' === EDIWORMAN_Readiness::get_readiness_for_post( $ediworman_id )['readiness'], 'Saved designation left stale readiness.' );
	delete_post_meta( $ediworman_attachment, '_wp_attachment_image_alt' );
	ediworman_image_test_expect( 'incomplete' === EDIWORMAN_Readiness::get_readiness_for_post( $ediworman_id )['readiness'], 'Featured image without a Media Library description passed.' );
	update_post_meta( $ediworman_attachment, '_wp_attachment_image_alt', 'Restored Media Library description' );
	wp_update_post( array( 'ID' => $ediworman_id, 'post_status' => 'publish' ) );
	ediworman_image_test_expect( 'publish' === get_post_status( $ediworman_id ), 'A valid decorative image blocked first publication.' );

	$ediworman_publish_date = gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS );
	$ediworman_due_date = gmdate( 'Y-m-d H:i:s', time() - 60 );
	$ediworman_future = $ediworman_insert( array( 'post_title' => 'Decorative image scheduled fixture', 'post_status' => 'future', 'post_date' => $ediworman_publish_date, 'post_date_gmt' => $ediworman_publish_date, 'post_content' => $ediworman_decorative ) );
	ediworman_image_test_expect( 'future' === get_post_status( $ediworman_future ), 'A valid decorative image blocked scheduling.' );
	// Simulate a due fixture which previously passed with attachment ALT alone.
	$wpdb->update( $wpdb->posts, array( 'post_content' => $ediworman_empty, 'post_date' => $ediworman_due_date, 'post_date_gmt' => $ediworman_due_date ), array( 'ID' => $ediworman_future ) );
	clean_post_cache( $ediworman_future );
	do_action( 'publish_future_post', $ediworman_future );
	ediworman_image_test_expect( 'draft' === get_post_status( $ediworman_future ) && get_post( $ediworman_future )->post_content === $ediworman_empty, 'Scheduled Block did not hold the post and retain the stored markup.' );

	if ( function_exists( 'block_core_image_get_lightbox_settings' ) ) {
		$ediworman_inherited = '<!-- wp:image {"isDecorative":true} -->' . $ediworman_empty . '<!-- /wp:image -->';
		$ediworman_setting_post = $ediworman_insert( array( 'post_title' => 'Inherited lightbox readiness fixture', 'post_status' => 'draft', 'post_content' => $ediworman_inherited ) );
		EDIWORMAN_Rule_Registry::maybe_invalidate_registry_cache();
		ediworman_image_test_expect( 'ready' === EDIWORMAN_Readiness::get_readiness_for_post( $ediworman_setting_post )['readiness'], 'An inherited disabled lightbox failed.' );
		$ediworman_override = true;
		wp_clean_theme_json_cache();
		EDIWORMAN_Rule_Registry::maybe_invalidate_registry_cache();
		ediworman_image_test_expect( ! metadata_exists( 'post', $ediworman_setting_post, EDIWORMAN_Readiness::READINESS_CACHE_META ), 'An inherited lightbox change left stored readiness behind.' );
		ediworman_image_test_expect( 'incomplete' === EDIWORMAN_Readiness::get_readiness_for_post( $ediworman_setting_post )['readiness'], 'Inherited enabled lightbox passed.' );
	}
	WP_CLI::success( 'Image ALT fixtures, featured-image readiness, and programmatic, REST, and scheduled publication passed.' );
} finally {
	remove_filter( 'wp_theme_json_data_user', $ediworman_theme_data );
	if ( function_exists( 'wp_clean_theme_json_cache' ) ) {
		wp_clean_theme_json_cache();
	}
	foreach ( array_reverse( $ediworman_created ) as $ediworman_fixture_id ) {
		wp_delete_post( $ediworman_fixture_id, true );
	}
	if ( null === $ediworman_options ) {
		delete_option( EDIWORMAN_Settings::OPTION_NAME );
	} else {
		update_option( EDIWORMAN_Settings::OPTION_NAME, $ediworman_options );
	}
	if ( null === $ediworman_fingerprint ) {
		delete_option( EDIWORMAN_Rule_Registry::FINGERPRINT_OPTION );
	} else {
		update_option( EDIWORMAN_Rule_Registry::FINGERPRINT_OPTION, $ediworman_fingerprint );
	}
	wp_set_current_user( $ediworman_previous_user );
}
