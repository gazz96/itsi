<?php
/**
 * LP2M Settings — TypeRocket v6 (Free) + WordPress Native Save
 *
 * Akses: WP Admin → LP2M → Settings
 * REST API grouped by component:
 *   GET /wp-json/lp2m/v1/settings/{site|dokumen|hero|about|bidang|mitra|footer|homepage}
 *
 * Semua field dirender via TypeRocket field objects (text/textarea/image/file/
 * input/select/color/repeater) dan disimpan sebagai option wp_ (prefix lp2m_).
 * Nama field menggunakan bahasa natural (bukan kode teknis seperti 'eyebrow').
 */

defined( 'ABSPATH' ) || exit;

// =================================================================
// 1. REGISTER ADMIN MENU
// =================================================================
add_action( 'admin_menu', function () {
	add_menu_page( 'LP2M', 'LP2M', 'manage_options', 'lp2m-settings', 'lp2m_render', 'dashicons-welcome-learn-more', 30 );
} );

// =================================================================
// 2. RENDER PAGE — TypeRocket Tabs (satu halaman, tiap section = tab)
// =================================================================
function lp2m_render() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Anda tidak memiliki akses ke halaman ini.', 'itsi' ) );
	}

	$saved = isset( $_GET['lp2m_saved'] ) && '1' === $_GET['lp2m_saved']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flag.

	$tabs = new \TypeRocket\Elements\Tabs();

	// ── SITE ────────────────────────────────────────────────────────
	$tabs->tab( __( 'Site', 'itsi' ), 'dashicons-admin-site', array(
		\TypeRocket\Utility\Helper::form()->image( 'lp2m_site_logo_id' )
			->setLabel( __( 'Logo', 'itsi' ) )
			->setHelp( __( 'Logo utama LP2M. Tampil di navbar dan footer.', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()->image( 'lp2m_site_favicon_id' )
			->setLabel( __( 'Favicon', 'itsi' ) )
			->setHelp( __( 'Ikon kecil di tab browser. Ukuran ideal 512×512 px, format PNG/ICO/SVG.', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()->text( 'lp2m_site_nama' )
			->setLabel( __( 'Nama Lembaga', 'itsi' ) )
			->setHelp( __( 'Nama singkat, mis. LP2M ITSI.', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()->text( 'lp2m_site_nama_panjang' )
			->setLabel( __( 'Nama Panjang', 'itsi' ) )
			->setHelp( __( 'Nama lengkap lembaga, mis. Lembaga Penelitian dan Pengabdian kepada Masyarakat.', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()->input( 'lp2m_site_email' )->setTypeEmail()
			->setLabel( __( 'Email', 'itsi' ) )
			->setHelp( __( 'Email kontak yang tampil di footer & topbar.', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()->input( 'lp2m_site_telepon' )->setTypeTel()
			->setLabel( __( 'Telepon', 'itsi' ) )
			->setHelp( __( 'Nomor telepon kontak. Format bebas, mis. (061) 663 7060.', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()->textarea( 'lp2m_site_alamat' )
			->setLabel( __( 'Alamat', 'itsi' ) )
			->setHelp( __( 'Alamat lengkap (boleh lebih dari satu baris).', 'itsi' ) )
			->setAttribute( 'rows', 3 ),
		\TypeRocket\Utility\Helper::form()->input( 'lp2m_site_admin_email' )->setTypeEmail()
			->setLabel( __( 'Email Admin (Terima Notif)', 'itsi' ) )
			->setHelp( __( 'Email yang menerima notifikasi pendaftaran hibah baru.', 'itsi' ) ),
	) );

	// ── DOKUMEN ─────────────────────────────────────────────────────
	$tabs->tab( __( 'Dokumen', 'itsi' ), 'dashicons-media-document', array(
		\TypeRocket\Utility\Helper::form()->file( 'lp2m_dok_panduan_id' )
			->setLabel( __( 'Panduan Penulisan (PDF)', 'itsi' ) )
			->setHelp( __( 'File panduan penulisan proposal. Format PDF.', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()->file( 'lp2m_dok_template_id' )
			->setLabel( __( 'Template Dokumen (PDF)', 'itsi' ) )
			->setHelp( __( 'Template dokumen proposal. Format PDF.', 'itsi' ) ),
	) );

	// ── HERO ────────────────────────────────────────────────────────
	$tabs->tab( __( 'Hero', 'itsi' ), 'dashicons-format-image', array(
		\TypeRocket\Utility\Helper::form()->textarea( 'lp2m_hero_headline' )
			->setLabel( __( 'Headline (Baris Utama)', 'itsi' ) )
			->setHelp( __( 'Kalimat besar di hero. Mendukung HTML sederhana.', 'itsi' ) )
			->setAttribute( 'rows', 2 ),
		\TypeRocket\Utility\Helper::form()->text( 'lp2m_hero_title' )
			->setLabel( __( 'Judul', 'itsi' ) )
			->setHelp( __( 'Label kecil di atas headline (subtitle).', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()->textarea( 'lp2m_hero_caption' )
			->setLabel( __( 'Deskripsi', 'itsi' ) )
			->setHelp( __( 'Paragraf pengantar di hero.', 'itsi' ) )
			->setAttribute( 'rows', 3 ),
		\TypeRocket\Utility\Helper::form()->text( 'lp2m_hero_btn_primary_text' )
			->setLabel( __( 'Tombol Utama — Teks', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()->input( 'lp2m_hero_btn_primary_url' )->setTypeUrl()
			->setLabel( __( 'Tombol Utama — URL', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()->text( 'lp2m_hero_btn_secondary_text' )
			->setLabel( __( 'Tombol Kedua — Teks', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()->input( 'lp2m_hero_btn_secondary_url' )->setTypeUrl()
			->setLabel( __( 'Tombol Kedua — URL', 'itsi' ) ),
		// Infografis (statistik kecil di hero).
		\TypeRocket\Utility\Helper::form()->repeater( 'lp2m_hero_infografis' )
			->setTitle( __( 'Statistik', 'itsi' ) )
			->setLimit( 6 )
			->setFields( array(
				\TypeRocket\Utility\Helper::form()->text( 'label' )
					->setLabel( __( 'Label', 'itsi' ) )
					->setHelp( __( 'Mis. Dosen Aktif', 'itsi' ) ),
				\TypeRocket\Utility\Helper::form()->input( 'angka' )->setTypeNumber( 0 )
					->setLabel( __( 'Angka', 'itsi' ) )
					->setHelp( __( 'Mis. 58', 'itsi' ) ),
			) ),
	) );

	// ── ABOUT ───────────────────────────────────────────────────────
	$tabs->tab( __( 'Tentang', 'itsi' ), 'dashicons-info', array(
		\TypeRocket\Utility\Helper::form()->text( 'lp2m_about_eyebrow' )
			->setLabel( __( 'Label Kecil', 'itsi' ) )
			->setHelp( __( 'Label kecil di atas judul, mis. Tentang Kami.', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()->text( 'lp2m_about_title' )
			->setLabel( __( 'Judul', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()->textarea( 'lp2m_about_desc' )
			->setLabel( __( 'Deskripsi', 'itsi' ) )
			->setAttribute( 'rows', 4 ),
		\TypeRocket\Utility\Helper::form()->textarea( 'lp2m_about_quote' )
			->setLabel( __( 'Kutipan Utama', 'itsi' ) )
			->setHelp( __( 'Kalimat penegas di bagian tentang.', 'itsi' ) )
			->setAttribute( 'rows', 2 ),
		\TypeRocket\Utility\Helper::form()->textarea( 'lp2m_about_quote_body' )
			->setLabel( __( 'Isi Kutipan', 'itsi' ) )
			->setHelp( __( 'Paragraf lanjutan dari kutipan utama.', 'itsi' ) )
			->setAttribute( 'rows', 4 ),
		// Pilar.
		\TypeRocket\Utility\Helper::form()->repeater( 'lp2m_about_pillars' )
			->setTitle( __( 'Pilar', 'itsi' ) )
			->setLimit( 6 )
			->setFields( array(
				\TypeRocket\Utility\Helper::form()->text( 'num' )
					->setLabel( __( 'Nomor', 'itsi' ) )
					->setHelp( __( 'Mis. 01', 'itsi' ) ),
				\TypeRocket\Utility\Helper::form()->text( 'title' )
					->setLabel( __( 'Judul', 'itsi' ) ),
				\TypeRocket\Utility\Helper::form()->textarea( 'desc' )
					->setLabel( __( 'Deskripsi', 'itsi' ) )
					->setAttribute( 'rows', 2 ),
			) ),
		// Kepemimpinan.
		\TypeRocket\Utility\Helper::form()->repeater( 'lp2m_about_leadership' )
			->setTitle( __( 'Kepemimpinan', 'itsi' ) )
			->setLimit( 6 )
			->setFields( array(
				\TypeRocket\Utility\Helper::form()->text( 'role' )
					->setLabel( __( 'Peran', 'itsi' ) )
					->setHelp( __( 'Mis. Ketua LP2M', 'itsi' ) ),
				\TypeRocket\Utility\Helper::form()->text( 'name' )
					->setLabel( __( 'Nama', 'itsi' ) ),
				\TypeRocket\Utility\Helper::form()->textarea( 'unit' )
					->setLabel( __( 'Unit / Keterangan', 'itsi' ) )
					->setAttribute( 'rows', 2 ),
			) ),
	) );

	// ── BIDANG ──────────────────────────────────────────────────────
	$tabs->tab( __( 'Bidang', 'itsi' ), 'dashicons-grid-view', array(
		\TypeRocket\Utility\Helper::form()->text( 'lp2m_bidang_label' )
			->setLabel( __( 'Label Kecil', 'itsi' ) )
			->setHelp( __( 'Label kecil di atas judul, mis. Fokus Riset.', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()->text( 'lp2m_bidang_title' )
			->setLabel( __( 'Judul', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()->textarea( 'lp2m_bidang_desc' )
			->setLabel( __( 'Deskripsi', 'itsi' ) )
			->setAttribute( 'rows', 3 ),
		// Items.
		\TypeRocket\Utility\Helper::form()->repeater( 'lp2m_bidang_items' )
			->setTitle( __( 'Bidang Unggulan', 'itsi' ) )
			->setLimit( 8 )
			->setFields( array(
				\TypeRocket\Utility\Helper::form()->select( 'icon' )
					->setLabel( __( 'Ikon', 'itsi' ) )
					->setOptions( array(
						'leaf'    => 'Daun (leaf)',
						'gear'    => 'Gigi (gear)',
						'cross'   => 'Plus (cross)',
						'hexagon' => 'Segi enam (hexagon)',
					) ),
				\TypeRocket\Utility\Helper::form()->text( 'title' )
					->setLabel( __( 'Judul', 'itsi' ) ),
				\TypeRocket\Utility\Helper::form()->textarea( 'desc' )
					->setLabel( __( 'Deskripsi', 'itsi' ) )
					->setAttribute( 'rows', 2 ),
			) ),
	) );

	// ── MITRA ───────────────────────────────────────────────────────
	$tabs->tab( __( 'Mitra', 'itsi' ), 'dashicons-groups', array(
		\TypeRocket\Utility\Helper::form()->text( 'lp2m_mitra_label' )
			->setLabel( __( 'Label Kecil', 'itsi' ) )
			->setHelp( __( 'Label kecil di atas judul, mis. Kemitraan.', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()->text( 'lp2m_mitra_title' )
			->setLabel( __( 'Judul', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()->repeater( 'lp2m_mitra_items' )
			->setTitle( __( 'Mitra Kerja Sama', 'itsi' ) )
			->setLimit( 12 )
			->setFields( array(
				\TypeRocket\Utility\Helper::form()->text( 'nama' )
					->setLabel( __( 'Nama Mitra', 'itsi' ) )
					->setHelp( __( 'Mis. BUMN Perkebunan', 'itsi' ) ),
			) ),
	) );

	// ── FOOTER ──────────────────────────────────────────────────────
	$tabs->tab( __( 'Footer', 'itsi' ), 'dashicons-admin-page', array(
		\TypeRocket\Utility\Helper::form()->textarea( 'lp2m_footer_tagline' )
			->setLabel( __( 'Tagline', 'itsi' ) )
			->setHelp( __( 'Kalimat singkat tentang LP2M di footer.', 'itsi' ) )
			->setAttribute( 'rows', 2 ),
		\TypeRocket\Utility\Helper::form()->text( 'lp2m_footer_copyright' )
			->setLabel( __( 'Copyright', 'itsi' ) )
			->setHelp( __( 'Teks hak cipta di baris paling bawah footer.', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()->text( 'lp2m_footer_credit' )
			->setLabel( __( 'Credit', 'itsi' ) )
			->setHelp( __( 'Teks kredit pengelola, mis. Dikelola oleh Pusat Data LP2M.', 'itsi' ) ),
		// Tautan Cepat.
		\TypeRocket\Utility\Helper::form()->repeater( 'lp2m_footer_tautan' )
			->setTitle( __( 'Tautan Cepat', 'itsi' ) )
			->setLimit( 8 )
			->setFields( array(
				\TypeRocket\Utility\Helper::form()->text( 'label' )
					->setLabel( __( 'Label', 'itsi' ) ),
				\TypeRocket\Utility\Helper::form()->input( 'href' )->setTypeUrl()
					->setLabel( __( 'URL', 'itsi' ) ),
			) ),
		// Layanan.
		\TypeRocket\Utility\Helper::form()->repeater( 'lp2m_footer_layanan' )
			->setTitle( __( 'Layanan', 'itsi' ) )
			->setLimit( 8 )
			->setFields( array(
				\TypeRocket\Utility\Helper::form()->text( 'label' )
					->setLabel( __( 'Label', 'itsi' ) ),
				\TypeRocket\Utility\Helper::form()->input( 'href' )->setTypeUrl()
					->setLabel( __( 'URL', 'itsi' ) ),
			) ),
	) );

	// ── HOMEPAGE ────────────────────────────────────────────────────
	$tabs->tab( __( 'Homepage', 'itsi' ), 'dashicons-admin-home', array(
		\TypeRocket\Utility\Helper::form()->text( 'lp2m_home_bidang_title' )
			->setLabel( __( 'Judul Bidang', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()->textarea( 'lp2m_home_bidang_desc' )
			->setLabel( __( 'Deskripsi Bidang', 'itsi' ) )
			->setAttribute( 'rows', 3 ),
		\TypeRocket\Utility\Helper::form()->text( 'lp2m_home_mitra_title' )
			->setLabel( __( 'Judul Mitra', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()->text( 'lp2m_home_cta_title' )
			->setLabel( __( 'Judul CTA', 'itsi' ) ),
		\TypeRocket\Utility\Helper::form()->textarea( 'lp2m_home_cta_desc' )
			->setLabel( __( 'Deskripsi CTA', 'itsi' ) )
			->setAttribute( 'rows', 3 ),
		\TypeRocket\Utility\Helper::form()->textarea( 'lp2m_home_footer_tagline' )
			->setLabel( __( 'Tagline Footer', 'itsi' ) )
			->setAttribute( 'rows', 2 ),
	) );

	$tabs->setTitle( __( 'LP2M Settings', 'itsi' ) )
		->layoutTopEnclosed();

	// Pre-populate field values from option store (pola admin-menu.php).
	lp2m_populate_tabs_from_options( $tabs );

	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'LP2M Settings', 'itsi' ); ?></h1>
		<p>REST API: <code>/wp-json/lp2m/v1/settings/{site|dokumen|hero|about|bidang|mitra|footer|homepage}</code></p>
		<?php if ( $saved ) : ?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Pengaturan tersimpan.', 'itsi' ); ?></p>
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="lp2m_settings_save">
			<?php wp_nonce_field( 'lp2m_settings_save', '_lp2m_nonce' ); ?>

			<?php $tabs->render(); ?>

			<p class="submit" style="margin-top:1rem">
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Simpan Semua Pengaturan', 'itsi' ); ?>
				</button>
			</p>
		</form>
	</div>
	<?php
}

// =================================================================
// 3. POPULATE — isi nilai field dari option (agar input keisi saat render)
// =================================================================
function lp2m_populate_tabs_from_options( $node ) {
	if ( method_exists( $node, 'getTabs' ) ) {
		foreach ( $node->getTabs() as $child ) {
			lp2m_populate_tabs_from_options( $child );
		}
		return;
	}
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
			$opt = get_option( $key, null );
			if ( null === $opt ) {
				continue;
			}

			// Checkbox boolean → set checked attr.
			if ( is_bool( $opt ) ) {
				if ( $opt && method_exists( $field, 'setAttribute' ) ) {
					$field->setAttribute( 'checked', 'checked' );
				}
				continue;
			}

			// Repeater: value = JSON array → inject ke model agar dirender.
			if ( $opt && method_exists( $field, 'setModel' ) ) {
				$decoded = is_array( $opt ) ? $opt : json_decode( (string) $opt, true );
				if ( is_array( $decoded ) ) {
					$field->setModel( array( $key => $decoded ) );
				}
			} elseif ( method_exists( $field, 'setModel' ) ) {
				$field->setModel( array( $key => $opt ) );
			}

			// Belt-and-braces: value attr fallback.
			if ( method_exists( $field, 'setAttribute' ) && is_scalar( $opt ) ) {
				$field->setAttribute( 'value', (string) $opt );
			}
		}
	}
}

// =================================================================
// 4. SAVE — admin_post handler (ambil $_POST['tr'], sanitize, update_option)
// =================================================================
add_action( 'admin_post_lp2m_settings_save', 'lp2m_handle_settings_save' );
function lp2m_handle_settings_save() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Anda tidak memiliki akses untuk menyimpan pengaturan ini.', 'itsi' ) );
	}
	check_admin_referer( 'lp2m_settings_save', '_lp2m_nonce' );

	$posted = isset( $_POST['tr'] ) && is_array( $_POST['tr'] )
		? wp_unslash( $_POST['tr'] )
		: array();

	// Daftar option key → sanitizer.
	$text_keys = array(
		'lp2m_site_nama', 'lp2m_site_nama_panjang', 'lp2m_site_telepon', 'lp2m_site_alamat',
		'lp2m_hero_headline', 'lp2m_hero_title', 'lp2m_hero_caption',
		'lp2m_hero_btn_primary_text', 'lp2m_hero_btn_secondary_text',
		'lp2m_about_eyebrow', 'lp2m_about_title', 'lp2m_about_desc', 'lp2m_about_quote', 'lp2m_about_quote_body',
		'lp2m_bidang_label', 'lp2m_bidang_title', 'lp2m_bidang_desc',
		'lp2m_mitra_label', 'lp2m_mitra_title',
		'lp2m_footer_tagline', 'lp2m_footer_copyright', 'lp2m_footer_credit',
		'lp2m_home_bidang_title', 'lp2m_home_bidang_desc', 'lp2m_home_mitra_title',
		'lp2m_home_cta_title', 'lp2m_home_cta_desc', 'lp2m_home_footer_tagline',
	);
	$email_keys = array( 'lp2m_site_email', 'lp2m_site_admin_email' );
	$url_keys   = array(
		'lp2m_hero_btn_primary_url', 'lp2m_hero_btn_secondary_url',
	);
	$id_keys    = array( 'lp2m_site_logo_id', 'lp2m_site_favicon_id', 'lp2m_dok_panduan_id', 'lp2m_dok_template_id' );
	$json_keys  = array( 'lp2m_hero_infografis', 'lp2m_about_pillars', 'lp2m_about_leadership', 'lp2m_bidang_items', 'lp2m_mitra_items', 'lp2m_footer_tautan', 'lp2m_footer_layanan' );

	foreach ( $text_keys as $key ) {
		if ( isset( $posted[ $key ] ) ) {
			update_option( $key, sanitize_textarea_field( $posted[ $key ] ) );
		}
	}
	foreach ( $email_keys as $key ) {
		if ( isset( $posted[ $key ] ) ) {
			update_option( $key, sanitize_email( $posted[ $key ] ) );
		}
	}
	foreach ( $url_keys as $key ) {
		if ( isset( $posted[ $key ] ) ) {
			update_option( $key, esc_url_raw( $posted[ $key ] ) );
		}
	}
	foreach ( $id_keys as $key ) {
		if ( isset( $posted[ $key ] ) ) {
			$id = (int) $posted[ $key ];
			update_option( $key, $id > 0 ? $id : '' );
		}
	}

	// Repeater: JSON encode dari array yang dikirim (pakai tr prefix).
	foreach ( $json_keys as $key ) {
		$val = isset( $posted[ $key ] ) ? $posted[ $key ] : array();
		if ( ! is_array( $val ) ) {
			$val = array();
		}
		// Bersihkan item kosong (semua field kosong).
		$clean = array();
		foreach ( $val as $item ) {
			if ( is_array( $item ) && array_filter( $item ) ) {
				$clean[] = array_map( 'sanitize_text_field', $item );
			}
		}
		update_option( $key, wp_json_encode( $clean ) );
	}

	wp_safe_redirect(
		add_query_arg(
			array( 'page' => 'lp2m-settings', 'lp2m_saved' => '1' ),
			admin_url( 'admin.php' )
		)
	);
	exit;
}

// =================================================================
// 5. REST API — grouped by component (tetap idem, + bidang/mitra/footer)
// =================================================================
add_action( 'rest_api_init', function () {
	foreach ( array( '', 'site', 'dokumen', 'hero', 'about', 'bidang', 'mitra', 'footer', 'homepage' ) as $g ) {
		$path = $g ? '/settings/' . $g : '/settings';
		$cb   = 'lp2m_rest' . ( $g ? '_' . $g : '_all' );
		register_rest_route( 'lp2m/v1', $path, array(
			'methods'             => 'GET',
			'callback'            => $cb,
			'permission_callback' => '__return_true',
		) );
	}
} );

function lp2m_rest_all() {
	return rest_ensure_response( array(
		'site'     => lp2m_site_data(),
		'dokumen'  => lp2m_dok_data(),
		'hero'     => lp2m_hero_data(),
		'about'    => lp2m_about_data(),
		'bidang'   => lp2m_bidang_data(),
		'mitra'    => lp2m_mitra_data(),
		'footer'   => lp2m_footer_data(),
		'homepage' => lp2m_home_data(),
	) );
}
function lp2m_rest_site()    { return rest_ensure_response( lp2m_site_data() ); }
function lp2m_rest_dokumen() { return rest_ensure_response( lp2m_dok_data() ); }
function lp2m_rest_hero()    { return rest_ensure_response( lp2m_hero_data() ); }
function lp2m_rest_about()   { return rest_ensure_response( lp2m_about_data() ); }
function lp2m_rest_bidang()  { return rest_ensure_response( lp2m_bidang_data() ); }
function lp2m_rest_mitra()   { return rest_ensure_response( lp2m_mitra_data() ); }
function lp2m_rest_footer()  { return rest_ensure_response( lp2m_footer_data() ); }
function lp2m_rest_homepage(){ return rest_ensure_response( lp2m_home_data() ); }

// ── POST /settings/branding — set/clear override logo & favicon (admin) ──
function lp2m_rest_branding_update( WP_REST_Request $request ) {
	$logo_id    = (int) $request->get_param( 'logo_id' );
	$favicon_id = (int) $request->get_param( 'favicon_id' );

	// Kosong/0 → clear override (fallback ke itsi); >0 → set override.
	if ( $logo_id > 0 ) {
		update_option( 'lp2m_override_logo_id', $logo_id );
	} else {
		delete_option( 'lp2m_override_logo_id' );
	}
	if ( $favicon_id > 0 ) {
		update_option( 'lp2m_override_favicon_id', $favicon_id );
	} else {
		delete_option( 'lp2m_override_favicon_id' );
	}

	return rest_ensure_response( array(
		'success' => true,
		'site'    => lp2m_site_data(),
	) );
}
add_action( 'rest_api_init', function () {
	register_rest_route( 'lp2m/v1', '/settings/branding', array(
		'methods'             => 'POST',
		'callback'            => 'lp2m_rest_branding_update',
		'permission_callback' => function () {
			return current_user_can( 'manage_options' );
		},
	) );
} );

function lp2m_opt( $k ) { return get_option( 'lp2m_' . $k, '' ); }
function lp2m_url( $id ) { return $id ? wp_get_attachment_url( (int) $id ) : ''; }
/** Decode option JSON — tahan string JSON maupun array mentah (migrasi data). */
function lp2m_json( $k ) {
	$v = lp2m_opt( $k );
	if ( is_array( $v ) ) { return $v; }
	$decoded = json_decode( (string) $v, true );
	return is_array( $decoded ) ? $decoded : array();
}

function lp2m_site_data() {
	// Override LP2M (opsional) menang; default ambil dari itsi.
	$logo_id   = lp2m_opt( 'override_logo_id' ) ?: lp2m_opt( 'site_logo_id' );
	$favicon_id = lp2m_opt( 'override_favicon_id' ) ?: lp2m_opt( 'site_favicon_id' );
	return array(
		'logo_id'        => $logo_id,
		'logo_url'       => lp2m_url( $logo_id ),
		'favicon_id'     => $favicon_id,
		'favicon_url'    => lp2m_url( $favicon_id ),
		'logo_is_override'   => (bool) lp2m_opt( 'override_logo_id' ),
		'favicon_is_override'=> (bool) lp2m_opt( 'override_favicon_id' ),
		'nama'           => lp2m_opt( 'site_nama' ) ?: 'LP2M ITSI',
		'nama_panjang'   => lp2m_opt( 'site_nama_panjang' ),
		'email'          => lp2m_opt( 'site_email' ),
		'telepon'        => lp2m_opt( 'site_telepon' ),
		'alamat'         => lp2m_opt( 'site_alamat' ),
	);
}
function lp2m_dok_data() {
	return array(
		'panduan_id'   => lp2m_opt( 'dok_panduan_id' ),
		'panduan_url'  => lp2m_url( lp2m_opt( 'dok_panduan_id' ) ),
		'template_id'  => lp2m_opt( 'dok_template_id' ),
		'template_url' => lp2m_url( lp2m_opt( 'dok_template_id' ) ),
	);
}
function lp2m_hero_data() {
	return array(
		'headline'             => lp2m_opt( 'hero_headline' ),
		'title'                => lp2m_opt( 'hero_title' ),
		'caption'              => lp2m_opt( 'hero_caption' ),
		'btn_primary_text'     => lp2m_opt( 'hero_btn_primary_text' ),
		'btn_primary_url'      => lp2m_opt( 'hero_btn_primary_url' ),
		'btn_secondary_text'   => lp2m_opt( 'hero_btn_secondary_text' ),
		'btn_secondary_url'    => lp2m_opt( 'hero_btn_secondary_url' ),
		'infografis'           => lp2m_json( 'hero_infografis' ),
	);
}
function lp2m_about_data() {
	return array(
		'eyebrow'     => lp2m_opt( 'about_eyebrow' ),
		'title'       => lp2m_opt( 'about_title' ),
		'desc'        => lp2m_opt( 'about_desc' ),
		'quote'       => lp2m_opt( 'about_quote' ),
		'quote_body'  => lp2m_opt( 'about_quote_body' ),
		'pillars'     => lp2m_json( 'about_pillars' ),
		'leadership'  => lp2m_json( 'about_leadership' ),
	);
}
function lp2m_bidang_data() {
	return array(
		'label'       => lp2m_opt( 'bidang_label' ),
		'title'       => lp2m_opt( 'bidang_title' ),
		'desc'        => lp2m_opt( 'bidang_desc' ),
		'items'       => lp2m_json( 'bidang_items' ),
	);
}
function lp2m_mitra_data() {
	return array(
		'label'       => lp2m_opt( 'mitra_label' ),
		'title'       => lp2m_opt( 'mitra_title' ),
		'items'       => lp2m_json( 'mitra_items' ),
	);
}
function lp2m_footer_data() {
	return array(
		'tagline'    => lp2m_opt( 'footer_tagline' ),
		'copyright'  => lp2m_opt( 'footer_copyright' ),
		'credit'     => lp2m_opt( 'footer_credit' ),
		'tautan'     => lp2m_json( 'footer_tautan' ),
		'layanan'    => lp2m_json( 'footer_layanan' ),
	);
}
function lp2m_home_data() {
	return array(
		'bidang_title' => lp2m_opt( 'home_bidang_title' ),
		'bidang_desc'  => lp2m_opt( 'home_bidang_desc' ),
		'mitra_title'  => lp2m_opt( 'home_mitra_title' ),
		'cta_title'    => lp2m_opt( 'home_cta_title' ),
		'cta_desc'     => lp2m_opt( 'home_cta_desc' ),
		'footer_tagline'=> lp2m_opt( 'home_footer_tagline' ),
	);
}
