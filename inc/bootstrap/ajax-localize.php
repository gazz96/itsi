<?php
/**
 * Ship the admin-ajax URL + Permohonan nonce to the front-end script.
 *
 * Hooks wp_enqueue_scripts at priority 20, i.e. after itsi_scripts() in
 * inc/bootstrap/enqueue.php has registered the `itsi-main` handle.
 *
 * @package itsi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
