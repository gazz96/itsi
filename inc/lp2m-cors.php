<?php
/**
 * LP2M CORS — izinkan akses lintas-origin untuk SPA LP2M.
 *
 * WordPress core hanya mengirim header `Access-Control-Allow-Origin` bila
 * origin request cocok dengan home/siteurl (same-origin). SPA LP2M berjalan
 * di domain terpisah (lp2m.bagistudio.com, lp2m-102.pages.dev, lp2m.itsi.ac.id)
 * sehingga semua request /wp-json lintas-origin diblokir browser tanpa header
 * CORS. File ini menambahkan header CORS untuk origin LP2M yang terdaftar
 * (allowlist — bukan `*`, agar kredensial Basic auth tidak bocor ke origin
 * asing), termasuk pada response error/404 dini via `rest_pre_serve_request`.
 *
 * @package itsi
 */

defined( 'ABSPATH' ) || exit;

/**
 * Daftar origin LP2M yang diizinkan akses lintas-origin.
 *
 * @return string[]
 */
function itsi_lp2m_cors_allowed_origins() {
	return array(
		'https://lp2m.bagistudio.com',
		'https://lp2m-102.pages.dev',
		'https://lp2m.itsi.ac.id',
		'http://localhost:5173',
		'https://localhost:5173',
	);
}

/**
 * Kirim header CORS bila origin request terdaftar.
 * Dipakai di rest_pre_serve_request (semua response REST, termasuk error dini)
 * dan rest_send_cors_headers (response sukses core).
 */
function itsi_lp2m_send_cors_headers( $origin ) {
	if ( ! is_string( $origin ) || '' === $origin ) {
		return;
	}
	if ( ! in_array( $origin, itsi_lp2m_cors_allowed_origins(), true ) ) {
		return;
	}

	header( 'Access-Control-Allow-Origin: ' . $origin );
	header( 'Vary: Origin' );
	header( 'Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS' );
	// Authorization penting untuk preflight Basic auth (dashboard admin).
	header( 'Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce' );
	header( 'Access-Control-Max-Age: 86400' );

	if ( 'OPTIONS' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		// Preflight: cukup header, tanpa body.
		status_header( 204 );
		nocache_headers();
	}
}

/**
 * Hook rest_pre_serve_request — pastikan header CORS keluar di SEMUA response
 * REST (termasuk 401/404 dini yang biasanya tidak membawa header CORS core).
 *
 * @param bool             $served  True kalau response sudah disajikan.
 * @param WP_HTTP_Response $result  Response REST.
 * @param WP_REST_Request  $request Request REST.
 * @return bool
 */
function itsi_lp2m_cors_pre_serve( $served, $result, $request ) {
	if ( ! $request instanceof WP_REST_Request ) {
		return $served;
	}
	$origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? (string) $_SERVER['HTTP_ORIGIN'] : '';
	itsi_lp2m_send_cors_headers( $origin );

	if ( 'OPTIONS' === $request->get_method() && in_array( $origin, itsi_lp2m_cors_allowed_origins(), true ) ) {
		// Preflight milik LP2M: berhenti di sini, jangan lanjut ke dispatch.
		return true;
	}

	return $served;
}
add_filter( 'rest_pre_serve_request', 'itsi_lp2m_cors_pre_serve', 10, 3 );

/**
 * Hook rest_send_cors_headers — tambah Authorization ke Allow-Headers yang
 * dikirim core (preflight Basic auth dashboard butuh ini).
 *
 * @param mixed $value  Nilai header yang akan dikirim core.
 * @param mixed $origin Origin dari get_http_origin().
 * @return mixed
 */
function itsi_lp2m_cors_send_headers( $value, $origin ) {
	$origin = is_string( $origin ) ? $origin : '';
	if ( ! in_array( $origin, itsi_lp2m_cors_allowed_origins(), true ) ) {
		return $value;
	}

	itsi_lp2m_send_cors_headers( $origin );

	return $value;
}
add_filter( 'rest_send_cors_headers', 'itsi_lp2m_cors_send_headers', 10, 2 );

/**
 * Hook rest_authentication_errors — jangan gagalkan preflight OPTIONS.
 * Berjalan sebelum fallback auth LP2M (priority 200) supaya preflight
 * (yang tidak membawa kredensial) tidak kena auth error.
 *
 * @param true|WP_Error|null $result Hasil autentikasi REST.
 * @return true|WP_Error|null
 */
function itsi_lp2m_cors_auth_options( $result ) {
	if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'OPTIONS' === $_SERVER['REQUEST_METHOD'] ) {
		$origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? (string) $_SERVER['HTTP_ORIGIN'] : '';
		if ( in_array( $origin, itsi_lp2m_cors_allowed_origins(), true ) ) {
			return true;
		}
	}
	return $result;
}
add_filter( 'rest_authentication_errors', 'itsi_lp2m_cors_auth_options', 5 );
