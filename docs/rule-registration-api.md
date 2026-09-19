# Automatic Requirement Rule API

Editorial Workflow Manager 1.2.0 lets a separate plugin add automatic requirements without modifying the free plugin. PHP evaluation is authoritative. An optional JavaScript evaluator gives authors provisional live feedback while editing.

## Registration lifecycle

Attach registration to `ediworman_register_rules`. Register any editor script before priority 5 on `init`; the script is enqueued only when its rule is enabled for the mapped template.

```php
add_action( 'init', 'acme_register_rule_script', 1 );
add_action( 'ediworman_register_rules', 'acme_register_editorial_rule' );
```

Rule IDs must use lowercase `vendor/rule-name` form. Built-in IDs are reserved. `ediworman_register_rule()` returns `true` or `WP_Error`; extensions should not terminate the request when registration is rejected.

## PHP definition

```php
$result = ediworman_register_rule(
	'acme/reading-time',
	array(
		'api_version'       => 1,
		'rule_version'      => '1.0.0',
		'label'             => __( 'Reading time available', 'acme-extension' ),
		'description'       => __( 'Checks whether the article has readable content.', 'acme-extension' ),
		'post_types'        => array( 'post' ),
		'dependencies'      => array(
			'post_fields'    => array( 'post_content' ),
			'post_meta'      => array(),
			'taxonomies'     => array(),
			'attachment_alt' => false,
		),
		'evaluate_callback' => 'acme_evaluate_reading_time',
		'editor_script'     => 'acme-ediworman-reading-time',
	)
);
```

`post_types` may be omitted or empty to support every mapped post type. Supported `post_fields` values are `post_title`, `post_content`, `post_excerpt`, `post_date`, `post_author`, `post_status`, and `featured_media`. Declare every post-meta key and taxonomy that can change the result. Set `attachment_alt` when attachment alternative text affects evaluation.

The server callback receives `( int $post_id, array $config, array $rule )` and returns:

```php
array(
	'status'  => 'pass', // pass, fail, or not_applicable.
	'message' => __( 'Readable content detected.', 'acme-extension' ),
)
```

An exception or malformed result becomes a safe failed requirement. Diagnostic integrations can listen to `ediworman_rule_evaluation_error`; exception details are never shown to authors.

## JavaScript evaluator

Register the editor script with `ediworman-rule-api` as a dependency, then register the same rule ID:

```js
window.EDIWORMAN_RULES.register('acme/reading-time', {
  evaluate(context) {
    const passed = context.content.trim().length > 0;
    return {
      status: passed ? 'pass' : 'fail',
      message: passed ? 'Readable content detected.' : 'Add readable content.',
    };
  },
});
```

The context contains the rule definition and configuration, current post and meta objects, content, excerpt, featured-media ID, detected images, taxonomy terms, and the Gutenberg `select` function. Evaluation must be synchronous and return `pass`, `fail`, or `not_applicable` with a plain-text message.

Rules without JavaScript use their last authoritative saved result and display a save-to-refresh explanation. After a successful editor save, the plugin refreshes those results through its permission-protected REST endpoint.

## Configuration, caching, and compatibility

- Every registered rule receives one enable checkbox per checklist template and is disabled by default.
- Enabled automatic rules are required and cannot be checked manually.
- Unknown namespaced configuration is preserved when a provider plugin is inactive.
- Increase `rule_version` whenever evaluation semantics or dependencies change. The registry fingerprint invalidates aggregate readiness caches.
- Call `ediworman_invalidate_post_readiness( $post_id )` after an undeclared per-post external dependency changes.
- Call `ediworman_invalidate_rule_readiness( $rule_id )` after shared external state affecting all uses of a rule changes.
- API version 1 has no custom template fields, asynchronous browser evaluators, remote execution, telemetry, or publication enforcement.

See [`examples/ediworman-reading-time-rule`](examples/ediworman-reading-time-rule/) for a complete standalone sample plugin.
