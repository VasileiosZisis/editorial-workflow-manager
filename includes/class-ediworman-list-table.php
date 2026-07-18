<?php
/**
 * Admin list table readiness column.
 *
 * @package EditorialWorkflowManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds checklist readiness to mapped post type list tables.
 */
class EDIWORMAN_List_Table {

	const READINESS_COLUMN = 'ediworman_readiness';

	/**
	 * Register admin list-table hooks.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'register_column_hooks' ) );
	}

	/**
	 * Register column filters/actions for configurable post types.
	 *
	 * @return void
	 */
	public function register_column_hooks() {
		$post_types = get_post_types(
			array(
				'show_ui' => true,
			),
			'names'
		);

		foreach ( $post_types as $post_type ) {
			$post_type = sanitize_key( $post_type );
			if ( ! EDIWORMAN_Readiness::is_cacheable_post_type( $post_type ) ) {
				continue;
			}

			add_filter(
				'manage_' . $post_type . '_posts_columns',
				function ( $columns ) use ( $post_type ) {
					return $this->filter_columns( $columns, $post_type );
				}
			);

			add_action(
				'manage_' . $post_type . '_posts_custom_column',
				function ( $column_name, $post_id ) use ( $post_type ) {
					$this->render_column( $column_name, $post_id, $post_type );
				},
				10,
				2
			);
		}
	}

	/**
	 * Add the readiness column after title when the post type is mapped.
	 *
	 * @param array  $columns   Existing list table columns.
	 * @param string $post_type Post type slug.
	 * @return array
	 */
	public function filter_columns( $columns, $post_type ) {
		if ( ! is_array( $columns ) || ! EDIWORMAN_Readiness::should_show_column_for_post_type( $post_type ) ) {
			return $columns;
		}

		if ( isset( $columns[ self::READINESS_COLUMN ] ) ) {
			return $columns;
		}

		$readiness_column = array(
			self::READINESS_COLUMN => __( 'Readiness', 'editorial-workflow-manager' ),
		);

		$updated_columns = array();
		$inserted        = false;

		foreach ( $columns as $column_key => $column_label ) {
			$updated_columns[ $column_key ] = $column_label;

			if ( 'title' === $column_key ) {
				$updated_columns = array_merge( $updated_columns, $readiness_column );
				$inserted        = true;
			}
		}

		if ( ! $inserted ) {
			$updated_columns = array_merge( $updated_columns, $readiness_column );
		}

		return $updated_columns;
	}

	/**
	 * Render the readiness cell.
	 *
	 * @param string $column_name Current column name.
	 * @param int    $post_id     Current post ID.
	 * @param string $post_type   List table post type.
	 * @return void
	 */
	public function render_column( $column_name, $post_id, $post_type ) {
		if ( self::READINESS_COLUMN !== $column_name ) {
			return;
		}

		$post_id = absint( $post_id );
		if ( $post_id <= 0 || ! EDIWORMAN_Readiness::should_show_column_for_post_type( $post_type ) ) {
			echo esc_html__( 'No template', 'editorial-workflow-manager' );
			return;
		}

		$readiness = EDIWORMAN_Readiness::get_readiness_for_post( $post_id, true, true );
		if ( null === $readiness ) {
			echo esc_html__( 'No template', 'editorial-workflow-manager' );
			return;
		}

		$readiness_label = EDIWORMAN_Readiness::READINESS_READY === $readiness['readiness']
			? __( 'Ready', 'editorial-workflow-manager' )
			: __( 'Incomplete', 'editorial-workflow-manager' );

		$required_progress = sprintf(
			/* translators: 1: completed required item count, 2: total required item count. */
			__( 'Required %1$d/%2$d', 'editorial-workflow-manager' ),
			(int) $readiness['required_done'],
			(int) $readiness['required_total']
		);

		printf(
			'<strong>%1$s</strong><br><span>%2$s</span>',
			esc_html( $readiness_label ),
			esc_html( $required_progress )
		);

		if ( EDIWORMAN_Readiness::READINESS_INCOMPLETE !== $readiness['readiness'] ) {
			return;
		}

		$missing_labels = EDIWORMAN_Readiness::get_missing_required_item_labels( $post_id );
		if ( empty( $missing_labels ) ) {
			return;
		}
		?>
		<details class="ediworman-missing-items">
			<summary>
				<?php
				printf(
					/* translators: %d: number of missing required checklist items. */
					esc_html__( 'Missing required items (%d)', 'editorial-workflow-manager' ),
					count( $missing_labels )
				);
				?>
			</summary>
			<ul>
				<?php foreach ( $missing_labels as $missing_label ) : ?>
					<li><?php echo esc_html( $missing_label ); ?></li>
				<?php endforeach; ?>
			</ul>
		</details>
		<?php
	}
}
