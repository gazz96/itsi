<?php
/**
 * Pretty permalinks for the program_studi CPT: /program-studi/<slug>/.
 *
 * Registers the rewrite, suppresses the /?post_type=... canonical redirect and
 * rewrites the incoming request back to the CPT single.
 *
 * @package itsi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
