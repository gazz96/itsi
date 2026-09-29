<?php
/**
 * Shared rendering helpers for the info_publik CPT.
 *
 * Used by archive-info_publik.php, single templates and the LDII/KIP card
 * partials to normalize the repeater field, compute statistics, build the card
 * list, render the PPID banner, read per-form settings and resolve category
 * icons.
 *
 * @package itsi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalize a repeater-style theme_mod into clean rows.
 *
 * Accepts array, serialized array (set_theme_mod auto-serializes), or JSON
 * string (TypeRocket repeater). Drops fully-empty rows, caps at $limit, and
 * returns rows keyed by $fields (missing keys → '').
 *
 * @param string $mod_key Theme mod key.
 * @param array  $fields  Field keys to keep, in order.
 * @param int    $limit   Max rows (0 = unlimited).
 * @return array<int,array<string,string>>
 */
function itsi_ip_normalize_repeater( $mod_key, $fields, $limit = 0 ) {
	$raw = get_theme_mod( $mod_key, null );

	if ( is_string( $raw ) ) {
		$decoded = json_decode( $raw, true );
		if ( is_array( $decoded ) ) {
			$raw = $decoded;
		} else {
			$unser = maybe_unserialize( $raw );
			$raw   = is_array( $unser ) ? $unser : array();
		}
	}
	if ( ! is_array( $raw ) ) {
		$raw = array();
	}

	$rows = array();
	foreach ( $raw as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$out = array();
		foreach ( $fields as $f ) {
			$out[ $f ] = isset( $row[ $f ] ) ? $row[ $f ] : '';
		}
		$is_empty = true;
		foreach ( $out as $v ) {
			if ( '' !== trim( (string) $v ) ) {
				$is_empty = false;
				break;
			}
		}
		if ( $is_empty ) {
			continue;
		}
		$rows[] = $out;
		if ( $limit && count( $rows ) >= $limit ) {
			break;
		}
	}

	return $rows;
}

/**
 * Get stats bar rows for the Informasi Publik archive.
 *
 * Rows come from theme_mod `itsi_ip_stats` (repeater: icon/angka/label).
 * When unset, returns the default 4 rows; the first two rows' angka can be
 * filled dynamically via $ctx (total_docs / total_cats from the template).
 *
 * @param array $ctx Optional context: [ 'total_docs' => int, 'total_cats' => int ].
 * @return array<int,array{icon:string,angka:string,label:string}>
 */
function itsi_ip_get_stats( $ctx = array() ) {
	$rows = itsi_ip_normalize_repeater( 'itsi_ip_stats', array( 'icon', 'angka', 'label' ), 6 );

	if ( empty( $rows ) ) {
		$total_docs = isset( $ctx['total_docs'] ) ? $ctx['total_docs'] : 0;
		$total_cats = isset( $ctx['total_cats'] ) ? $ctx['total_cats'] : 0;
		$rows       = array(
			array( 'icon' => '', 'angka' => (string) $total_docs, 'label' => 'Total Dokumen' ),
			array( 'icon' => '', 'angka' => (string) $total_cats, 'label' => 'Kategori' ),
			array( 'icon' => '', 'angka' => '24/7', 'label' => 'Akses Online' ),
			array( 'icon' => '', 'angka' => '10', 'label' => 'Hari Kerja Respons' ),
		);
	}

	return $rows;
}

/**
 * Get KIP info cards for the Informasi Publik archive.
 *
 * Rows come from theme_mod `itsi_ip_kip_cards` (repeater: icon/title/text).
 * Falls back to 3 default cards when unset.
 *
 * @return array<int,array{icon:string,title:string,text:string}>
 */
function itsi_ip_get_kip_cards() {
	$rows = itsi_ip_normalize_repeater( 'itsi_ip_kip_cards', array( 'icon', 'title', 'text' ), 8 );

	if ( empty( $rows ) ) {
		$rows = array(
			array(
				'icon'  => '',
				'title' => 'UU No. 14 Tahun 2008',
				'text'  => 'Undang-Undang tentang Keterbukaan Informasi Publik yang menjamin hak masyarakat untuk memperoleh informasi publik.',
			),
			array(
				'icon'  => '',
				'title' => 'Hak Memperoleh Informasi',
				'text'  => 'Setiap orang berhak memperoleh informasi publik sesuai ketentuan yang berlaku, dengan pengecualian yang diatur dalam UU.',
			),
			array(
				'icon'  => '',
				'title' => 'Layanan Transparan',
				'text'  => 'ITSI melalui PPID berkomitmen memberikan layanan informasi yang transparan, akuntabel, dan dapat dipertanggungjawabkan.',
			),
		);
	}

	return $rows;
}

/**
 * Get PPID banner content (icon attachment ID, title, description).
 *
 * @return array{icon:string,title:string,desc:string}
 */
function itsi_ip_get_ppid_banner() {
	return array(
		'icon'  => (string) get_theme_mod( 'itsi_ip_ppid_icon', '' ),
		'title' => (string) get_theme_mod( 'itsi_ip_ppid_title', 'PPID Institut Teknologi Sawit Indonesia' ),
		'desc'  => (string) get_theme_mod(
			'itsi_ip_ppid_desc',
			'Pejabat Pengelola Informasi dan Dokumentasi (PPID) ITSI melayani permohonan informasi publik sesuai UU No. 14 Tahun 2008 tentang Keterbukaan Informasi Publik. Akses dokumen resmi, laporan keuangan, akreditasi, dan regulasi secara transparan.'
		),
	);
}

/**
 * Get the Informasi Publik form + notification settings.
 *
 * @return array{
 *   form_enabled:bool,
 *   email_to:string,
 *   email_from:string,
 *   email_subject:string,
 *   rate_max:int,
 *   rate_window:int
 * }
 */
function itsi_ip_get_form_settings() {
	$admin_email = get_option( 'admin_email' );

	return array(
		'form_enabled'  => filter_var( get_theme_mod( 'itsi_ip_form_enabled', true ), FILTER_VALIDATE_BOOLEAN ),
		'email_to'      => (string) get_theme_mod( 'itsi_ip_email_to', $admin_email ),
		'email_from'    => (string) get_theme_mod( 'itsi_ip_email_from', $admin_email ),
		'email_subject' => (string) get_theme_mod( 'itsi_ip_email_subject', '[ITSI] Permohonan Informasi Baru' ),
		'rate_max'      => max( 1, (int) get_theme_mod( 'itsi_ip_rate_max', 5 ) ),
		'rate_window'   => max( 1, (int) get_theme_mod( 'itsi_ip_rate_window', 15 ) ),
	);
}

/**
 * Get the icon attachment ID for a kategori_info term (term meta).
 *
 * @param int $term_id Term ID.
 * @return string Attachment ID or ''.
 */
function itsi_ip_get_term_icon( $term_id ) {
	$term_id = (int) $term_id;
	if ( ! $term_id ) {
		return '';
	}
	$icon = get_term_meta( $term_id, 'kategori_info_icon', true );
	return $icon ? (string) $icon : '';
}

/**
 * Render an icon <img> from an attachment ID, with a graceful fallback when
 * the ID is empty/invalid (a neutral inline SVG so no emoji is ever shown).
 *
 * @param int|string $attachment_id Attachment ID (or '').
 * @param string     $class         Extra CSS classes.
 * @param string     $alt           Alt text.
 * @return string HTML or '' if not needed.
 */
function itsi_ip_render_icon_img( $attachment_id, $class = '', $alt = '' ) {
	$attachment_id = (int) $attachment_id;
	if ( $attachment_id > 0 ) {
		$url = (string) wp_get_attachment_image_url( $attachment_id, 'thumbnail' );
		if ( $url ) {
			return sprintf(
				'<img src="%s" alt="%s" class="ip-ic-img %s" loading="lazy" decoding="async" />',
				esc_url( $url ),
				esc_attr( $alt ),
				esc_attr( $class )
			);
		}
	}
	// Fallback: neutral document glyph — never an emoji.
	return sprintf(
		'<span class="ip-ic-img ip-ic-fallback %s" aria-hidden="true"><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/></svg></span>',
		esc_attr( $class )
	);
}
