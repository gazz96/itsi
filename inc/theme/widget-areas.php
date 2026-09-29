<?php
/**
 * Widget areas (sidebars) registration + data-driven widget registration.
 *
 * Registers the eight single-post / archive-berita areas, the dynamic footer
 * columns derived from the Footer Layout setting, and the full-width
 * "Before Footer" band; then registers the ITSI TOC / Popular / Category Filter
 * widgets defined in inc/theme/widgets.php.
 *
 * @package itsi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register widget areas for single post / page.
 *
 * All five are scoped to the single-post / single-page layout — they only
 * render inside `single.php` and `page.php` when `is_active_sidebar()` is
 * true. Admin label is the user-facing name in the Customizer; the `id`
 * follows the `itsi_single_post_widget_*` convention you requested.
 *
 * Position reference (see single.php):
 *   _before_post  → top of <article>, above header (was: top AdSense slot)
 *   _after_post   → end of <article>, after Related (was: post-article AdSense)
 *   _sidebar      → sidebar first slot (was: tall AdSense)
 *   _popular      → sidebar middle slot (was: Popular Posts card)
 *   _toc          → sidebar first slot (was: Daftar Isi card)
 */
function itsi_widgets_init() {
	$itsi_widget_areas = array(
		array(
			'name'          => __( 'Single Post — Before Article', 'itsi' ),
			'id'            => 'itsi_single_post_widget_before_post',
			'description'   => __( 'Widget area di atas artikel (sebelum judul). Hanya muncul di single post / page.', 'itsi' ),
			'wrap_widget'   => false,
		),
		array(
			'name'          => __( 'Single Post — After Article', 'itsi' ),
			'id'            => 'itsi_single_post_widget_after_post',
			'description'   => __( 'Widget area di bawah artikel (setelah Related Posts). Hanya muncul di single post / page.', 'itsi' ),
			'wrap_widget'   => false,
		),
		array(
			'name'          => __( 'Single Post — Table of Contents', 'itsi' ),
			'id'            => 'itsi_single_post_widget_toc',
			'description'   => __( 'Widget area Daftar Isi (TOC). Taruh widget "ITSI — Daftar Isi (Auto)" di sini. Hanya muncul di single post / page.', 'itsi' ),
			'wrap_widget'   => true,
		),
		array(
			'name'          => __( 'Single Post — Popular Posts', 'itsi' ),
			'id'            => 'itsi_single_post_widget_popular',
			'description'   => __( 'Widget area Popular Posts. Taruh widget "ITSI — Paling Banyak Dibaca (Auto)" di sini. Hanya muncul di single post / page.', 'itsi' ),
			'wrap_widget'   => true,
		),
		array(
			'name'          => __( 'Single Post — Sidebar', 'itsi' ),
			'id'            => 'itsi_single_post_widget_sidebar',
			'description'   => __( 'Widget area iklan / CTA di sidebar (di bawah Popular Posts). Hanya muncul di single post / page.', 'itsi' ),
			'wrap_widget'   => false,
		),
		array(
			'name'          => __( 'Archive Berita — Filter Kategori', 'itsi' ),
			'id'            => 'itsi_archive_berita_widget_filter',
			'description'   => __( 'Widget area filter kategori di sidebar archive / category berita. Taruh widget "ITSI — Filter Kategori (Auto)" di sini. Hanya muncul di archive / category.', 'itsi' ),
			'wrap_widget'   => true,
		),
		array(
			'name'          => __( 'Archive Berita — Popular Posts', 'itsi' ),
			'id'            => 'itsi_archive_berita_widget_popular',
			'description'   => __( 'Widget area popular posts di sidebar archive / category berita. Taruh widget "ITSI — Paling Banyak Dibaca (Auto)" di sini. Hanya muncul di archive / category.', 'itsi' ),
			'wrap_widget'   => true,
		),
		array(
			'name'          => __( 'Archive Berita — Sidebar Ads', 'itsi' ),
			'id'            => 'itsi_archive_berita_widget_sidebar',
			'description'   => __( 'Widget area iklan / CTA di sidebar archive / category berita. Taruh widget Custom HTML (AdSense) di sini. Hanya muncul di archive / category.', 'itsi' ),
			'wrap_widget'   => false,
		),
	);

	foreach ( $itsi_widget_areas as $area ) {
		if ( $area['wrap_widget'] ) {
			// Card-style wrap for TOC & Popular — matches the .at-s-card design.
			register_sidebar(
				array(
					'name'          => $area['name'],
					'id'            => $area['id'],
					'description'   => $area['description'],
					'before_widget' => '<div id="%1$s" class="at-s-card widget %2$s">',
					'after_widget'  => '</div>',
					'before_title'  => '<div class="at-s-head">',
					'after_title'   => '</div>',
				)
			);
		} else {
			// Plain wrap for ad / CTA slots.
			register_sidebar(
				array(
					'name'          => $area['name'],
					'id'            => $area['id'],
					'description'   => $area['description'],
					'before_widget' => '<div id="%1$s" class="at-widget-slot widget %2$s">',
					'after_widget'  => '</div>',
					'before_title'  => '<div class="at-s-head">',
					'after_title'   => '</div>',
				)
			);
		}
	}

	// Footer widget areas — dinamis dari layout (Appearance → ITSI → Footer → Layout Footer).
	// Setiap baris layout = satu widget area `footer_N`. Default 4 kolom.
	$footer_layout = itsi_get_footer_layout();
	foreach ( $footer_layout as $i => $col ) {
		$num = (int) $i + 1;
		register_sidebar(
			array(
				'name'          => sprintf( __( 'Footer %d — %s', 'itsi' ), $num, $col['label'] ),
				'id'            => 'footer_' . $num,
				'description'   => __( 'Kolom footer dari Layout Footer (Appearance → ITSI → Footer). Kosongkan untuk fallback footer statis.', 'itsi' ),
				'before_widget' => '<div id="%1$s" class="f-col widget %2$s">',
				'after_widget'  => '</div>',
				'before_title'  => '<div class="f-col-ttl">',
				'after_title'   => '</div>',
			)
		);
	}

	// Before Footer — band penuh (full-width) di atas <footer>, di luar footer
	// gelap. Render di footer.php tepat sebelum <footer id="colophon">.
	register_sidebar(
		array(
			'name'          => __( 'Before Footer', 'itsi' ),
			'id'            => 'itsi_before_footer',
			'description'   => __( 'Widget area penuh di atas footer. Cocok untuk CTA banner, newsletter, atau strip iklan. Hanya muncul jika minimal satu widget terisi.', 'itsi' ),
			'before_widget' => '<div id="%1$s" class="bf-widget widget %2$s">',
			'after_widget'  => '</div>',
			'before_title'  => '<div class="bf-title">',
			'after_title'   => '</div>',
		)
	);

	// Data-driven widgets (auto-render TOC from <h2>, Popular from post_views_count).
	register_widget( 'ITSI_TOC_Widget' );
	register_widget( 'ITSI_Popular_Widget' );
	register_widget( 'ITSI_CategoryFilter_Widget' );
}
add_action( 'widgets_init', 'itsi_widgets_init' );
