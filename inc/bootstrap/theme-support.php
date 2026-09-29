<?php
/**
 * Theme bootstrap: theme supports, menus, image sizes, text domain and the
 * global content width.
 *
 * Split out of functions.php (was the top of the file, right after the header).
 *
 * @package itsi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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
