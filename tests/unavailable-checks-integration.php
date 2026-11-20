<?php
/**
 * Run with wp eval-file tests/unavailable-checks-integration.php allow-local-fixtures.
 *
 * @package EditorialWorkflowManager
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( 'allow-local-fixtures', $args, true ) || ! preg_match( '/(?:\.local|\.test)$|^localhost$/D', wp_parse_url( home_url(), PHP_URL_HOST ) ) ) {
	exit( 1 );
}

/**
 * Assert an integration expectation independently of PHP assertion settings.
 *
 * @param bool   $condition Expected condition.
 * @param string $message Failure description.
 * @throws RuntimeException If the expectation is false.
 */
function ediworman_unavailable_expect( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
}

$ediworman_settings = get_option( EDIWORMAN_Settings::OPTION_NAME, null );
$ediworman_user = get_current_user_id();
$ediworman_admin = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
ediworman_unavailable_expect( ! empty( $ediworman_admin ), 'A local administrator is required.' );
wp_set_current_user( $ediworman_admin[0] );
$ediworman_created = array();
$ediworman_insert = static function ( $data ) use ( &$ediworman_created ) {
	$id = wp_insert_post( wp_slash( $data ), true );
	ediworman_unavailable_expect( is_int( $id ) && $id > 0, 'Fixture creation failed.' );
	$ediworman_created[] = $id;
	return $id;
};
$ediworman_capture = static function ( $render ) {
	ob_start();
	try {
		$render();
		return ob_get_contents();
	} finally {
		ob_end_clean();
	}
};

try {
	global $wpdb;
	$ediworman_fixture = array(
		'fixture/missing' => array( 'enabled' => true ),
		'fixture/returning' => array( 'enabled' => true ),
		'fixture/disabled' => array( 'enabled' => false ),
		'fixture/page-only' => array( 'enabled' => true ),
		'fixture/bad name' => array( 'enabled' => true ),
		"fixture/trailing\n" => array( 'enabled' => true ),
	);
	$ediworman_definition = array(
		'api_version' => 1,
		'label' => 'Fixture check',
		'evaluate_callback' => static function () {
			return array( 'status' => 'fail', 'message' => 'Fixture check failed.' );
		},
	);
	ediworman_unavailable_expect( true === ediworman_register_rule( 'fixture/page-only', array_merge( $ediworman_definition, array( 'post_types' => array( 'page' ) ) ) ), 'Fixture registration failed.' );
	$ediworman_template = $ediworman_insert( array( 'post_title' => 'Unavailable checks integration template', 'post_type' => 'ediworman_template', 'post_status' => 'publish' ) );
	update_post_meta( $ediworman_template, EDIWORMAN_Automatic_Requirements::META_KEY, $ediworman_fixture );
	$ediworman_mapping = array( 'post_type_templates' => array( 'post' => $ediworman_template ), 'publication_modes' => array( 'post' => 'block' ) );
	update_option( EDIWORMAN_Settings::OPTION_NAME, $ediworman_mapping );

	$ediworman_config = EDIWORMAN_Automatic_Requirements::get_template_config( $ediworman_template );
	$ediworman_missing = EDIWORMAN_Automatic_Requirements::get_unavailable_custom_rule_ids( $ediworman_config );
	$ediworman_plural = '2 configured checks are unavailable and were not evaluated.';
	ediworman_unavailable_expect( array( 'fixture/missing', 'fixture/returning' ) === $ediworman_missing, 'Advisory included disabled, malformed, or registered checks.' );
	ediworman_unavailable_expect( EDIWORMAN_Automatic_Requirements::get_unavailable_custom_checks_message( $ediworman_missing ) === $ediworman_plural, 'Plural advisory is incorrect.' );
	ediworman_unavailable_expect( '' === EDIWORMAN_Automatic_Requirements::get_unavailable_custom_checks_message( array() ), 'Unexpected zero-check advisory.' );

	$ediworman_draft = $ediworman_insert( array( 'post_title' => 'Unavailable checks integration draft', 'post_status' => 'draft' ) );
	$ediworman_readiness = EDIWORMAN_Readiness::evaluate_readiness_for_post( $ediworman_draft );
	ediworman_unavailable_expect( 'ready' === $ediworman_readiness['readiness'] && 0 === $ediworman_readiness['required_total'] && array() === $ediworman_readiness['missing_required_labels'], 'Unavailable checks contributed to readiness.' );
	$ediworman_endpoint = new WP_REST_Request( 'GET', '/editorial-workflow-manager/v1/posts/' . $ediworman_draft . '/rules' );
	$ediworman_saved = rest_do_request( $ediworman_endpoint )->get_data();
	ediworman_unavailable_expect( array( 'schemaVersion', 'postId', 'results' ) === array_keys( $ediworman_saved ) && array() === $ediworman_saved['results'], 'The REST results contract changed.' );

	$ediworman_output = $ediworman_capture(
		static function () use ( $ediworman_draft ) {
			( new EDIWORMAN_List_Table() )->render_column( EDIWORMAN_List_Table::READINESS_COLUMN, $ediworman_draft, 'post' );
			( new EDIWORMAN_Manager_Visibility() )->render_dashboard_widget();
		}
	);
	ediworman_unavailable_expect( 2 === substr_count( $ediworman_output, $ediworman_plural ) && false !== strpos( $ediworman_output, '<strong>Ready</strong>' ), 'List or dashboard omitted the advisory.' );
	$ediworman_output = $ediworman_capture(
		static function () use ( $ediworman_template ) {
			( new EDIWORMAN_Templates_CPT() )->render_items_metabox( get_post( $ediworman_template ) );
		}
	);
	ediworman_unavailable_expect( false !== strpos( $ediworman_output, '<code>fixture/missing</code>' ) && false === strpos( $ediworman_output, 'fixture/bad name' ), 'The template editor did not show the expected IDs.' );

	$ediworman_rest_update = static function ( $id ) {
		$request = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $id );
		$request->set_body_params( array( 'status' => 'publish', 'content' => 'Fixture work' ) );
		return rest_do_request( $request );
	};
	ediworman_unavailable_expect( 200 === $ediworman_rest_update( $ediworman_draft )->get_status() && 'publish' === get_post_status( $ediworman_draft ), 'Unavailable checks blocked REST first publication.' );
	$ediworman_direct = $ediworman_insert( array( 'post_title' => 'Unavailable checks direct fixture', 'post_status' => 'publish' ) );
	ediworman_unavailable_expect( 'publish' === get_post_status( $ediworman_direct ), 'Unavailable checks blocked programmatic publication.' );
	$ediworman_schedule = static function () use ( $ediworman_insert ) {
		$date = gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS );
		return $ediworman_insert( array( 'post_title' => 'Unavailable checks scheduled fixture', 'post_status' => 'future', 'post_date' => $date, 'post_date_gmt' => $date ) );
	};
	$ediworman_due = static function ( $id ) use ( $wpdb ) {
		$date = gmdate( 'Y-m-d H:i:s', time() - MINUTE_IN_SECONDS );
		// Set the due time on a test-owned row without invoking publication early.
		$wpdb->update( $wpdb->posts, array( 'post_date' => $date, 'post_date_gmt' => $date ), array( 'ID' => $id ) );
		clean_post_cache( $id );
		do_action( 'publish_future_post', $id );
	};
	$ediworman_first_future = $ediworman_schedule();
	$ediworman_second_future = $ediworman_schedule();
	ediworman_unavailable_expect( 'future' === get_post_status( $ediworman_first_future ) && 'future' === get_post_status( $ediworman_second_future ), 'Unavailable checks blocked scheduling.' );
	$ediworman_due( $ediworman_first_future );
	ediworman_unavailable_expect( 'publish' === get_post_status( $ediworman_first_future ), 'Unavailable checks held a due scheduled post.' );

	ediworman_unavailable_expect( true === ediworman_register_rule( 'fixture/returning', $ediworman_definition ), 'Returning provider registration failed.' );
	$ediworman_missing = EDIWORMAN_Automatic_Requirements::get_unavailable_custom_rule_ids( $ediworman_config );
	ediworman_unavailable_expect( '1 configured check is unavailable and was not evaluated.' === EDIWORMAN_Automatic_Requirements::get_unavailable_custom_checks_message( $ediworman_missing ), 'Returning provider did not clear its advisory.' );
	$ediworman_due( $ediworman_second_future );
	ediworman_unavailable_expect( 'draft' === get_post_status( $ediworman_second_future ), 'A failing registered rule did not hold a due scheduled post.' );
	$ediworman_failed = $ediworman_insert( array( 'post_title' => 'Registered failing check fixture', 'post_status' => 'draft' ) );
	ediworman_unavailable_expect( 409 === $ediworman_rest_update( $ediworman_failed )->get_status() && 'draft' === get_post_status( $ediworman_failed ) && 'Fixture work' === get_post( $ediworman_failed )->post_content, 'A registered failure did not hold REST publication and preserve work.' );
	$ediworman_mapping['publication_modes']['post'] = 'advisory';
	update_option( EDIWORMAN_Settings::OPTION_NAME, $ediworman_mapping );
	ediworman_unavailable_expect( 200 === $ediworman_rest_update( $ediworman_failed )->get_status() && 'publish' === get_post_status( $ediworman_failed ), 'Advisory held publication.' );
	WP_CLI::success( 'Unavailable check classification, admin surfaces, REST contract, reactivation, and publication passed.' );
} finally {
	foreach ( array_reverse( $ediworman_created ) as $ediworman_id ) {
		wp_delete_post( $ediworman_id, true );
	}
	if ( null === $ediworman_settings ) {
		delete_option( EDIWORMAN_Settings::OPTION_NAME );
	} else {
		update_option( EDIWORMAN_Settings::OPTION_NAME, $ediworman_settings );
	}
	wp_set_current_user( $ediworman_user );
}
