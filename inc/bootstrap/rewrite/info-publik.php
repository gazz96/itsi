<?php
/**
 * Pretty permalinks for the info_publik CPT: /info-publik/<slug>/ and
 * /info-publik/kategori/<term>/.
 *
 * Registers the rewrites, suppresses the canonical redirect and rewrites the
 * incoming request back to the CPT single / archive.
 *
 * @package itsi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
