<?php
/**
 * Manager readiness filters, recalculation tools, and dashboard summary.
 *
 * @package EditorialWorkflowManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds manager-focused readiness visibility to wp-admin.
 */
class EDIWORMAN_Manager_Visibility {

	const FILTER_QUERY_ARG = 'ediworman_readiness';
	const FILTER_READY     = 'ready';
	const FILTER_INCOMPLETE = 'incomplete';
	const FILTER_UNCALCULATED = 'uncalculated';

	const BULK_ACTION = 'ediworman_recalculate_readiness';
	const AJAX_ACTION = 'ediworman_recalculate_readiness';
	const NONCE_ACTION = 'ediworman_recalculate_readiness';
	const BATCH_SIZE   = 50;

	/**
	 * Register admin-only manager visibility hooks.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'register_post_type_hooks' ) );
		add_action( 'restrict_manage_posts', array( $this, 'render_readiness_controls' ), 10, 2 );
		add_action( 'pre_get_posts', array( $this, 'filter_admin_post_query' ) );
		add_action( 'admin_notices', array( $this, 'render_bulk_recalculation_notice' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'register_dashboard_widget' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'handle_ajax_recalculation' ) );
	}

	/**
	 * Register native bulk-action hooks for mapped post types.
	 *
	 * @return void
	 */
	public function register_post_type_hooks() {
		foreach ( $this->get_mapped_post_types() as $post_type ) {
			$screen_id = 'edit-' . $post_type;

			add_filter(
				'bulk_actions-' . $screen_id,
				function ( $actions ) use ( $post_type ) {
					return $this->filter_bulk_actions( $actions, $post_type );
				}
			);

			add_filter(
				'handle_bulk_actions-' . $screen_id,
				function ( $sendback, $action, $post_ids ) use ( $post_type ) {
					return $this->handle_bulk_action( $sendback, $action, $post_ids, $post_type );
				},
				10,
				3
			);
		}
	}

	/**
	 * Add readiness recalculation to a mapped post type's bulk actions.
	 *
	 * @param array<string, string> $actions   Existing actions.
	 * @param string                $post_type Post type slug.
	 * @return array<string, string>
	 */
	public function filter_bulk_actions( $actions, $post_type ) {
		$post_type_object = get_post_type_object( $post_type );
		if ( ! $post_type_object || ! current_user_can( $post_type_object->cap->edit_posts ) ) {
			return $actions;
		}

		$actions[ self::BULK_ACTION ] = __( 'Recalculate readiness', 'editorial-workflow-manager' );
		return $actions;
	}

	/**
	 * Recalculate selected posts through WordPress's nonce-protected bulk flow.
	 *
	 * @param string             $sendback  Redirect URL.
	 * @param string             $action    Selected bulk action.
	 * @param array<int, string> $post_ids  Selected post IDs.
	 * @param string             $post_type Post type slug.
	 * @return string
	 */
	public function handle_bulk_action( $sendback, $action, $post_ids, $post_type ) {
		if ( self::BULK_ACTION !== $action ) {
			return $sendback;
		}

		check_admin_referer( 'bulk-posts' );

		$recalculated = 0;
		$skipped      = 0;
		$post_ids     = is_array( $post_ids ) ? array_filter( $post_ids, 'is_scalar' ) : array();
		$post_ids     = array_values( array_unique( array_map( 'absint', $post_ids ) ) );

		foreach ( $post_ids as $post_id ) {
			if ( 0 >= $post_id || get_post_type( $post_id ) !== $post_type || ! current_user_can( 'edit_post', $post_id ) ) {
				++$skipped;
				continue;
			}

			if ( null === EDIWORMAN_Readiness::refresh_cache_for_post( $post_id ) ) {
				++$skipped;
				continue;
			}

			++$recalculated;
		}

		return add_query_arg(
			array(
				'ediworman_recalculated'        => $recalculated,
				'ediworman_recalculation_skipped' => $skipped,
			),
			$sendback
		);
	}

	/**
	 * Render the readiness filter and all-post recalculation control.
	 *
	 * @param string $post_type Current post type.
	 * @param string $which     Top or bottom table navigation.
	 * @return void
	 */
	public function render_readiness_controls( $post_type, $which ) {
		$post_type = sanitize_key( $post_type );
		if ( 'top' !== $which || ! $this->is_mapped_post_type( $post_type ) ) {
			return;
		}

		$selected = $this->get_requested_readiness_filter();
		?>
		<label class="screen-reader-text" for="ediworman-readiness-filter">
			<?php esc_html_e( 'Filter by editorial readiness', 'editorial-workflow-manager' ); ?>
		</label>
		<select id="ediworman-readiness-filter" name="<?php echo esc_attr( self::FILTER_QUERY_ARG ); ?>">
			<option value=""><?php esc_html_e( 'All readiness', 'editorial-workflow-manager' ); ?></option>
			<option value="<?php echo esc_attr( self::FILTER_READY ); ?>" <?php selected( self::FILTER_READY, $selected ); ?>>
				<?php esc_html_e( 'Ready', 'editorial-workflow-manager' ); ?>
			</option>
			<option value="<?php echo esc_attr( self::FILTER_INCOMPLETE ); ?>" <?php selected( self::FILTER_INCOMPLETE, $selected ); ?>>
				<?php esc_html_e( 'Incomplete', 'editorial-workflow-manager' ); ?>
			</option>
			<option value="<?php echo esc_attr( self::FILTER_UNCALCULATED ); ?>" <?php selected( self::FILTER_UNCALCULATED, $selected ); ?>>
				<?php esc_html_e( 'Not calculated', 'editorial-workflow-manager' ); ?>
			</option>
		</select>
		<?php if ( $this->current_user_can_manage_post_type( $post_type ) ) : ?>
			<button type="button" class="button" id="ediworman-recalculate-all">
				<?php esc_html_e( 'Recalculate all readiness', 'editorial-workflow-manager' ); ?>
			</button>
			<span id="ediworman-recalculation-status" class="ediworman-recalculation-status" role="status" aria-live="polite"></span>
		<?php endif; ?>
		<?php
	}

	/**
	 * Apply an exact cache-state filter to the main post-list query.
	 *
	 * @param WP_Query $query Current query.
	 * @return void
	 */
	public function filter_admin_post_query( $query ) {
		global $pagenow;

		if ( ! is_admin() || 'edit.php' !== $pagenow || ! $query instanceof WP_Query || ! $query->is_main_query() ) {
			return;
		}

		$post_type = $query->get( 'post_type' );
		$post_type = is_string( $post_type ) && '' !== $post_type ? sanitize_key( $post_type ) : 'post';
		if ( ! $this->is_mapped_post_type( $post_type ) ) {
			return;
		}

		$filter = $this->get_requested_readiness_filter();
		if ( '' === $filter ) {
			return;
		}

		$readiness_clause = $this->get_readiness_meta_clause( $filter );
		if ( empty( $readiness_clause ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin-only exact filtering over the plugin's existing bounded readiness-cache keys.
		$existing_meta_query = $query->get( 'meta_query' );
		if ( ! is_array( $existing_meta_query ) || empty( $existing_meta_query ) ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin-only exact filtering over the plugin's existing bounded readiness-cache keys.
			$query->set( 'meta_query', $readiness_clause );
			return;
		}

		$query->set(
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Preserves third-party meta filters while adding the bounded readiness-cache clause.
			'meta_query',
			array(
				'relation' => 'AND',
				$existing_meta_query,
				$readiness_clause,
			)
		);
	}

	/**
	 * Render a notice after selected-post recalculation.
	 *
	 * @return void
	 */
	public function render_bulk_recalculation_notice() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'edit' !== $screen->base || empty( $screen->post_type ) || ! $this->is_mapped_post_type( $screen->post_type ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only result count from the nonce-protected core bulk action.
		$has_recalculated = isset( $_GET['ediworman_recalculated'] );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only result count from the nonce-protected core bulk action.
		$recalculated_value = $has_recalculated ? sanitize_text_field( wp_unslash( $_GET['ediworman_recalculated'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only result count from the nonce-protected core bulk action.
		$skipped_value = isset( $_GET['ediworman_recalculation_skipped'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only result count from the nonce-protected core bulk action.
			? sanitize_text_field( wp_unslash( $_GET['ediworman_recalculation_skipped'] ) )
			: '0';

		if ( ! $has_recalculated || ! ctype_digit( $recalculated_value ) ) {
			return;
		}

		$recalculated = absint( $recalculated_value );
		$skipped      = ctype_digit( $skipped_value ) ? absint( $skipped_value ) : 0;

		$notice_message = sprintf(
			/* translators: 1: recalculated post count, 2: skipped post count. */
			_n(
				'Readiness recalculated for %1$d post. Skipped: %2$d.',
				'Readiness recalculated for %1$d posts. Skipped: %2$d.',
				$recalculated,
				'editorial-workflow-manager'
			),
			$recalculated,
			$skipped
		);
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html( $notice_message ); ?></p>
		</div>
		<?php
	}

	/**
	 * Enqueue list-table styles and the scoped all-post recalculation script.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		if ( 'index.php' === $hook_suffix ) {
			if ( ! empty( $this->get_dashboard_post_types() ) ) {
				$this->enqueue_styles();
			}
			return;
		}

		if ( 'edit.php' !== $hook_suffix || ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || empty( $screen->post_type ) || ! $this->is_mapped_post_type( $screen->post_type ) ) {
			return;
		}

		$this->enqueue_styles();

		if ( ! $this->current_user_can_manage_post_type( $screen->post_type ) ) {
			return;
		}

		wp_enqueue_script(
			'ediworman-manager-visibility',
			EDIWORMAN_URL . 'assets/js/manager-visibility.js',
			array(),
			EDIWORMAN_VERSION,
			true
		);

		wp_localize_script(
			'ediworman-manager-visibility',
			'EDIWORMAN_MANAGER_VISIBILITY',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'action'   => self::AJAX_ACTION,
				'nonce'    => wp_create_nonce( self::NONCE_ACTION ),
				'postType' => sanitize_key( $screen->post_type ),
				'i18n'     => array(
					/* translators: 1: processed post count, 2: skipped post count. */
					'running' => __( 'Recalculating readiness: %1$d processed, %2$d skipped.', 'editorial-workflow-manager' ),
					'complete' => __( 'Readiness recalculation complete. Reloading the post list.', 'editorial-workflow-manager' ),
					/* translators: 1: processed post count, 2: skipped post count. */
					'error' => __( 'Readiness recalculation stopped after %1$d processed and %2$d skipped. Select the button to resume.', 'editorial-workflow-manager' ),
					'resume' => __( 'Resume readiness recalculation', 'editorial-workflow-manager' ),
				),
			)
		);
	}

	/**
	 * Register the Editorial Readiness dashboard widget for managers.
	 *
	 * @return void
	 */
	public function register_dashboard_widget() {
		if ( empty( $this->get_dashboard_post_types() ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'ediworman_editorial_readiness',
			__( 'Editorial Readiness', 'editorial-workflow-manager' ),
			array( $this, 'render_dashboard_widget' ),
			null,
			null,
			'normal',
			'core'
		);
	}

	/**
	 * Render cached readiness counts for mapped post types.
	 *
	 * @return void
	 */
	public function render_dashboard_widget() {
		$post_types = $this->get_dashboard_post_types();
		if ( empty( $post_types ) ) {
			return;
		}
		?>
		<p><?php esc_html_e( 'Review editorial readiness across mapped content. Not calculated posts can be refreshed from their post list.', 'editorial-workflow-manager' ); ?></p>
		<table class="widefat striped ediworman-readiness-summary">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Content', 'editorial-workflow-manager' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Ready', 'editorial-workflow-manager' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Incomplete', 'editorial-workflow-manager' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Not calculated', 'editorial-workflow-manager' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $post_types as $post_type => $post_type_object ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $post_type_object->labels->name ); ?></th>
						<?php foreach ( array( self::FILTER_READY, self::FILTER_INCOMPLETE, self::FILTER_UNCALCULATED ) as $filter ) : ?>
							<?php $count = $this->count_posts_for_filter( $post_type, $filter ); ?>
							<td>
								<a href="<?php echo esc_url( $this->get_filtered_list_url( $post_type, $filter ) ); ?>">
									<?php echo esc_html( number_format_i18n( $count ) ); ?>
								</a>
							</td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Process one nonce-protected all-post recalculation batch.
	 *
	 * @return void
	 */
	public function handle_ajax_recalculation() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		$post_type = isset( $_POST['post_type'] )
			? sanitize_key( sanitize_text_field( wp_unslash( $_POST['post_type'] ) ) )
			: '';
		$post_type_obj = get_post_type_object( $post_type );

		if ( ! $post_type_obj || empty( $post_type_obj->cap->edit_others_posts ) || ! current_user_can( $post_type_obj->cap->edit_others_posts ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You are not allowed to recalculate this post type.', 'editorial-workflow-manager' ) ), 403 );
		}

		if ( ! $this->is_mapped_post_type( $post_type ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'This post type does not have a checklist template.', 'editorial-workflow-manager' ) ), 400 );
		}

		$cursor_value = isset( $_POST['cursor'] )
			? sanitize_text_field( wp_unslash( $_POST['cursor'] ) )
			: '0';

		if ( ! ctype_digit( $cursor_value ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid recalculation cursor.', 'editorial-workflow-manager' ) ), 400 );
		}

		$cursor   = absint( $cursor_value );
		$post_ids = $this->get_recalculation_batch( $post_type, $cursor );
		$processed = 0;
		$skipped   = 0;

		foreach ( $post_ids as $post_id ) {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				++$skipped;
				continue;
			}

			if ( null === EDIWORMAN_Readiness::refresh_cache_for_post( $post_id ) ) {
				++$skipped;
				continue;
			}

			++$processed;
		}

		$next_cursor = empty( $post_ids ) ? $cursor : max( $post_ids );
		wp_send_json_success(
			array(
				'processed'  => $processed,
				'skipped'    => $skipped,
				'nextCursor' => $next_cursor,
				'done'       => count( $post_ids ) < self::BATCH_SIZE,
			)
		);
	}

	/**
	 * Enqueue shared manager-visibility styles.
	 *
	 * @return void
	 */
	private function enqueue_styles() {
		wp_enqueue_style(
			'ediworman-manager-visibility',
			EDIWORMAN_URL . 'assets/css/manager-visibility.css',
			array(),
			EDIWORMAN_VERSION
		);
	}

	/**
	 * Return all show-ui post types with an active checklist mapping.
	 *
	 * @return array<int, string>
	 */
	private function get_mapped_post_types() {
		$post_types = get_post_types( array( 'show_ui' => true ), 'names' );
		$mapped     = array();

		foreach ( $post_types as $post_type ) {
			$post_type = sanitize_key( $post_type );
			if ( $this->is_mapped_post_type( $post_type ) ) {
				$mapped[] = $post_type;
			}
		}

		return $mapped;
	}

	/**
	 * Return mapped post types the current user can manage for dashboard display.
	 *
	 * @return array<string, WP_Post_Type>
	 */
	private function get_dashboard_post_types() {
		$post_types = array();

		foreach ( $this->get_mapped_post_types() as $post_type ) {
			$post_type_object = get_post_type_object( $post_type );
			if ( $post_type_object && $this->current_user_can_manage_post_type( $post_type ) ) {
				$post_types[ $post_type ] = $post_type_object;
			}
		}

		return $post_types;
	}

	/**
	 * Return whether the current user can edit other users' posts of a type.
	 *
	 * @param string $post_type Post type slug.
	 * @return bool
	 */
	private function current_user_can_manage_post_type( $post_type ) {
		$post_type_object = get_post_type_object( sanitize_key( $post_type ) );
		return $post_type_object && ! empty( $post_type_object->cap->edit_others_posts ) && current_user_can( $post_type_object->cap->edit_others_posts );
	}

	/**
	 * Return whether a post type is cacheable and mapped to a live template.
	 *
	 * @param string $post_type Post type slug.
	 * @return bool
	 */
	private function is_mapped_post_type( $post_type ) {
		$post_type = sanitize_key( $post_type );
		return '' !== $post_type && EDIWORMAN_Readiness::should_show_column_for_post_type( $post_type );
	}

	/**
	 * Return the sanitized requested readiness filter.
	 *
	 * @return string
	 */
	private function get_requested_readiness_filter() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list-table filter.
		$value = isset( $_GET[ self::FILTER_QUERY_ARG ] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list-table filter.
			? sanitize_key( sanitize_text_field( wp_unslash( $_GET[ self::FILTER_QUERY_ARG ] ) ) )
			: '';
		return in_array( $value, array( self::FILTER_READY, self::FILTER_INCOMPLETE, self::FILTER_UNCALCULATED ), true ) ? $value : '';
	}

	/**
	 * Return a WP_Meta_Query clause for a readiness filter.
	 *
	 * @param string $filter Readiness filter.
	 * @return array
	 */
	private function get_readiness_meta_clause( $filter ) {
		if ( in_array( $filter, array( self::FILTER_READY, self::FILTER_INCOMPLETE ), true ) ) {
			return array(
				'relation' => 'AND',
				array(
					'key'     => EDIWORMAN_Readiness::READINESS_CACHE_META,
					'value'   => $filter,
					'compare' => '=',
				),
				array(
					'key'     => EDIWORMAN_Readiness::REQUIRED_TOTAL_CACHE_META,
					'compare' => 'EXISTS',
				),
				array(
					'key'     => EDIWORMAN_Readiness::REQUIRED_DONE_CACHE_META,
					'compare' => 'EXISTS',
				),
			);
		}

		if ( self::FILTER_UNCALCULATED !== $filter ) {
			return array();
		}

		return array(
			'relation' => 'OR',
			array(
				'key'     => EDIWORMAN_Readiness::READINESS_CACHE_META,
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => EDIWORMAN_Readiness::READINESS_CACHE_META,
				'value'   => array( EDIWORMAN_Readiness::READINESS_READY, EDIWORMAN_Readiness::READINESS_INCOMPLETE ),
				'compare' => 'NOT IN',
			),
			array(
				'key'     => EDIWORMAN_Readiness::REQUIRED_TOTAL_CACHE_META,
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => EDIWORMAN_Readiness::REQUIRED_DONE_CACHE_META,
				'compare' => 'NOT EXISTS',
			),
		);
	}

	/**
	 * Return post statuses included in WordPress's admin All view.
	 *
	 * @param string $post_type Post type slug.
	 * @return array<int, string>
	 */
	private function get_admin_visible_post_statuses( $post_type ) {
		$statuses = get_post_stati( array( 'show_in_admin_all_list' => true ), 'names' );
		$statuses = array_values( array_diff( array_map( 'sanitize_key', $statuses ), array( 'trash', 'auto-draft', 'inherit' ) ) );
		if ( empty( $statuses ) ) {
			$statuses = array( 'publish', 'future', 'draft', 'pending', 'private' );
		}

		$post_type_object = get_post_type_object( sanitize_key( $post_type ) );

		if (
			in_array( 'private', $statuses, true ) &&
			$post_type_object &&
			! empty( $post_type_object->cap->read_private_posts ) &&
			! current_user_can( $post_type_object->cap->read_private_posts )
		) {
			$statuses = array_values( array_diff( $statuses, array( 'private' ) ) );
		}

		return $statuses;
	}

	/**
	 * Return one ID-cursor batch for all-post recalculation.
	 *
	 * @param string $post_type Post type slug.
	 * @param int    $cursor    Last processed post ID.
	 * @return array<int, int>
	 */
	private function get_recalculation_batch( $post_type, $cursor ) {
		global $wpdb;

		$cursor_filter = static function ( $where, $query ) use ( $cursor, $wpdb ) {
			if ( ! $query instanceof WP_Query || true !== $query->get( 'ediworman_recalculation_query' ) ) {
				return $where;
			}

			return $where . $wpdb->prepare( " AND {$wpdb->posts}.ID > %d", $cursor );
		};

		add_filter( 'posts_where', $cursor_filter, 10, 2 );
		$post_ids = get_posts(
			array(
				'post_type'              => $post_type,
				'post_status'            => $this->get_admin_visible_post_statuses( $post_type ),
				'posts_per_page'         => self::BATCH_SIZE,
				'fields'                 => 'ids',
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'suppress_filters'       => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'ediworman_recalculation_query' => true,
			)
		);
		remove_filter( 'posts_where', $cursor_filter, 10 );

		return array_values( array_filter( array_map( 'absint', $post_ids ) ) );
	}

	/**
	 * Count posts for one exact readiness filter.
	 *
	 * @param string $post_type Post type slug.
	 * @param string $filter    Readiness filter.
	 * @return int
	 */
	private function count_posts_for_filter( $post_type, $filter ) {
		$query = new WP_Query(
			array(
				'post_type'              => $post_type,
				'post_status'            => $this->get_admin_visible_post_statuses( $post_type ),
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => false,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Dashboard counts query the plugin's bounded readiness-cache keys only on wp-admin.
				'meta_query'             => $this->get_readiness_meta_clause( $filter ),
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		return absint( $query->found_posts );
	}

	/**
	 * Build a filtered post-list URL.
	 *
	 * @param string $post_type Post type slug.
	 * @param string $filter    Readiness filter.
	 * @return string
	 */
	private function get_filtered_list_url( $post_type, $filter ) {
		return add_query_arg(
			array(
				'post_type'              => sanitize_key( $post_type ),
				self::FILTER_QUERY_ARG => sanitize_key( $filter ),
			),
			admin_url( 'edit.php' )
		);
	}
}
