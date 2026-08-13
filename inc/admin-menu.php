<?php
/**
 * Admin sidebar menu: ITSI
 *
 * Top-level admin menu "ITSI" with six flat TypeRocket tabs:
 *   - Top Bar – Kiri     → email/tel/alamat
 *   - Top Bar – Kanan    → language switcher + PMB pill
 *   - Brand Bar          → short/full label
 *   - Brand Colors       → topbar/navbar bg + PMB gradient
 *   - Footer             → copyright + social URLs
 *   - Posts              → per-page, excerpt length, featured-in-archive
 *
 * Storage: theme_mods (so values are read by header.php / footer.php via
 * get_theme_mod). Fields are rendered with TypeRocket field classes for UI
 * consistency; submission is handled manually via admin-post.php so we can
 * call set_theme_mod() instead of the Form's auto-save to the Option model.
 *
 * @package itsi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the "ITSI" top-level admin menu.
 *
 * @return void
 */
function itsi_register_admin_menu() {
	add_menu_page(
		__( 'ITSI', 'itsi' ),
		__( 'ITSI', 'itsi' ),
		'manage_options',
		'itsi-settings',
		'itsi_render_settings_page',
		'dashicons-admin-customizer',
		61
	);
}
add_action( 'admin_menu', 'itsi_register_admin_menu' );

/**
 * Handle POST save for ITSI settings page.
 *
 * Saves every whitelisted key via set_theme_mod() so existing header.php /
 * footer.php get_theme_mod() calls pick up the new values without code changes.
 *
 * Hook: admin_post_itsi_settings_save
 *
 * @return void
 */
function itsi_handle_settings_save() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Anda tidak memiliki akses untuk menyimpan pengaturan ini.', 'itsi' ) );
	}
	check_admin_referer( 'itsi_settings_save', '_itsi_nonce' );

	// TypeRocket field inputs render with a `tr[...]` prefix, so the posted
	// payload is $_POST['tr']['itsi_*'] not $_POST['itsi_*'].
	$posted = isset( $_POST['tr'] ) && is_array( $_POST['tr'] )
		? wp_unslash( $_POST['tr'] )
		: array();

	// Map: posted field name → sanitizer callback.
	$fields = array(
		// Header → top bar HTML (free-form, supports shortcode like [gtranslate]).
		'itsi_tb_left_html'  => 'wp_kses_post',
		'itsi_tb_right_html' => 'wp_kses_post',

		// Header → brand bar.
		'itsi_brand_short' => 'sanitize_text_field',
		'itsi_brand_full'  => 'sanitize_text_field',

		// Header → brand colors.
		'itsi_color_topbar_bg'    => 'sanitize_hex_color',
		'itsi_color_navbar_bg'    => 'sanitize_hex_color',
		'itsi_color_navbar_alpha' => 'itsi_sanitize_opacity',
		'itsi_color_pmb_from'     => 'sanitize_hex_color',
		'itsi_color_pmb_to'       => 'sanitize_hex_color',

		// Footer.
		'itsi_footer_copyright'       => 'sanitize_text_field',
		'itsi_footer_social_facebook' => 'esc_url_raw',
		'itsi_footer_social_instagram'=> 'esc_url_raw',
		'itsi_footer_social_youtube'  => 'esc_url_raw',
		'itsi_footer_social_x'        => 'esc_url_raw',
		// 2026-07-08: Tambah 3 social lain agar konsisten dengan schema set
		// (inc/schema.php line 110-127 itsi_schema_same_as baca tiktok/twitter/linkedin).
		'itsi_footer_social_tiktok'   => 'esc_url_raw',
		'itsi_footer_social_twitter'  => 'esc_url_raw',   // twitter.com klasik (X = rebrand, field terpisah)
		'itsi_footer_social_linkedin' => 'esc_url_raw',
		// 2026-07-08: Footer static content (alamat, telp, email, jam) — independent
		// dari itsi_schema_* (schema JSON-LD) agar admin bisa beda display vs structured data.
		'itsi_footer_address'         => 'sanitize_text_field',
		'itsi_footer_phone'           => 'sanitize_text_field',
		'itsi_footer_phone_link'      => 'sanitize_text_field',   // E.164 (mis: +6261****4567)
		'itsi_footer_email'           => 'sanitize_email',
		'itsi_footer_hours'           => 'sanitize_text_field',
		// 2026-07-08: Footer legal/nav links.
		'itsi_footer_privacy_url'     => 'esc_url_raw',
		'itsi_footer_terms_url'       => 'esc_url_raw',
		'itsi_footer_sitemap_url'     => 'esc_url_raw',
		// 2026-08-12: Footer layout builder (repeater → widget areas + grid widths).
		'itsi_footer_layout'          => 'itsi_sanitize_footer_layout',

		// Posts.
		'itsi_posts_per_page'         => 'absint',
		'itsi_posts_excerpt_length'   => 'absint',
		'itsi_posts_show_featured'    => 'rest_sanitize_boolean',
		'itsi_posts_default_category' => 'absint',

		// Schema / SEO (EducationalOrganization JSON-LD).
		'itsi_schema_org_name'           => 'sanitize_text_field',
		'itsi_schema_org_alt_name'       => 'sanitize_text_field',
		'itsi_schema_street'             => 'sanitize_text_field',
		'itsi_schema_city'               => 'sanitize_text_field',
		'itsi_schema_region'             => 'sanitize_text_field',
		'itsi_schema_postal'             => 'sanitize_text_field',
		'itsi_schema_country'            => 'sanitize_text_field',
		'itsi_schema_lat'                => 'sanitize_text_field',
		'itsi_schema_lng'                => 'sanitize_text_field',
		'itsi_schema_phone'              => 'sanitize_text_field',
		'itsi_schema_email'              => 'sanitize_email',
		'itsi_schema_founded'            => 'sanitize_text_field',
		'itsi_schema_social_facebook'    => 'esc_url_raw',
		'itsi_schema_social_instagram'   => 'esc_url_raw',
		'itsi_schema_social_youtube'     => 'esc_url_raw',
		'itsi_schema_social_tiktok'      => 'esc_url_raw',
		'itsi_schema_social_twitter'     => 'esc_url_raw',
		'itsi_schema_social_linkedin'    => 'esc_url_raw',

		// Informasi Publik — PPID banner.
		'itsi_ip_ppid_icon'  => 'absint',
		'itsi_ip_ppid_title' => 'sanitize_text_field',
		'itsi_ip_ppid_desc'  => 'sanitize_textarea_field',

		// Informasi Publik — stats & KIP repeater.
		'itsi_ip_stats'     => 'itsi_sanitize_ip_stats',
		'itsi_ip_kip_cards' => 'itsi_sanitize_ip_kip_cards',

		// Informasi Publik — form & email.
		'itsi_ip_form_enabled'  => 'rest_sanitize_boolean',
		'itsi_ip_email_to'      => 'sanitize_email',
		'itsi_ip_email_from'    => 'sanitize_email',
		'itsi_ip_email_subject' => 'sanitize_text_field',
		'itsi_ip_rate_max'      => 'itsi_sanitize_rate_max',
		'itsi_ip_rate_window'   => 'itsi_sanitize_rate_window',
	);

	// WP native theme_mod keys for logo + favicon — TR Image field posts
	// an attachment ID (scalar) under these names.
	$native_image_keys = array( 'custom_logo', 'site_icon' );

	foreach ( $fields as $key => $sanitizer ) {
		if ( isset( $posted[ $key ] ) ) {
			$val = call_user_func( $sanitizer, $posted[ $key ] );
			// sanitize_hex_color returns null/empty for invalid hex — skip rather
			// than storing a falsy value that would clobber a previously valid hex.
			if ( 'sanitize_hex_color' === $sanitizer && empty( $val ) ) {
				continue;
			}
			set_theme_mod( $key, $val );
		} elseif ( 'rest_sanitize_boolean' === $sanitizer ) {
			// Unchecked checkbox: store false rather than leaving stale true.
			set_theme_mod( $key, false );
		}
	}

	// Image fields: if a non-zero attachment ID was posted, set_theme_mod.
	// If absent or zero/empty (admin cleared the picker), remove_theme_mod so
	// header.php falls back to the bundled SVG (logo) or no favicon (site_icon).
	foreach ( $native_image_keys as $img_key ) {
		if ( isset( $posted[ $img_key ] ) && is_numeric( $posted[ $img_key ] ) && (int) $posted[ $img_key ] > 0 ) {
			$att_id = (int) $posted[ $img_key ];
			set_theme_mod( $img_key, $att_id );

			// WP core has_site_icon() + get_site_icon_url() read from the
			// `site_icon` OPTION, NOT the theme_mod — and emits <link rel="icon">
			// only when option is non-zero. Sync both stores so the favicon
			// actually shows in <head>. Without this, theme_mod has ID 5621 but
			// option is stuck at '0' → has_site_icon() = false → no favicon.
			if ( 'site_icon' === $img_key ) {
				update_option( 'site_icon', $att_id );
			}
		} else {
			remove_theme_mod( $img_key );

			// Same dual-store sync — when admin clears the picker, wipe both so
			// a stale option value can't resurrect a removed favicon.
			if ( 'site_icon' === $img_key ) {
				delete_option( 'site_icon' );
			}
		}
	}

	wp_safe_redirect(
		add_query_arg(
			array( 'page' => 'itsi-settings', 'itsi_saved' => '1' ),
			admin_url( 'admin.php' )
		)
	);
	exit;
}
add_action( 'admin_post_itsi_settings_save', 'itsi_handle_settings_save' );

/**
 * Sanitize the footer layout repeater value.
 *
 * Each row = [ 'label' => string, 'width' => string ].
 *  - Label: sanitize_text_field.
 *  - Width: whitelist token per grid-template-columns — angka+fr/%, px/em/rem,
 *    auto, min-content, max-content, minmax(...). Token dipisah spasi/koma;
 *    token tak dikenal → '1fr'. Baris tanpa label & tanpa width valid dibuang.
 *
 * @param mixed $value Raw repeater value (array of rows or JSON string).
 * @return array List of sanitized rows.
 */
function itsi_sanitize_footer_layout( $value ) {
	if ( is_string( $value ) ) {
		$decoded = json_decode( $value, true );
		if ( is_array( $decoded ) ) {
			$value = $decoded;
		}
	}
	if ( ! is_array( $value ) ) {
		return array();
	}

	$rows = array();
	foreach ( $value as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		$label = isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '';
		$width = isset( $row['width'] ) ? trim( (string) $row['width'] ) : '';

		// Width: split by whitespace / comma, validate each token.
		$tokens = preg_split( '/[\s,]+/', $width, -1, PREG_SPLIT_NO_EMPTY );
		$valid  = array();
		foreach ( $tokens as $tok ) {
			if ( 1 === preg_match( '#^(\d+(\.\d+)?(fr|%|px|em|rem|vw|vh))|auto|min-content|max-content|minmax\([^)]*\)$#i', $tok ) ) {
				$valid[] = $tok;
			} else {
				$valid[] = '1fr'; // fallback token tak dikenal.
			}
		}

		// Baris kosong total → buang. Minimal salah satu (label ATAU width) boleh ada;
		// kalau width kosong tapi ada label, default width 1fr biar grid tetap konsisten.
		if ( '' === $label && empty( $valid ) ) {
			continue;
		}
		$width_str = empty( $valid ) ? '1fr' : implode( ' ', $valid );

		$rows[] = array(
			'label' => $label,
			'width' => $width_str,
		);
	}

	return $rows;
}

/**
 * Sanitize the Informasi Publik stats repeater value.
 *
 * Each row = [ 'icon' => attachment id, 'angka' => string, 'label' => string ].
 *  - icon: absint (attachment id) — 0/empty di-drop.
 *  - angka: sanitize_text_field (boleh "24/7", "10").
 *  - label: sanitize_text_field.
 *  Baris tanpa angka & tanpa label & tanpa icon → dibuang.
 *
 * @param mixed $value Raw repeater value.
 * @return array List of sanitized rows.
 */
function itsi_sanitize_ip_stats( $value ) {
	if ( is_string( $value ) ) {
		$decoded = json_decode( $value, true );
		if ( is_array( $decoded ) ) {
			$value = $decoded;
		}
	}
	if ( ! is_array( $value ) ) {
		return array();
	}

	$rows = array();
	foreach ( $value as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		$icon  = isset( $row['icon'] ) && is_numeric( $row['icon'] ) ? absint( $row['icon'] ) : 0;
		$angka = isset( $row['angka'] ) ? sanitize_text_field( (string) $row['angka'] ) : '';
		$label = isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '';

		if ( '' === $angka && '' === $label && 0 === $icon ) {
			continue;
		}

		$rows[] = array(
			'icon'  => $icon,
			'angka' => $angka,
			'label' => $label,
		);
	}

	return $rows;
}

/**
 * Sanitize the Informasi Publik KIP cards repeater value.
 *
 * Each row = [ 'icon' => attachment id, 'title' => string, 'text' => string ].
 * Baris tanpa title & tanpa text & tanpa icon → dibuang.
 *
 * @param mixed $value Raw repeater value.
 * @return array List of sanitized rows.
 */
function itsi_sanitize_ip_kip_cards( $value ) {
	if ( is_string( $value ) ) {
		$decoded = json_decode( $value, true );
		if ( is_array( $decoded ) ) {
			$value = $decoded;
		}
	}
	if ( ! is_array( $value ) ) {
		return array();
	}

	$rows = array();
	foreach ( $value as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		$icon  = isset( $row['icon'] ) && is_numeric( $row['icon'] ) ? absint( $row['icon'] ) : 0;
		$title = isset( $row['title'] ) ? sanitize_text_field( (string) $row['title'] ) : '';
		$text  = isset( $row['text'] ) ? sanitize_textarea_field( (string) $row['text'] ) : '';

		if ( '' === $title && '' === $text && 0 === $icon ) {
			continue;
		}

		$rows[] = array(
			'icon'  => $icon,
			'title' => $title,
			'text'  => $text,
		);
	}

	return $rows;
}

/**
 * Sanitize rate limit max submissions per IP (clamp 1–100, default 5).
 *
 * @param mixed $value Raw input.
 * @return int
 */
function itsi_sanitize_rate_max( $value ) {
	$n = is_numeric( $value ) ? (int) $value : 5;
	if ( $n < 1 ) {
		return 1;
	}
	if ( $n > 100 ) {
		return 100;
	}
	return $n;
}

/**
 * Sanitize rate limit window in minutes (clamp 1–1440, default 15).
 *
 * @param mixed $value Raw input.
 * @return int
 */
function itsi_sanitize_rate_window( $value ) {
	$n = is_numeric( $value ) ? (int) $value : 15;
	if ( $n < 1 ) {
		return 1;
	}
	if ( $n > 1440 ) {
		return 1440;
	}
	return $n;
}

/**
 * Sanitize a numeric opacity value into 0.0–1.0 range.
 *
 * @param mixed $value Raw input.
 * @return float
 */
function itsi_sanitize_opacity( $value ) {
	$f = is_numeric( $value ) ? (float) $value : 0.88;
	if ( $f < 0 ) {
		return 0.0;
	}
	if ( $f > 1 ) {
		return 1.0;
	}
	return $f;
}

/**
 * Render the ITSI settings page with TypeRocket tabs.
 *
 * @return void
 */
function itsi_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Anda tidak memiliki akses ke halaman ini.', 'itsi' ) );
	}

	$saved = isset( $_GET['itsi_saved'] ) && '1' === $_GET['itsi_saved']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flag.

	$tabs = new \TypeRocket\Elements\Tabs();

	$tabs->tab( __( 'Header', 'itsi' ), 'dashicons-admin-generic', array(
		// Logo + Favicon (WP native theme_mod keys).
		\TypeRocket\Utility\Helper::form()
			->image( 'custom_logo' )
			->setLabel( __( 'Site Logo', 'itsi' ) )
			->setHelp( __( 'Logo utama situs. Dipakai di navbar dan footer. Pilih dari Media Library. Kosongkan untuk pakai logo default.', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()
			->image( 'site_icon' )
			->setLabel( __( 'Site Favicon', 'itsi' ) )
			->setHelp( __( 'Ikon kecil di tab browser (favicon). Ukuran ideal 512×512 px. Format yang disarankan: PNG (transparan OK), ICO, atau SVG square. JPEG TIDAK disarankan — akan distretch & Google SERP abaikan. PNG/ICO/SVG akan otomatis generate ke /favicon.ico, /favicon-32x32.png, /apple-touch-icon.png, dan /site.webmanifest saat disimpan. CATATAN: jika upload image baru via Media Library dan Site Favicon di atas masih kosong, image terbaru akan otomatis dipromosikan jadi favicon.', 'itsi' ) ),

		// Brand Bar.
		\TypeRocket\Utility\Helper::form()
			->text( 'itsi_brand_short' )
			->setLabel( __( 'Site Short Label', 'itsi' ) )
			->setHelp( __( 'Label pendek di navbar, mis. ITSI.', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()
			->text( 'itsi_brand_full' )
			->setLabel( __( 'Site Full Label', 'itsi' ) )
			->setHelp( __( 'Label panjang di navbar.', 'itsi' ) ),

		// Brand Colors.
		\TypeRocket\Utility\Helper::form()
			->color( 'itsi_color_topbar_bg' )
			->setLabel( __( 'Topbar BG', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()
			->color( 'itsi_color_navbar_bg' )
			->setLabel( __( 'Navbar BG (warna dasar)', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()
			->number( 'itsi_color_navbar_alpha' )
			->setLabel( __( 'Navbar BG opacity (0.0–1.0)', 'itsi' ) )
			->setAttribute( 'step', '0.01' )
			->setAttribute( 'min', '0' )
			->setAttribute( 'max', '1' ),
		\TypeRocket\Utility\Helper::form()
			->color( 'itsi_color_pmb_from' )
			->setLabel( __( 'PMB Pill – gradient kiri', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()
			->color( 'itsi_color_pmb_to' )
			->setLabel( __( 'PMB Pill – gradient kanan', 'itsi' ) ),

		// Top Bar – Kiri (free HTML, supports shortcode).
		\TypeRocket\Utility\Helper::form()
			->wpEditor( 'itsi_tb_left_html' )
			->setLabel( __( 'Top Bar – Kiri (HTML)', 'itsi' ) )
			->setHelp( __( 'Konten HTML bebas untuk sisi kiri top bar. Mendukung shortcode (mis. [gtranslate]). Kosongkan jika tidak dipakai.', 'itsi' ) )
			->setSetting( 'options', array(
				'textarea_rows' => 4,
				'teeny'         => true,
			) ),

		// Top Bar – Kanan (free HTML, supports shortcode).
		\TypeRocket\Utility\Helper::form()
			->wpEditor( 'itsi_tb_right_html' )
			->setLabel( __( 'Top Bar – Kanan (HTML)', 'itsi' ) )
			->setHelp( __( 'Konten HTML bebas untuk sisi kanan top bar. Mendukung shortcode (mis. [gtranslate] untuk language switcher).', 'itsi' ) )
			->setSetting( 'options', array(
				'textarea_rows' => 4,
				'teeny'         => true,
			) ),
	) );

	$tabs->tab( __( 'Schema / SEO', 'itsi' ), 'dashicons-chart-line', array(
		// Org identity.
		\TypeRocket\Utility\Helper::form()
			->text( 'itsi_schema_org_name' )
			->setLabel( __( 'Nama resmi institusi', 'itsi' ) )
			->setHelp( __( 'Dipakai sebagai EducationalOrganization.name di schema. Default: Site Title.', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()
			->text( 'itsi_schema_org_alt_name' )
			->setLabel( __( 'Nama pendek / akronim', 'itsi' ) )
			->setHelp( __( 'Mis. ITSI untuk Institut Teknologi Sawit Indonesia. Dipakai sebagai alternateName.', 'itsi' ) ),

		// Postal address.
		\TypeRocket\Utility\Helper::form()
			->text( 'itsi_schema_street' )
			->setLabel( __( 'Alamat jalan', 'itsi' ) )
			->setHelp( __( 'Jalan, nomor, kompleks. Mis: Jl. Rumah Sakit Haji (Jl. Willem Iskandar) Komplek PT LPP Agro Nusantara', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()
			->text( 'itsi_schema_city' )
			->setLabel( __( 'Kota', 'itsi' ) )
			->setHelp( __( 'Mis: Medan Estate', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()
			->text( 'itsi_schema_region' )
			->setLabel( __( 'Provinsi', 'itsi' ) )
			->setHelp( __( 'Mis: Sumatera Utara', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()
			->text( 'itsi_schema_postal' )
			->setLabel( __( 'Kode pos', 'itsi' ) )
			->setHelp( __( 'Mis: 20371', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()
			->text( 'itsi_schema_country' )
			->setLabel( __( 'Kode negara (ISO-3166-1 alpha-2)', 'itsi' ) )
			->setHelp( __( 'Default: ID. Mis: ID untuk Indonesia.', 'itsi' ) ),

		// Geo.
		\TypeRocket\Utility\Helper::form()
			->text( 'itsi_schema_lat' )
			->setLabel( __( 'Latitude', 'itsi' ) )
			->setHelp( __( 'Dari Google Maps share. Mis: 3.5952', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()
			->text( 'itsi_schema_lng' )
			->setLabel( __( 'Longitude', 'itsi' ) )
			->setHelp( __( 'Dari Google Maps share. Mis: 98.7331', 'itsi' ) ),

		// Contact.
		\TypeRocket\Utility\Helper::form()
			->text( 'itsi_schema_phone' )
			->setLabel( __( 'Telepon', 'itsi' ) )
			->setHelp( __( 'Format bebas. Mis: (061) 6637060', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()
			->text( 'itsi_schema_email' )
			->setLabel( __( 'Email kontak', 'itsi' ) )
			->setHelp( __( 'Mis: medan@itsi.ac.id', 'itsi' ) ),

		// Founded.
		\TypeRocket\Utility\Helper::form()
			->text( 'itsi_schema_founded' )
			->setLabel( __( 'Tahun berdiri', 'itsi' ) )
			->setHelp( __( 'Format YYYY atau YYYY-MM-DD. Mis: 2017', 'itsi' ) ),

		// Social.
		\TypeRocket\Utility\Helper::form()
			->text( 'itsi_schema_social_facebook' )
			->setLabel( __( 'Facebook URL', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()
			->text( 'itsi_schema_social_instagram' )
			->setLabel( __( 'Instagram URL', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()
			->text( 'itsi_schema_social_youtube' )
			->setLabel( __( 'YouTube URL', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()
			->text( 'itsi_schema_social_tiktok' )
			->setLabel( __( 'TikTok URL', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()
			->text( 'itsi_schema_social_twitter' )
			->setLabel( __( 'X / Twitter URL', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()
			->text( 'itsi_schema_social_linkedin' )
			->setLabel( __( 'LinkedIn URL', 'itsi' ) ),
	) );

	$tabs->tab( __( 'Footer', 'itsi' ), 'dashicons-share', array(
		// === Layout Footer (repeater → widget areas + grid widths) ===
		// Setiap baris = 1 kolom footer. Jumlah baris menentukan jumlah widget
		// area yang diregistrasi (footer_1, footer_2, …). Default: 4 baris.
		// Width mengikuti syntax CSS grid-template-columns (fr, %, px, em, …).
		\TypeRocket\Utility\Helper::form()
			->repeater( 'itsi_footer_layout' )
			->setFields(
				array(
					\TypeRocket\Utility\Helper::form()
						->text( 'Label' )
						->setAttribute( 'placeholder', 'Footer 1' )
						->setHelp( __( 'Nama kolom — dipakai sebagai label widget area (Appearance → Widgets).', 'itsi' ) ),
					\TypeRocket\Utility\Helper::form()
						->text( 'Width' )
						->setAttribute( 'placeholder', '2fr' )
						->setHelp( __( 'Lebar kolom, mengikuti syntax CSS grid-template-columns. Contoh: 2fr, 1fr, 25%, 300px, auto. Pisahkan beberapa token dengan spasi/koma untuk kolom gabungan.', 'itsi' ) ),
				)
			)
			->setTitle( __( 'Layout Footer', 'itsi' ) )
			->setSetting( 'help', __( 'Setiap baris = satu kolom footer & satu widget area (footer_1, footer_2, …). Kosongkan semua baris untuk kembali ke footer statis default. Jika minimal satu widget area terisi, footer memakai widget; jika kosong, footer statis (Brand / Prodi / Informasi / Kontak) tetap tampil.', 'itsi' ) )
			->setLimit( 12 ),

		// === Copyright + contact (konten statis) ===
		\TypeRocket\Utility\Helper::form()
			->text( 'itsi_footer_copyright' )
			->setLabel( __( 'Teks copyright', 'itsi' ) )
			->setHelp( __( 'Ditampilkan di bagian bawah footer. Tahun otomatis ditambah di depan.', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()
			->text( 'itsi_footer_address' )
			->setLabel( __( 'Alamat', 'itsi' ) )
			->setHelp( __( 'Alamat lengkap untuk kolom Kontak. Mis: Jl. Willem Iskandar, Medan.', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()
			->text( 'itsi_footer_phone' )
			->setLabel( __( 'Telepon (display)', 'itsi' ) )
			->setHelp( __( 'Teks yang ditampilkan. Mis: (061) 6637060', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()
			->text( 'itsi_footer_phone_link' )
			->setLabel( __( 'Telepon (link, E.164)', 'itsi' ) )
			->setHelp( __( 'Format E.164 untuk tel: href. Mis: +6261****4567', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()
			->text( 'itsi_footer_email' )
			->setLabel( __( 'Email kontak', 'itsi' ) )
			->setHelp( __( 'Mis: info@itsi.ac.id', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()
			->text( 'itsi_footer_hours' )
			->setLabel( __( 'Jam operasional', 'itsi' ) )
			->setHelp( __( 'Mis: Senin–Jumat: 08.00–16.00 WIB', 'itsi' ) ),

		// === Social media URLs (independen dari schema set) ===
		\TypeRocket\Utility\Helper::form()
			->text( 'itsi_footer_social_facebook' )
			->setLabel( __( 'Facebook URL', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()
			->text( 'itsi_footer_social_instagram' )
			->setLabel( __( 'Instagram URL', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()
			->text( 'itsi_footer_social_youtube' )
			->setLabel( __( 'YouTube URL', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()
			->text( 'itsi_footer_social_tiktok' )
			->setLabel( __( 'TikTok URL', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()
			->text( 'itsi_footer_social_twitter' )
			->setLabel( __( 'Twitter URL (twitter.com klasik)', 'itsi' ) )
			->setHelp( __( 'Pisahkan dengan X (kolom di bawah) — twitter.com vs x.com adalah platform berbeda sejak rebrand 2023.', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()
			->text( 'itsi_footer_social_x' )
			->setLabel( __( 'X URL (x.com, rebrand Twitter)', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()
			->text( 'itsi_footer_social_linkedin' )
			->setLabel( __( 'LinkedIn URL', 'itsi' ) ),

		// === Legal / nav links ===
		\TypeRocket\Utility\Helper::form()
			->text( 'itsi_footer_privacy_url' )
			->setLabel( __( 'Kebijakan Privasi (URL)', 'itsi' ) )
			->setHelp( __( 'URL halaman Privacy Policy. Kosongkan untuk sembunyikan link.', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()
			->text( 'itsi_footer_terms_url' )
			->setLabel( __( 'Syarat Penggunaan (URL)', 'itsi' ) )
			->setHelp( __( 'URL halaman Terms of Service. Kosongkan untuk sembunyikan link.', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()
			->text( 'itsi_footer_sitemap_url' )
			->setLabel( __( 'Sitemap (URL)', 'itsi' ) )
			->setHelp( __( 'Biasanya /sitemap.xml. Kosongkan untuk sembunyikan link.', 'itsi' ) ),
	) );

	$tabs->tab( __( 'Posts', 'itsi' ), 'dashicons-admin-post', array(
		\TypeRocket\Utility\Helper::form()
			->number( 'itsi_posts_per_page' )
			->setLabel( __( 'Posts per page', 'itsi' ) )
			->setHelp( __( 'Jumlah pos per halaman pada arsip (default WP: 10).', 'itsi' ) )
			->setAttribute( 'min', '1' )
			->setAttribute( 'max', '100' ),
		\TypeRocket\Utility\Helper::form()
			->number( 'itsi_posts_excerpt_length' )
			->setLabel( __( 'Excerpt length (kata)', 'itsi' ) )
			->setHelp( __( 'Panjang kutipan pos di arsip (default WP: 55).', 'itsi' ) )
			->setAttribute( 'min', '10' )
			->setAttribute( 'max', '500' ),
		\TypeRocket\Utility\Helper::form()
			->checkbox( 'itsi_posts_show_featured' )
			->setLabel( __( 'Tampilkan featured image di arsip pos', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()
			->number( 'itsi_posts_default_category' )
			->setLabel( __( 'Default category ID', 'itsi' ) )
			->setHelp( __( 'Opsional. Kosongkan jika tidak dipakai.', 'itsi' ) )
			->setAttribute( 'min', '0' ),
	) );

	// ═══ INFORMASI PUBLIK ═════════════════════════════════════════
	$tabs->tab( __( 'Informasi Publik', 'itsi' ), 'dashicons-media-document', array(
		// ── PPID Banner ─────────────────────────────────────────
		'<h3 style="margin:.2rem 0 .9rem;border-bottom:1px solid #e5e7eb;padding-bottom:.5rem">PPID Banner</h3>',
		\TypeRocket\Utility\Helper::form()
			->image( 'itsi_ip_ppid_icon' )
			->setLabel( __( 'Ikon PPID (gambar)', 'itsi' ) )
			->setHelp( __( 'Gambar ikon di kiri banner PPID. Disarankan 96×96 px, PNG/SVG transparan. Kosongkan untuk tampil tanpa ikon.', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()
			->text( 'itsi_ip_ppid_title' )
			->setLabel( __( 'Judul PPID', 'itsi' ) )
			->setHelp( __( 'Mis. PPID Institut Teknologi Sawit Indonesia', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()
			->textarea( 'itsi_ip_ppid_desc' )
			->setLabel( __( 'Deskripsi PPID', 'itsi' ) )
			->setHelp( __( 'Paragraf penjelasan PPID di banner.', 'itsi' ) )
			->setAttribute( 'rows', 3 ),

		// ── Stats Bar ──────────────────────────────────────────
		'<h3 style="margin:1.4rem 0 .9rem;border-bottom:1px solid #e5e7eb;padding-bottom:.5rem">Stats Bar</h3>',
		\TypeRocket\Utility\Helper::form()
			->repeater( 'itsi_ip_stats' )
			->setTitle( __( 'Statistik', 'itsi' ) )
			->setLimit( 6 )
			->setFields( array(
				\TypeRocket\Utility\Helper::form()
					->image( 'icon' )
					->setLabel( __( 'Ikon (gambar, opsional)', 'itsi' ) )
					->setHelp( __( 'Kosongkan jika tidak perlu ikon.', 'itsi' ) ),
				\TypeRocket\Utility\Helper::form()
					->text( 'angka' )
					->setLabel( __( 'Angka', 'itsi' ) )
					->setHelp( __( 'Mis. 22', 'itsi' ) ),
				\TypeRocket\Utility\Helper::form()
					->text( 'label' )
					->setLabel( __( 'Label', 'itsi' ) )
					->setHelp( __( 'Mis. Total Dokumen', 'itsi' ) ),
			) ),

		// ── KIP Cards ──────────────────────────────────────────
		'<h3 style="margin:1.4rem 0 .9rem;border-bottom:1px solid #e5e7eb;padding-bottom:.5rem">KIP Info Cards</h3>',
		\TypeRocket\Utility\Helper::form()
			->repeater( 'itsi_ip_kip_cards' )
			->setTitle( __( 'Kartu Info', 'itsi' ) )
			->setLimit( 8 )
			->setFields( array(
				\TypeRocket\Utility\Helper::form()
					->image( 'icon' )
					->setLabel( __( 'Ikon (gambar)', 'itsi' ) )
					->setHelp( __( 'Gambar ikon kartu, mis. gambar buku/kertas. Disarankan 96×96 px.', 'itsi' ) ),
				\TypeRocket\Utility\Helper::form()
					->text( 'title' )
					->setLabel( __( 'Judul', 'itsi' ) )
					->setHelp( __( 'Mis. UU No. 14 Tahun 2008', 'itsi' ) ),
				\TypeRocket\Utility\Helper::form()
					->textarea( 'text' )
					->setLabel( __( 'Deskripsi', 'itsi' ) )
					->setHelp( __( 'Teks singkat kartu.', 'itsi' ) )
					->setAttribute( 'rows', 3 ),
			) ),

		// ── Form Permohonan ────────────────────────────────────
		'<h3 style="margin:1.4rem 0 .9rem;border-bottom:1px solid #e5e7eb;padding-bottom:.5rem">Form Permohonan</h3>',
		\TypeRocket\Utility\Helper::form()
			->checkbox( 'itsi_ip_form_enabled' )
			->setLabel( __( 'Aktifkan form permohonan informasi', 'itsi' ) )
			->setHelp( __( 'Jika nonaktif, form tidak ditampilkan di halaman dan submit via AJAX ditolak (403).', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()
			->input( 'itsi_ip_email_to' )->setTypeEmail()
			->setLabel( __( 'Email penerima notifikasi (To)', 'itsi' ) )
			->setHelp( __( 'Email yang menerima pemberitahuan permohonan baru. Default: Email Admin situs.', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()
			->input( 'itsi_ip_email_from' )->setTypeEmail()
			->setLabel( __( 'Email pengirim (From)', 'itsi' ) )
			->setHelp( __( 'Alamat pengirim notifikasi. Default: Email Admin situs.', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()
			->text( 'itsi_ip_email_subject' )
			->setLabel( __( 'Subjek email', 'itsi' ) )
			->setHelp( __( 'Default: [ITSI] Permohonan Informasi Baru', 'itsi' ) ),
		'<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-top:.5rem">',
		\TypeRocket\Utility\Helper::form()
			->number( 'itsi_ip_rate_max' )
			->setLabel( __( 'Rate limit — maks. submit per IP', 'itsi' ) )
			->setHelp( __( 'Batas kirim form per IP dalam jendela waktu di bawah. Default 5.', 'itsi' ) )
			->setAttribute( 'min', '1' )
			->setAttribute( 'max', '100' ),
		\TypeRocket\Utility\Helper::form()
			->number( 'itsi_ip_rate_window' )
			->setLabel( __( 'Rate limit — jendela waktu (menit)', 'itsi' ) )
			->setHelp( __( 'Berapa menit jendela rate limit. Default 15.', 'itsi' ) )
			->setAttribute( 'min', '1' )
			->setAttribute( 'max', '1440' ),
		'</div>',
	) );

	$tabs->setTitle( __( 'ITSI Theme Settings', 'itsi' ) )
		->layoutTopEnclosed();

	// Pre-populate field values from theme_mod so the rendered inputs show the
	// current state. Without this, every input would render empty because
	// Helper::form() has no Model attached on a custom admin page.
	itsi_populate_tabs_from_theme_mods( $tabs );

	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'ITSI Theme Settings', 'itsi' ); ?></h1>
		<?php if ( $saved ) : ?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Pengaturan tersimpan.', 'itsi' ); ?></p>
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="itsi_settings_save">
			<?php wp_nonce_field( 'itsi_settings_save', '_itsi_nonce' ); ?>

			<?php $tabs->render(); ?>

			<p class="submit" style="margin-top:1rem">
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Simpan Perubahan', 'itsi' ); ?>
				</button>
			</p>
		</form>
	</div>
	<?php
}

/**
 * Walk a Tabs/Tab tree and pre-populate every Field's value from theme_mod.
 *
 * TypeRocket fields render content via getValue() (model-backed) rather than
 * reading the `value` HTML attribute. For Text/Number/Color/Html::input fields
 * the attribute fallback happens to work, but for WordPressEditor and Image
 * the value must come from the attached Model — otherwise the rendered
 * <textarea> is empty and the image picker shows no preview.
 *
 * Strategy: for each leaf field, if a matching theme_mod exists, attach a
 * fresh DataCollection with the stored value via setModel(). For boolean
 * checkbox values, set the `checked` attribute directly.
 *
 * @param \TypeRocket\Elements\Tabs|\TypeRocket\Elements\Components\Tab $node
 * @return void
 */
function itsi_populate_tabs_from_theme_mods( $node ) {
	if ( method_exists( $node, 'getTabs' ) ) {
		foreach ( $node->getTabs() as $child ) {
			itsi_populate_tabs_from_theme_mods( $child );
		}
		return;
	}
	// Leaf: a Tab.
	if ( method_exists( $node, 'getFields' ) ) {
		foreach ( $node->getFields() as $field ) {
			if ( ! is_object( $field ) || ! method_exists( $field, 'getName' ) ) {
				continue;
			}
			$name = $field->getName();
			if ( '' === $name || null === $name ) {
				continue;
			}
			$key = preg_replace( '/\[([^\]]+)\]/', '.$1', $name );
			$mod = get_theme_mod( $key, null );

			// Repeater `itsi_footer_layout`: value bisa belum pernah disimpan (null)
			// → tampilkan default 4 baris. Kalau sudah disimpan sebagai array / JSON /
			// serialized string → normalisasi ke array (kosong = biarkan kosong,
			// jangan ditimpa default, supaya user yang sengaja menghapus semua baris
			// tetap melihat repeater kosong).
			if ( 'itsi_footer_layout' === $key ) {
				if ( null === $mod ) {
					$mod = itsi_footer_layout_default();
				} elseif ( is_string( $mod ) ) {
					$decoded = json_decode( $mod, true );
					if ( is_array( $decoded ) ) {
						$mod = $decoded;
					} else {
						$unser = maybe_unserialize( $mod );
						$mod   = is_array( $unser ) ? $unser : array();
					}
				}
			}

			// Repeater `itsi_ip_stats` / `itsi_ip_kip_cards`: normalisasi sama
			// (null → default; string → JSON/unserialize → array). Kosong dibiarkan
			// kosong supaya admin yang sengaja menghapus semua baris tetap lihat repeater kosong.
			if ( 'itsi_ip_stats' === $key || 'itsi_ip_kip_cards' === $key ) {
				if ( is_string( $mod ) ) {
					$decoded = json_decode( $mod, true );
					if ( is_array( $decoded ) ) {
						$mod = $decoded;
					} else {
						$unser = maybe_unserialize( $mod );
						$mod   = is_array( $unser ) ? $unser : array();
					}
				}
			}

			if ( null === $mod ) {
				continue;
			}

			// Checkbox: model value would be truthy/falsy, but the render path
			// checks the `checked` attribute. Set it explicitly.
			if ( is_bool( $mod ) ) {
				if ( $mod && method_exists( $field, 'setAttribute' ) ) {
					$field->setAttribute( 'checked', 'checked' );
				}
				continue;
			}

			// Repeater `itsi_footer_layout`: dua model harus diset —
			// (1) model FIELD repeater agar getValue() (setCast('array')) mengembalikan
			//     baris-baris tersimpan → jumlah baris dirender benar;
			// (2) model FORM field agar sub-field (Label/Width) yang di-clone saat
			//     render ($form->super($k, $this)) bisa resolve nilainya lewat group
			//     path `itsi_footer_layout.0.label` dst.
			// Format: [ 'itsi_footer_layout' => [ ['label'=>'…','width'=>'…'], … ] ]
			if ( in_array( $key, array( 'itsi_footer_layout', 'itsi_ip_stats', 'itsi_ip_kip_cards' ), true ) && is_array( $mod ) ) {
				if ( method_exists( $field, 'setModel' ) ) {
					$field->setModel( array( $key => $mod ) );
				}
				$form = method_exists( $field, 'getForm' ) ? $field->getForm() : null;
				if ( $form && method_exists( $form, 'setModel' ) ) {
					$form->setModel( array( $key => $mod ) );
				}
				continue;
			}

			// Scalar value (string, int, hex, attachment id, html content):
			// inject into a DataCollection so getValue() resolves correctly for
			// WP Editor, Image, Color, Text, Number — all field types that read
			// via $this->getValue() at render time.
			if ( method_exists( $field, 'setModel' ) ) {
				$field->setModel( array( $key => $mod ) );
			}

			// Belt-and-braces: also set `value` attribute so Html::input-style
			// fields keep their attribute fallback path.
			if ( method_exists( $field, 'setAttribute' ) && is_scalar( $mod ) ) {
				$field->setAttribute( 'value', (string) $mod );
			}
		}
	}
}