<?php
/**
 * One-shot seeders that populate defaults on the first `init`.
 *
 * Assigns widgets to the archive-berita sidebars and pre-fills the Schema / SEO
 * theme_mods from the Kontak settings, so a fresh install renders correctly
 * without manual admin setup.
 *
 * @package itsi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One-shot auto-populate: assign default widgets to the 3 archive-berita sidebar
 * areas so the sidebar renders out-of-the-box without manual admin setup.
 */
function itsi_archive_berita_widgets_autopopulate() {
	if ( false !== get_option( 'itsi_archive_berita_autopopulated', false ) ) {
		return;
	}

	$sidebars = get_option( 'sidebars_widgets', null );
	if ( null === $sidebars || ! is_array( $sidebars ) ) {
		$sidebars = array();
	}

	// Create ITSI_CategoryFilter_Widget instance.
	$cat_filter_instances = get_option( 'widget_itsi_category_filter_widget', array() );
	if ( ! is_array( $cat_filter_instances ) ) {
		$cat_filter_instances = array();
	}
	if ( ! isset( $cat_filter_instances['_multiwidget'] ) ) {
		$cat_filter_instances['_multiwidget'] = 1;
	}
	$next_id = 1;
	while ( isset( $cat_filter_instances[ $next_id ] ) ) {
		$next_id++;
	}
	if ( ! isset( $cat_filter_instances[ $next_id ] ) ) {
		$cat_filter_instances[ $next_id ] = array(
			'title' => 'Filter Kategori',
			'count' => 12,
		);
		update_option( 'widget_itsi_category_filter_widget', $cat_filter_instances );
	}
	$cat_filter_widget_id = 'itsi_category_filter_widget-' . $next_id;

	// Reuse or create ITSI_Popular_Widget instance.
	$pop_instances = get_option( 'widget_itsi_popular_widget', array() );
	$pop_widget_id  = null;
	if ( is_array( $pop_instances ) && ! empty( $pop_instances ) ) {
		foreach ( $pop_instances as $id => $inst ) {
			if ( '_multiwidget' === $id ) { continue; }
			if ( is_array( $inst ) ) {
				$pop_widget_id = 'itsi_popular_widget-' . $id;
				break;
			}
		}
	}

	// Assign widgets to sidebar areas.
	$targets = array(
		'itsi_archive_berita_widget_filter'  => $cat_filter_widget_id,
		'itsi_archive_berita_widget_popular' => $pop_widget_id,
	);
	foreach ( $targets as $sidebar_id => $widget_id ) {
		if ( null === $widget_id ) { continue; }
		if ( ! isset( $sidebars[ $sidebar_id ] ) || ! is_array( $sidebars[ $sidebar_id ] ) || empty( $sidebars[ $sidebar_id ] ) ) {
			$sidebars[ $sidebar_id ] = array( $widget_id );
		}
	}

	update_option( 'sidebars_widgets', $sidebars );
	update_option( 'itsi_archive_berita_autopopulated', 1 );
}
add_action( 'init', 'itsi_archive_berita_widgets_autopopulate', 11 );

/**
 * One-shot auto-populate: kalau tab Schema / SEO belum pernah di-simpan admin,
 * scrape halaman Kontak (ID 155) sebagai default. Hanya jalan sekali — setelah
 * admin simpan tab Schema, nilai mereka yang dipakai.
 *
 * Trigger:
 *   - front-end load pertama setelah theme update (init, priority 10)
 *   - hanya jalan kalau itsi_schema_org_name belum ada
 *
 * Source data (halaman Kontak):
 *   - Alamat: "Jl. Rumah Sakit Haji (Jl. Willem Iskandar) Komplek PT LPP Agro
 *     Nusantara, Medan Estate, Deli Serdang, Sumatera Utara 20371"
 *   - Phone: (061) 6637060
 *   - Email: medan@itsi.ac.id
 *   - Social: Facebook + Instagram + YouTube + TikTok (sudah lengkap di halaman)
 */
function itsi_schema_autopopulate_from_kontak() {
	// Guard: kalau org_name sudah ada (admin pernah simpan), skip total.
	if ( false !== get_theme_mod( 'itsi_schema_org_name', false ) ) {
		return;
	}

	// Default values ini dari scraping halaman Kontak (ID 155) 2026-07.
	// Override kapan saja via /wp-admin/admin.php?page=itsi-settings tab Schema.
	$defaults = array(
		'itsi_schema_org_name'        => 'Institut Teknologi Sawit Indonesia',
		'itsi_schema_org_alt_name'    => 'ITSI',
		'itsi_schema_street'          => 'Jl. Rumah Sakit Haji (Jl. Willem Iskandar) Komplek PT LPP Agro Nusantara',
		'itsi_schema_city'            => 'Medan Estate',
		'itsi_schema_region'          => 'Sumatera Utara',
		'itsi_schema_postal'          => '20371',
		'itsi_schema_country'         => 'ID',
		'itsi_schema_phone'           => '(061) 6637060',
		'itsi_schema_email'           => 'medan@itsi.ac.id',
		'itsi_schema_social_facebook' => 'https://www.facebook.com/itsimedan21',
		'itsi_schema_social_instagram'=> 'https://instagram.com/itsimedan',
		'itsi_schema_social_youtube'  => 'https://www.youtube.com/channel/UCsb3ihtJSWGoQbi5uwUdaiw',
		'itsi_schema_social_tiktok'   => 'https://vt.tiktok.com/ZSewMkcDm',
	);
	foreach ( $defaults as $key => $val ) {
		// Hanya set kalau benar-benar belum ada. Kalau ada string kosong (admin
		// pernah simpan kosong), jangan override — hormati input admin.
		if ( null === get_theme_mod( $key, null ) ) {
			set_theme_mod( $key, $val );
		}
	}
}
add_action( 'init', 'itsi_schema_autopopulate_from_kontak', 10 );
