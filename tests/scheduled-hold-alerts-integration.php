<?php
/**
 * Run with wp eval-file tests/scheduled-hold-alerts-integration.php allow-local-fixtures.
 *
 * @package EditorialWorkflowManager
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( 'allow-local-fixtures', $args, true ) || ! preg_match( '/(?:\.local|\.test)$|^localhost$/D', wp_parse_url( home_url(), PHP_URL_HOST ) ) ) {
	exit( 1 );
}

/**
 * Check an integration expectation.
 *
 * @param bool   $condition Expected condition.
 * @param string $message   Failure description.
 * @return void
 * @throws RuntimeException If the condition fails.
 */
function ediworman_alert_test_expect( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
}

$ediworman_saved_settings = get_option( EDIWORMAN_Settings::OPTION_NAME, null );
$ediworman_saved_user = get_current_user_id();
$ediworman_created = array();
$ediworman_messages = array();
$ediworman_mail_result = true;
$ediworman_mail_hook = static function ( $result, $message ) use ( &$ediworman_messages, &$ediworman_mail_result ) {
	$ediworman_messages[] = $message;
	return $ediworman_mail_result;
};
add_filter( 'pre_wp_mail', $ediworman_mail_hook, PHP_INT_MAX, 2 );

$ediworman_insert = static function ( $data ) use ( &$ediworman_created ) {
	$id = wp_insert_post( wp_slash( $data ), true );
	ediworman_alert_test_expect( is_int( $id ) && $id > 0, 'Fixture creation failed.' );
	$ediworman_created[] = $id;
	return $id;
};

try {
	global $wpdb;
	wp_set_current_user( 0 );
	$ediworman_template = $ediworman_insert( array( 'post_type' => 'ediworman_template', 'post_title' => 'Scheduled alert template fixture', 'post_status' => 'publish' ) );
	update_post_meta( $ediworman_template, EDIWORMAN_Automatic_Requirements::META_KEY, array( 'excerpt' => array( 'enabled' => true ) ) );
	$ediworman_settings = array( 'post_type_templates' => array( 'post' => $ediworman_template ), 'publication_modes' => array( 'post' => 'block' ) );
	update_option( EDIWORMAN_Settings::OPTION_NAME, $ediworman_settings );
	ediworman_alert_test_expect( array( 'enabled' => false, 'email' => '' ) === EDIWORMAN_Settings::get_scheduled_hold_alerts(), 'Alerts must start disabled on existing settings.' );
	$ediworman_recipient = 'editorial-qa@example.test';
	$ediworman_alert_config = array( 'enabled' => true, 'email' => $ediworman_recipient );
	$ediworman_settings_form = ( new ReflectionClass( EDIWORMAN_Settings::class ) )->newInstanceWithoutConstructor();
	$ediworman_valid = $ediworman_settings_form->sanitize_settings( $ediworman_settings + array( 'scheduled_hold_alerts' => array( 'enabled' => '1', 'email' => $ediworman_recipient ) ) );
	ediworman_alert_test_expect( $ediworman_valid['scheduled_hold_alerts'] === $ediworman_alert_config, 'A valid address could not be enabled.' );

	$ediworman_future_date = gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS );
	$ediworman_new_future = static function () use ( $ediworman_insert, $ediworman_future_date ) {
		return $ediworman_insert(
			array(
				'post_title' => 'Scheduled alert fixture',
				'post_content' => 'Unpublished draft body fixture',
				'post_excerpt' => 'An excerpt that passes when initially scheduled.',
				'post_status' => 'future',
				'post_date' => $ediworman_future_date,
				'post_date_gmt' => $ediworman_future_date,
			)
		);
	};
	$ediworman_timestamp = time() - HOUR_IN_SECONDS;
	$ediworman_apply_schedule = static function ( $id, $timestamp, $excerpt = '' ) use ( $wpdb ) {
		$date = gmdate( 'Y-m-d H:i:s', $timestamp );
		// Simulate due publication using only test-owned rows.
		$wpdb->update( $wpdb->posts, array( 'post_status' => 'future', 'post_date' => $date, 'post_date_gmt' => $date, 'post_excerpt' => $excerpt ), array( 'ID' => $id ) );
		clean_post_cache( $id );
		do_action( 'publish_future_post', $id );
	};

	$ediworman_off = $ediworman_new_future();
	$ediworman_apply_schedule( $ediworman_off, $ediworman_timestamp );
	ediworman_alert_test_expect( 'draft' === get_post_status( $ediworman_off ) && array() === $ediworman_messages, 'A due hold sent mail while disabled or did not hold the post.' );
	update_option( EDIWORMAN_Settings::OPTION_NAME, $ediworman_valid );

	$ediworman_invalid = $ediworman_settings_form->sanitize_settings( $ediworman_settings + array( 'scheduled_hold_alerts' => array( 'enabled' => '1', 'email' => "wrong@example.test\r\nBcc: another@example.test" ) ) );
	ediworman_alert_test_expect( $ediworman_invalid['scheduled_hold_alerts'] === $ediworman_alert_config, 'An invalid address replaced the saved recipient.' );
	$ediworman_missing = $ediworman_settings_form->sanitize_settings( $ediworman_settings );
	ediworman_alert_test_expect( $ediworman_missing['scheduled_hold_alerts'] === $ediworman_alert_config, 'An unrelated settings save reset alerts.' );
	$ediworman_disabled = $ediworman_settings_form->sanitize_settings( $ediworman_settings + array( 'scheduled_hold_alerts' => array( 'email' => $ediworman_recipient ) ) );
	ediworman_alert_test_expect( false === $ediworman_disabled['scheduled_hold_alerts']['enabled'], 'Clearing the checkbox did not disable alerts.' );

	$ediworman_initial = $ediworman_insert( array( 'post_title' => 'Never scheduled fixture', 'post_status' => 'future', 'post_date' => $ediworman_future_date, 'post_date_gmt' => $ediworman_future_date ) );
	$ediworman_direct = $ediworman_insert( array( 'post_title' => 'Manual publication fixture', 'post_status' => 'publish' ) );
	ediworman_alert_test_expect( 'draft' === get_post_status( $ediworman_initial ) && 'draft' === get_post_status( $ediworman_direct ) && array() === $ediworman_messages, 'An initial schedule or manual publish failure triggered an alert.' );

	$ediworman_rejected = $ediworman_new_future();
	$ediworman_due_date = gmdate( 'Y-m-d H:i:s', $ediworman_timestamp );
	$wpdb->update( $wpdb->posts, array( 'post_date' => $ediworman_due_date, 'post_date_gmt' => $ediworman_due_date, 'post_excerpt' => '' ), array( 'ID' => $ediworman_rejected ) );
	clean_post_cache( $ediworman_rejected );
	$ediworman_reject_draft = static function ( $is_empty, $post ) use ( $ediworman_rejected ) {
		return $is_empty || ( ( $post['ID'] ?? 0 ) === $ediworman_rejected && 'draft' === $post['post_status'] );
	};
	add_filter( 'wp_insert_post_empty_content', $ediworman_reject_draft, 10, 2 );
	try {
		EDIWORMAN_Publication_Policy::check_scheduled_post( $ediworman_rejected );
		ediworman_alert_test_expect( 'future' === get_post_status( $ediworman_rejected ) && array() === $ediworman_messages, 'An unconfirmed draft transition attempted an alert.' );
	} finally {
		remove_filter( 'wp_insert_post_empty_content', $ediworman_reject_draft, 10 );
	}

	$ediworman_post = $ediworman_new_future();
	do_action( 'publish_future_post', $ediworman_post );
	ediworman_alert_test_expect( 'future' === get_post_status( $ediworman_post ) && array() === $ediworman_messages, 'An early callback sent mail or held the post.' );
	$ediworman_apply_schedule( $ediworman_post, $ediworman_timestamp );
	ediworman_alert_test_expect( 'draft' === get_post_status( $ediworman_post ) && 1 === count( $ediworman_messages ), 'Confirmed hold did not attempt exactly one alert.' );
	$ediworman_message = $ediworman_messages[0];
	$ediworman_link = add_query_arg( array( 'post' => $ediworman_post, 'action' => 'edit' ), admin_url( 'post.php' ) );
	ediworman_alert_test_expect( array( $ediworman_recipient ) === (array) $ediworman_message['to'] && false !== strpos( $ediworman_message['subject'], 'Scheduled alert fixture' ) && in_array( 'Content-Type: text/plain', (array) $ediworman_message['headers'], true ), 'Mail had the wrong recipient, subject, or content type.' );
	ediworman_alert_test_expect( false !== strpos( $ediworman_message['message'], 'excerpt' ) && false !== strpos( $ediworman_message['message'], $ediworman_link ) && false === strpos( $ediworman_message['message'], 'Unpublished draft body fixture' ), 'Mail lacked recovery guidance or included the draft body.' );
	do_action( 'publish_future_post', $ediworman_post );
	// An overlapping worker still holding the same scheduled occurrence must lose the claim.
	$ediworman_apply_schedule( $ediworman_post, $ediworman_timestamp );
	ediworman_alert_test_expect( 1 === count( $ediworman_messages ), 'Repeated occurrence attempted more than one alert.' );
	wp_update_post( array( 'ID' => $ediworman_post, 'post_status' => 'future', 'post_excerpt' => 'Fixed and rescheduled.', 'post_date' => $ediworman_future_date, 'post_date_gmt' => $ediworman_future_date ) );
	ediworman_alert_test_expect( 'future' === get_post_status( $ediworman_post ), 'A recovered post could not be scheduled again.' );
	$ediworman_mail_result = false;
	$ediworman_apply_schedule( $ediworman_post, $ediworman_timestamp + 60 );
	ediworman_alert_test_expect( 'draft' === get_post_status( $ediworman_post ) && 2 === count( $ediworman_messages ) && ! empty( EDIWORMAN_Publication_Policy::get_blocked_details( $ediworman_post )['scheduled'] ), 'A later occurrence missed its alert or mail failure affected recovery.' );
	$ediworman_apply_schedule( $ediworman_post, $ediworman_timestamp + 60 );
	ediworman_alert_test_expect( 2 === count( $ediworman_messages ), 'Mail failure retried the same occurrence.' );
	$ediworman_passing = $ediworman_new_future();
	$ediworman_apply_schedule( $ediworman_passing, $ediworman_timestamp, 'Requirements still pass.' );
	ediworman_alert_test_expect( 'publish' === get_post_status( $ediworman_passing ) && 2 === count( $ediworman_messages ), 'A passing Block post was held or alerted.' );

	$ediworman_no_template = $ediworman_new_future();
	$ediworman_advisory = $ediworman_new_future();
	$ediworman_valid['post_type_templates'] = array();
	update_option( EDIWORMAN_Settings::OPTION_NAME, $ediworman_valid );
	$ediworman_apply_schedule( $ediworman_no_template, $ediworman_timestamp );
	ediworman_alert_test_expect( 'draft' === get_post_status( $ediworman_no_template ) && 3 === count( $ediworman_messages ) && false !== strpos( $ediworman_messages[2]['message'], 'assign a valid checklist template' ), 'Missing-template alert did not contain recovery guidance.' );
	$ediworman_valid['publication_modes']['post'] = 'advisory';
	update_option( EDIWORMAN_Settings::OPTION_NAME, $ediworman_valid );
	$ediworman_apply_schedule( $ediworman_advisory, $ediworman_timestamp );
	ediworman_alert_test_expect( 'publish' === get_post_status( $ediworman_advisory ) && 3 === count( $ediworman_messages ), 'Advisory sent mail or held publication.' );

	$ediworman_claim = EDIWORMAN_Scheduled_Hold_Alerts::CLAIM_PREFIX . $ediworman_post;
	ediworman_alert_test_expect( (string) ( $ediworman_timestamp + 60 ) === get_option( $ediworman_claim ), 'The deduplication claim did not track the latest held occurrence.' );
	wp_delete_post( $ediworman_post, true );
	ediworman_alert_test_expect( false === get_option( $ediworman_claim ), 'Deleting a post left its private alert claim.' );
	WP_CLI::success( 'Alert settings, scheduled holds, mail content, deduplication, and unaffected publication paths passed.' );
} finally {
	remove_filter( 'pre_wp_mail', $ediworman_mail_hook, PHP_INT_MAX );
	foreach ( array_reverse( $ediworman_created ) as $ediworman_id ) {
		wp_delete_post( $ediworman_id, true );
	}
	if ( null === $ediworman_saved_settings ) {
		delete_option( EDIWORMAN_Settings::OPTION_NAME );
	} else {
		update_option( EDIWORMAN_Settings::OPTION_NAME, $ediworman_saved_settings );
	}
	wp_set_current_user( $ediworman_saved_user );
}
