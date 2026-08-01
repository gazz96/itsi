<?php
/**
 * Theme Builder — Admin Page
 *
 * Submenu "Theme Builder" di bawah parent menu "ITSI".
 * Menampilkan:
 *   1. Tree View semua template di theme (root + template-parts)
 *   2. Condition Matrix: template × post_types/taxonomies/conditions
 *   3. Missing Template Detector: CPT/tax tanpa template-nya
 *   4. Assignment Editor: map role/CPT/term → template (CRUD via theme_mod)
 *
 * Front-end template override di-handle oleh ITSI_Theme_Builder_Assignment::resolve_template()
 * yang di-hook ke `template_include` di functions.php.
 *
 * @package itsi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once get_template_directory() . '/inc/class-theme-builder-scanner.php';
require_once get_template_directory() . '/inc/class-theme-builder-assignment.php';

/**
 * Daftarkan submenu "Theme Builder" di bawah menu "ITSI".
 *
 * @return void
 */
function itsi_register_theme_builder_menu() {
	add_submenu_page(
		'itsi-settings',
		__( 'Theme Builder', 'itsi' ),
		__( 'Theme Builder', 'itsi' ),
		'manage_options',
		'itsi-theme-builder',
		'itsi_render_theme_builder_page',
		3
	);
}
add_action( 'admin_menu', 'itsi_register_theme_builder_menu' );

/**
 * Handle POST save untuk assignment editor.
 *
 * Hook: admin_post_itsi_tb_save
 *
 * @return void
 */
function itsi_handle_tb_save() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Anda tidak memiliki akses.', 'itsi' ) );
	}
	check_admin_referer( 'itsi_tb_save', '_itsi_tb_nonce' );

	$assignment = new ITSI_Theme_Builder_Assignment();
	$updated = $assignment->save_all( wp_unslash( $_POST ) );

	wp_safe_redirect(
		add_query_arg(
			array(
				'page'         => 'itsi-theme-builder',
				'itsi_tb_saved' => '1',
			),
			admin_url( 'admin.php' )
		)
	);
	exit;
}
add_action( 'admin_post_itsi_tb_save', 'itsi_handle_tb_save' );

/**
 * Enqueue admin assets khusus halaman Theme Builder.
 *
 * @param string $hook_suffix
 * @return void
 */
function itsi_theme_builder_admin_assets( $hook_suffix ) {
	// Hanya load di halaman ini, jangan polusi admin lain.
	if ( 'itsi_page_itsi-theme-builder' !== $hook_suffix ) {
		return;
	}
	// Cache-bust CSS version so Cloudflare (and browser) don't serve stale assets
	// after we patch the file. Using filemtime() gives a new query string every
	// time the file changes — bypasses CF max-age=14400 cache that would otherwise
	// serve old CSS for up to 4 hours after we patch.
	$css_ver = (int) filemtime( get_template_directory() . '/assets/css/theme-builder.css' );
	wp_enqueue_style(
		'itsi-theme-builder',
		get_template_directory_uri() . '/assets/css/theme-builder.css',
		array(),
		$css_ver
	);
	wp_enqueue_script(
		'itsi-theme-builder',
		get_template_directory_uri() . '/js/theme-builder.js',
		array(),
		$css_ver,
		true
	);
}
add_action( 'admin_enqueue_scripts', 'itsi_theme_builder_admin_assets' );

/**
 * Render halaman Theme Builder.
 *
 * @return void
 */
function itsi_render_theme_builder_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Anda tidak memiliki akses ke halaman ini.', 'itsi' ) );
	}

	$scanner     = new ITSI_Theme_Builder_Scanner();
	$assignment  = new ITSI_Theme_Builder_Assignment();
	$templates   = $scanner->scan();
	$scopes      = $scanner->group_by_scope( $templates );
	$missing     = $scanner->detect_missing();
	$all_assigns = $assignment->get_all();

	$saved = isset( $_GET['itsi_tb_saved'] ) && '1' === $_GET['itsi_tb_saved']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'tree';
	if ( ! in_array( $tab, array( 'tree', 'conditions', 'missing', 'assignments' ), true ) ) {
		$tab = 'tree';
	}

	// View state (Tree View tab only) — passed via URL params, no user persistence.
	// view  = 'grid' (default, card grid) — Tree view removed; only grid remains.
	// size  = 'large' (default) | 'medium' | 'small' — controls node card density
	// level = '' (all) | scope_key — filter tree by top-level scope
	$view  = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'grid';
	if ( ! in_array( $view, array( 'grid' ), true ) ) {
		$view = 'grid';
	}
	$size  = isset( $_GET['size'] ) ? sanitize_key( wp_unslash( $_GET['size'] ) ) : 'large';
	if ( ! in_array( $size, array( 'large', 'medium', 'small' ), true ) ) {
		$size = 'large';
	}
	$level = isset( $_GET['level'] ) ? sanitize_key( wp_unslash( $_GET['level'] ) ) : '';
	if ( ! empty( $level ) && ! isset( $scopes[ $level ] ) ) {
		$level = '';
	}

	// Count totals for tab badges.
	$total_templates = count( $templates );

	?>
	<div class="wrap itsi-tb-wrap" data-view="<?php echo esc_attr( $view ); ?>" data-size="<?php echo esc_attr( $size ); ?>">
		<div class="itsi-tb-header">
			<h1 class="itsi-tb-h1">
				<span class="dashicons dashicons-admin-customizer" aria-hidden="true"></span>
				<?php esc_html_e( 'Theme Builder', 'itsi' ); ?>
			</h1>
			<p class="itsi-tb-sub">
				<?php esc_html_e( 'Create custom templates for pages, posts, headers, footers, etc., and set flexible display conditions for each.', 'itsi' ); ?>
			</p>
			<a href="<?php echo esc_url( admin_url( 'theme-editor.php' ) ); ?>" class="button itsi-tb-import-btn">
				<span class="dashicons dashicons-download" aria-hidden="true"></span>
				<?php esc_html_e( 'Import page template', 'itsi' ); ?>
			</a>
		</div>

		<?php if ( $saved ) : ?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Assignment tersimpan.', 'itsi' ); ?></p>
			</div>
		<?php endif; ?>

		<nav class="nav-tab-wrapper itsi-tb-tabs" aria-label="<?php esc_attr_e( 'Theme Builder Sections', 'itsi' ); ?>">
			<?php
			$tabs = array(
				'tree'        => array( __( 'Tree View', 'itsi' ), 'dashicons-networking' ),
				'conditions'  => array( __( 'Conditions', 'itsi' ), 'dashicons-filter' ),
				'missing'     => array( __( 'Missing', 'itsi' ), 'dashicons-warning' ),
				'assignments' => array( __( 'Assignments', 'itsi' ), 'dashicons-admin-settings' ),
			);
			foreach ( $tabs as $key => $data ) :
				$url = add_query_arg( array( 'page' => 'itsi-theme-builder', 'tab' => $key ), admin_url( 'admin.php' ) );
				$is_active = ( $key === $tab );
				?>
				<a href="<?php echo esc_url( $url ); ?>" class="nav-tab <?php echo $is_active ? 'nav-tab-active' : ''; ?>">
					<span class="dashicons <?php echo esc_attr( $data[1] ); ?>" aria-hidden="true"></span>
					<?php echo esc_html( $data[0] ); ?>
					<?php if ( 'missing' === $key && count( $missing ) > 0 ) : ?>
						<span class="itsi-tb-badge itsi-tb-badge-warn"><?php echo (int) count( $missing ); ?></span>
					<?php elseif ( 'tree' === $key ) : ?>
						<span class="itsi-tb-badge"><?php echo (int) $total_templates; ?></span>
					<?php endif; ?>
				</a>
			<?php endforeach; ?>
		</nav>

		<?php if ( 'tree' === $tab ) : ?>
			<?php itsi_tb_render_controls_bar( $view, $size, $level, $scopes ); ?>
			<?php
			// Apply level filter for both views.
			$filtered_scopes = empty( $level ) ? $scopes : ( isset( $scopes[ $level ] ) ? array( $level => $scopes[ $level ] ) : array() );
			// Tree view removed — Grid view is the only option.
			itsi_tb_render_grid( $filtered_scopes );
			?>
		<?php elseif ( 'conditions' === $tab ) : ?>
			<?php itsi_tb_render_conditions( $templates ); ?>
		<?php elseif ( 'missing' === $tab ) : ?>
			<?php itsi_tb_render_missing( $missing, $templates ); ?>
		<?php elseif ( 'assignments' === $tab ) : ?>
			<?php itsi_tb_render_assignments( $assignment ); ?>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Render filter controls bar di atas tree (view toggle + size + level filter).
 */
function itsi_tb_render_controls_bar( $view, $size, $level, $scopes ) {
	$base_url = add_query_arg(
		array( 'page' => 'itsi-theme-builder', 'tab' => 'tree' ),
		admin_url( 'admin.php' )
	);
	?>
	<div class="itsi-tb-controls">
		<div class="itsi-tb-control-group">
			<label for="itsi-tb-level-filter" class="itsi-tb-control-label">
				<?php esc_html_e( 'Filter by tree levels', 'itsi' ); ?>
			</label>
			<select id="itsi-tb-level-filter" class="itsi-tb-level-filter" data-base-url="<?php echo esc_attr( $base_url ); ?>">
				<option value=""><?php esc_html_e( '— All levels —', 'itsi' ); ?></option>
				<?php foreach ( $scopes as $key => $data ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $level, $key ); ?>>
						<?php echo esc_html( $data['label'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="itsi-tb-control-group itsi-tb-control-right">
			<div class="itsi-tb-size-toggle" role="group" aria-label="<?php esc_attr_e( 'View size', 'itsi' ); ?>">
				<?php
				$sizes = array(
					'large'  => __( 'Large', 'itsi' ),
					'medium' => __( 'Medium', 'itsi' ),
					'small'  => __( 'Small', 'itsi' ),
				);
				foreach ( $sizes as $key => $label ) :
					$url = add_query_arg( array_merge( $_GET, array( 'size' => $key ) ), admin_url( 'admin.php' ) );
					$is_active = ( $key === $size );
					?>
					<a href="<?php echo esc_url( wp_nonce_url( $url, 'itsi_tb_view' ) ); ?>"
					   class="itsi-tb-size-btn <?php echo $is_active ? 'is-active' : ''; ?>"
					   data-size="<?php echo esc_attr( $key ); ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</div>

			<div class="itsi-tb-view-toggle" role="group" aria-label="<?php esc_attr_e( 'View mode', 'itsi' ); ?>">
				<span class="itsi-tb-view-btn is-active" data-view="grid" aria-current="true">
					<span class="dashicons dashicons-grid-view" aria-hidden="true"></span>
					<?php esc_html_e( 'Grid view', 'itsi' ); ?>
				</span>
			</div>
		</div>
	</div>
	<?php
}

/**
 * TAB 1: Tree View (Crocoblock JetThemeCore-style).
 *
 * Horizontal-flow layout dengan:
 *   - Color-coded scope groups (green/orange/blue)
 *   - Solid colored banners per scope group
 *   - Dashed-border template nodes per scope color
 *   - SVG connecting lines + circular junctions (parent → child)
 *   - Add (+) button per template node → trigger template editor
 *
 * Layout algorithm:
 *   - Scope groups flow vertically (one row per scope)
 *   - Within a scope, templates flow horizontally with connecting lines
 *   - Single template: standalone node, no connecting line
 *   - Multiple templates: connecting line runs left-to-right with circles
 */
function itsi_tb_render_tree( $scopes, $size = 'large' ) {
	if ( empty( $scopes ) ) {
		echo '<div class="itsi-tb-empty-itsi"><span class="dashicons dashicons-info"></span><p>';
		esc_html_e( 'Tidak ada template dalam scope yang dipilih.', 'itsi' );
		echo '</p></div>';
		return;
	}

	// Sort scopes by intended visual order (matches Crocoblock).
	$scope_order = array(
		'entire_site',
		'all_archives',
		'blog_posts',
		'taxonomies',
		'singular_page',
		'cpt_singular',
		'cpt_archive',
	);
	$ordered = array();
	foreach ( $scope_order as $key ) {
		if ( isset( $scopes[ $key ] ) ) {
			$ordered[ $key ] = $scopes[ $key ];
		}
	}
	// Append any unexpected scopes at end.
	foreach ( $scopes as $key => $data ) {
		if ( ! isset( $ordered[ $key ] ) ) {
			$ordered[ $key ] = $data;
		}
	}

	// Layout: Crocoblock JetThemeCore-style hierarchy.
		//   - Spine row: 5+ scope columns arranged horizontally left-to-right.
		//   - Each scope column: items stacked VERTICALLY (vs old horizontal row).
		//   - Singular Page hangs BELOW Entire Site as vertical branch.
		//   - SVG connector circles between adjacent spine columns.
		//   - Nested depth chain visible inside Singular Page column (Page → Child Page).
		?>
		<div class="itsi-tb-tree-canvas itsi-tb-size-<?php echo esc_attr( $size ); ?>" role="tree" aria-label="<?php esc_attr_e( 'Template tree', 'itsi' ); ?>">

			<?php
			// ── Spine columns ─────────────────────────────────────────────────────
			// Crocoblock-style: scopes laid out horizontally as a row, each scope
			// is a vertical column of dashed-border cards.
			$spine_keys  = array( 'entire_site', 'all_archives', 'blog_posts', 'taxonomies', 'cpt_singular' );
			$branch_keys = array( 'singular_page' ); // hangs below Entire Site
			$spine_scopes  = array();
			$branch_scopes = array();
			foreach ( $spine_keys as $k ) {
				if ( isset( $ordered[ $k ] ) ) {
					$spine_scopes[ $k ] = $ordered[ $k ];
				}
			}
			foreach ( $branch_keys as $k ) {
				if ( isset( $ordered[ $k ] ) ) {
					$branch_scopes[ $k ] = $ordered[ $k ];
				}
			}
			// Any leftover scopes (cpt_archive, etc.) get appended after the spine.
			foreach ( $ordered as $k => $data ) {
				if ( ! isset( $spine_scopes[ $k ] ) && ! isset( $branch_scopes[ $k ] ) ) {
					$spine_scopes[ $k ] = $data;
				}
			}
			?>

			<?php if ( ! empty( $spine_scopes ) ) : ?>
			<div class="itsi-tb-spine" role="presentation">
				<?php
				$spine_count = count( $spine_scopes );
				$spine_idx   = 0;
				foreach ( $spine_scopes as $scope_key => $scope ) :
					++$spine_idx;
					$color    = $scope['color'];
					$count    = count( $scope['items'] );
					$has_next = ( $spine_idx < $spine_count );
					?>
					<section class="itsi-tb-scope itsi-tb-scope-<?php echo esc_attr( $color ); ?>" role="group">
						<header class="itsi-tb-scope-hdr">
							<span class="itsi-tb-scope-banner"><?php echo esc_html( $scope['label'] ); ?></span>
							<span class="itsi-tb-scope-count"><?php echo (int) $count; ?></span>
						</header>

						<div class="itsi-tb-scope-items">
							<?php if ( empty( $scope['items'] ) ) : ?>
								<div class="itsi-tb-item itsi-tb-item-placeholder">
									<span class="itsi-tb-item-name">—</span>
								</div>
							<?php else : ?>
								<?php foreach ( $scope['items'] as $idx => $tpl ) :
									$is_first = ( 0 === $idx );
									$is_last  = ( $idx === $count - 1 );
									itsi_tb_render_node_vertical( $tpl, $color, $is_first, $is_last, 0 );
								endforeach; ?>
							<?php endif; ?>
						</div>

						<?php if ( $has_next ) : ?>
							<span class="itsi-tb-scope-connector" aria-hidden="true">
								<span class="itsi-tb-connector-circle"></span>
							</span>
						<?php endif; ?>
					</section>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>

			<?php if ( ! empty( $branch_scopes ) ) : ?>
			<div class="itsi-tb-branch" role="presentation">
				<?php foreach ( $branch_scopes as $scope_key => $scope ) :
					$color = $scope['color'];
					$count = count( $scope['items'] );
					?>
					<section class="itsi-tb-scope itsi-tb-scope-<?php echo esc_attr( $color ); ?> itsi-tb-scope-branch" role="group">
						<header class="itsi-tb-scope-hdr">
							<span class="itsi-tb-scope-banner"><?php echo esc_html( $scope['label'] ); ?></span>
							<span class="itsi-tb-scope-count"><?php echo (int) $count; ?></span>
						</header>

						<div class="itsi-tb-scope-items">
							<?php foreach ( $scope['items'] as $idx => $tpl ) :
								$is_first = ( 0 === $idx );
								$is_last  = ( $idx === $count - 1 );
								// Singular Page items get nested-child treatment if name matches
								// a known parent (page.php) or known child (page-{slug}.php).
								$depth = itsi_tb_compute_depth( $tpl['name'] );
								itsi_tb_render_node_vertical( $tpl, $color, $is_first, $is_last, $depth );
							endforeach; ?>
						</div>
					</section>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>

		</div>
		<?php
	}

	/**
	 * Compute depth (0 = top-level, 1 = child, 2 = grandchild) for a template name.
	 *
	 * Heuristic for itsi:
	 *   depth 0 → page.php (default), template-home-static.php
	 *   depth 1 → page-{slug}.php or page-{id}.php
	 *   depth 2 → nested page-template child (reserved for future use)
	 *
	 * @param string $name Template basename (no .php).
	 * @return int
	 */
	function itsi_tb_compute_depth( $name ) {
		if ( 'page' === $name ) {
			return 0;
		}
		if ( strpos( $name, 'page-' ) === 0 ) {
			// First page- prefix = depth 1, double page- prefix = depth 2.
			if ( strpos( $name, 'page-page-' ) === 0 ) {
				return 2;
			}
			return 1;
		}
		if ( 'template-home-static' === $name ) {
			return 0;
		}
		return 0;
	}

/**
 * Render satu template node sebagai VERTICAL card (untuk Crocoblock-style column).
 *
 * Layout:
 *   ┌─────────────────────┐
 *   │  template-name      │ ← dashed-border card
 *   └─────────────────────┘
 *                              ⊕ ← Add (+) button di kanan card
 *   For depth > 0, card di-indent left per level (parent → child chain).
 *
 * @param array  $tpl      Template data.
 * @param string $color    Scope color (green|orange|blue).
 * @param bool   $is_first First item in column (no top connector).
 * @param bool   $is_last  Last item in column (no bottom connector).
 * @param int    $depth    Nesting depth (0 = top-level, 1 = child, 2 = grandchild).
 */
function itsi_tb_render_node_vertical( $tpl, $color, $is_first = true, $is_last = true, $depth = 0 ) {
	$edit_url = add_query_arg(
		array( 'file' => $tpl['file'], 'theme' => get_stylesheet() ),
		admin_url( 'theme-editor.php' )
	);
	$add_url  = add_query_arg(
		array( 'page' => 'itsi-theme-builder', 'tab' => 'tree', 'action' => 'new', 'base' => $tpl['name'] ),
		admin_url( 'admin.php' )
	);
	$depth_class = $depth > 0 ? ' is-child depth-' . (int) $depth : '';
	?>
	<div class="itsi-tb-item itsi-tb-color-<?php echo esc_attr( $color ); ?><?php echo $depth_class; ?>">
		<div class="itsi-tb-item-row">
			<a href="<?php echo esc_url( $edit_url ); ?>" class="itsi-tb-item-card" title="<?php echo esc_attr( $tpl['file'] ); ?>">
				<span class="itsi-tb-item-name"><?php echo esc_html( $tpl['name'] ); ?></span>
			</a>
			<a href="<?php echo esc_url( $add_url ); ?>" class="itsi-tb-add-btn" aria-label="<?php
				/* translators: %s: template name */
				printf( esc_attr__( 'Create new template based on %s', 'itsi' ), $tpl['name'] );
			?>">
				<span class="dashicons dashicons-plus" aria-hidden="true"></span>
			</a>
		</div>
		<?php if ( ! $is_last ) : ?>
			<span class="itsi-tb-item-connector" aria-hidden="true">
				<span class="itsi-tb-connector-circle"></span>
			</span>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Render satu template node (dashed-border card dengan Add + button).
 *
 * @param array $tpl     Template data dari scanner.
 * @param string $color  Scope color (green|orange|blue).
 * @param string $size   View size (large|medium|small).
 * @param bool   $connected Whether this node has connecting line on left/right.
 * @param bool   $is_first  First node in row (no left connector).
 * @param bool   $is_last   Last node in row (no right connector).
 */
function itsi_tb_render_node( $tpl, $color, $size, $connected = false, $is_first = false, $is_last = false ) {
	$edit_url = add_query_arg(
		array( 'file' => $tpl['file'], 'theme' => get_stylesheet() ),
		admin_url( 'theme-editor.php' )
	);
	$add_url  = add_query_arg(
		array( 'page' => 'itsi-theme-builder', 'tab' => 'tree', 'action' => 'new', 'base' => $tpl['name'] ),
		admin_url( 'admin.php' )
	);
	?>
	<div class="itsi-tb-node itsi-tb-color-<?php echo esc_attr( $color ); ?> <?php echo $connected ? 'is-connected' : 'is-standalone'; ?>">
		<?php if ( $connected && ! $is_first ) : ?>
			<span class="itsi-tb-line itsi-tb-line-left" aria-hidden="true"></span>
			<span class="itsi-tb-junction itsi-tb-junction-start" aria-hidden="true"></span>
		<?php endif; ?>

		<a href="<?php echo esc_url( $edit_url ); ?>" class="itsi-tb-node-card" title="<?php echo esc_attr( $tpl['file'] ); ?>">
			<span class="itsi-tb-node-name"><?php echo esc_html( $tpl['name'] ); ?></span>
		</a>

		<a href="<?php echo esc_url( $add_url ); ?>" class="itsi-tb-add-btn" aria-label="<?php
			/* translators: %s: template name */
			printf( esc_attr__( 'Create new template based on %s', 'itsi' ), $tpl['name'] );
		?>">
			<span class="dashicons dashicons-plus" aria-hidden="true"></span>
		</a>

		<?php if ( $connected && ! $is_last ) : ?>
			<span class="itsi-tb-line itsi-tb-line-right" aria-hidden="true"></span>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * TAB 1 alternate: Grid view — flat card grid per template dengan kondisi.
 */
function itsi_tb_render_grid( $scopes ) {
	if ( empty( $scopes ) ) {
		echo '<div class="itsi-tb-empty-itsi"><span class="dashicons dashicons-info"></span><p>';
		esc_html_e( 'Tidak ada template dalam scope yang dipilih.', 'itsi' );
		echo '</p></div>';
		return;
	}
	?>
	<div class="itsi-tb-grid" role="grid" aria-label="<?php esc_attr_e( 'Template grid', 'itsi' ); ?>">
		<?php foreach ( $scopes as $scope_key => $scope ) : ?>
			<div class="itsi-tb-grid-section">
				<h2 class="itsi-tb-grid-section-title">
					<span class="itsi-tb-scope-pill itsi-tb-scope-pill-<?php echo esc_attr( $scope['color'] ); ?>">
						<?php echo esc_html( $scope['label'] ); ?>
					</span>
					<span class="itsi-tb-grid-section-count"><?php echo (int) count( $scope['items'] ); ?></span>
				</h2>
				<div class="itsi-tb-grid-cards">
					<?php foreach ( $scope['items'] as $tpl ) :
						$edit_url = add_query_arg(
							array( 'file' => $tpl['file'], 'theme' => get_stylesheet() ),
							admin_url( 'theme-editor.php' )
						);
						?>
						<article class="itsi-tb-grid-card itsi-tb-color-<?php echo esc_attr( $scope['color'] ); ?>">
							<header class="itsi-tb-grid-card-hdr">
								<code class="itsi-tb-grid-card-file"><?php echo esc_html( $tpl['file'] ); ?></code>
							</header>
							<h3 class="itsi-tb-grid-card-name"><?php echo esc_html( $tpl['name'] ); ?></h3>
							<?php if ( ! empty( $tpl['conditions'] ) ) : ?>
								<ul class="itsi-tb-grid-card-conds">
									<?php foreach ( $tpl['conditions'] as $c ) : ?>
										<li><?php echo esc_html( $c ); ?></li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
							<footer class="itsi-tb-grid-card-ftr">
								<span class="itsi-tb-grid-card-size"><?php echo esc_html( round( $tpl['size'] / 1024, 1 ) ); ?> KB</span>
								<a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small">
									<?php esc_html_e( 'Edit', 'itsi' ); ?>
								</a>
							</footer>
						</article>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
}

/**
 * Empty state helper.
 */
function itsi_tb_render_empty_message( $msg ) {
	?>
	<div class="itsi-tb-empty-itsi">
		<span class="dashicons dashicons-info" aria-hidden="true"></span>
		<p><?php echo esc_html( $msg ); ?></p>
	</div>
	<?php
}

/**
 * TAB 2: Conditions Matrix.
 */
function itsi_tb_render_conditions( $templates ) {
	?>
	<table class="widefat striped itsi-tb-matrix" aria-label="<?php esc_attr_e( 'Conditions matrix', 'itsi' ); ?>">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Template', 'itsi' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Type', 'itsi' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Post Types', 'itsi' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Taxonomies', 'itsi' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Conditions / Scope', 'itsi' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Used By', 'itsi' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $templates as $tpl ) : ?>
				<tr>
					<td><code><?php echo esc_html( $tpl['file'] ); ?></code></td>
					<td><span class="itsi-tb-type-pill itsi-tb-type-<?php echo esc_attr( $tpl['type'] ); ?>"><?php echo esc_html( $tpl['type'] ); ?></span></td>
					<td>
						<?php if ( ! empty( $tpl['post_types'] ) ) : ?>
							<?php foreach ( $tpl['post_types'] as $pt ) : ?>
								<code class="itsi-tb-tag"><?php echo esc_html( $pt ); ?></code>
							<?php endforeach; ?>
						<?php else : ?>
							<span class="itsi-tb-dim">—</span>
						<?php endif; ?>
					</td>
					<td>
						<?php if ( ! empty( $tpl['taxonomies'] ) ) : ?>
							<?php foreach ( $tpl['taxonomies'] as $tx ) : ?>
								<code class="itsi-tb-tag"><?php echo esc_html( $tx ); ?></code>
							<?php endforeach; ?>
						<?php else : ?>
							<span class="itsi-tb-dim">—</span>
						<?php endif; ?>
					</td>
					<td class="itsi-tb-conds-cell">
						<?php foreach ( $tpl['conditions'] as $c ) : ?>
							<span class="itsi-tb-cond-pill"><?php echo esc_html( $c ); ?></span>
						<?php endforeach; ?>
					</td>
					<td>
						<?php if ( $tpl['used_by_pages'] > 0 ) : ?>
							<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=page' ) ); ?>" class="itsi-tb-link-count">
								<?php
								/* translators: %d: number of pages */
								printf( esc_html( _n( '%d page', '%d pages', (int) $tpl['used_by_pages'], 'itsi' ) ), (int) $tpl['used_by_pages'] );
								?>
							</a>
						<?php else : ?>
							<span class="itsi-tb-dim">—</span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

/**
 * TAB 3: Missing templates.
 */
function itsi_tb_render_missing( $missing, $templates ) {
	?>
	<?php if ( empty( $missing ) ) : ?>
		<div class="itsi-tb-empty">
			<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
			<h2><?php esc_html_e( 'Semua template lengkap!', 'itsi' ); ?></h2>
			<p><?php esc_html_e( 'Tidak ada CPT atau taxonomy publik yang missing template archive-nya.', 'itsi' ); ?></p>
		</div>
	<?php else : ?>
		<div class="itsi-tb-missing-list" role="list">
			<?php foreach ( $missing as $m ) : ?>
				<article class="itsi-tb-missing-card" role="listitem">
					<header class="itsi-tb-missing-hdr">
						<span class="dashicons dashicons-warning" aria-hidden="true"></span>
						<h3><?php echo esc_html( $m['label'] ); ?></h3>
					</header>
					<p class="itsi-tb-missing-reason"><?php echo esc_html( $m['reason'] ); ?></p>
					<p class="itsi-tb-missing-expected">
						<strong><?php esc_html_e( 'Expected file:', 'itsi' ); ?></strong>
						<code><?php echo esc_html( $m['expected'] ); ?></code>
					</p>
					<p class="itsi-tb-missing-fix">
						<?php
						$kind = ( 'cpt-archive' === $m['kind'] ) ? __( 'CPT archive', 'itsi' ) : __( 'Taxonomy', 'itsi' );
						/* translators: 1: kind, 2: filename */
						echo esc_html( sprintf( __( '%1$s fallback akan jalan tanpa file ini. Untuk override, tambahkan %2$s di theme root.', 'itsi' ), $kind, $m['expected'] ) );
						?>
					</p>
				</article>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<section class="itsi-tb-existing">
		<h2 class="itsi-tb-existing-title"><?php esc_html_e( 'Template yang sudah ada', 'itsi' ); ?></h2>
		<p class="description">
			<?php
			/* translators: %d: count */
			printf( esc_html__( '%d template file terdeteksi di theme.', 'itsi' ), count( $templates ) );
			?>
		</p>
	</section>
	<?php
}

/**
 * TAB 4: Assignment Editor.
 */
function itsi_tb_render_assignments( $assignment ) {
	$roles   = ITSI_Theme_Builder_Assignment::list_roles_for_ui();
	$cpts    = ITSI_Theme_Builder_Assignment::list_cpts_for_ui();
	$terms   = ITSI_Theme_Builder_Assignment::list_terms_for_ui();
	$tmpls   = ITSI_Theme_Builder_Assignment::list_templates_for_ui();

	$role_assigns = $assignment->get_role_assignments();
	$cpt_assigns  = $assignment->get_cpt_assignments();
	$term_assigns = $assignment->get_term_assignments();
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="itsi-tb-form">
		<input type="hidden" name="action" value="itsi_tb_save">
		<?php wp_nonce_field( 'itsi_tb_save', '_itsi_tb_nonce' ); ?>

		<p class="description">
			<?php esc_html_e( 'Mapping ini override konvensi penamaan template WP. Priority: term assignment > role assignment > CPT assignment. Front-end akan resolve lewat filter template_include dengan prioritas rendah agar WP native resolution jalan dulu.', 'itsi' ); ?>
		</p>

		<fieldset class="itsi-tb-fset">
			<legend><?php esc_html_e( 'Role → Template', 'itsi' ); ?></legend>
			<p class="description"><?php esc_html_e( 'Override template single view untuk user dengan role tertentu (saat login).', 'itsi' ); ?></p>
			<table class="widefat itsi-tb-assign-tbl">
				<thead>
					<tr>
						<th><?php esc_html_e( 'User Role', 'itsi' ); ?></th>
						<th><?php esc_html_e( 'Template File', 'itsi' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $roles as $slug => $label ) :
						$current = isset( $role_assigns[ $slug ] ) ? $role_assigns[ $slug ] : '';
						?>
						<tr>
							<td>
								<label>
									<code><?php echo esc_html( $slug ); ?></code>
									<span class="itsi-tb-muted"><?php echo esc_html( $label ); ?></span>
								</label>
							</td>
							<td>
								<select name="role[<?php echo esc_attr( $slug ); ?>]">
									<option value="_none_"><?php esc_html_e( '— Use default —', 'itsi' ); ?></option>
									<?php foreach ( $tmpls as $file => $tlabel ) : ?>
										<option value="<?php echo esc_attr( $file ); ?>" <?php selected( $current, $file ); ?>>
											<?php echo esc_html( $tlabel ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</fieldset>

		<fieldset class="itsi-tb-fset">
			<legend><?php esc_html_e( 'Post Type → Template', 'itsi' ); ?></legend>
			<p class="description"><?php esc_html_e( 'Override single-{post_type}.php convention dengan template file lain.', 'itsi' ); ?></p>
			<table class="widefat itsi-tb-assign-tbl">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Post Type', 'itsi' ); ?></th>
						<th><?php esc_html_e( 'Template File', 'itsi' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $cpts as $slug => $label ) :
						$current = isset( $cpt_assigns[ $slug ] ) ? $cpt_assigns[ $slug ] : '';
						?>
						<tr>
							<td>
								<label>
									<code><?php echo esc_html( $slug ); ?></code>
									<span class="itsi-tb-muted"><?php echo esc_html( $label ); ?></span>
								</label>
							</td>
							<td>
								<select name="cpt[<?php echo esc_attr( $slug ); ?>]">
									<option value="_none_"><?php esc_html_e( '— Use default single-{slug}.php —', 'itsi' ); ?></option>
									<?php foreach ( $tmpls as $file => $tlabel ) : ?>
										<option value="<?php echo esc_attr( $file ); ?>" <?php selected( $current, $file ); ?>>
											<?php echo esc_html( $tlabel ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</fieldset>

		<?php if ( ! empty( $terms ) ) : ?>
			<fieldset class="itsi-tb-fset">
				<legend><?php esc_html_e( 'Term → Template', 'itsi' ); ?></legend>
				<p class="description"><?php esc_html_e( 'Override archive listing untuk term tertentu (category, tag, custom taxonomy).', 'itsi' ); ?></p>
				<table class="widefat itsi-tb-assign-tbl">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Term', 'itsi' ); ?></th>
							<th><?php esc_html_e( 'Template File', 'itsi' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $terms as $tid => $label ) :
							$current = isset( $term_assigns[ $tid ] ) ? $term_assigns[ $tid ] : '';
							?>
							<tr>
								<td>
									<label>
										<code>#<?php echo (int) $tid; ?></code>
										<span class="itsi-tb-muted"><?php echo esc_html( $label ); ?></span>
									</label>
								</td>
								<td>
									<select name="term[<?php echo (int) $tid; ?>]">
										<option value="_none_"><?php esc_html_e( '— Use default archive —', 'itsi' ); ?></option>
										<?php foreach ( $tmpls as $file => $tlabel ) : ?>
											<option value="<?php echo esc_attr( $file ); ?>" <?php selected( $current, $file ); ?>>
												<?php echo esc_html( $tlabel ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</fieldset>
		<?php endif; ?>

		<p class="submit">
			<button type="submit" class="button button-primary">
				<?php esc_html_e( 'Simpan Assignments', 'itsi' ); ?>
			</button>
		</p>
	</form>
	<?php
}

/**
 * Pilih dashicon untuk template type.
 *
 * @param string $type
 * @return string
 */
function itsi_tb_icon_for_type( $type ) {
	$icons = array(
		'front-page'    => 'dashicons-admin-home',
		'single'        => 'dashicons-media-default',
		'archive'       => 'dashicons-list-view',
		'taxonomy'      => 'dashicons-tag',
		'author'        => 'dashicons-admin-users',
		'page'          => 'dashicons-admin-page',
		'search'        => 'dashicons-search',
		'404'           => 'dashicons-dismiss',
		'index'         => 'dashicons-admin-generic',
		'custom'        => 'dashicons-welcome-widgets-menus',
		'core'          => 'dashicons-admin-tools',
		'template-part' => 'dashicons-carrot',
	);
	return isset( $icons[ $type ] ) ? $icons[ $type ] : 'dashicons-media-default';
}