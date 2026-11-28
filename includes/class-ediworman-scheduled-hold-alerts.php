<?php
/**
 * Email alerts for confirmed scheduled publication holds.
 *
 * @package EditorialWorkflowManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Send at most one alert per held scheduled occurrence.
 */
final class EDIWORMAN_Scheduled_Hold_Alerts {

	const CLAIM_PREFIX = 'ediworman_scheduled_hold_alert_';

	/**
	 * Send a best-effort alert after the scheduled post has been held as a draft.
	 *
	 * @param WP_Post  $post              Post as read before the hold.
	 * @param int      $scheduled_time    Original scheduled UTC timestamp.
	 * @param string[] $missing           Failure explanations from the publication gate.
	 * @return void
	 */
	public static function send( $post, $scheduled_time, $missing ) {
		$settings = EDIWORMAN_Settings::get_scheduled_hold_alerts();
		if ( ! $settings['enabled'] || ! self::claim( $post->ID, $scheduled_time ) ) {
			return;
		}

		$site = sanitize_text_field( wp_specialchars_decode( get_option( 'blogname', '' ), ENT_QUOTES ) );
		$title = sanitize_text_field( wp_specialchars_decode( $post->post_title, ENT_QUOTES ) );
		if ( '' === $title ) {
			$title = __( '(Untitled post)', 'editorial-workflow-manager' );
		}
		$subject = sprintf(
			/* translators: 1: site name, 2: post title. */
			__( '[%1$s] Scheduled publication held: %2$s', 'editorial-workflow-manager' ),
			$site,
			$title
		);
		$link = add_query_arg( array( 'post' => $post->ID, 'action' => 'edit' ), admin_url( 'post.php' ) );
		$lines = array(
			sprintf(
				/* translators: 1: post title, 2: site name. */
				__( 'The scheduled post “%1$s” on %2$s was held as a draft.', 'editorial-workflow-manager' ),
				$title,
				$site
			),
			'',
		);
		foreach ( $missing as $reason ) {
			$reason = sanitize_text_field( wp_specialchars_decode( $reason, ENT_QUOTES ) );
			if ( '' !== $reason ) {
				$lines[] = '- ' . $reason;
			}
		}
		$lines[] = '';
		$lines[] = __( 'Open the post, fix the issue, and schedule it again.', 'editorial-workflow-manager' );
		$lines[] = $link;

		try {
			wp_mail( $settings['email'], $subject, implode( "\n", $lines ), 'Content-Type: text/plain' );
		} catch ( Throwable $exception ) {
			// A failing mail provider must not interrupt scheduled publication handling.
			return;
		}
	}

	/**
	 * Remember the latest occurrence claimed for this post across cron workers.
	 *
	 * @param int $post_id        Post ID.
	 * @param int $scheduled_time Original scheduled UTC timestamp.
	 * @return bool
	 */
	private static function claim( $post_id, $scheduled_time ) {
		global $wpdb;

		$key = self::CLAIM_PREFIX . $post_id;
		// Options API add_option() uses an upsert. Use the unique option name with an insert-only query.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic claim with option-cache invalidation below.
		$changed = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %d, 'no')", $key, $scheduled_time ) );
		if ( 0 === $changed ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Compare and update atomically so the same occurrence cannot claim twice.
			$changed = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %d WHERE option_name = %s AND CAST(option_value AS UNSIGNED) < %d", $scheduled_time, $key, $scheduled_time ) );
		}
		if ( 1 !== $changed ) {
			return false;
		}
		wp_cache_delete( $key, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		return true;
	}

	/**
	 * Remove the claim when its post is permanently deleted.
	 *
	 * @param int $post_id Deleted post ID.
	 * @return void
	 */
	public static function delete_claim( $post_id ) {
		delete_option( self::CLAIM_PREFIX . $post_id );
	}
}
