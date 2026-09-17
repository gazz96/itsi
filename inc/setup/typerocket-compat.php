<?php
/**
 * ITSI Theme — TypeRocket jQuery 3.x compatibility shim (admin).
 *
 * TypeRocket v6 (page builder) core.js uses deprecated jQuery static APIs
 * (`$.isFunction`, `$.type`, `$.trim`, `$.parseJSON`, `$.now`) that trigger
 * `QMIGRATE: ... is deprecated` warnings under WordPress 6.9.1 (jQuery 3.7.1
 * + jquery-migrate 3.4.1) — and the page builder appears unresponsive when
 * clicking components.
 *
 * Instead of patching the TypeRocket plugin (which is shared with other
 * environments), this theme enqueues a tiny compatibility shim that loads
 * AFTER jquery-migrate and BEFORE TypeRocket's core.js (which is printed in
 * the footer). The shim mutes migrate warnings and cleanly re-implements the
 * removed APIs, so the plugin source stays 100% untouched.
 *
 * @package itsi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue the jQuery compatibility shim on every admin screen.
 *
 * TypeRocket enqueues `core.js` via `admin_footer` (System::addBottomJs),
 * unconditionally across the admin. We enqueue in the header with the
 * `jquery` dependency so the load order is guaranteed:
 *   jquery-core → jquery-migrate → typerocket-compat → (footer) core.js
 *
 * Priority 999 runs after TypeRocket has registered its own assets but the
 * header script still prints before the footer core.js regardless.
 *
 * @return void
 */
function itsi_typerocket_compat_assets() {
	$file = get_template_directory() . '/js/typerocket-compat.js';
	$ver  = file_exists( $file ) ? (string) filemtime( $file ) : '1.0.0';

	wp_enqueue_script(
		'itsi-tr-compat',
		get_template_directory_uri() . '/js/typerocket-compat.js',
		array( 'jquery' ),
		$ver,
		false // header — must run before TypeRocket core.js (footer).
	);
}
add_action( 'admin_enqueue_scripts', 'itsi_typerocket_compat_assets', 999 );
