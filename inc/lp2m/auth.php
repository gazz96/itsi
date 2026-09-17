<?php
/**
 * LP2M Auth — Fallback autentikasi REST dengan password akun.
 *
 * WordPress core hanya menerima Application Password via HTTP Basic Auth
 * di REST API. Filter ini menambahkan fallback: bila header Basic tidak
 * cocok dengan application password, coba autentikasi dengan username +
 * password akun biasa (wp_authenticate). App password yang sudah ada tetap
 * valid (hash terpisah) — filter ini hanya berjalan saat core belum
 * mengautentikasi request.
 *
 * @package itsi
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'rest_authentication_errors', 'itsi_lp2m_rest_auth_fallback', 200 );

/**
 * Fallback auth: username + password akun via Basic header.
 *
 * Core REST (WP ≥ 5.6) memproses application password lebih dulu pada
 * priority 10 (wp_authenticate_application_password). Filter ini berjalan
 * pada priority 200 — hanya jika belum ada user yang terautentikasi dan
 * request membawa kredensial Basic yang valid untuk password akun.
 *
 * @param true|\WP_Error|null $result Hasil autentikasi REST saat ini.
 * @return true|\WP_Error|null
 */
function itsi_lp2m_rest_auth_fallback( $result ) {
	// Sudah terautentikasi (cookie, app password, atau filter lain) — jangan ganggu.
	if ( ! is_wp_error( $result ) && is_user_logged_in() ) {
		return $result;
	}

	// Jangan menimpa error 401 dari core — biarkan tetap 401 bila fallback gagal.
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	// Baca kredensial Basic. Dukungan lintas server:
	//   Apache + mod_php: HTTP_AUTHORIZATION otomatis tersedia.
	//   Apache + PHP-FPM/CGI: header Authorization dibuang Apache (spek CGI) —
	//     tambahkan di .htaccess root WP:
	//     SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1
	//   nginx + fastcgi: REDIRECT_HTTP_AUTHORIZATION (harus dipasang di config).
	$auth_header = '';
	foreach ( array( 'HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION', 'PHP_AUTH_USER' ) as $key ) {
		if ( isset( $_SERVER[ $key ] ) && '' !== (string) $_SERVER[ $key ] ) {
			$auth_header = (string) $_SERVER[ $key ];
			break;
		}
	}
	if ( '' === $auth_header ) {
		return $result;
	}

	// Header dari nginx kadang berisi 'Basic <base64>'; PHP_AUTH_USER berisi user saja.
	if ( 'PHP_AUTH_USER' === $auth_header ) {
		$username = (string) $_SERVER['PHP_AUTH_USER'];
		$password = isset( $_SERVER['PHP_AUTH_PW'] ) ? (string) $_SERVER['PHP_AUTH_PW'] : '';
	} else {
		if ( 0 !== stripos( $auth_header, 'basic ' ) ) {
			return $result;
		}
		$decoded = base64_decode( substr( $auth_header, 6 ), true );
		if ( false === $decoded || false === strpos( $decoded, ':' ) ) {
			return $result;
		}
		list( $username, $password ) = explode( ':', $decoded, 2 );
	}

	if ( '' === $username || '' === $password ) {
		return $result;
	}

	$user = wp_authenticate( $username, $password );

	if ( is_wp_error( $user ) || ! $user instanceof WP_User ) {
		// Jangan bocorkan detail; core akan mengembalikan 401 (atau kredensial
		// app password diproses lebih dulu oleh core).
		return $result;
	}

	wp_set_current_user( $user->ID );

	return $result;
}
