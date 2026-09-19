<?php
/**
 * Public automatic-requirement API functions.
 *
 * @package EditorialWorkflowManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register a code-defined automatic requirement.
 *
 * Register from the `ediworman_register_rules` action.
 *
 * @since 1.2.0
 *
 * @param string $rule_id Namespaced rule ID in vendor/rule-name form.
 * @param array  $args    Rule definition.
 * @return true|WP_Error
 */
function ediworman_register_rule( $rule_id, $args ) {
	return EDIWORMAN_Rule_Registry::register_rule( $rule_id, $args );
}

/**
 * Refresh readiness for one post after an external dependency changes.
 *
 * @since 1.2.0
 *
 * @param int $post_id Post ID.
 * @return array|null
 */
function ediworman_invalidate_post_readiness( $post_id ) {
	return EDIWORMAN_Readiness::refresh_cache_for_post( absint( $post_id ) );
}

/**
 * Invalidate mapped readiness caches for templates using one rule.
 *
 * @since 1.2.0
 *
 * @param string $rule_id Registered rule ID.
 * @return bool|WP_Error
 */
function ediworman_invalidate_rule_readiness( $rule_id ) {
	$rule_id = is_string( $rule_id ) ? trim( $rule_id ) : '';
	if ( ! EDIWORMAN_Rule_Registry::get_rule( $rule_id ) ) {
		return new WP_Error( 'ediworman_rule_not_registered', __( 'The automatic requirement is not registered.', 'editorial-workflow-manager' ) );
	}

	EDIWORMAN_Readiness::invalidate_caches_for_rule( $rule_id );
	return true;
}
