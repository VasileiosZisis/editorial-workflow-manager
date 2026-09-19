<?php
/**
 * Plugin Name: EDIWORMAN Reading Time Rule Example
 * Description: Example extension for the Editorial Workflow Manager public rule API.
 * Version:     1.0.0
 * Requires PHP: 7.4
 * License:     GPL-2.0-or-later
 * Text Domain: ediworman-reading-time-rule
 *
 * @package EdiwormanReadingTimeRule
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the sample editor script before EWM collects rule definitions.
 *
 * @return void
 */
function ediworman_example_register_script() {
	wp_register_script(
		'ediworman-example-reading-time-rule',
		plugin_dir_url( __FILE__ ) . 'reading-time-rule.js',
		array( 'ediworman-rule-api', 'wp-i18n' ),
		'1.0.0',
		true
	);
}
add_action( 'init', 'ediworman_example_register_script', 1 );

/**
 * Register the sample automatic requirement.
 *
 * @return void
 */
function ediworman_example_register_rule() {
	if ( ! function_exists( 'ediworman_register_rule' ) ) {
		return;
	}

	ediworman_register_rule(
		'example/reading-time',
		array(
			'api_version'       => 1,
			'rule_version'      => '1.0.0',
			'label'             => __( 'At least one minute of reading', 'ediworman-reading-time-rule' ),
			'description'       => __( 'Passes when the post contains at least 200 readable words.', 'ediworman-reading-time-rule' ),
			'post_types'        => array( 'post' ),
			'dependencies'      => array(
				'post_fields' => array( 'post_content' ),
			),
			'evaluate_callback' => 'ediworman_example_evaluate_rule',
			'editor_script'     => 'ediworman-example-reading-time-rule',
		)
	);
}
add_action( 'ediworman_register_rules', 'ediworman_example_register_rule' );

/**
 * Evaluate the sample rule on the server.
 *
 * @param int   $post_id Post ID.
 * @param array $config  Rule configuration.
 * @param array $rule    Registered definition.
 * @return array{status:string,message:string}
 */
function ediworman_example_evaluate_rule( $post_id, $config, $rule ) {
	unset( $config, $rule );
	$post = get_post( $post_id );
	if ( ! $post ) {
		return array( 'status' => 'not_applicable', 'message' => '' );
	}

	$text       = html_entity_decode( wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) ), ENT_QUOTES, get_bloginfo( 'charset' ) );
	$word_count = preg_match_all( "/[\\p{L}\\p{N}]+(?:[\\x{2019}'-][\\p{L}\\p{N}]+)*/u", $text, $matches );
	$word_count = false === $word_count ? 0 : (int) $word_count;
	unset( $matches );

	return array(
		'status'  => $word_count >= 200 ? 'pass' : 'fail',
		'message' => sprintf(
			/* translators: %d: current readable word count. */
			__( '%d readable words detected; 200 are required.', 'ediworman-reading-time-rule' ),
			$word_count
		),
	);
}
