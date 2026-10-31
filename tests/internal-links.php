<?php
/**
 * Standalone fixtures for the production PHP counter; no WordPress required.
 *
 * @package EditorialWorkflowManager
 */

define( 'ABSPATH', __DIR__ );
require dirname( __DIR__ ) . '/includes/class-ediworman-internal-links.php';

// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local fixtures; standalone without WordPress.
$ediworman_fixtures = json_decode( file_get_contents( __DIR__ . '/internal-links-fixtures.json' ), true, 512, JSON_THROW_ON_ERROR );
$ediworman_results = array();
foreach ( $ediworman_fixtures as $ediworman_fixture ) {
	try {
		$ediworman_count = EDIWORMAN_Internal_Links::count( $ediworman_fixture['content'], $ediworman_fixture['home'] ?? 'https://example.test/site/', $ediworman_fixture['post'] ?? 'https://example.test/site/posts/current/' );
		if ( ! empty( $ediworman_fixture['error'] ) || $ediworman_count !== $ediworman_fixture['expected'] ) {
			throw new LogicException( $ediworman_fixture['name'] . ': unexpected count ' . $ediworman_count );
		}
		$ediworman_results[] = $ediworman_count;
	} catch ( RuntimeException $ediworman_error ) {
		if ( empty( $ediworman_fixture['error'] ) ) {
			throw $ediworman_error;
		}
		$ediworman_results[] = 'error';
	}
}
if ( in_array( '--json', $argv, true ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Standalone runner without WordPress.
	echo json_encode( array( 'counts' => $ediworman_results, 'entities' => EDIWORMAN_Internal_Links::get_entities() ), JSON_THROW_ON_ERROR );
} else {
	echo count( $ediworman_results ) . " PHP internal-link fixtures passed.\n";
}
