<?php
/**
 * itsi Theme Customizer
 *
 * @package itsi
 */

/**
 * Add postMessage support for site title and description for the Theme Customizer.
 *
 * The "Header & Top Menu" panel and its 4 sections (Top Bar – Kiri, Top Bar –
 * Kanan, Brand Bar, Brand Colors) were removed 2026-07-01. Theme mods previously
 * registered there (itsi_tb_left_*, itsi_tb_right_*, itsi_brand_*, itsi_color_*)
 * still live in wp_options and are read by header.php via get_theme_mod(). A new
 * admin sidebar menu "ITSI" (see inc/admin-menu.php) now hosts theme settings.
 *
 * @param WP_Customize_Manager $wp_customize Theme Customizer object.
 */
function itsi_customize_register( $wp_customize ) {
	$wp_customize->get_setting( 'blogname' )->transport         = 'postMessage';
	$wp_customize->get_setting( 'blogdescription' )->transport  = 'postMessage';
	$wp_customize->get_setting( 'header_textcolor' )->transport = 'postMessage';

	if ( isset( $wp_customize->selective_refresh ) ) {
		$wp_customize->selective_refresh->add_partial(
			'blogname',
			array(
				'selector'        => '.site-title a',
				'render_callback' => 'itsi_customize_partial_blogname',
			)
		);
		$wp_customize->selective_refresh->add_partial(
			'blogdescription',
			array(
				'selector'        => '.site-description',
				'render_callback' => 'itsi_customize_partial_blogdescription',
			)
		);
	}
}
add_action( 'customize_register', 'itsi_customize_register' );

/**
 * Render the site title for the selective refresh partial.
 *
 * @return void
 */
function itsi_customize_partial_blogname() {
	bloginfo( 'name' );
}

/**
 * Render the site tagline for the selective refresh partial.
 *
 * @return void
 */
function itsi_customize_partial_blogdescription() {
	bloginfo( 'description' );
}

/**
 * Binds JS handlers to make Theme Customizer preview reload changes asynchronously.
 * Kept for future use; currently no preview-bound controls exist.
 */
function itsi_customize_preview_js() {
	wp_enqueue_script( 'itsi-customizer', get_template_directory_uri() . '/js/customizer.js', array( 'customize-preview' ), _S_VERSION, true );
}
add_action( 'customize_preview_init', 'itsi_customize_preview_js' );