<?php
/**
 * Route the /berita path and the post archive to archive-berita.php.
 *
 * Builds pretty rewrites for /berita/<slug>/, suppresses WP's canonical redirect
 * on those URLs and forces WP_Query to resolve them as single posts / the post
 * archive so the hybrid featured + grid template renders.
 *
 * @package itsi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
