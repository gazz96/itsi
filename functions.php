<?php
/**
 * ITSI Theme functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package itsi
 */

if ( ! defined( '_S_VERSION' ) ) {
	define( '_S_VERSION', '1.0.1' );
}

if ( ! function_exists( 'itsi_setup' ) ) :
	/**
	 * Sets up theme defaults and registers support for various WordPress features.
	 */
	function itsi_setup() {
		load_theme_textdomain( 'itsi', get_template_directory() . '/languages' );
		add_theme_support( 'automatic-feed-links' );
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );

		// Register nav menus used by the header.
		register_nav_menus(
			array(
				'menu-1'     => esc_html__( 'Primary', 'itsi' ),
				'mobile-menu' => esc_html__( 'Mobile Menu', 'itsi' ),
			)
		);

		add_theme_support(
			'html5',
			array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' )
		);

		add_theme_support(
			'custom-logo',
			array(
				'height'      => 250,
				'width'       => 250,
				'flex-width'  => true,
				'flex-height' => true,
			)
		);

		// Favicon — site_icon theme_mod is read by WP core in wp_head() and
		// emits <link rel="icon"> automatically when set.
		add_theme_support( 'site-icon' );
	}
endif;
add_action( 'after_setup_theme', 'itsi_setup' );

/**
 * Content width.
 */
function itsi_content_width() {
	$GLOBALS['content_width'] = apply_filters( 'itsi_content_width', 860 );
}
add_action( 'after_setup_theme', 'itsi_content_width', 0 );

/**
 * Force the /berita route (and the post-type archive) to render
 * archive-berita.php — the hybrid featured + grid template. Falls back
 * gracefully if a category, tag, or search query is requested: the same
 * template handles those via its own query logic.
 *
 * Trigger conditions:
 *   - request slug ends with /berita/
 *   - query var post_type=post (default post archive)
 *   - category/tag archives are routed by WP itself; we don't override.
 */
function itsi_force_berita_template( $template ) {
	if ( is_admin() ) {
		return $template;
	}

	$req_path = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	$is_berita_route = ( $req_path !== '' ) && (
		preg_match( '#/berita/?(\?.*)?$#', $req_path ) ||
		preg_match( '#/index\.php/berita/?(\?.*)?$#', $req_path )
	);

	if ( ! $is_berita_route && ! is_post_type_archive( 'post' ) ) {
		return $template;
	}

	$archive = locate_template( 'archive-berita.php' );
	if ( $archive ) {
		return $archive;
	}
	return $template;
}
add_filter( 'template_include', 'itsi_force_berita_template', 99 );

/**
 * Suppress WP's canonical redirect for /berita/* so the route resolves
 * to archive-berita.php via template_include rather than bouncing to the
 * existing /berita-itsi/ page. We only suppress the redirect — the rest
 * of canonical behaviour (pagination, trailing-slash) is untouched.
 */
function itsi_suppress_berita_canonical( $redirect_url, $requested_url ) {
	if ( ! is_string( $requested_url ) ) {
		return $redirect_url;
	}
	// If something else is requesting /berita or /berita/..., return null = no redirect.
	if ( preg_match( '#/berita/?(\?.*)?$#', $requested_url ) ||
	     preg_match( '#/index\.php/berita/?(\?.*)?$#', $requested_url ) ) {
		return null;
	}
	return $redirect_url;
}
add_filter( 'redirect_canonical', 'itsi_suppress_berita_canonical', 1, 2 );

/**
 * Add a rewrite rule so /berita/ resolves to the post archive. WP's
 * catch-all pagename rule otherwise claims it (no page slug `berita`
 * exists, but `redirect_canonical` then nudges to /berita-itsi/).
 *
 * Rule order matters — we insert ours before the generic page rule.
 */
function itsi_berita_rewrite_rules( $rules ) {
	$custom = array(
		'index\.php/berita/feed/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?post_type=post&feed=$matches[1]',
		'index\.php/berita/(feed|rdf|rss|rss2|atom)/?$'      => 'index.php?post_type=post&feed=$matches[1]',
		'index\.php/berita/?$'                               => 'index.php?post_type=post',
		'index\.php/berita/?\?paged=([0-9]+)$'                => 'index.php?post_type=post&paged=$matches[1]',
	);
	return $custom + $rules;
}
add_filter( 'rewrite_rules_array', 'itsi_berita_rewrite_rules', 5 );

/**
 * Some hosting layers (notably PHP built-in server with PATHINFO mode,
 * i.e. /index.php/berita/) don't run WordPress rewrite rules. WP sees
 * `pagename=berita`, finds no matching page, and 404s. The `request`
 * filter runs early — before the main query — so we hijack the
 * query_vars to point at the post archive instead.
 */
function itsi_berita_request( $query_vars ) {
	if ( is_admin() ) {
		return $query_vars;
	}

	// Match `name=berita` OR `pagename=berita` — PHP built-in server sends
	// the path segment after /index.php/ as `name`; mod_rewrite setups
	// send it as `pagename`.
	$slug = isset( $query_vars['name'] ) ? $query_vars['name']
	       : ( isset( $query_vars['pagename'] ) ? $query_vars['pagename'] : null );

	if ( 'berita' !== $slug ) {
		return $query_vars;
	}

	// Strip page-singulation vars and repoint to the post archive.
	unset(
		$query_vars['name'],
		$query_vars['pagename'],
		$query_vars['page'],
		$query_vars['feed'],
		$query_vars['post_type'],
		$query_vars['p']
	);
	$query_vars['post_type'] = 'post';

	return $query_vars;
}
add_filter( 'request', 'itsi_berita_request', 1 );

/**
 * Program Studi single — hijack PATHINFO requests so /index.php/program-studi/{slug}/
 * resolves to the CPT single instead of 404.
 *
 * Same hosting issue as /berita/: PATHINFO mode (PHP built-in server) doesn't run
 * WP rewrite rules. WP sees `pagename=program-studi/{slug}`, finds no matching page
 * (only the top-level static page `program-studi` exists), and 404s. We repoint the
 * query vars to the program_studi CPT single.
 *
 * The archive /index.php/program-studi/ (no trailing segment) is left untouched.
 *
 * Robustness: we parse REQUEST_URI as the source of truth instead of relying on
 * the shape of the already-parsed query vars. On some hosting layers the rewrite
 * does not emit `post_type=program_studi` for /program-studi/{slug}/ (it falls
 * back to the generic post/page rules), so neither `name` nor `pagename` carries
 * the `program-studi/` prefix in a predictable way. Checking the raw path makes
 * the hijack work regardless.
 */
function itsi_program_studi_request( $query_vars ) {
	if ( is_admin() ) {
		return $query_vars;
	}

	// 1) Source of truth: raw request path.
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
	$path = rawurldecode( wp_parse_url( $uri, PHP_URL_PATH ) ?: '' );

	if ( preg_match( '#/program-studi/([^/]+)/?$#', $path, $pm ) ) {
		// Strip page-singulation vars and repoint to the CPT single.
		unset(
			$query_vars['name'],
			$query_vars['pagename'],
			$query_vars['page'],
			$query_vars['feed'],
			$query_vars['post_type'],
			$query_vars['p']
		);
		$query_vars['post_type'] = 'program_studi';
		$query_vars['name']      = $pm[1];

		return $query_vars;
	}

	// 2) Fallback: hijack name/pagename that carries the two-segment shape.
	$slug = isset( $query_vars['name'] ) ? $query_vars['name']
	       : ( isset( $query_vars['pagename'] ) ? $query_vars['pagename'] : null );

	// Only hijack when there is a second segment after `program-studi`.
	if ( ! is_string( $slug ) || ! preg_match( '#^program-studi/([^/]+)/?$#', $slug, $m ) ) {
		return $query_vars;
	}

	// Strip page-singulation vars and repoint to the CPT single.
	unset(
		$query_vars['name'],
		$query_vars['pagename'],
		$query_vars['page'],
		$query_vars['feed'],
		$query_vars['post_type'],
		$query_vars['p']
	);
	$query_vars['post_type'] = 'program_studi';
	$query_vars['name']      = $m[1];

	return $query_vars;
}
add_filter( 'request', 'itsi_program_studi_request', 1 );

/**
 * Suppress WP's canonical redirect for /program-studi/{slug}/ so the route resolves
 * to the CPT single via the request filter rather than bouncing to the static
 * `program-studi` page or a 404. Archive (no trailing segment) is not matched.
 */
function itsi_suppress_program_studi_canonical( $redirect_url, $requested_url ) {
	if ( ! is_string( $requested_url ) ) {
		return $redirect_url;
	}
	if ( preg_match( '#/index\.php/program-studi/[^/]+/?(\?.*)?$#', $requested_url ) ||
	     preg_match( '#/program-studi/[^/]+/?(\?.*)?$#', $requested_url ) ) {
		return null;
	}
	return $redirect_url;
}
add_filter( 'redirect_canonical', 'itsi_suppress_program_studi_canonical', 1, 2 );

/**
 * Add a rewrite rule so /program-studi/{slug}/ resolves to the CPT single.
 * Mirror of the /berita/ rules: PATHINFO + some nginx setups need the
 * `index.php/`-prefixed variant registered explicitly.
 */
function itsi_program_studi_rewrite_rules( $rules ) {
	$custom = array(
		'index\.php/program-studi/([^/]+)/?$' => 'index.php?post_type=program_studi&name=$matches[1]',
	);
	return $custom + $rules;
}
add_filter( 'rewrite_rules_array', 'itsi_program_studi_rewrite_rules', 5 );

/**
 * Informasi Publik archive — hijack PATHINFO requests so /index.php/informasi-publik/
 * (and the legacy /info-publik/ alias) resolves to the info_publik CPT archive
 * instead of 404.
 *
 * Same hosting issue as /berita/: PATHINFO mode (PHP built-in server) doesn't run
 * WP rewrite rules. WP sees `pagename=informasi-publik`, finds no matching page,
 * and 404s. We repoint the query vars to the info_publik CPT archive.
 *
 * Robustness: parse REQUEST_URI as the source of truth, then fall back to the
 * name/pagename query vars — mirrors the program_studi hijack.
 */
function itsi_info_publik_request( $query_vars ) {
	if ( is_admin() ) {
		return $query_vars;
	}

	// 1) Source of truth: raw request path.
	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
	$path = rawurldecode( wp_parse_url( $uri, PHP_URL_PATH ) ?: '' );

	// Archive only: optional /page/N/ segment after the slug.
	if ( preg_match( '#/(?:index\.php/)?(?:informasi-publik|info-publik)(?:/page/([0-9]+))?/?$#', $path, $pm ) ) {
		// Strip page-singulation vars and repoint to the CPT archive.
		unset(
			$query_vars['name'],
			$query_vars['pagename'],
			$query_vars['page'],
			$query_vars['feed'],
			$query_vars['post_type'],
			$query_vars['p']
		);
		$query_vars['post_type'] = 'info_publik';
		if ( ! empty( $pm[1] ) ) {
			$query_vars['paged'] = (int) $pm[1];
		}

		return $query_vars;
	}

	// 2) Fallback: hijack name/pagename that carries the slug.
	$slug = isset( $query_vars['name'] ) ? $query_vars['name']
	       : ( isset( $query_vars['pagename'] ) ? $query_vars['pagename'] : null );

	// Match the bare slug or a paginated shape (informasi-publik/page/N).
	if ( is_string( $slug ) && preg_match( '#^(?:informasi-publik|info-publik)(?:/page/([0-9]+))?/?$#', $slug, $sm ) ) {
		// Strip page-singulation vars and repoint to the CPT archive.
		unset(
			$query_vars['name'],
			$query_vars['pagename'],
			$query_vars['page'],
			$query_vars['feed'],
			$query_vars['post_type'],
			$query_vars['p']
		);
		$query_vars['post_type'] = 'info_publik';
		if ( ! empty( $sm[1] ) ) {
			$query_vars['paged'] = (int) $sm[1];
		}

		return $query_vars;
	}

	return $query_vars;
}
add_filter( 'request', 'itsi_info_publik_request', 1 );

/**
 * Suppress WP's canonical redirect for /informasi-publik/ (and the legacy
 * /info-publik/ alias) so the route resolves to the CPT archive via the
 * request filter rather than bouncing to a static page or a 404.
 */
function itsi_suppress_info_publik_canonical( $redirect_url, $requested_url ) {
	if ( ! is_string( $requested_url ) ) {
		return $redirect_url;
	}
	if ( preg_match( '#/(?:index\.php/)?(?:informasi-publik|info-publik)/?(?:\?.*)?$#', $requested_url ) ) {
		return null;
	}
	return $redirect_url;
}
add_filter( 'redirect_canonical', 'itsi_suppress_info_publik_canonical', 1, 2 );

/**
 * Add rewrite rules so /informasi-publik/ (archive + pagination) resolves to
 * the info_publik CPT. The `index.php/`-prefixed variants cover PATHINFO mode;
 * the unprefixed variants cover mod_rewrite/nginx setups where the default
 * CPT rules may not be generated (e.g. rules never flushed).
 */
function itsi_info_publik_rewrite_rules( $rules ) {
	$custom = array(
		'index\.php/informasi-publik/page/([0-9]+)/?$' => 'index.php?post_type=info_publik&paged=$matches[1]',
		'index\.php/informasi-publik/?$'               => 'index.php?post_type=info_publik',
		'informasi-publik/page/([0-9]+)/?$'            => 'index.php?post_type=info_publik&paged=$matches[1]',
		'informasi-publik/?$'                          => 'index.php?post_type=info_publik',
	);
	return $custom + $rules;
}
add_filter( 'rewrite_rules_array', 'itsi_info_publik_rewrite_rules', 5 );

/**
 * Enqueue scripts and styles.
 */
function itsi_scripts() {
	// Google Fonts (preconnect for performance).
	wp_enqueue_style(
		'itsi-fonts',
		'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400;1,600&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap',
		array(),
		null
	);

	// Bootstrap Icons — used for article meta icons (calendar, clock, eye,
	// share buttons, category fallbacks) instead of emoji.
	wp_enqueue_style(
		'itsi-bootstrap-icons',
		'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
		array(),
		'1.11.3'
	);

	wp_enqueue_style( 'itsi-style', get_stylesheet_uri(), array( 'itsi-fonts', 'itsi-bootstrap-icons' ), _S_VERSION );
	wp_style_add_data( 'itsi-style', 'rtl', 'replace' );

	wp_enqueue_script(
		'itsi-main',
		get_template_directory_uri() . '/js/itsi-main.js',
		array(),
		_S_VERSION,
		true
	);

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}

	// Program Studi (BDP) page styles — loaded only on single-program_studi view.
	if ( is_singular( 'program_studi' ) ) {
		wp_enqueue_style(
			'itsi-program-studi',
			get_template_directory_uri() . '/assets/css/program-studi.css',
			array( 'itsi-style' ),
			_S_VERSION
		);
	}

	// Artikel detail + search results — loaded on single post / page / search.
	if ( is_singular( 'post' ) || is_singular( 'page' ) || is_search() ) {
		wp_enqueue_style(
			'itsi-artikel-detail',
			get_template_directory_uri() . '/assets/css/artikel-detail.css',
			array( 'itsi-style' ),
			_S_VERSION
		);
	}

	// Berita archive (also reuses artikel-detail.css).
	if ( is_post_type_archive( 'post' ) || is_home() || is_category() ) {
		wp_enqueue_style(
			'itsi-artikel-detail',
			get_template_directory_uri() . '/assets/css/artikel-detail.css',
			array( 'itsi-style' ),
			_S_VERSION
		);
	}
}
add_action( 'wp_enqueue_scripts', 'itsi_scripts' );

/**
 * Load the data-driven widget classes (TOC, Popular Posts).
 *
 * Required here — not autoloaded — because the widget classes extend
 * WP_Widget, which is only fully available after WordPress core widgets
 * have been registered.
 */
require_once get_template_directory() . '/inc/widgets.php';

/**
 * REST API enhancement untuk CPT Hibah LP2M.
 *
 * Register custom fields (timeline_items, file_panduan, file_template, etc.)
 * so the Vue frontend at lp2m.bagistudio.com can fetch event data directly
 * from /wp-json/wp/v2/hibah.
 */
require_once get_template_directory() . '/inc/rest-api-hibah.php';
require_once get_template_directory() . '/inc/lp2m-settings.php';
require_once get_template_directory() . '/inc/lp2m-pendaftaran.php';
require_once get_template_directory() . '/inc/lp2m-smtp.php';

/**
 * TypeRocket jQuery 3.x compatibility shim (admin).
 *
 * Mutes jquery-migrate warnings and re-implements deprecated static APIs
 * (`$.isFunction`, `$.type`, `$.trim`, `$.parseJSON`, `$.now`) so the
 * TypeRocket page builder loads cleanly under WP 6.9.1 — without touching
 * the shared plugin itself. See inc/typerocket-compat.php.
 */
require_once get_template_directory() . '/inc/typerocket-compat.php';

/**
 * LP2M CORS — izinkan akses lintas-origin dari SPA LP2M.
 *
 * SPA LP2M (lp2m.bagistudio.com / lp2m-102.pages.dev / lp2m.itsi.ac.id)
 * memanggil REST API itsi.ac.id secara langsung lintas-origin. WordPress core
 * hanya mengirim header CORS untuk origin same-site, jadi tanpa filter ini
 * semua request /wp-json dari domain LP2M diblokir browser.
 */
require_once get_template_directory() . '/inc/lp2m-cors.php';

/**
 * LP2M Auth — fallback autentikasi REST dengan password akun.
 *
 * WordPress core hanya menerima Application Password via Basic Auth; filter
 * ini (rest_authentication_errors) menambahkan dukungan username + password
 * akun biasa agar SPA LP2M bisa login & mengakses /wp-json langsung dengan
 * password akun. Application password lama tetap valid (diproses core dulu).
 */
require_once get_template_directory() . '/inc/lp2m-auth.php';

/**
 * LP2M User Password — ganti password akun via REST (POST /lp2m/v1/me/password).
 *
 * Dipakai dashboard LP2M (Profile) untuk mengganti password akun pengguna.
 */
require_once get_template_directory() . '/inc/lp2m/class-user-password.php';

/**
 * LP2M Hibah Receiver — form submission, sanitization, REST endpoints.
 *
 * Handles POST/GET /lp2m/v1/hibah with:
 *   - Strict input sanitization (whitelist, regex, HTML strip)
 *   - Rate limiting (5 per 15 min per IP)
 *   - CPT pendaftaran_hibah + post meta storage
 *   - hibah_id foreign-key linking to CPT hibah
 *   - Dynamic form builder (custom fields per event)
 */
require_once get_template_directory() . '/inc/lp2m/class-hibah-receiver.php';
require_once get_template_directory() . '/inc/lp2m/class-lp2m-pdf.php';

/**
 * Register widget areas for single post / page.
 *
 * All five are scoped to the single-post / single-page layout — they only
 * render inside `single.php` and `page.php` when `is_active_sidebar()` is
 * true. Admin label is the user-facing name in the Customizer; the `id`
 * follows the `itsi_single_post_widget_*` convention you requested.
 *
 * Position reference (see single.php):
 *   _before_post  → top of <article>, above header (was: top AdSense slot)
 *   _after_post   → end of <article>, after Related (was: post-article AdSense)
 *   _sidebar      → sidebar first slot (was: tall AdSense)
 *   _popular      → sidebar middle slot (was: Popular Posts card)
 *   _toc          → sidebar first slot (was: Daftar Isi card)
 */

/**
 * Default footer layout — 4 kolom: Brand 2fr + Prodi/Info/Kontak 1fr.
 *
 * Dipakai oleh: itsi_get_footer_layout(), widget registration (functions.php),
 * populate tabs (admin-menu.php), dan render (footer.php). Semua merujuk ke
 * sini agar default konsisten.
 *
 * @return array<int,array{label:string,width:string}>
 */
function itsi_footer_layout_default() {
	return array(
		array( 'label' => 'Footer 1', 'width' => '2fr' ),
		array( 'label' => 'Footer 2', 'width' => '1fr' ),
		array( 'label' => 'Footer 3', 'width' => '1fr' ),
		array( 'label' => 'Footer 4', 'width' => '1fr' ),
	);
}

/**
 * Get the footer layout from theme_mod, normalized to rows [label, width].
 *
 * Handles: absent (null), array, serialized string (set_theme_mod auto-serializes
 * arrays), and JSON string (TypeRocket repeater can post JSON). Invalid/empty
 * rows dropped; missing width → '1fr'; empty result → default.
 *
 * @return array<int,array{label:string,width:string}>
 */
function itsi_get_footer_layout() {
	$raw = get_theme_mod( 'itsi_footer_layout', null );

	if ( is_string( $raw ) ) {
		$decoded = json_decode( $raw, true );
		if ( is_array( $decoded ) ) {
			$raw = $decoded;
		} else {
			$unser = maybe_unserialize( $raw );
			$raw   = is_array( $unser ) ? $unser : array();
		}
	}
	if ( ! is_array( $raw ) ) {
		$raw = array();
	}

	$rows = array();
	foreach ( $raw as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$label = isset( $row['label'] ) ? (string) $row['label'] : '';
		$width = isset( $row['width'] ) ? trim( (string) $row['width'] ) : '';
		if ( '' === $label && '' === $width ) {
			continue;
		}
		$rows[] = array(
			'label' => '' !== $label ? $label : sprintf( 'Footer %d', count( $rows ) + 1 ),
			'width' => '' !== $width ? $width : '1fr',
		);
	}

	return ! empty( $rows ) ? $rows : itsi_footer_layout_default();
}

/**
 * Build a sanitized CSS `grid-template-columns` value from layout rows.
 *
 * Only whitelisted tokens pass: angka+unit (fr, %, px, em, rem, vw, vh),
 * auto, min-content, max-content, minmax(...). Token tak dikenal → '1fr'.
 * Empty rows → default '2fr 1fr 1fr 1fr'.
 *
 * @param array $rows Rows from itsi_get_footer_layout().
 * @return string CSS value, e.g. "2fr 1fr 1fr 1fr".
 */
function itsi_footer_grid_columns( $rows ) {
	if ( ! is_array( $rows ) || empty( $rows ) ) {
		return '2fr 1fr 1fr 1fr';
	}

	$tokens = array();
	foreach ( $rows as $row ) {
		$width = isset( $row['width'] ) ? trim( (string) $row['width'] ) : '';
		if ( '' === $width ) {
			$tokens[] = '1fr';
			continue;
		}
		$parts = preg_split( '/[\s,]+/', $width, -1, PREG_SPLIT_NO_EMPTY );
		foreach ( $parts as $tok ) {
			if ( 1 === preg_match( '#^(\d+(\.\d+)?(fr|%|px|em|rem|vw|vh))|auto|min-content|max-content|minmax\([^)]*\)$#i', $tok ) ) {
				$tokens[] = $tok;
			} else {
				$tokens[] = '1fr';
			}
		}
	}

	return implode( ' ', $tokens );
}

/**
 * Normalize a repeater-style theme_mod into clean rows.
 *
 * Accepts array, serialized array (set_theme_mod auto-serializes), or JSON
 * string (TypeRocket repeater). Drops fully-empty rows, caps at $limit, and
 * returns rows keyed by $fields (missing keys → '').
 *
 * @param string $mod_key Theme mod key.
 * @param array  $fields  Field keys to keep, in order.
 * @param int    $limit   Max rows (0 = unlimited).
 * @return array<int,array<string,string>>
 */
function itsi_ip_normalize_repeater( $mod_key, $fields, $limit = 0 ) {
	$raw = get_theme_mod( $mod_key, null );

	if ( is_string( $raw ) ) {
		$decoded = json_decode( $raw, true );
		if ( is_array( $decoded ) ) {
			$raw = $decoded;
		} else {
			$unser = maybe_unserialize( $raw );
			$raw   = is_array( $unser ) ? $unser : array();
		}
	}
	if ( ! is_array( $raw ) ) {
		$raw = array();
	}

	$rows = array();
	foreach ( $raw as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$out = array();
		foreach ( $fields as $f ) {
			$out[ $f ] = isset( $row[ $f ] ) ? $row[ $f ] : '';
		}
		$is_empty = true;
		foreach ( $out as $v ) {
			if ( '' !== trim( (string) $v ) ) {
				$is_empty = false;
				break;
			}
		}
		if ( $is_empty ) {
			continue;
		}
		$rows[] = $out;
		if ( $limit && count( $rows ) >= $limit ) {
			break;
		}
	}

	return $rows;
}

/**
 * Get stats bar rows for the Informasi Publik archive.
 *
 * Rows come from theme_mod `itsi_ip_stats` (repeater: icon/angka/label).
 * When unset, returns the default 4 rows; the first two rows' angka can be
 * filled dynamically via $ctx (total_docs / total_cats from the template).
 *
 * @param array $ctx Optional context: [ 'total_docs' => int, 'total_cats' => int ].
 * @return array<int,array{icon:string,angka:string,label:string}>
 */
function itsi_ip_get_stats( $ctx = array() ) {
	$rows = itsi_ip_normalize_repeater( 'itsi_ip_stats', array( 'icon', 'angka', 'label' ), 6 );

	if ( empty( $rows ) ) {
		$total_docs = isset( $ctx['total_docs'] ) ? $ctx['total_docs'] : 0;
		$total_cats = isset( $ctx['total_cats'] ) ? $ctx['total_cats'] : 0;
		$rows       = array(
			array( 'icon' => '', 'angka' => (string) $total_docs, 'label' => 'Total Dokumen' ),
			array( 'icon' => '', 'angka' => (string) $total_cats, 'label' => 'Kategori' ),
			array( 'icon' => '', 'angka' => '24/7', 'label' => 'Akses Online' ),
			array( 'icon' => '', 'angka' => '10', 'label' => 'Hari Kerja Respons' ),
		);
	}

	return $rows;
}

/**
 * Get KIP info cards for the Informasi Publik archive.
 *
 * Rows come from theme_mod `itsi_ip_kip_cards` (repeater: icon/title/text).
 * Falls back to 3 default cards when unset.
 *
 * @return array<int,array{icon:string,title:string,text:string}>
 */
function itsi_ip_get_kip_cards() {
	$rows = itsi_ip_normalize_repeater( 'itsi_ip_kip_cards', array( 'icon', 'title', 'text' ), 8 );

	if ( empty( $rows ) ) {
		$rows = array(
			array(
				'icon'  => '',
				'title' => 'UU No. 14 Tahun 2008',
				'text'  => 'Undang-Undang tentang Keterbukaan Informasi Publik yang menjamin hak masyarakat untuk memperoleh informasi publik.',
			),
			array(
				'icon'  => '',
				'title' => 'Hak Memperoleh Informasi',
				'text'  => 'Setiap orang berhak memperoleh informasi publik sesuai ketentuan yang berlaku, dengan pengecualian yang diatur dalam UU.',
			),
			array(
				'icon'  => '',
				'title' => 'Layanan Transparan',
				'text'  => 'ITSI melalui PPID berkomitmen memberikan layanan informasi yang transparan, akuntabel, dan dapat dipertanggungjawabkan.',
			),
		);
	}

	return $rows;
}

/**
 * Get PPID banner content (icon attachment ID, title, description).
 *
 * @return array{icon:string,title:string,desc:string}
 */
function itsi_ip_get_ppid_banner() {
	return array(
		'icon'  => (string) get_theme_mod( 'itsi_ip_ppid_icon', '' ),
		'title' => (string) get_theme_mod( 'itsi_ip_ppid_title', 'PPID Institut Teknologi Sawit Indonesia' ),
		'desc'  => (string) get_theme_mod(
			'itsi_ip_ppid_desc',
			'Pejabat Pengelola Informasi dan Dokumentasi (PPID) ITSI melayani permohonan informasi publik sesuai UU No. 14 Tahun 2008 tentang Keterbukaan Informasi Publik. Akses dokumen resmi, laporan keuangan, akreditasi, dan regulasi secara transparan.'
		),
	);
}

/**
 * Get the Informasi Publik form + notification settings.
 *
 * @return array{
 *   form_enabled:bool,
 *   email_to:string,
 *   email_from:string,
 *   email_subject:string,
 *   rate_max:int,
 *   rate_window:int
 * }
 */
function itsi_ip_get_form_settings() {
	$admin_email = get_option( 'admin_email' );

	return array(
		'form_enabled'  => filter_var( get_theme_mod( 'itsi_ip_form_enabled', true ), FILTER_VALIDATE_BOOLEAN ),
		'email_to'      => (string) get_theme_mod( 'itsi_ip_email_to', $admin_email ),
		'email_from'    => (string) get_theme_mod( 'itsi_ip_email_from', $admin_email ),
		'email_subject' => (string) get_theme_mod( 'itsi_ip_email_subject', '[ITSI] Permohonan Informasi Baru' ),
		'rate_max'      => max( 1, (int) get_theme_mod( 'itsi_ip_rate_max', 5 ) ),
		'rate_window'   => max( 1, (int) get_theme_mod( 'itsi_ip_rate_window', 15 ) ),
	);
}

/**
 * Get the icon attachment ID for a kategori_info term (term meta).
 *
 * @param int $term_id Term ID.
 * @return string Attachment ID or ''.
 */
function itsi_ip_get_term_icon( $term_id ) {
	$term_id = (int) $term_id;
	if ( ! $term_id ) {
		return '';
	}
	$icon = get_term_meta( $term_id, 'kategori_info_icon', true );
	return $icon ? (string) $icon : '';
}

/**
 * Render an icon <img> from an attachment ID, with a graceful fallback when
 * the ID is empty/invalid (a neutral inline SVG so no emoji is ever shown).
 *
 * @param int|string $attachment_id Attachment ID (or '').
 * @param string     $class         Extra CSS classes.
 * @param string     $alt           Alt text.
 * @return string HTML or '' if not needed.
 */
function itsi_ip_render_icon_img( $attachment_id, $class = '', $alt = '' ) {
	$attachment_id = (int) $attachment_id;
	if ( $attachment_id > 0 ) {
		$url = (string) wp_get_attachment_image_url( $attachment_id, 'thumbnail' );
		if ( $url ) {
			return sprintf(
				'<img src="%s" alt="%s" class="ip-ic-img %s" loading="lazy" decoding="async" />',
				esc_url( $url ),
				esc_attr( $alt ),
				esc_attr( $class )
			);
		}
	}
	// Fallback: neutral document glyph — never an emoji.
	return sprintf(
		'<span class="ip-ic-img ip-ic-fallback %s" aria-hidden="true"><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/></svg></span>',
		esc_attr( $class )
	);
}

/**
 * Register widget areas for single post / page.
 *
 * All five are scoped to the single-post / single-page layout — they only
 * render inside `single.php` and `page.php` when `is_active_sidebar()` is
 * true. Admin label is the user-facing name in the Customizer; the `id`
 * follows the `itsi_single_post_widget_*` convention you requested.
 *
 * Position reference (see single.php):
 *   _before_post  → top of <article>, above header (was: top AdSense slot)
 *   _after_post   → end of <article>, after Related (was: post-article AdSense)
 *   _sidebar      → sidebar first slot (was: tall AdSense)
 *   _popular      → sidebar middle slot (was: Popular Posts card)
 *   _toc          → sidebar first slot (was: Daftar Isi card)
 */
function itsi_widgets_init() {
	$itsi_widget_areas = array(
		array(
			'name'          => __( 'Single Post — Before Article', 'itsi' ),
			'id'            => 'itsi_single_post_widget_before_post',
			'description'   => __( 'Widget area di atas artikel (sebelum judul). Hanya muncul di single post / page.', 'itsi' ),
			'wrap_widget'   => false,
		),
		array(
			'name'          => __( 'Single Post — After Article', 'itsi' ),
			'id'            => 'itsi_single_post_widget_after_post',
			'description'   => __( 'Widget area di bawah artikel (setelah Related Posts). Hanya muncul di single post / page.', 'itsi' ),
			'wrap_widget'   => false,
		),
		array(
			'name'          => __( 'Single Post — Table of Contents', 'itsi' ),
			'id'            => 'itsi_single_post_widget_toc',
			'description'   => __( 'Widget area Daftar Isi (TOC). Taruh widget "ITSI — Daftar Isi (Auto)" di sini. Hanya muncul di single post / page.', 'itsi' ),
			'wrap_widget'   => true,
		),
		array(
			'name'          => __( 'Single Post — Popular Posts', 'itsi' ),
			'id'            => 'itsi_single_post_widget_popular',
			'description'   => __( 'Widget area Popular Posts. Taruh widget "ITSI — Paling Banyak Dibaca (Auto)" di sini. Hanya muncul di single post / page.', 'itsi' ),
			'wrap_widget'   => true,
		),
		array(
			'name'          => __( 'Single Post — Sidebar', 'itsi' ),
			'id'            => 'itsi_single_post_widget_sidebar',
			'description'   => __( 'Widget area iklan / CTA di sidebar (di bawah Popular Posts). Hanya muncul di single post / page.', 'itsi' ),
			'wrap_widget'   => false,
		),
		array(
			'name'          => __( 'Archive Berita — Filter Kategori', 'itsi' ),
			'id'            => 'itsi_archive_berita_widget_filter',
			'description'   => __( 'Widget area filter kategori di sidebar archive / category berita. Taruh widget "ITSI — Filter Kategori (Auto)" di sini. Hanya muncul di archive / category.', 'itsi' ),
			'wrap_widget'   => true,
		),
		array(
			'name'          => __( 'Archive Berita — Popular Posts', 'itsi' ),
			'id'            => 'itsi_archive_berita_widget_popular',
			'description'   => __( 'Widget area popular posts di sidebar archive / category berita. Taruh widget "ITSI — Paling Banyak Dibaca (Auto)" di sini. Hanya muncul di archive / category.', 'itsi' ),
			'wrap_widget'   => true,
		),
		array(
			'name'          => __( 'Archive Berita — Sidebar Ads', 'itsi' ),
			'id'            => 'itsi_archive_berita_widget_sidebar',
			'description'   => __( 'Widget area iklan / CTA di sidebar archive / category berita. Taruh widget Custom HTML (AdSense) di sini. Hanya muncul di archive / category.', 'itsi' ),
			'wrap_widget'   => false,
		),
	);

	foreach ( $itsi_widget_areas as $area ) {
		if ( $area['wrap_widget'] ) {
			// Card-style wrap for TOC & Popular — matches the .at-s-card design.
			register_sidebar(
				array(
					'name'          => $area['name'],
					'id'            => $area['id'],
					'description'   => $area['description'],
					'before_widget' => '<div id="%1$s" class="at-s-card widget %2$s">',
					'after_widget'  => '</div>',
					'before_title'  => '<div class="at-s-head">',
					'after_title'   => '</div>',
				)
			);
		} else {
			// Plain wrap for ad / CTA slots.
			register_sidebar(
				array(
					'name'          => $area['name'],
					'id'            => $area['id'],
					'description'   => $area['description'],
					'before_widget' => '<div id="%1$s" class="at-widget-slot widget %2$s">',
					'after_widget'  => '</div>',
					'before_title'  => '<div class="at-s-head">',
					'after_title'   => '</div>',
				)
			);
		}
	}

	// Footer widget areas — dinamis dari layout (Appearance → ITSI → Footer → Layout Footer).
	// Setiap baris layout = satu widget area `footer_N`. Default 4 kolom.
	$footer_layout = itsi_get_footer_layout();
	foreach ( $footer_layout as $i => $col ) {
		$num = (int) $i + 1;
		register_sidebar(
			array(
				'name'          => sprintf( __( 'Footer %d — %s', 'itsi' ), $num, $col['label'] ),
				'id'            => 'footer_' . $num,
				'description'   => __( 'Kolom footer dari Layout Footer (Appearance → ITSI → Footer). Kosongkan untuk fallback footer statis.', 'itsi' ),
				'before_widget' => '<div id="%1$s" class="f-col widget %2$s">',
				'after_widget'  => '</div>',
				'before_title'  => '<div class="f-col-ttl">',
				'after_title'   => '</div>',
			)
		);
	}

	// Before Footer — band penuh (full-width) di atas <footer>, di luar footer
	// gelap. Render di footer.php tepat sebelum <footer id="colophon">.
	register_sidebar(
		array(
			'name'          => __( 'Before Footer', 'itsi' ),
			'id'            => 'itsi_before_footer',
			'description'   => __( 'Widget area penuh di atas footer. Cocok untuk CTA banner, newsletter, atau strip iklan. Hanya muncul jika minimal satu widget terisi.', 'itsi' ),
			'before_widget' => '<div id="%1$s" class="bf-widget widget %2$s">',
			'after_widget'  => '</div>',
			'before_title'  => '<div class="bf-title">',
			'after_title'   => '</div>',
		)
	);

	// Data-driven widgets (auto-render TOC from <h2>, Popular from post_views_count).
	register_widget( 'ITSI_TOC_Widget' );
	register_widget( 'ITSI_Popular_Widget' );
	register_widget( 'ITSI_CategoryFilter_Widget' );
}
add_action( 'widgets_init', 'itsi_widgets_init' );

/**
 * Localize the main JS with AJAX URL + nonce for the Permohonan form.
 */
function itsi_localize_ajax() {
	$data = array(
		'url'   => admin_url( 'admin-ajax.php' ),
		'nonce' => wp_create_nonce( 'itsi-permohonan' ),
	);

	wp_localize_script(
		'itsi-main',
		'itsiAjax',
		$data
	);
}
add_action( 'wp_enqueue_scripts', 'itsi_localize_ajax', 20 );

/**
 * Register Custom Post Types, Taxonomies & Meta Boxes via TypeRocket.
 *
 * TypeRocket auto-registers the post types when this hook fires — no
 * `add_action( 'init', ... )` needed. Docs: https://typerocket.com/docs/v6/post-types/
 */
add_action( 'typerocket_loaded', function () {

	// (Pengumuman uses the standard `post` post type + `category` taxonomy.
	//  Filter it from the section component via the Categories field.)

	// ═══ INFORMASI PUBLIK ═════════════════════════════════════
	$info_publik = tr_post_type( 'Informasi Publik', 'Informasi Publik' );
	$info_publik->setId( 'info_publik' );
	$info_publik->setSlug( 'informasi-publik' );
	$info_publik->setIcon( 'dashicons-media-document' );
	$info_publik->setPosition( 7 );
	$info_publik->setSupports( array( 'title', 'editor', 'excerpt', 'thumbnail' ) );
	$info_publik->setRest( 'info_publik' );
	$info_publik->setTitlePlaceholder( 'Tulis judul dokumen...' );
	$info_publik->setArchivePostsPerPage( 12 );

	// ═══ PROGRAM STUDI ════════════════════════════════════════
	$program_studi = tr_post_type( 'Program Studi', 'Program Studi' );
	$program_studi->setId( 'program_studi' );
	$program_studi->setSlug( 'program-studi' );
	$program_studi->setIcon( 'dashicons-welcome-learn-more' );
	$program_studi->setPosition( 8 );
	$program_studi->setSupports( array( 'title', 'editor', 'excerpt', 'thumbnail' ) );
	$program_studi->setRest( 'program_studi' );
	$program_studi->setTitlePlaceholder( 'Tulis nama program studi...' );
	$program_studi->setArchivePostsPerPage( 9 );

	// Force classic editor for program_studi so the TypeRocket 'Detail Program Studi'
	// meta box (which uses wpEditor/textarea) renders properly. Gutenberg hides all
	// classic meta boxes in its iframe region — filter opt-out keeps TR visible.
	add_filter( 'use_block_editor_for_post', function ( $use, $post ) {
		if ( $post instanceof \WP_Post && isset( $post->post_type ) && 'program_studi' === $post->post_type ) {
			return false;
		}
		return $use;
	}, 10, 2 );

	// ═══ PERMOHONAN INFORMASI (private, for form submissions) ═
	$permohonan = tr_post_type( 'Permohonan Informasi', 'Permohonan Informasi' );
	$permohonan->setId( 'permohonan_informasi' );
	$permohonan->setSlug( 'permohonan-informasi' );
	$permohonan->setIcon( 'dashicons-email-alt' );
	$permohonan->setPosition( 9 );
	$permohonan->setSupports( array( 'title', 'editor' ) );
	$permohonan->setTitlePlaceholder( 'Otomatis: Permohonan Informasi – {nama}' );
	$permohonan->setArgument( 'public', false );
	$permohonan->setArgument( 'exclude_from_search', true );
	$permohonan->setArgument( 'show_in_rest', false );
	$permohonan->setArchivePostsPerPage( 20 );

	// ═══ HIBAH ════════════════════════════════════════════════
	$hibah = tr_post_type( 'Hibah', 'Hibah' );
	$hibah->setId( 'hibah' );
	$hibah->setSlug( 'hibah' );
	$hibah->setIcon( 'dashicons-awards' );
	$hibah->setPosition( 10 );
	$hibah->setSupports( array( 'title', 'editor', 'excerpt', 'thumbnail' ) );
	$hibah->setRest( 'hibah' );
	$hibah->setTitlePlaceholder( 'Tulis judul event hibah...' );
	$hibah->setArchivePostsPerPage( 12 );

	// Force classic editor for hibah + pendaftaran_hibah so the TypeRocket
	// meta boxes render properly. Gutenberg hides all classic meta boxes.
	add_filter( 'use_block_editor_for_post', function ( $use, $post ) {
		if ( $post instanceof \WP_Post && isset( $post->post_type )
			&& in_array( $post->post_type, array( 'hibah', 'pendaftaran_hibah' ), true ) ) {
			return false;
		}
		return $use;
	}, 10, 2 );

	// ═══ TAXONOMIES ════════════════════════════════════════════
	// (Kategori Pengumuman uses the standard `category` taxonomy.
	//  Pick categories in the Pengumuman section component.)

	// (Artikel uses the standard `post` post type + `category` taxonomy.
	//  Filter it from the section component via the Categories field.)

	$fakultas = tr_taxonomy( 'Fakultas', 'Fakultas' );
	$fakultas->setId( 'fakultas' );
	$fakultas->setSlug( 'fakultas' );
	$fakultas->setHierarchical( true );
	$fakultas->addPostType( 'program_studi' );

	$kat_info = tr_taxonomy( 'Kategori Informasi', 'Kategori Informasi' );
	$kat_info->setId( 'kategori_info' );
	$kat_info->setSlug( 'kategori-info' );
	$kat_info->setHierarchical( true );
	$kat_info->setRest( 'kategori_info' );
	$kat_info->addPostType( 'info_publik' );

	$kat_hibah = tr_taxonomy( 'Kategori Hibah', 'Kategori Hibah' );
	$kat_hibah->setId( 'kategori_hibah' );
	$kat_hibah->setSlug( 'kategori-hibah' );
	$kat_hibah->setHierarchical( true );
	$kat_hibah->setRest( 'kategori_hibah' );
	$kat_hibah->addPostType( 'hibah' );

	$skema_hibah = tr_taxonomy( 'Model Hibah', 'Model Hibah' );
	$skema_hibah->setId( 'model_hibah' );
	$skema_hibah->setSlug( 'model-hibah' );
	$skema_hibah->setHierarchical( true );
	$skema_hibah->setRest( 'model_hibah' );
	$skema_hibah->addPostType( 'hibah' );

	$jenis_hibah = tr_taxonomy( 'Jenis Hibah', 'Jenis Hibah' );
	$jenis_hibah->setId( 'jenis_hibah' );
	$jenis_hibah->setSlug( 'jenis-hibah' );
	$jenis_hibah->setHierarchical( true );
	$jenis_hibah->setRest( 'jenis_hibah' );
	$jenis_hibah->addPostType( 'hibah' );

	$sdgs = tr_taxonomy( 'SDGs (Sustainable Development Goals)', 'SDGs' );
	$sdgs->setId( 'sdgs' );
	$sdgs->setSlug( 'sdgs' );
	$sdgs->setHierarchical( false );
	$sdgs->setRest( 'sdgs' );
	$sdgs->addPostType( 'hibah' );

	$kelompok_keahlian = tr_taxonomy( 'Kelompok Keahlian', 'Kelompok Keahlian' );
	$kelompok_keahlian->setId( 'kelompok_keahlian' );
	$kelompok_keahlian->setSlug( 'kelompok-keahlian' );
	$kelompok_keahlian->setHierarchical( true );
	$kelompok_keahlian->setRest( 'kelompok_keahlian' );
	$kelompok_keahlian->addPostType( 'hibah' );

	// ── Migrasi & seed: skema_hibah → model_hibah + default SDGs ──
	add_action( 'init', function () {
		if ( ! taxonomy_exists( 'skema_hibah' ) ) { return; }

		// 1) Migrasi term skema_hibah → model_hibah (sekali saja, via option flag).
		if ( ! get_option( 'itsi_model_hibah_migrated' ) ) {
			$old_terms = get_terms( array(
				'taxonomy'   => 'skema_hibah',
				'hide_empty' => false,
			) );
			if ( ! is_wp_error( $old_terms ) ) {
				foreach ( $old_terms as $term ) {
					$exists = term_exists( $term->name, 'model_hibah' );
					if ( ! $exists ) {
						$new = wp_insert_term( $term->name, 'model_hibah', array(
							'slug'        => $term->slug,
							'parent'      => 0,
							'description' => $term->description,
						) );
						if ( ! is_wp_error( $new ) ) {
							$new_id = (int) $new['term_id'];
						} else {
							$maybe = term_exists( $term->name, 'model_hibah' );
							$new_id = is_array( $maybe ) ? (int) $maybe['term_id'] : 0;
						}
					} else {
						$new_id = is_array( $exists ) ? (int) $exists['term_id'] : (int) $exists;
					}

					// Pindahkan relasi post (obj_id → old term) ke term baru.
					if ( $new_id ) {
						global $wpdb;
						$posts = $wpdb->get_col( $wpdb->prepare(
							"SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id = %d",
							(int) $term->term_taxonomy_id
						) );
						foreach ( $posts as $pid ) {
							wp_set_object_terms( (int) $pid, array( $new_id ), 'model_hibah', true );
						}
					}
				}
			}
			update_option( 'itsi_model_hibah_migrated', 1 );
		}

		// 2) Seed default SDGs (17 tujuan) kalau taxonomy masih kosong.
		if ( ! get_option( 'itsi_sdgs_seeded' ) ) {
			$sdgs = array(
				'1 No Poverty', '2 Zero Hunger', '3 Good Health and Well-being',
				'4 Quality Education', '5 Gender Equality', '6 Clean Water and Sanitation',
				'7 Affordable and Clean Energy', '8 Decent Work and Economic Growth',
				'9 Industry, Innovation and Infrastructure', '10 Reduced Inequality',
				'11 Sustainable Cities and Communities', '12 Responsible Consumption and Production',
				'13 Climate Action', '14 Life Below Water', '15 Life on Land',
				'16 Peace and Justice Strong Institutions', '17 Partnerships for the Goals',
			);
			$existing = get_terms( array( 'taxonomy' => 'sdgs', 'hide_empty' => false, 'fields' => 'names' ) );
			$existing = is_wp_error( $existing ) ? array() : $existing;
			foreach ( $sdgs as $name ) {
				if ( ! in_array( $name, $existing, true ) ) {
					wp_insert_term( $name, 'sdgs', array( 'slug' => sanitize_title( $name ) ) );
				}
			}
			update_option( 'itsi_sdgs_seeded', 1 );
		}
	} );

	// ═══ META BOXES ════════════════════════════════════════════
	tr_meta_box( 'Prioritas Pengumuman' )
		->addPostType( 'post' )
		->setCallback(
			function () {
				$form = \TypeRocket\Utility\Helper::form();
				echo '<p style="margin:0"><label style="display:flex;align-items:center;gap:.5rem;cursor:pointer">';
				echo $form->checkbox( 'sangat_penting' )->setLabel( 'Tandai sebagai SANGAT PENTING (badge merah)' )->setAttribute( 'style', 'width:auto' );
				echo '</label></p>';
			}
		);

	tr_meta_box( 'Detail Dokumen Informasi Publik' )
		->addPostType( 'info_publik' )
		->setCallback(
			function () {
				$form = \TypeRocket\Utility\Helper::form();
				echo '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem">';
				echo '<div><label style="display:block;font-weight:600;margin-bottom:.4rem">Tahun Dokumen</label>' . $form->number( 'tahun' )->setAttribute( 'min', 2000 )->setAttribute( 'max', 2099 ) . '</div>';
				echo '<div><label style="display:block;font-weight:600;margin-bottom:.4rem">Ukuran File</label>' . $form->text( 'ukuran_file' )->setAttribute( 'placeholder', 'mis. 2.4 MB' ) . '</div>';
				echo '<div><label style="display:block;font-weight:600;margin-bottom:.4rem">URL File (PDF)</label>' . $form->text( 'file_url' )->setAttribute( 'placeholder', 'https://…' ) . '</div>';
				echo '</div>';
			}
		);

	tr_meta_box( 'Detail Program Studi' )
		->addPostType( 'program_studi' )
		->setCallback(
			function () {
				$form = \TypeRocket\Utility\Helper::form();
				$tabs = \TypeRocket\Elements\Tabs::new();

				/* ─── TAB 1: Statistik ─── */
							$tabs->tab( 'Statistik', 'dashicons-chart-bar', array(
								'<div style="margin-bottom:1rem">'
								. $form->image( 'prodi_icon_image' )->setLabel( 'Icon Program Studi (gambar)' )->setHelp( 'Gambar kecil (~40×40 px) yang ditampilkan di kartu prodi pada section "Program Studi" di homepage. Kosongkan jika tidak ada — slot akan kosong (tanpa emoji fallback).' )
								. '</div>'
								. '<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">'
								. '<div>' . $form->text( 'gelar' )->setLabel( 'Gelar' )->setAttribute( 'placeholder', 'mis. S.T., S.P., M.P.' ) . '</div>'
								. '<div>' . $form->text( 'akreditasi' )->setLabel( 'Akreditasi' )->setAttribute( 'placeholder', 'mis. Unggul / A / B' ) . '</div>'
								. '<div>' . $form->number( 'durasi' )->setLabel( 'Durasi Studi (semester)' )->setAttribute( 'placeholder', '8' ) . '</div>'
								. '<div>' . $form->number( 'total_sks' )->setLabel( 'Total SKS' )->setAttribute( 'placeholder', '144' ) . '</div>'
								. '<div>' . $form->number( 'jumlah_dosen' )->setLabel( 'Jumlah Dosen' )->setAttribute( 'placeholder', '22' ) . '</div>'
								. '<div>' . $form->number( 'tahun_berdiri' )->setLabel( 'Tahun Berdiri' )->setAttribute( 'placeholder', '2005' ) . '</div>'
																		. '</div>'
																		. '<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-top:1rem">'
																		. '<div>' . $form->select( 'jenjang' )->setLabel( 'Jenjang' )->setOptions( array(
																													'D3 — Diploma 3'                  => 'D3',
																													'D4 — Diploma 4 / Sarjana Terapan' => 'D4',
																													'S1 — Sarjana'                    => 'S1',
																													'S2 — Magister'                   => 'S2',
																													'S3 — Doktor'                     => 'S3',
																													'Profesi — Pendidikan Profesi'    => 'Profesi',
																												) )->setAttribute( 'style', 'width:100%' ) . '</div>'
																		. '<div></div>'
																		. '</div>'
																		. '<div style="margin-top:1rem;padding:1rem;background:#f6f8fc;border-radius:8px;border-left:3px solid #2271b3">'
																		. '<h4 style="margin:.2rem 0 .6rem">🏷️ Chip Hero (Override Manual)</h4>'
																		. '<p style="margin:0 0 .8rem;font-size:.85em;color:#666">5 chip yang tampil di baris badge di bawah judul hero. <strong>Kosongkan semua untuk tidak menampilkan chip sama sekali</strong> — tidak ada fallback otomatis. Setiap field adalah string lengkap (sudah termasuk emoji + label).</p>'
																		. '<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">'
																		. '<div>' . $form->text( 'chip_gelar' )->setLabel( 'Chip — Gelar / S.Tr.P' )->setAttribute( 'placeholder', 'mis. 🎓 S.Tr.P' ) . '</div>'
																		. '<div>' . $form->text( 'chip_jenjang' )->setLabel( 'Chip — Jenjang' )->setAttribute( 'placeholder', 'mis. 🎓 Jenjang D4 — Diploma 4 / Sarjana Terapan' ) . '</div>'
																		. '<div>' . $form->text( 'chip_akreditasi' )->setLabel( 'Chip — Akreditasi' )->setAttribute( 'placeholder', 'mis. 🏆 Akreditasi Baik' ) . '</div>'
																		. '<div>' . $form->text( 'chip_berdiri' )->setLabel( 'Chip — Tahun Berdiri' )->setAttribute( 'placeholder', 'mis. 📅 Berdiri 2005' ) . '</div>'
																		. '<div>' . $form->text( 'chip_semester' )->setLabel( 'Chip — Durasi' )->setAttribute( 'placeholder', 'mis. 🕐 8 Semester' ) . '</div>'
																		. '</div>'
																		. '</div>'
													) );

				/* ─── TAB 2: Hero & Sidebar ─── */
				$tabs->tab( 'Hero & Sidebar', 'dashicons-cover-image', array(
					'<div style="padding:1rem;background:#f6f8fc;border-radius:8px;margin-bottom:1rem">'
					. '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;margin-bottom:1rem">'
					. '<div>' . $form->text( 'akreditasi_value' )->setLabel( 'Akreditasi BAN-PT (kode)' )->setAttribute( 'placeholder', 'mis. B / Unggul / A' ) . '</div>'
					. '<div>' . $form->text( 'akreditasi_sub' )->setLabel( 'Status Akreditasi' )->setAttribute( 'placeholder', 'Terakreditasi Baik' ) . '</div>'
					. '<div>' . $form->text( 'sk_akreditasi' )->setLabel( 'No. SK Akreditasi' )->setAttribute( 'placeholder', 'mis. 5828/D/T/K-I/2011' ) . '</div>'
					. '<div>' . $form->select( '_use_default_content' )->setLabel( 'Gunakan Konten Default BDP (LEGACY — tidak digunakan lagi)' )->setOptions( array( '1' => 'Ya (fallback BDP)', '0' => 'Tidak (kosong jika belum diisi)' ) )->setAttribute( 'style', 'width:100%' ) . '</div>'
					. '</div>'
					. '<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">'
					. '<div>' . $form->text( 'pmb_label' )->setLabel( 'PMB — Label' )->setAttribute( 'placeholder', 'Brosur / Formulir PMB' ) . '</div>'
					. '<div>' . $form->file( 'pmb_url' )->setLabel( 'PMB — File Brosur/Formulir' )->setHelp( 'PDF, gambar, atau dokumen. Disarankan PDF.' ) . '</div>'
					. '</div>'
					. '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;margin-top:1rem">'
					. '<div>' . $form->textarea( 'hero_subtitle' )->setLabel( 'Hero Subtitle' )->setAttribute( 'rows', 3 )->setAttribute( 'placeholder', 'Mencetak Sarjana Terapan…' ) . '</div>'
					. '<div>' . $form->text( 'hero_badge_icon' )->setLabel( 'Hero Badge Icon (bi-* atau emoji)' )->setHelp( 'Isi class Bootstrap Icons (mis. bi-tree) untuk icon, atau emoji legacy. Render mendukung keduanya.' )->setAttribute( 'placeholder', 'bi-tree' ) . '</div>'
					. '<div>' . $form->text( 'hero_badge_text' )->setLabel( 'Hero Badge Text' )->setAttribute( 'placeholder', 'D4 · Fakultas Vokasi' ) . '</div>'
					. '</div>'
					. '</div>'
					. '<div style="padding:1rem;background:#eef7ff;border-radius:8px;border-left:3px solid #0d6efd;margin-bottom:1rem">'
					. '<h4 style="margin:.2rem 0 .6rem">📰 Panel Berita &amp; Kegiatan Prodi</h4>'
					. '<p style="margin:0 0 .8rem;font-size:.85em;color:#666">Pilih kategori berita (taxonomy <strong>Kategori</strong> dari post type <strong>Post</strong>) yang tampil di panel <em>Kegiatan Prodi</em> pada halaman prodi. Ketik untuk mencari, lalu klik hasilnya untuk menambah (bisa lebih dari satu, urutkan/drag untuk prioritas). <strong>Kosongkan = 3 berita terbaru</strong> (tanpa filter kategori).</p>'
					. '<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">'
					. '<div>' . $form->search( 'berita_kategori' )->setLabel( 'Kategori Berita' )->multiple()->setTaxonomyOptions( 'category' ) . '</div>'
					. '<div>' . $form->number( 'berita_jumlah' )->setLabel( 'Jumlah Berita' )->setAttribute( 'min', '1' )->setAttribute( 'max', '12' )->setAttribute( 'placeholder', '3' )->setHelp( 'Kosongkan untuk 3 berita terbaru.' ) . '</div>'
					. '</div>'
					. '</div>'
					. '<div style="padding:1rem;background:#fff8e1;border-radius:8px;border-left:3px solid #f0b429">'
					. '<h4 style="margin:.2rem 0 .6rem">🏛️ Struktur Organisasi (Opsional — Gambar Alternatif)</h4>'
					. '<p style="margin:0 0 .8rem;font-size:.85em;color:#666">Upload gambar bagan struktur organisasi dari Media Library. <strong>Jika diisi, gambar ini akan menggantikan bagan default (pohon)</strong> di section Struktur Organisasi. Kosongkan untuk tetap pakai bagan default.</p>'
					. $form->image( 'struktur_organisasi_image' )->setLabel( 'Gambar Struktur Organisasi' )->setHelp( 'Format: PNG / JPG / SVG. Disarankan rasio landscape & lebar minimal 1000 px agar tajam.' )
					. '</div>'
				) );

				/* ─── TAB 3: Profil & Visi ─── */
				$tabs->tab( 'Profil & Visi', 'dashicons-text-page', array(
					'<div style="margin-bottom:.6rem">' . $form->wpEditor( 'profil' )->setLabel( 'Profil Singkat' )->setHelp( 'Tampil di panel Profil Prodi, di atas Sejarah & Timeline.' ) . '</div>'
					. '<div style="margin-bottom:.6rem">' . $form->wpEditor( 'visi' )->setLabel( 'Visi (rich text)' )->setHelp( 'Tampil di panel Visi & Misi. Boleh pakai bold/paragraf.' ) . '</div>'
					. '<div style="margin-bottom:.8rem">' . $form->textarea( 'tujuan_text' )->setLabel( 'Tujuan & Kompetensi (LEGACY — tidak dirender, pakai repeater Tujuan di tab Misi & Lulusan)' ) . '</div>'
				) );

				/* ─── TAB 4: Misi, Tujuan, Kompetensi, Lulusan ─── */
				$misi = $form->repeater( 'misi' )->setFields(
					array(
						$form->text( 'Icon' )->setAttribute( 'placeholder', 'bi-bullseye' ),
						$form->textarea( 'Teks Misi' )->setAttribute( 'rows', 3 ),
					)
				);
				$tujuan = $form->repeater( 'tujuan' )->setFields(
					array(
						$form->text( 'Icon' )->setAttribute( 'placeholder', 'bi-bullseye' ),
						$form->textarea( 'Teks Tujuan' )->setAttribute( 'rows', 3 ),
					)
				);
				$kompetensi = $form->repeater( 'kompetensi' )->setFields(
					array(
						$form->text( 'Icon' )->setAttribute( 'placeholder', 'bi-patch-check' ),
						$form->text( 'Nama Kompetensi' )->setAttribute( 'placeholder', 'Pengelolaan Budidaya Tanaman Kelapa Sawit' ),
					)
				);
				$lulusan = $form->repeater( 'lulusan' )->setFields(
					array(
						$form->text( 'Icon' )->setAttribute( 'placeholder', 'bi-briefcase' ),
						$form->text( 'Nama Karir' )->setAttribute( 'placeholder', 'Asisten Kebun Kelapa Sawit' ),
						$form->textarea( 'Deskripsi' )->setAttribute( 'rows', 2 ),
					)
				);
				$tabs->tab( 'Misi & Lulusan', 'dashicons-list-view', array(
					'<h4 style="margin:.4rem 0 .5rem">📜 Misi (poin per item)</h4>' . $misi
					. '<h4 style="margin:1.2rem 0 .5rem">🎯 Tujuan Program Studi (icon + text)</h4>' . $tujuan
					. '<h4 style="margin:1.2rem 0 .5rem">✅ Kompetensi Utama (icon + nama)</h4>' . $kompetensi
					. '<h4 style="margin:1.2rem 0 .5rem">🎓 Profil Lulusan / Karir (icon + nama + deskripsi)</h4>' . $lulusan
				) );

				/* ─── TAB 5: Dosen ─── */
				$dosen = $form->repeater( 'dosen' )->setFields(
					array(
						$form->text( 'Inisial (2 huruf)' )->setAttribute( 'placeholder', 'AF' )->setAttribute( 'maxlength', 3 ),
						$form->text( 'Nama Lengkap + Gelar' )->setAttribute( 'placeholder', 'Dr. Ahmad Fauzi' ),
						$form->text( 'NIDN' )->setAttribute( 'placeholder', '0117128903' ),
						//$form->text( 'Universitas' )->setAttribute( 'placeholder', 'Institut Teknologi Bandung' ),
						$form->text( 'Bidang Keilmuan' )->setAttribute( 'placeholder', 'Pertanian' ),
						$form->select( 'Jenjang' )->setOptions( array( 's3' => 'S3 — Doktor', 's2' => 'S2 — Magister' ) )->setAttribute( 'style', 'width:100%' ),
						$form->image( 'Foto Dosen' )->setLabel( 'Foto Dosen (opsional)' )->setHelp( 'Pilih/upload foto dari Media Library. Jika kosong, kartu dosen menampilkan avatar inisial otomatis.' ),
					)
				);
				$tabs->tab( 'Dosen', 'dashicons-groups', array(
					'<h4 style="margin:.4rem 0 .5rem">👨‍🏫 Dosen &amp; Tenaga Pengajar</h4>' . $dosen
				) );

				/* ─── TAB 6: Mata Kuliah ─── */
				$mk = $form->repeater( 'mk_semesters' )->setFields(
					array(
						$form->text( 'No Semester' )->setAttribute( 'placeholder', '1' ),
						$form->select( 'Tipe Semester' )->setOptions( array( 'ganjil' => 'Ganjil', 'genap' => 'Genap' ) )->setAttribute( 'style', 'width:100%' ),
						$form->number( 'Total SKS' )->setAttribute( 'placeholder', '20' ),
						$form->repeater( 'Daftar Mata Kuliah' )->setFields(
							array(
								$form->text( 'Kode' )->setAttribute( 'placeholder', 'BDP101' ),
								$form->text( 'Nama Mata Kuliah' ),
								$form->number( 'SKS' )->setAttribute( 'placeholder', '3' ),
								$form->select( 'Jenis' )->setOptions( array( 'Wajib' => 'Wajib', 'Pilihan' => 'Pilihan', 'Praktik' => 'Praktik' ) )->setAttribute( 'style', 'width:100%' ),
							)
						),
					)
				);
				$tabs->tab( 'Mata Kuliah', 'dashicons-book', array(
					'<h4 style="margin:.4rem 0 .5rem">📚 Mata Kuliah per Semester</h4>' . $mk
					. '<p style="font-size:.85em;color:#666;margin-top:.6rem"><em>Catatan: isi repeater ini untuk menampilkan kurikulum dari data admin (per semester: No, Tipe Ganjil/Genap, Total SKS, daftar Kode/Nama/SKS/Jenis). Bila repeater <strong>kosong</strong>, template otomatis memuat fallback kurikulum BDP default (8 semester / 144 SKS) sehingga halaman tidak pernah kosong.</em></p>'
				) );

				/* ─── TAB 7: Sejarah & Timeline ─── */
				$timeline = $form->repeater( 'timeline' )->setFields(
					array(
						$form->text( 'Tahun' )->setAttribute( 'placeholder', '2005' ),
						$form->text( 'Judul' )->setAttribute( 'placeholder', 'Pendirian Prodi' ),
						$form->textarea( 'Deskripsi' )->setAttribute( 'rows', 2 ),
						$form->checkbox( 'Highlight' )->setLabel( 'Tandai sebagai milestone emas (gold)' )->setAttribute( 'style', 'width:auto' ),
					)
				);
				$tabs->tab( 'Sejarah & Timeline', 'dashicons-backup', array(
					'<div style="margin-bottom:.8rem">' . $form->wpEditor( 'sejarah' )->setLabel( '📖 Sejarah (rich text)' ) . '</div>'
					. '<h4 style="margin:1rem 0 .5rem">⏳ Timeline Sejarah (tahun + judul + deskripsi)</h4>' . $timeline
				) );

				/* ─── TAB 8: Fasilitas & Mitra ─── */
				$fas = $form->repeater( 'fasilitas' )->setFields(
					array(
						$form->text( 'Icon' )->setAttribute( 'placeholder', 'bi-buildings' ),
						$form->text( 'Nama Fasilitas' )->setAttribute( 'placeholder', 'Laboratorium Kultur Jaringan' ),
						$form->textarea( 'Deskripsi' )->setAttribute( 'rows', 2 ),
					)
				);
				$mit = $form->repeater( 'mitra' )->setFields(
					array(
						$form->text( 'Nama Mitra' )->setAttribute( 'placeholder', 'PT Perkebunan Nusantara III' ),
						$form->text( 'URL Logo' )->setAttribute( 'placeholder', 'https://...' ),
						$form->text( 'Website' )->setAttribute( 'placeholder', 'https://...' ),
					)
				);
				$tabs->tab( 'Fasilitas & Mitra', 'dashicons-building', array(
					'<h4 style="margin:.4rem 0 .5rem">🏢 Fasilitas (icon + nama + deskripsi)</h4>' . $fas
					. '<h4 style="margin:1.2rem 0 .5rem">🤝 Mitra Industri / Kerjasama</h4>' . $mit
				) );

				/* ─── TAB 9: Prestasi & Testimoni ─── */
				$pres = $form->repeater( 'prestasi' )->setFields(
					array(
						$form->text( 'Tahun' )->setAttribute( 'placeholder', '2024' ),
						$form->text( 'Judul Prestasi' ),
						$form->textarea( 'Deskripsi' )->setAttribute( 'rows', 2 ),
					)
				);
				$test = $form->repeater( 'testimoni' )->setFields(
					array(
						$form->text( 'Nama Alumni' ),
						$form->text( 'Angkatan / Profesi' )->setAttribute( 'placeholder', 'Angkatan 2018 · Manager PT X' ),
						$form->textarea( 'Quote Testimoni' )->setAttribute( 'rows', 3 ),
					)
				);
				$tabs->tab( 'Prestasi & Alumni', 'dashicons-awards', array(
					'<h4 style="margin:.4rem 0 .5rem">🏆 Prestasi Mahasiswa (tahun + judul + deskripsi)</h4>' . $pres
					. '<h4 style="margin:1.2rem 0 .5rem">💬 Testimoni Alumni (nama + angkatan + quote)</h4>' . $test
				) );

				/* ─── TAB 10: CPL ─── */
				$tabs->tab( 'Capaian Pembelajaran', 'dashicons-welcome-learn-more', array(
					'<p style="color:#666;margin-top:0">Kompetensi lulusan sesuai standar KKNI level 6 &amp; OBE.</p>'
					. '<div style="margin-bottom:.6rem">' . $form->wpEditor( 'cpl_pengetahuan' )->setLabel( '📚 Pengetahuan' ) . '</div>'
					. '<div style="margin-bottom:.6rem">' . $form->wpEditor( 'cpl_keterampilan' )->setLabel( '🛠️ Keterampilan Khusus' ) . '</div>'
					. '<div style="margin-bottom:.6rem">' . $form->wpEditor( 'cpl_sikap' )->setLabel( '🌟 Sikap &amp; Tanggung Jawab' ) . '</div>'
				) );

				$tabs->layoutLeftEnclosed()->render();
			}
		);

	tr_meta_box( 'Detail Permohonan' )
		->addPostType( 'permohonan_informasi' )
		->setCallback(
			function () {
				$form = \TypeRocket\Utility\Helper::form();
				echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">';
				echo '<div><label style="display:block;font-weight:600;margin-bottom:.4rem">Nama Lengkap</label>' . $form->text( 'nama' ) . '</div>';
				echo '<div><label style="display:block;font-weight:600;margin-bottom:.4rem">NIK</label>' . $form->text( 'nik' ) . '</div>';
				echo '<div><label style="display:block;font-weight:600;margin-bottom:.4rem">Email</label>' . $form->text( 'email' ) . '</div>';
				echo '<div><label style="display:block;font-weight:600;margin-bottom:.4rem">No. HP</label>' . $form->text( 'no_hp' ) . '</div>';
				echo '<div><label style="display:block;font-weight:600;margin-bottom:.4rem">Pekerjaan</label>' . $form->text( 'pekerjaan' ) . '</div>';
				echo '<div><label style="display:block;font-weight:600;margin-bottom:.4rem">Cara Penerimaan</label>' . $form->select( 'cara_penerimaan' )->setOptions( array( 'email' => 'Email', 'pos' => 'Pos', 'langsung' => 'Diambil Langsung' ) ) . '</div>';
				echo '</div>';
				echo '<div style="margin-top:.8rem"><label style="display:block;font-weight:600;margin-bottom:.4rem">Tujuan Permohonan Informasi</label>' . $form->textarea( 'tujuan' ) . '</div>';
			}
		);

	// ═══ META BOX — Detail Hibah ════════════════════════════
	tr_meta_box( 'Detail Hibah' )
		->addPostType( 'hibah' )
		->setCallback(
			function () {
				$form = \TypeRocket\Utility\Helper::form();
				$tabs = \TypeRocket\Elements\Tabs::new();

				/* ─── TAB 1: Info Dasar ─── */
				$tabs->tab( 'Info Dasar', 'dashicons-info', array(
					'<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">'
					. '<div>' . $form->select( 'status_hibah' )->setLabel( 'Status Event' )
						->setOptions( array(
							'aktif'   => 'Aktif (sedang dibuka)',
							'ditutup' => 'Ditutup',
							'arsip'   => 'Arsip',
						) )->setAttribute( 'style', 'width:100%' ) . '</div>'
					. '<div>' . $form->date( 'deadline' )->setLabel( 'Deadline Tanggal' )
						->setFormatYearMonthDay()
						->setHelp( 'Tanggal terakhir pendaftaran.' ) . '</div>'
					. '<div>' . $form->time( 'deadline_time' )->setLabel( 'Deadline Jam' )
						->setHelp( 'Opsional. Kosongkan = 23:59:59.' ) . '</div>'
					. '<div>' . $form->checkbox( 'allow_after_deadline' )
						->setLabel( 'Boleh Daftar Setelah Deadline' )
						->setHelp( 'Centang jika event ini TETAP menerima pendaftaran meski sudah lewat deadline (perpanjangan/darurat). Tidak dicentang = ikuti pengaturan global di LP2M → Settings.' )
						->setAttribute( 'style', 'width:auto' ) . '</div>'
					. '<div>' . $form->text( 'event_eyebrow' )->setLabel( 'Tahun Akademik' )
						->setAttribute( 'placeholder', 'mis. TA 2026/2027' ) . '</div>'
					. '<div>' . $form->text( 'dana_maks' )->setLabel( 'Dana Maksimal' )
						->setAttribute( 'placeholder', 'mis. 35000000' ) . '</div>'
					. '<div>' . $form->text( 'jumlah_tim_maks' )->setLabel( 'Jumlah Tim Maksimal' )
						->setAttribute( 'placeholder', 'mis. 3' ) . '</div>'
					. '<div>' . $form->search( 'program_studi_id' )->setPostTypeOptions( 'program_studi' )
						->setLabel( 'Program Studi Terkait' )
						->setHelp( 'Cari & pilih program studi (CPT). Simpan ID post.' ) . '</div>'
					. '</div>'
					. '<div style="margin-top:1rem">'
					. $form->textarea( 'info_tambahan' )->setLabel( 'Info Tambahan (satu per baris)' )
						->setAttribute( 'rows', 4 )->setAttribute( 'placeholder', "Maks. 3 anggota tim per usulan\nDana s.d. Rp 35 juta / skema penelitian" )
					. '</div>'
				) );

				/* ─── TAB 2: Timeline ─── */
				$timeline_rpt = $form->repeater( 'timeline_items' )->setFields(
					array(
						$form->date( 'Tanggal' )->setLabel( 'Tanggal' )->setFormatYearMonthDay()
							->setAttribute( 'placeholder', 'YYYY-MM-DD' ),
						$form->textarea( 'Deskripsi' )->setAttribute( 'rows', 2 )
							->setAttribute( 'placeholder', 'Sosialisasi & pembukaan pendaftaran usulan' ),
					)
				);
				$tabs->tab( 'Timeline', 'dashicons-backup', array(
					'<h4 style="margin:.4rem 0 .5rem">⏳ Timeline Event</h4>' . $timeline_rpt
				) );

				/* ─── TAB 3: Panduan & Template ─── */
				$tabs->tab( 'Panduan & Template', 'dashicons-media-document', array(
					'<div style="margin-bottom:1rem">'
					. '<h4 style="margin:.4rem 0 .5rem">📘 Panduan Penulisan (DOCX/PDF)</h4>'
					. $form->file( 'file_panduan' )->setLabel( 'Upload File Panduan' )
						->setHelp( 'File panduan penulisan proposal (DOCX/PDF). Field ini single-file. File tambahan dari dashboard LP2M tetap tersimpan & ditampilkan di situs.' )
					. itsi_hibah_metabox_file_note( 'file_panduan' )
					. '</div>'
					. '<div style="margin-bottom:1rem">'
					. '<h4 style="margin:.4rem 0 .5rem">📝 Template Dokumen (DOCX/XLSX)</h4>'
					. $form->file( 'file_template' )->setLabel( 'Upload File Template' )
						->setHelp( 'File template proposal/laporan yang siap diisi. Field ini single-file.' )
					. itsi_hibah_metabox_file_note( 'file_template' )
					. '</div>'
					. '<div style="margin-bottom:1rem">'
					. '<h4 style="margin:.4rem 0 .5rem">👥 Template Kelompok Keahlian (DOCX/PDF)</h4>'
					. $form->file( 'file_kelompok_keahlian' )->setLabel( 'Upload File Template Kelompok Keahlian' )
						->setHelp( 'File template/berkas kelompok keahlian yang siap diisi (DOCX/PDF). Field ini single-file.' )
					. itsi_hibah_metabox_file_note( 'file_kelompok_keahlian' )
					. '</div>'
				) );

				$tabs->layoutLeftEnclosed()->render();
			}
		);

	// Detail Pendaftaran — disatukan ke inc/lp2m/class-hibah-receiver.php
	// (register_tr_cpt_and_metabox) agar konsisten TypeRocket form + file
	// proposal sinkron TR ↔ REST. Dihapus dari functions.php untuk hindari
	// double metabox.
} );

/**
 * AJAX handler: create Permohonan Informasi from public form.
 *
 * Guards: nonce → form toggle → rate limit → validation.
 * Email To/From/Subject diatur dari pengaturan "Informasi Publik" (theme_mods).
 */
function itsi_submit_permohonan() {
	check_ajax_referer( 'itsi-permohonan', 'nonce' );

	$settings = itsi_ip_get_form_settings();

	// 1) Form non-aktif → tolak dengan 403.
	if ( ! $settings['form_enabled'] ) {
		wp_send_json_error( array( 'message' => 'Formulir permohonan informasi sedang ditutup. Silakan hubungi PPID secara langsung.' ), 403 );
	}

	// 2) Rate limit per IP.
	$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
	$key   = 'itsi_ip_rate_' . md5( $ip );
	$count = (int) get_transient( $key );
	if ( $count >= $settings['rate_max'] ) {
		wp_send_json_error(
			array(
				'message' => sprintf(
					'Terlalu banyak permohonan dari perangkat ini. Silakan coba lagi dalam %d menit.',
					$settings['rate_window']
				),
			),
			429
		);
	}
	set_transient( $key, $count + 1, $settings['rate_window'] * MINUTE_IN_SECONDS );

	// 3) Sanitasi input.
	$nama      = isset( $_POST['nama'] ) ? sanitize_text_field( wp_unslash( $_POST['nama'] ) ) : '';
	$nik       = isset( $_POST['nik'] ) ? sanitize_text_field( wp_unslash( $_POST['nik'] ) ) : '';
	$email     = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$no_hp     = isset( $_POST['no_hp'] ) ? sanitize_text_field( wp_unslash( $_POST['no_hp'] ) ) : '';
	$tujuan    = isset( $_POST['tujuan'] ) ? sanitize_textarea_field( wp_unslash( $_POST['tujuan'] ) ) : '';
	$pekerjaan = isset( $_POST['pekerjaan'] ) ? sanitize_text_field( wp_unslash( $_POST['pekerjaan'] ) ) : '';
	$deskripsi = isset( $_POST['deskripsi'] ) ? sanitize_textarea_field( wp_unslash( $_POST['deskripsi'] ) ) : '';
	$cara      = isset( $_POST['cara_penerimaan'] ) ? sanitize_text_field( wp_unslash( $_POST['cara_penerimaan'] ) ) : '';

	// 4) Validasi.
	$errors = array();
	if ( '' === $nama ) {
		$errors[] = 'Nama lengkap wajib diisi.';
	} elseif ( mb_strlen( $nama ) > 120 ) {
		$errors[] = 'Nama lengkap terlalu panjang (maks. 120 karakter).';
	}

	if ( '' === $nik ) {
		$errors[] = 'NIK wajib diisi.';
	} elseif ( 1 !== preg_match( '/^\d{16}$/', $nik ) ) {
		$errors[] = 'NIK harus 16 digit angka (sesuai KTP).';
	}

	if ( '' === $email ) {
		$errors[] = 'Alamat email wajib diisi.';
	} elseif ( ! is_email( $email ) ) {
		$errors[] = 'Format email tidak valid.';
	}

	if ( '' === $no_hp ) {
		$errors[] = 'Nomor HP/WhatsApp wajib diisi.';
	} elseif ( mb_strlen( $no_hp ) > 20 || 1 !== preg_match( '/^[0-9+()\-\s]+$/', $no_hp ) ) {
		$errors[] = 'Format nomor HP tidak valid.';
	}

	if ( '' === $deskripsi ) {
		$errors[] = 'Deskripsi informasi yang dimohon wajib diisi.';
	} elseif ( mb_strlen( $deskripsi ) > 2000 ) {
		$errors[] = 'Deskripsi terlalu panjang (maks. 2000 karakter).';
	}

	// Whitelist tujuan (opsional, tapi bila diisi harus salah satu dari daftar).
	$tujuan_whitelist = array(
		'Penelitian / Akademik',
		'Jurnalisme / Media',
		'Kebutuhan Hukum',
		'Pengawasan Publik',
		'Kepentingan Pribadi',
		'Lainnya',
	);
	if ( '' !== $tujuan && ! in_array( $tujuan, $tujuan_whitelist, true ) ) {
		$errors[] = 'Tujuan penggunaan informasi tidak valid.';
	}
	if ( mb_strlen( $tujuan ) > 200 ) {
		$errors[] = 'Tujuan terlalu panjang (maks. 200 karakter).';
	}

	// Whitelist cara penerimaan.
	if ( ! in_array( $cara, array( 'email', 'pos', 'langsung' ), true ) ) {
		$errors[] = 'Cara penerimaan tidak valid.';
	}

	if ( mb_strlen( $pekerjaan ) > 120 ) {
		$errors[] = 'Pekerjaan/instansi terlalu panjang (maks. 120 karakter).';
	}

	if ( ! empty( $errors ) ) {
		wp_send_json_error( array( 'message' => implode( ' ', $errors ) ), 400 );
	}

	$title = sprintf( 'Permohonan Informasi – %s', $nama );
	$body  = sprintf(
		"Nama: %s\nNIK: %s\nEmail: %s\nNo. HP: %s\nPekerjaan: %s\nCara Penerimaan: %s\n\nTujuan:\n%s\n\nDeskripsi:\n%s",
		$nama, $nik, $email, $no_hp, $pekerjaan, $cara, $tujuan, $deskripsi
	);

	$post_id = wp_insert_post(
		array(
			'post_type'    => 'permohonan_informasi',
			'post_status'  => 'private',
			'post_title'   => $title,
			'post_content' => $body,
		),
		true
	);

	if ( is_wp_error( $post_id ) ) {
		wp_send_json_error( array( 'message' => 'Gagal menyimpan permohonan: ' . $post_id->get_error_message() ), 500 );
	}

	update_post_meta( $post_id, 'nama', $nama );
	update_post_meta( $post_id, 'nik', $nik );
	update_post_meta( $post_id, 'email', $email );
	update_post_meta( $post_id, 'no_hp', $no_hp );
	update_post_meta( $post_id, 'tujuan', $tujuan );
	update_post_meta( $post_id, 'pekerjaan', $pekerjaan );
	update_post_meta( $post_id, 'cara_penerimaan', $cara );
	update_post_meta( $post_id, 'deskripsi', $deskripsi );

	// Email ke pengaturan: To/From/Subject dari theme_mods.
	$to       = $settings['email_to'];
	$subject  = $settings['email_subject'];
	$from     = $settings['email_from'];
	$headers  = array();
	if ( is_email( $from ) ) {
		$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$headers[] = 'From: ' . $site_name . ' <' . $from . '>';
	}
	$message = sprintf(
		"Permohonan informasi baru dari %s (%s) telah masuk.\n\nLihat di admin: %s",
		$nama,
		$email,
		admin_url( 'post.php?post=' . $post_id . '&action=edit' )
	);
	wp_mail( $to, $subject, $message, $headers );

	wp_send_json_success(
		array(
			'message' => 'Permohonan Anda berhasil dikirim. Tim PPID akan menindaklanjuti dalam 10 hari kerja.',
			'post_id' => $post_id,
		)
	);
}
add_action( 'wp_ajax_itsi_submit_permohonan', 'itsi_submit_permohonan' );
add_action( 'wp_ajax_nopriv_itsi_submit_permohonan', 'itsi_submit_permohonan' );

/**
 * Atomically bump a view counter stored in post meta.
 *
 * @param int    $post_id Post ID.
 * @param string $key     Meta key to increment.
 */
function itsi_bump_post_views( $post_id, $key ) {
	global $wpdb;

	if ( metadata_exists( 'post', $post_id, $key ) ) {
		// Atomic UPDATE — safe under concurrent requests.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta}
				 SET meta_value = CAST( meta_value AS UNSIGNED ) + 1
				 WHERE post_id = %d AND meta_key = %s",
				$post_id,
				$key
			)
		);
	} else {
		// First view — insert with `$unique = true` so the meta is never duplicated.
		add_post_meta( $post_id, $key, 1, true );
	}

	wp_cache_delete( $post_id, 'post_meta' );
}

/**
 * Classic server-side view counter.
 *
 * Runs on every single-post page load via `template_redirect` — no AJAX, no
 * JS. Every human visitor (including logged-in admins/editors) counts as one
 * view; bots / link-preview crawlers are excluded so the counter stays human.
 *
 * Writes both `_itsi_views` (canonical key, read by BeritaComponent) and
 * `post_views_count` (read by single.php, widgets, archive-berita.php,
 * search.php) so every consumer stays in sync.
 */
function itsi_count_post_views() {
	if ( ! is_singular( 'post' ) ) {
		return;
	}

	$post_id = (int) get_queried_object_id();
	$post    = $post_id ? get_post( $post_id ) : null;

	// Only count real, published articles — ignore other post types, drafts.
	if ( ! $post || 'post' !== $post->post_type || 'publish' !== $post->post_status ) {
		return;
	}

	// Skip known crawlers / link-preview bots (WhatsApp, Facebook, Google…)
	// so the counter stays human.
	if ( isset( $_SERVER['HTTP_USER_AGENT'] ) && preg_match( '/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|whatsapp|telegrambot|curl|wget|headless/i', $_SERVER['HTTP_USER_AGENT'] ) ) {
		return;
	}

	itsi_bump_post_views( $post_id, '_itsi_views' );
	itsi_bump_post_views( $post_id, 'post_views_count' );
}
add_action( 'template_redirect', 'itsi_count_post_views' );

/**
 * Prevent Cloudflare / proxy caches from serving single-post HTML from cache.
 *
 * The classic PHP view counter runs on `template_redirect` — it only fires
 * when WordPress actually renders the page. If the article HTML were served
 * straight from a page cache (Cloudflare), PHP never runs and the counter
 * would never increment. Sending no-cache headers on single posts tells any
 * shared cache to revalidate with the origin on every request.
 */
function itsi_single_post_no_cache() {
	if ( is_singular( 'post' ) ) {
		nocache_headers();
	}
}
add_action( 'send_headers', 'itsi_single_post_no_cache' );

/**
 * Format a view count for display.
 *
 * Examples:
 *   0      → "— views"
 *   1      → "1 view"
 *   1.234  → "1.234 views"
 *   10.234 → "10.2K"
 *   15.000 → "15K"
 *
 * @param int $n Raw view count.
 * @return string
 */
function itsi_format_views( $n ) {
	$n = max( 0, (int) $n );

	if ( $n === 0 ) {
		return '— views';
	}

	if ( $n >= 10000 ) {
		$k       = round( $n / 1000, 1 );
		$k_str   = number_format( $k, 1, '.', '' );
		if ( substr( $k_str, -2 ) === '.0' ) {
			$k_str = substr( $k_str, 0, -2 );
		}
		return $k_str . 'K';
	}

	return number_format_i18n( $n ) . ( 1 === $n ? ' view' : ' views' );
}

/**
 * Get the site logo URL with a sensible fallback to the bundled SVG.
 */
function itsi_get_logo_url() {
	$custom = get_theme_mod( 'custom_logo' );
	if ( $custom ) {
		$src = wp_get_attachment_image_src( $custom, 'full' );
		if ( $src ) {
			return $src[0];
		}
	}
	return get_template_directory_uri() . '/assets/logo.svg';
}

/**
 * Helper: normalize a repeater field value from TypeRocket components.
 *
 * TypeRocket's Matrix/Builder stores repeater data as a JSON-encoded string in
 * post meta. When rendered via `tr_components_field('builder')`, the value can
 * arrive as:
 *   - null / unset  -> use default
 *   - array         -> use as-is
 *   - JSON string   -> decode and use, fallback to default on failure
 *   - empty string  -> use default
 *
 * The `??` operator alone is not enough because `??` only triggers on null.
 *
 * @param mixed $value   The raw value from $data[...] (may be null, string, array).
 * @param array $default The fallback array to use when value is not a usable array.
 * @return array
 */
function itsi_repeater_value( $value, $default = array() ) {
	if ( is_array( $value ) ) {
		return $value;
	}
	if ( is_string( $value ) && '' !== $value ) {
		$decoded = json_decode( $value, true );
		if ( is_array( $decoded ) ) {
			return $decoded;
		}
		// TypeRocket may also use serialized PHP arrays in some storage paths.
		if ( function_exists( 'maybe_unserialize' ) ) {
			$unserialized = maybe_unserialize( $value );
			if ( is_array( $unserialized ) ) {
				return $unserialized;
			}
		}
	}
	return is_array( $default ) ? $default : array();
}

/**
 * Helper: normalize CTA icon list input.
 *
 * Accepts: array of strings, comma-separated string, or single string.
 * Returns: array of trimmed non-empty icon class strings.
 *
 * Used by HeroComponent and other matrix components that read
 * `cta_primary_icon` / `cta_secondary_icon` from builder data.
 *
 * @param mixed $raw The raw value (array | string | null).
 * @return array
 */
function itsi_normalize_icon_list( $raw ) {
	if ( is_array( $raw ) ) {
		$out = array();
		foreach ( $raw as $v ) {
			if ( is_string( $v ) || is_numeric( $v ) ) {
				$v = trim( (string) $v );
				if ( $v !== '' ) { $out[] = $v; }
			}
		}
		return $out;
	}
	if ( is_string( $raw ) ) {
		$parts = array_map( 'trim', explode( ',', $raw ) );
		return array_values( array_filter( $parts, static function( $v ) { return $v !== ''; } ) );
	}
	return array();
}

/**
 * Helper: latest "pengumuman" posts (for the hero card).
 *
 * Sources from the standard `post` post type and, when present, filters by the
 * `pengumuman` category (slug). If that category doesn't exist yet, falls back
 * to recent posts. Returns a `WP_Query` ready to be looped.
 *
 * @param int $count Number of posts to fetch.
 * @return \WP_Query
 */
function itsi_get_latest_pengumuman( $count = 3 ) {
	$count = max( 1, (int) $count );

	$args = array(
		'post_type'      => 'post',
		'posts_per_page' => $count,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'no_found_rows'  => true,
	);

	if ( function_exists( 'get_category_by_slug' ) ) {
		$cat = get_category_by_slug( 'pengumuman' );
		if ( $cat && ! is_wp_error( $cat ) ) {
			$args['cat'] = (int) $cat->term_id;
		}
	}

	return new WP_Query( $args );
}

/**
 * Inline CSS for Header & Top Menu → Brand Colors customizer settings.
 *
 * Emits a small <style> block in <head> AFTER style.css so the variables
 * override :root defaults. Defaults match :root values, so unset mods
 * produce no visual change.
 *
 * @return void
 */
function itsi_inline_brand_colors_css() {
	$topbar_bg  = get_theme_mod( 'itsi_color_topbar_bg', '#010D1E' );
	$navbar_bg  = get_theme_mod( 'itsi_color_navbar_bg', '#020C1C' );
	$navbar_alp = get_theme_mod( 'itsi_color_navbar_alpha', 0.88 );
	$pmb_from   = get_theme_mod( 'itsi_color_pmb_from', '#1459B3' );
	$pmb_to     = get_theme_mod( 'itsi_color_pmb_to', '#1E72D4' );

	// Defensive: sanitize_hex_color may return null/empty for invalid input.
	$topbar_bg = sanitize_hex_color( $topbar_bg ) ?: '#010D1E';
	$navbar_bg = sanitize_hex_color( $navbar_bg ) ?: '#020C1C';
	$pmb_from  = sanitize_hex_color( $pmb_from ) ?: '#1459B3';
	$pmb_to    = sanitize_hex_color( $pmb_to ) ?: '#1E72D4';
	$navbar_alp = is_numeric( $navbar_alp )
		? max( 0.0, min( 1.0, (float) $navbar_alp ) )
		: 0.88;

	// Convert hex to r,g,b for rgba() of navbar bg.
	$nav_r = hexdec( substr( $navbar_bg, 1, 2 ) );
	$nav_g = hexdec( substr( $navbar_bg, 3, 2 ) );
	$nav_b = hexdec( substr( $navbar_bg, 5, 2 ) );

	$css = sprintf(
		':root{--itsi-topbar-bg:%1$s;--itsi-navbar-bg-color:rgba(%2$d,%3$d,%4$d,%5$s);--itsi-pmb-from:%6$s;--itsi-pmb-to:%7$s;}',
		$topbar_bg,
		$nav_r,
		$nav_g,
		$nav_b,
		$navbar_alp,
		$pmb_from,
		$pmb_to
	);
	echo '<style id="itsi-brand-colors">' . $css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — pure hex/numbers, no user HTML.
}
add_action( 'wp_head', 'itsi_inline_brand_colors_css', 99 );

/**
 * Inject Microsoft Clarity tracking script into the WordPress admin <head>.
 *
 * Why: header.php only fires on the front-end (wp_head). Admin pages use
 *      WP core's own admin-header.php, so the Clarity <script> that lives
 *      in header.php does NOT appear in wp-admin. This hook re-emits the
 *      exact same tracking tag for the admin context so editor behaviour,
 *      settings changes, plugin UIs, and login flows are recorded in the
 *      same Clarity project.
 *
 * Scope: admin only (is_admin() guard). Not echoed on wp-login.php login_head
 *        is hooked separately so auth-page loads — including failed logins —
 *        are also tracked.
 */
function itsi_admin_clarity_script() {
    // No need for is_admin() guard here — admin_head only fires on wp-admin/*.
    ?>
    <!-- Microsoft Clarity (admin) — must match the project ID in header.php. -->
    <script type="text/javascript">
        (function(c,l,a,r,i,t,y){
            c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
            t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
            y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
        })(window, document, "clarity", "script", "xgef7q4mf8");
    </script>
    <?php
}
add_action( 'admin_head', 'itsi_admin_clarity_script' );

/**
 * Same Clarity tag, on the wp-login.php page (login_head fires inside
 * <head> of the standalone login screen, where admin_head is NOT called).
 * Keeps failed-login and auth-page visits in the recording.
 */
function itsi_login_clarity_script() {
    ?>
    <!-- Microsoft Clarity (login) — matches project ID in header.php + admin_head. -->
    <script type="text/javascript">
        (function(c,l,a,r,i,t,y){
            c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
            t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
            y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
        })(window, document, "clarity", "script", "xgef7q4mf8");
    </script>
    <?php
}
add_action( 'login_head', 'itsi_login_clarity_script' );

/**
 * Inject Google AdSense auto-ads script into the front-end <head>.
 *
 * Scope (intentionally narrow): the AdSense account only authorises ad
 * placement on article / post surfaces — never on the homepage, custom
 * post types like `program_studi` / `info_publik`, static `page`, or
 * admin / login screens.
 *
 * Show on:
 *   - is_singular('post')            — single post / artikel
 *   - is_home()                      — blog posts index (default home if no static front page)
 *   - is_post_type_archive('post')   — /berita/ archive (theme-rewritten from pagename=berita)
 *   - is_category()                  — category archives
 *   - is_tag()                       — tag archives
 *
 * Explicitly NOT on:
 *   - is_front_page()                — static homepage
 *   - is_singular('program_studi')   — prodi detail pages
 *   - is_singular('info_publik')     — informasi publik detail pages
 *   - is_singular('page')            — generic WP pages (profil, dll)
 *   - wp-admin / wp-login            — admin_head / login_head are not hooked, so
 *                                       this function never fires there
 *
 * Loaded async so it never blocks first paint. Hoisted to wp_head at
 * priority 10 (before the schema JSON-LD emitter at 20 and the brand-colors
 * inline <style> at 99).
 */
function itsi_adsense_script() {
	// Defensive guard — header.php's inline Clarity block has a fallback
	// for the admin context via admin_head; we don't echo here because
	// wp_head does not fire on wp-admin anyway. Still: bail explicitly if
	// somehow reached outside the front-end.
	if ( ! function_exists( 'is_singular' ) || is_admin() ) {
		return;
	}

	$show_on_post_surface = is_singular( 'post' )
		|| is_home()
		|| is_post_type_archive( 'post' )
		|| is_category()
		|| is_tag();

	if ( ! $show_on_post_surface ) {
		return;
	}

	?>
	<!-- Google AdSense (auto ads) — restricted to post / berita surfaces. -->
	<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-3996303435900501"
		crossorigin="anonymous"></script>
	<?php
}
add_action( 'wp_head', 'itsi_adsense_script', 10 );

/**
 * Load the ITSI admin sidebar menu (top-level "ITSI" + placeholder settings page).
 *
 * Separate file keeps functions.php from ballooning as we migrate customizer
 * sections into admin pages. Required here — not autoloaded — so it runs in the
 * admin context only when wp-admin/admin.php loads.
 */
require_once get_template_directory() . '/inc/admin-menu.php';

/**
 * Load schema.org JSON-LD emitter (EducationalOrganization + Article/WebPage/Course).
 * Hooked to wp_head at priority 20 (after Clarity at 1, after Clarity inline at 99).
 */
require_once get_template_directory() . '/inc/schema.php';

/**
 * Fakultas taxonomy term meta: image picker di form Add/Edit term.
 *
 * Disimpan sebagai attachment ID di term meta `fakultas_icon_image`.
 * Dipakai oleh ProdiComponent::render() sebagai icon fakultas di section
 * "Program Studi" homepage.
 *
 * Pattern ini native WP — tidak bergantung pada TypeRocket form API karena
 * TR Taxonomy model tidak expose image field secara langsung di form add/edit
 * term. Kita render manual pakai wp.media() (sudah ada di admin).
 */
function itsi_fakultas_icon_form_field( $term = null ) {
	$icon_id = 0;
	if ( $term && isset( $term->term_id ) ) {
		$icon_id = (int) get_term_meta( $term->term_id, 'fakultas_icon_image', true );
	}
	$icon_url = $icon_id > 0 ? (string) wp_get_attachment_url( $icon_id ) : '';
	?>
	<tr class="form-field itsi-fakultas-icon-wrap">
		<th scope="row" valign="top"><label for="itsi_fakultas_icon_image"><?php esc_html_e( 'Icon Fakultas (gambar)', 'itsi' ); ?></label></th>
		<td>
			<input type="hidden" name="itsi_fakultas_icon_image" id="itsi_fakultas_icon_image" value="<?php echo esc_attr( $icon_id > 0 ? (string) $icon_id : '' ); ?>" />
			<div id="itsi-fakultas-icon-preview" style="margin-bottom:.6rem<?php echo $icon_url === '' ? ';display:none' : ''; ?>">
				<img src="<?php echo esc_url( $icon_url ); ?>" alt="" style="max-width:80px;max-height:80px;border:1px solid #ddd;border-radius:4px;padding:4px;background:#fff" />
			</div>
			<button type="button" class="button" id="itsi-fakultas-icon-upload">
				<?php echo $icon_id > 0 ? esc_html__( 'Ganti gambar', 'itsi' ) : esc_html__( 'Pilih gambar', 'itsi' ); ?>
			</button>
			<button type="button" class="button" id="itsi-fakultas-icon-clear" style="<?php echo $icon_id > 0 ? '' : 'display:none'; ?>">
				<?php esc_html_e( 'Hapus', 'itsi' ); ?>
			</button>
			<p class="description"><?php esc_html_e( 'Ditampilkan sebagai icon fakultas (~48×48 px) di section "Program Studi". Kosongkan jika tidak ada.', 'itsi' ); ?></p>
		</td>
	</tr>
	<?php
}
add_action( 'fakultas_add_form_fields', 'itsi_fakultas_icon_form_field', 10, 1 );
add_action( 'fakultas_edit_form_fields', 'itsi_fakultas_icon_form_field', 10, 1 );

/**
 * Simpan term meta fakultas_icon_image. Validasi: harus numeric attachment ID.
 */
function itsi_fakultas_icon_save( $term_id ) {
	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}
	// posted as POST[itsi_fakultas_icon_image]
	$raw = isset( $_POST['itsi_fakultas_icon_image'] ) ? sanitize_text_field( wp_unslash( $_POST['itsi_fakultas_icon_image'] ) ) : '';
	if ( $raw === '' ) {
		delete_term_meta( $term_id, 'fakultas_icon_image' );
		return;
	}
	if ( ! is_numeric( $raw ) ) {
		return;
	}
	$att_id = (int) $raw;
	// Verify it's a real attachment — wp_get_attachment_url returns false for non-media posts.
	if ( ! wp_get_attachment_url( $att_id ) ) {
		delete_term_meta( $term_id, 'fakultas_icon_image' );
		return;
	}
	update_term_meta( $term_id, 'fakultas_icon_image', $att_id );
}
add_action( 'created_fakultas', 'itsi_fakultas_icon_save', 10, 1 );
add_action( 'edited_fakultas', 'itsi_fakultas_icon_save', 10, 1 );

/**
 * Print wp.media picker JS di footer halaman taxonomy fakultas.
 * Lebih reliable daripada wp_add_inline_script yang bergantung pada handle script
 * yang mungkin tidak di-enqueue di halaman taxonomy.
 */
function itsi_fakultas_icon_admin_print_js() {
	$screen = get_current_screen();
	if ( ! $screen || $screen->taxonomy !== 'fakultas' ) {
		return;
	}
	wp_enqueue_media();
	?>
	<script type="text/javascript">
	(function($){
		$(function(){
			if (typeof wp === 'undefined' || !wp.media) { return; }
			$('#itsi-fakultas-icon-upload').on('click', function(e){
				e.preventDefault();
				var frame = wp.media({ title: 'Pilih Icon Fakultas', button: { text: 'Pilih' }, multiple: false, library: { type: 'image' } });
				frame.on('select', function(){
					var att = frame.state().get('selection').first().toJSON();
					$('#itsi_fakultas_icon_image').val(att.id);
					$('#itsi-fakultas-icon-preview img').attr('src', att.url);
					$('#itsi-fakultas-icon-preview').show();
					$('#itsi-fakultas-icon-clear').show();
					$('#itsi-fakultas-icon-upload').text('Ganti gambar');
				});
				frame.open();
			});
			$('#itsi-fakultas-icon-clear').on('click', function(e){
				e.preventDefault();
				$('#itsi_fakultas_icon_image').val('');
				$('#itsi-fakultas-icon-preview').hide();
				$('#itsi-fakultas-icon-clear').hide();
				$('#itsi-fakultas-icon-upload').text('Pilih gambar');
			});
		});
	})(jQuery);
	</script>
	<?php
}
add_action( 'admin_print_footer_scripts', 'itsi_fakultas_icon_admin_print_js' );

/**
 * Kategori Informasi taxonomy term meta: image picker di form Add/Edit term.
 *
 * Disimpan sebagai attachment ID di term meta `kategori_info_icon`.
 * Dipakai oleh archive-info_publik.php sebagai icon kategori (pengganti emoji)
 * di filter bar + badge dokumen. Pola sama persis dengan fakultas_icon_image.
 */
function itsi_kategori_info_icon_form_field( $term = null ) {
	$icon_id = 0;
	if ( $term && isset( $term->term_id ) ) {
		$icon_id = (int) get_term_meta( $term->term_id, 'kategori_info_icon', true );
	}
	$icon_url = $icon_id > 0 ? (string) wp_get_attachment_url( $icon_id ) : '';
	?>
	<tr class="form-field itsi-katinfo-icon-wrap">
		<th scope="row" valign="top"><label for="itsi_kategori_info_icon"><?php esc_html_e( 'Icon Kategori (gambar)', 'itsi' ); ?></label></th>
		<td>
			<input type="hidden" name="itsi_kategori_info_icon" id="itsi_kategori_info_icon" value="<?php echo esc_attr( $icon_id > 0 ? (string) $icon_id : '' ); ?>" />
			<div id="itsi-katinfo-icon-preview" style="margin-bottom:.6rem<?php echo $icon_url === '' ? ';display:none' : ''; ?>">
				<img src="<?php echo esc_url( $icon_url ); ?>" alt="" style="max-width:80px;max-height:80px;border:1px solid #ddd;border-radius:4px;padding:4px;background:#fff" />
			</div>
			<button type="button" class="button" id="itsi-katinfo-icon-upload">
				<?php echo $icon_id > 0 ? esc_html__( 'Ganti gambar', 'itsi' ) : esc_html__( 'Pilih gambar', 'itsi' ); ?>
			</button>
			<button type="button" class="button" id="itsi-katinfo-icon-clear" style="<?php echo $icon_id > 0 ? '' : 'display:none'; ?>">
				<?php esc_html_e( 'Hapus', 'itsi' ); ?>
			</button>
			<p class="description"><?php esc_html_e( 'Ditampilkan sebagai icon kategori di halaman Informasi Publik (menggantikan emoji). Kosongkan jika tidak ada.', 'itsi' ); ?></p>
		</td>
	</tr>
	<?php
}
add_action( 'kategori_info_add_form_fields', 'itsi_kategori_info_icon_form_field', 10, 1 );
add_action( 'kategori_info_edit_form_fields', 'itsi_kategori_info_icon_form_field', 10, 1 );

/**
 * Simpan term meta kategori_info_icon. Validasi: harus numeric attachment ID.
 */
function itsi_kategori_info_icon_save( $term_id ) {
	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}
	$raw = isset( $_POST['itsi_kategori_info_icon'] ) ? sanitize_text_field( wp_unslash( $_POST['itsi_kategori_info_icon'] ) ) : '';
	if ( $raw === '' ) {
		delete_term_meta( $term_id, 'kategori_info_icon' );
		return;
	}
	if ( ! is_numeric( $raw ) ) {
		return;
	}
	$att_id = (int) $raw;
	if ( ! wp_get_attachment_url( $att_id ) ) {
		delete_term_meta( $term_id, 'kategori_info_icon' );
		return;
	}
	update_term_meta( $term_id, 'kategori_info_icon', $att_id );
}
add_action( 'created_kategori_info', 'itsi_kategori_info_icon_save', 10, 1 );
add_action( 'edited_kategori_info', 'itsi_kategori_info_icon_save', 10, 1 );

/**
 * Print wp.media picker JS di footer halaman taxonomy kategori_info.
 */
function itsi_kategori_info_icon_admin_print_js() {
	$screen = get_current_screen();
	if ( ! $screen || $screen->taxonomy !== 'kategori_info' ) {
		return;
	}
	wp_enqueue_media();
	?>
	<script type="text/javascript">
	(function($){
		$(function(){
			if (typeof wp === 'undefined' || !wp.media) { return; }
			$('#itsi-katinfo-icon-upload').on('click', function(e){
				e.preventDefault();
				var frame = wp.media({ title: 'Pilih Icon Kategori', button: { text: 'Pilih' }, multiple: false, library: { type: 'image' } });
				frame.on('select', function(){
					var att = frame.state().get('selection').first().toJSON();
					$('#itsi_kategori_info_icon').val(att.id);
					$('#itsi-katinfo-icon-preview img').attr('src', att.url);
					$('#itsi-katinfo-icon-preview').show();
					$('#itsi-katinfo-icon-clear').show();
					$('#itsi-katinfo-icon-upload').text('Ganti gambar');
				});
				frame.open();
			});
			$('#itsi-katinfo-icon-clear').on('click', function(e){
				e.preventDefault();
				$('#itsi_kategori_info_icon').val('');
				$('#itsi-katinfo-icon-preview').hide();
				$('#itsi-katinfo-icon-clear').hide();
				$('#itsi-katinfo-icon-upload').text('Pilih gambar');
			});
		});
	})(jQuery);
	</script>
	<?php
}
add_action( 'admin_print_footer_scripts', 'itsi_kategori_info_icon_admin_print_js' );

/**
 * One-shot auto-populate: assign default widgets to the 3 archive-berita sidebar
 * areas so the sidebar renders out-of-the-box without manual admin setup.
 */
function itsi_archive_berita_widgets_autopopulate() {
	if ( false !== get_option( 'itsi_archive_berita_autopopulated', false ) ) {
		return;
	}

	$sidebars = get_option( 'sidebars_widgets', null );
	if ( null === $sidebars || ! is_array( $sidebars ) ) {
		$sidebars = array();
	}

	// Create ITSI_CategoryFilter_Widget instance.
	$cat_filter_instances = get_option( 'widget_itsi_category_filter_widget', array() );
	if ( ! is_array( $cat_filter_instances ) ) {
		$cat_filter_instances = array();
	}
	if ( ! isset( $cat_filter_instances['_multiwidget'] ) ) {
		$cat_filter_instances['_multiwidget'] = 1;
	}
	$next_id = 1;
	while ( isset( $cat_filter_instances[ $next_id ] ) ) {
		$next_id++;
	}
	if ( ! isset( $cat_filter_instances[ $next_id ] ) ) {
		$cat_filter_instances[ $next_id ] = array(
			'title' => 'Filter Kategori',
			'count' => 12,
		);
		update_option( 'widget_itsi_category_filter_widget', $cat_filter_instances );
	}
	$cat_filter_widget_id = 'itsi_category_filter_widget-' . $next_id;

	// Reuse or create ITSI_Popular_Widget instance.
	$pop_instances = get_option( 'widget_itsi_popular_widget', array() );
	$pop_widget_id  = null;
	if ( is_array( $pop_instances ) && ! empty( $pop_instances ) ) {
		foreach ( $pop_instances as $id => $inst ) {
			if ( '_multiwidget' === $id ) { continue; }
			if ( is_array( $inst ) ) {
				$pop_widget_id = 'itsi_popular_widget-' . $id;
				break;
			}
		}
	}

	// Assign widgets to sidebar areas.
	$targets = array(
		'itsi_archive_berita_widget_filter'  => $cat_filter_widget_id,
		'itsi_archive_berita_widget_popular' => $pop_widget_id,
	);
	foreach ( $targets as $sidebar_id => $widget_id ) {
		if ( null === $widget_id ) { continue; }
		if ( ! isset( $sidebars[ $sidebar_id ] ) || ! is_array( $sidebars[ $sidebar_id ] ) || empty( $sidebars[ $sidebar_id ] ) ) {
			$sidebars[ $sidebar_id ] = array( $widget_id );
		}
	}

	update_option( 'sidebars_widgets', $sidebars );
	update_option( 'itsi_archive_berita_autopopulated', 1 );
}
add_action( 'init', 'itsi_archive_berita_widgets_autopopulate', 11 );

/**
 * One-shot auto-populate: kalau tab Schema / SEO belum pernah di-simpan admin,
 * scrape halaman Kontak (ID 155) sebagai default. Hanya jalan sekali — setelah
 * admin simpan tab Schema, nilai mereka yang dipakai.
 *
 * Trigger:
 *   - front-end load pertama setelah theme update (init, priority 10)
 *   - hanya jalan kalau itsi_schema_org_name belum ada
 *
 * Source data (halaman Kontak):
 *   - Alamat: "Jl. Rumah Sakit Haji (Jl. Willem Iskandar) Komplek PT LPP Agro
 *     Nusantara, Medan Estate, Deli Serdang, Sumatera Utara 20371"
 *   - Phone: (061) 6637060
 *   - Email: medan@itsi.ac.id
 *   - Social: Facebook + Instagram + YouTube + TikTok (sudah lengkap di halaman)
 */
function itsi_schema_autopopulate_from_kontak() {
	// Guard: kalau org_name sudah ada (admin pernah simpan), skip total.
	if ( false !== get_theme_mod( 'itsi_schema_org_name', false ) ) {
		return;
	}

	// Default values ini dari scraping halaman Kontak (ID 155) 2026-07.
	// Override kapan saja via /wp-admin/admin.php?page=itsi-settings tab Schema.
	$defaults = array(
		'itsi_schema_org_name'        => 'Institut Teknologi Sawit Indonesia',
		'itsi_schema_org_alt_name'    => 'ITSI',
		'itsi_schema_street'          => 'Jl. Rumah Sakit Haji (Jl. Willem Iskandar) Komplek PT LPP Agro Nusantara',
		'itsi_schema_city'            => 'Medan Estate',
		'itsi_schema_region'          => 'Sumatera Utara',
		'itsi_schema_postal'          => '20371',
		'itsi_schema_country'         => 'ID',
		'itsi_schema_phone'           => '(061) 6637060',
		'itsi_schema_email'           => 'medan@itsi.ac.id',
		'itsi_schema_social_facebook' => 'https://www.facebook.com/itsimedan21',
		'itsi_schema_social_instagram'=> 'https://instagram.com/itsimedan',
		'itsi_schema_social_youtube'  => 'https://www.youtube.com/channel/UCsb3ihtJSWGoQbi5uwUdaiw',
		'itsi_schema_social_tiktok'   => 'https://vt.tiktok.com/ZSewMkcDm',
	);
	foreach ( $defaults as $key => $val ) {
		// Hanya set kalau benar-benar belum ada. Kalau ada string kosong (admin
		// pernah simpan kosong), jangan override — hormati input admin.
		if ( null === get_theme_mod( $key, null ) ) {
			set_theme_mod( $key, $val );
		}
	}
}
add_action( 'init', 'itsi_schema_autopopulate_from_kontak', 10 );

/**
 * Helper: list of Bootstrap Icons class names for the hero CTA icon picker.
 *
 * Used by HeroComponent::fields() to populate the `<select>` for
 * `cta_primary_icon` and `cta_secondary_icon`. The values are the actual
 * `<i>` class names (bi-<name>) that will be rendered on the front-end when
 * Bootstrap Icons CSS is loaded. Keys are also bi-<name>; the label is a
 * human-readable name (Indonesian).
 *
 * To add or remove icons: edit this map. The UI re-reads it on each admin
 * page load (no caching).
 *
 * @return array<string,string>
 */
function itsi_bootstrap_icons_map() {
	return array(
		'bi-arrow-right'             => 'Panah kanan (bi-arrow-right)',
		'bi-arrow-right-short'       => 'Panah kanan pendek (bi-arrow-right-short)',
		'bi-arrow-right-circle'      => 'Panah kanan lingkaran (bi-arrow-right-circle)',
		'bi-chevron-right'           => 'Chevron kanan (bi-chevron-right)',
		'bi-chevron-double-right'    => 'Chevron ganda kanan (bi-chevron-double-right)',
		'bi-arrow-up-right'          => 'Panah naik-kanan (bi-arrow-up-right)',
		'bi-box-arrow-up-right'      => 'Box arrow up-right (bi-box-arrow-up-right)',
		'bi-arrow-bar-right'         => 'Bar panah kanan (bi-arrow-bar-right)',
		'bi-arrow-clockwise'         => 'Panah searah jarum jam (bi-arrow-clockwise)',
		'bi-arrow-counterclockwise'  => 'Panah berlawanan jarum jam (bi-arrow-counterclockwise)',
		'bi-book'                    => 'Buku (bi-book)',
		'bi-mortarboard'             => 'Topi wisuda (bi-mortarboard)',
		'bi-building'                => 'Gedung (bi-building)',
		'bi-buildings'               => 'Gedung-gedung (bi-buildings)',
		'bi-easel'                   => 'Easel (bi-easel)',
		'bi-info-circle'             => 'Info lingkaran (bi-info-circle)',
		'bi-question-circle'         => 'Tanya lingkaran (bi-question-circle)',
		'bi-telephone'               => 'Telepon (bi-telephone)',
		'bi-envelope'                => 'Amplop (bi-envelope)',
		'bi-geo-alt'                 => 'Pin lokasi (bi-geo-alt)',
		'bi-people'                  => 'Orang-orang (bi-people)',
		'bi-person'                  => 'Orang (bi-person)',
		'bi-search'                  => 'Cari (bi-search)',
		'bi-newspaper'               => 'Koran (bi-newspaper)',
		'bi-file-earmark-text'       => 'File teks (bi-file-earmark-text)',
		'bi-download'                => 'Unduh (bi-download)',
		'bi-cloud-download'          => 'Unduh awan (bi-cloud-download)',
		'bi-play-circle'             => 'Putar lingkaran (bi-play-circle)',
		'bi-youtube'                 => 'YouTube (bi-youtube)',
		'bi-instagram'               => 'Instagram (bi-instagram)',
		'bi-facebook'                => 'Facebook (bi-facebook)',
		'bi-twitter-x'               => 'Twitter/X (bi-twitter-x)',
		'bi-whatsapp'                => 'WhatsApp (bi-whatsapp)',
		'bi-linkedin'                => 'LinkedIn (bi-linkedin)',
		'bi-globe'                   => 'Globe (bi-globe)',
		'bi-link-45deg'              => 'Tautan 45deg (bi-link-45deg)',
		'bi-share'                   => 'Bagikan (bi-share)',
		'bi-send'                    => 'Kirim (bi-send)',
		'bi-check-circle'            => 'Centang lingkaran (bi-check-circle)',
		'bi-check2-circle'           => 'Centang 2 lingkaran (bi-check2-circle)',
		'bi-hand-thumbs-up'          => 'Jempol (bi-hand-thumbs-up)',
		'bi-heart'                   => 'Hati (bi-heart)',
		'bi-star'                    => 'Bintang (bi-star)',
		'bi-trophy'                  => 'Trofi (bi-trophy)',
		'bi-award'                   => 'Penghargaan (bi-award)',
		'bi-lightbulb'               => 'Bohlam (bi-lightbulb)',
		'bi-graph-up'                => 'Grafik naik (bi-graph-up)',
		'bi-calendar-event'          => 'Kalender acara (bi-calendar-event)',
		'bi-broadcast'               => 'Siaran (bi-broadcast)',
		'bi-megaphone'               => 'Megaphone (bi-megaphone)',
		'bi-mic'                     => 'Mikrofon (bi-mic)',
	);
}

/**
 * Load custom nav-menu walkers so wp_nav_menu() output matches the theme's
 * CSS selectors (.nli, .nl-a, .dd, .dd-a for navbar; .mob-a for mobile).
 */
require_once get_template_directory() . '/inc/menu-walker.php';
