<?php
/**
 * Front-end asset pipeline: style.css, the program-studi / artikel-detail
 * stylesheets and the main theme script.
 *
 * Split out of functions.php (was the itsi_scripts() block).
 *
 * @package itsi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
		get_template_directory_uri() . '/assets/js/itsi-main.js',
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
