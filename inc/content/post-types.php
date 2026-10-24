<?php
/**
 * Custom Post Types, taxonomies and meta boxes registered through TypeRocket.
 *
 * Runs inside the `typerocket_loaded` action so the plugin is guaranteed to be
 * available. The whole block is kept exactly as it was in functions.php: the
 * TypeRocket field definitions, meta boxes, admin columns and the nested
 * `init` hook (which registers the CPTs themselves) are order-sensitive and must
 * not be reshuffled.
 *
 * @package itsi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register Custom Post Types, Taxonomies & Meta Boxes via TypeRocket.
 *
 * TypeRocket auto-registers the post types when this hook fires — no
 * `add_action( 'init', ... )` needed. Docs: https://typerocket.com/docs/v6/post-types/
 */
add_action( 'typerocket_loaded', function () {

	// (Pengumuman uses the standard `post` post type + `category` taxonomy.
	//  Filter it from the section component via the Categories field.)

	// ═══ INFORMASI PUBLIK ═════════════════════════════════════
	$info_publik = tr_post_type( 'Informasi Publik', 'Informasi Publik' );
	$info_publik->setId( 'info_publik' );
	$info_publik->setSlug( 'informasi-publik' );
	$info_publik->setIcon( 'dashicons-media-document' );
	$info_publik->setPosition( 7 );
	$info_publik->setSupports( array( 'title', 'editor', 'excerpt', 'thumbnail' ) );
	$info_publik->setRest( 'info_publik' );
	$info_publik->setTitlePlaceholder( 'Tulis judul dokumen...' );
	$info_publik->setArchivePostsPerPage( 12 );

	// ═══ PROGRAM STUDI ════════════════════════════════════════
	$program_studi = tr_post_type( 'Program Studi', 'Program Studi' );
	$program_studi->setId( 'program_studi' );
	$program_studi->setSlug( 'program-studi' );
	$program_studi->setIcon( 'dashicons-welcome-learn-more' );
	$program_studi->setPosition( 8 );
	$program_studi->setSupports( array( 'title', 'editor', 'excerpt', 'thumbnail' ) );
	$program_studi->setRest( 'program_studi' );
	$program_studi->setTitlePlaceholder( 'Tulis nama program studi...' );
	$program_studi->setArchivePostsPerPage( 9 );

	// Force classic editor for program_studi so the TypeRocket 'Detail Program Studi'
	// meta box (which uses wpEditor/textarea) renders properly. Gutenberg hides all
	// classic meta boxes in its iframe region — filter opt-out keeps TR visible.
	add_filter( 'use_block_editor_for_post', function ( $use, $post ) {
		if ( $post instanceof \WP_Post && isset( $post->post_type ) && 'program_studi' === $post->post_type ) {
			return false;
		}
		return $use;
	}, 10, 2 );

	// ═══ PERMOHONAN INFORMASI (private, for form submissions) ═
	$permohonan = tr_post_type( 'Permohonan Informasi', 'Permohonan Informasi' );
	$permohonan->setId( 'permohonan_informasi' );
	$permohonan->setSlug( 'permohonan-informasi' );
	$permohonan->setIcon( 'dashicons-email-alt' );
	$permohonan->setPosition( 9 );
	$permohonan->setSupports( array( 'title', 'editor' ) );
	$permohonan->setTitlePlaceholder( 'Otomatis: Permohonan Informasi – {nama}' );
	$permohonan->setArgument( 'public', false );
	$permohonan->setArgument( 'exclude_from_search', true );
	$permohonan->setArgument( 'show_in_rest', false );
	$permohonan->setArchivePostsPerPage( 20 );

	// ═══ HIBAH ════════════════════════════════════════════════
	$hibah = tr_post_type( 'Hibah', 'Hibah' );
	$hibah->setId( 'hibah' );
	$hibah->setSlug( 'hibah' );
	$hibah->setIcon( 'dashicons-awards' );
	$hibah->setPosition( 10 );
	$hibah->setSupports( array( 'title', 'editor', 'excerpt', 'thumbnail' ) );
	$hibah->setRest( 'hibah' );
	$hibah->setTitlePlaceholder( 'Tulis judul event hibah...' );
	$hibah->setArchivePostsPerPage( 12 );

	// Force classic editor for hibah + pendaftaran_hibah so the TypeRocket
	// meta boxes render properly. Gutenberg hides all classic meta boxes.
	add_filter( 'use_block_editor_for_post', function ( $use, $post ) {
		if ( $post instanceof \WP_Post && isset( $post->post_type )
			&& in_array( $post->post_type, array( 'hibah', 'pendaftaran_hibah' ), true ) ) {
			return false;
		}
		return $use;
	}, 10, 2 );

	// ═══ PENDAFTARAN HIBAH (data submit LP2M) ═════════════════
	// CPT dipusatkan di functions.php agar terlihat di Theme Builder;
	// metabox Detail Pendaftaran ada di inc/lp2m/class-hibah-receiver.php
	// (TypeRocket form konsisten + sync file proposal TR ↔ REST).
	// Penting: public=false saja bikin menu hilang — set show_ui/show_in_menu eksplisit.
	$pendaftaran_hibah = tr_post_type( 'Pendaftaran Hibah', 'Pendaftaran Hibah' );
	$pendaftaran_hibah->setId( 'pendaftaran_hibah' );
	$pendaftaran_hibah->setSlug( 'pendaftaran-hibah' );
	$pendaftaran_hibah->setIcon( 'dashicons-email-alt' );
	$pendaftaran_hibah->setPosition( 11 );
	$pendaftaran_hibah->setSupports( array( 'title' ) );
	$pendaftaran_hibah->setTitlePlaceholder( 'Otomatis — jangan edit manual' );
	$pendaftaran_hibah->setArgument( 'public', false );
	$pendaftaran_hibah->setArgument( 'publicly_queryable', false );
	$pendaftaran_hibah->setArgument( 'has_archive', false );
	$pendaftaran_hibah->setArgument( 'show_ui', true );
	$pendaftaran_hibah->setArgument( 'show_in_menu', true );
	$pendaftaran_hibah->setArgument( 'show_in_admin_bar', true );
	$pendaftaran_hibah->setArgument( 'exclude_from_search', true );
	$pendaftaran_hibah->setArgument( 'show_in_rest', false );
	$pendaftaran_hibah->setArchivePostsPerPage( 20 );

	// ═══ TAXONOMIES ════════════════════════════════════════════
	// (Kategori Pengumuman uses the standard `category` taxonomy.
	//  Pick categories in the Pengumuman section component.)

	// (Artikel uses the standard `post` post type + `category` taxonomy.
	//  Filter it from the section component via the Categories field.)

	$fakultas = tr_taxonomy( 'Fakultas', 'Fakultas' );
	$fakultas->setId( 'fakultas' );
	$fakultas->setSlug( 'fakultas' );
	$fakultas->setHierarchical( true );
	$fakultas->addPostType( 'program_studi' );

	$kat_info = tr_taxonomy( 'Kategori Informasi', 'Kategori Informasi' );
	$kat_info->setId( 'kategori_info' );
	$kat_info->setSlug( 'kategori-info' );
	$kat_info->setHierarchical( true );
	$kat_info->setRest( 'kategori_info' );
	$kat_info->addPostType( 'info_publik' );

	$kat_hibah = tr_taxonomy( 'Kategori Hibah', 'Kategori Hibah' );
	$kat_hibah->setId( 'kategori_hibah' );
	$kat_hibah->setSlug( 'kategori-hibah' );
	$kat_hibah->setHierarchical( true );
	$kat_hibah->setRest( 'kategori_hibah' );
	$kat_hibah->addPostType( 'hibah' );

	$skema_hibah = tr_taxonomy( 'Model Hibah', 'Model Hibah' );
	$skema_hibah->setId( 'model_hibah' );
	$skema_hibah->setSlug( 'model-hibah' );
	$skema_hibah->setHierarchical( true );
	$skema_hibah->setRest( 'model_hibah' );
	$skema_hibah->addPostType( 'hibah' );

	$jenis_hibah = tr_taxonomy( 'Jenis Hibah', 'Jenis Hibah' );
	$jenis_hibah->setId( 'jenis_hibah' );
	$jenis_hibah->setSlug( 'jenis-hibah' );
	$jenis_hibah->setHierarchical( true );
	$jenis_hibah->setRest( 'jenis_hibah' );
	$jenis_hibah->addPostType( 'hibah' );

	$sdgs = tr_taxonomy( 'SDGs (Sustainable Development Goals)', 'SDGs' );
	$sdgs->setId( 'sdgs' );
	$sdgs->setSlug( 'sdgs' );
	$sdgs->setHierarchical( false );
	$sdgs->setRest( 'sdgs' );
	$sdgs->addPostType( 'hibah' );

	$kelompok_keahlian = tr_taxonomy( 'Kelompok Keahlian', 'Kelompok Keahlian' );
	$kelompok_keahlian->setId( 'kelompok_keahlian' );
	$kelompok_keahlian->setSlug( 'kelompok-keahlian' );
	$kelompok_keahlian->setHierarchical( true );
	$kelompok_keahlian->setRest( 'kelompok_keahlian' );
	$kelompok_keahlian->addPostType( 'hibah' );

	// ── Migrasi & seed: skema_hibah → model_hibah + default SDGs ──
	add_action( 'init', function () {
		if ( ! taxonomy_exists( 'skema_hibah' ) ) { return; }

		// 1) Migrasi term skema_hibah → model_hibah (sekali saja, via option flag).
		if ( ! get_option( 'itsi_model_hibah_migrated' ) ) {
			$old_terms = get_terms( array(
				'taxonomy'   => 'skema_hibah',
				'hide_empty' => false,
			) );
			if ( ! is_wp_error( $old_terms ) ) {
				foreach ( $old_terms as $term ) {
					$exists = term_exists( $term->name, 'model_hibah' );
					if ( ! $exists ) {
						$new = wp_insert_term( $term->name, 'model_hibah', array(
							'slug'        => $term->slug,
							'parent'      => 0,
							'description' => $term->description,
						) );
						if ( ! is_wp_error( $new ) ) {
							$new_id = (int) $new['term_id'];
						} else {
							$maybe = term_exists( $term->name, 'model_hibah' );
							$new_id = is_array( $maybe ) ? (int) $maybe['term_id'] : 0;
						}
					} else {
						$new_id = is_array( $exists ) ? (int) $exists['term_id'] : (int) $exists;
					}

					// Pindahkan relasi post (obj_id → old term) ke term baru.
					if ( $new_id ) {
						global $wpdb;
						$posts = $wpdb->get_col( $wpdb->prepare(
							"SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id = %d",
							(int) $term->term_taxonomy_id
						) );
						foreach ( $posts as $pid ) {
							wp_set_object_terms( (int) $pid, array( $new_id ), 'model_hibah', true );
						}
					}
				}
			}
			update_option( 'itsi_model_hibah_migrated', 1 );
		}

		// 2) Seed default SDGs (17 tujuan) kalau taxonomy masih kosong.
		if ( ! get_option( 'itsi_sdgs_seeded' ) ) {
			$sdgs = array(
				'1 No Poverty', '2 Zero Hunger', '3 Good Health and Well-being',
				'4 Quality Education', '5 Gender Equality', '6 Clean Water and Sanitation',
				'7 Affordable and Clean Energy', '8 Decent Work and Economic Growth',
				'9 Industry, Innovation and Infrastructure', '10 Reduced Inequality',
				'11 Sustainable Cities and Communities', '12 Responsible Consumption and Production',
				'13 Climate Action', '14 Life Below Water', '15 Life on Land',
				'16 Peace and Justice Strong Institutions', '17 Partnerships for the Goals',
			);
			$existing = get_terms( array( 'taxonomy' => 'sdgs', 'hide_empty' => false, 'fields' => 'names' ) );
			$existing = is_wp_error( $existing ) ? array() : $existing;
			foreach ( $sdgs as $name ) {
				if ( ! in_array( $name, $existing, true ) ) {
					wp_insert_term( $name, 'sdgs', array( 'slug' => sanitize_title( $name ) ) );
				}
			}
			update_option( 'itsi_sdgs_seeded', 1 );
		}
	} );

	// ═══ META BOXES ════════════════════════════════════════════
	tr_meta_box( 'Prioritas Pengumuman' )
		->addPostType( 'post' )
		->setCallback(
			function () {
				$form = \TypeRocket\Utility\Helper::form();
				echo '<p style="margin:0"><label style="display:flex;align-items:center;gap:.5rem;cursor:pointer">';
				echo $form->checkbox( 'sangat_penting' )->setLabel( 'Tandai sebagai SANGAT PENTING (badge merah)' )->setAttribute( 'style', 'width:auto' );
				echo '</label></p>';
			}
		);

	tr_meta_box( 'Detail Dokumen Informasi Publik' )
		->addPostType( 'info_publik' )
		->setCallback(
			function () {
				$form = \TypeRocket\Utility\Helper::form();
				echo '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem">';
				echo '<div><label style="display:block;font-weight:600;margin-bottom:.4rem">Tahun Dokumen</label>' . $form->number( 'tahun' )->setAttribute( 'min', 2000 )->setAttribute( 'max', 2099 ) . '</div>';
				echo '<div><label style="display:block;font-weight:600;margin-bottom:.4rem">Ukuran File</label>' . $form->text( 'ukuran_file' )->setAttribute( 'placeholder', 'mis. 2.4 MB' ) . '</div>';
				echo '<div><label style="display:block;font-weight:600;margin-bottom:.4rem">URL File (PDF)</label>' . $form->text( 'file_url' )->setAttribute( 'placeholder', 'https://…' ) . '</div>';
				echo '</div>';
			}
		);

	tr_meta_box( 'Detail Program Studi' )
		->addPostType( 'program_studi' )
		->setCallback(
			function () {
				$form = \TypeRocket\Utility\Helper::form();
				$tabs = \TypeRocket\Elements\Tabs::new();

				/* ─── TAB 1: Statistik ─── */
							$tabs->tab( 'Statistik', 'dashicons-chart-bar', array(
								'<div style="margin-bottom:1rem">'
								. $form->image( 'prodi_icon_image' )->setLabel( 'Icon Program Studi (gambar)' )->setHelp( 'Gambar kecil (~40×40 px) yang ditampilkan di kartu prodi pada section "Program Studi" di homepage. Kosongkan jika tidak ada — slot akan kosong (tanpa emoji fallback).' )
								. '</div>'
								. '<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">'
								. '<div>' . $form->text( 'gelar' )->setLabel( 'Gelar' )->setAttribute( 'placeholder', 'mis. S.T., S.P., M.P.' ) . '</div>'
								. '<div>' . $form->text( 'akreditasi' )->setLabel( 'Akreditasi' )->setAttribute( 'placeholder', 'mis. Unggul / A / B' ) . '</div>'
								. '<div>' . $form->number( 'durasi' )->setLabel( 'Durasi Studi (semester)' )->setAttribute( 'placeholder', '8' ) . '</div>'
								. '<div>' . $form->number( 'total_sks' )->setLabel( 'Total SKS' )->setAttribute( 'placeholder', '144' ) . '</div>'
								. '<div>' . $form->number( 'jumlah_dosen' )->setLabel( 'Jumlah Dosen' )->setAttribute( 'placeholder', '22' ) . '</div>'
								. '<div>' . $form->number( 'tahun_berdiri' )->setLabel( 'Tahun Berdiri' )->setAttribute( 'placeholder', '2005' ) . '</div>'
																		. '</div>'
																		. '<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-top:1rem">'
																		. '<div>' . $form->select( 'jenjang' )->setLabel( 'Jenjang' )->setOptions( array(
																													'D3 — Diploma 3'                  => 'D3',
																													'D4 — Diploma 4 / Sarjana Terapan' => 'D4',
																													'S1 — Sarjana'                    => 'S1',
																													'S2 — Magister'                   => 'S2',
																													'S3 — Doktor'                     => 'S3',
																													'Profesi — Pendidikan Profesi'    => 'Profesi',
																												) )->setAttribute( 'style', 'width:100%' ) . '</div>'
																		. '<div></div>'
																		. '</div>'
																		. '<div style="margin-top:1rem;padding:1rem;background:#f6f8fc;border-radius:8px;border-left:3px solid #2271b3">'
																		. '<h4 style="margin:.2rem 0 .6rem">🏷️ Chip Hero (Override Manual)</h4>'
																		. '<p style="margin:0 0 .8rem;font-size:.85em;color:#666">5 chip yang tampil di baris badge di bawah judul hero. <strong>Kosongkan semua untuk tidak menampilkan chip sama sekali</strong> — tidak ada fallback otomatis. Setiap field adalah string lengkap (sudah termasuk emoji + label).</p>'
																		. '<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">'
																		. '<div>' . $form->text( 'chip_gelar' )->setLabel( 'Chip — Gelar / S.Tr.P' )->setAttribute( 'placeholder', 'mis. 🎓 S.Tr.P' ) . '</div>'
																		. '<div>' . $form->text( 'chip_jenjang' )->setLabel( 'Chip — Jenjang' )->setAttribute( 'placeholder', 'mis. 🎓 Jenjang D4 — Diploma 4 / Sarjana Terapan' ) . '</div>'
																		. '<div>' . $form->text( 'chip_akreditasi' )->setLabel( 'Chip — Akreditasi' )->setAttribute( 'placeholder', 'mis. 🏆 Akreditasi Baik' ) . '</div>'
																		. '<div>' . $form->text( 'chip_berdiri' )->setLabel( 'Chip — Tahun Berdiri' )->setAttribute( 'placeholder', 'mis. 📅 Berdiri 2005' ) . '</div>'
																		. '<div>' . $form->text( 'chip_semester' )->setLabel( 'Chip — Durasi' )->setAttribute( 'placeholder', 'mis. 🕐 8 Semester' ) . '</div>'
																		. '</div>'
																		. '</div>'
													) );

				/* ─── TAB 2: Hero & Sidebar ─── */
				$tabs->tab( 'Hero & Sidebar', 'dashicons-cover-image', array(
					'<div style="padding:1rem;background:#f6f8fc;border-radius:8px;margin-bottom:1rem">'
					. '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;margin-bottom:1rem">'
					. '<div>' . $form->text( 'akreditasi_value' )->setLabel( 'Akreditasi BAN-PT (kode)' )->setAttribute( 'placeholder', 'mis. B / Unggul / A' ) . '</div>'
					. '<div>' . $form->text( 'akreditasi_sub' )->setLabel( 'Status Akreditasi' )->setAttribute( 'placeholder', 'Terakreditasi Baik' ) . '</div>'
					. '<div>' . $form->text( 'sk_akreditasi' )->setLabel( 'No. SK Akreditasi' )->setAttribute( 'placeholder', 'mis. 5828/D/T/K-I/2011' ) . '</div>'
					. '<div>' . $form->select( '_use_default_content' )->setLabel( 'Gunakan Konten Default BDP (LEGACY — tidak digunakan lagi)' )->setOptions( array( '1' => 'Ya (fallback BDP)', '0' => 'Tidak (kosong jika belum diisi)' ) )->setAttribute( 'style', 'width:100%' ) . '</div>'
					. '</div>'
					. '<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">'
					. '<div>' . $form->text( 'pmb_label' )->setLabel( 'PMB — Label' )->setAttribute( 'placeholder', 'Brosur / Formulir PMB' ) . '</div>'
					. '<div>' . $form->file( 'pmb_url' )->setLabel( 'PMB — File Brosur/Formulir' )->setHelp( 'PDF, gambar, atau dokumen. Disarankan PDF.' ) . '</div>'
					. '</div>'
					. '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;margin-top:1rem">'
					. '<div>' . $form->textarea( 'hero_subtitle' )->setLabel( 'Hero Subtitle' )->setAttribute( 'rows', 3 )->setAttribute( 'placeholder', 'Mencetak Sarjana Terapan…' ) . '</div>'
					. '<div>' . $form->text( 'hero_badge_icon' )->setLabel( 'Hero Badge Icon (bi-* atau emoji)' )->setHelp( 'Isi class Bootstrap Icons (mis. bi-tree) untuk icon, atau emoji legacy. Render mendukung keduanya.' )->setAttribute( 'placeholder', 'bi-tree' ) . '</div>'
					. '<div>' . $form->text( 'hero_badge_text' )->setLabel( 'Hero Badge Text' )->setAttribute( 'placeholder', 'D4 · Fakultas Vokasi' ) . '</div>'
					. '</div>'
					. '</div>'
					. '<div style="padding:1rem;background:#eef7ff;border-radius:8px;border-left:3px solid #0d6efd;margin-bottom:1rem">'
					. '<h4 style="margin:.2rem 0 .6rem">📰 Panel Berita &amp; Kegiatan Prodi</h4>'
					. '<p style="margin:0 0 .8rem;font-size:.85em;color:#666">Pilih kategori berita (taxonomy <strong>Kategori</strong> dari post type <strong>Post</strong>) yang tampil di panel <em>Kegiatan Prodi</em> pada halaman prodi. Ketik untuk mencari, lalu klik hasilnya untuk menambah (bisa lebih dari satu, urutkan/drag untuk prioritas). <strong>Kosongkan = 3 berita terbaru</strong> (tanpa filter kategori).</p>'
					. '<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">'
					. '<div>' . $form->search( 'berita_kategori' )->setLabel( 'Kategori Berita' )->multiple()->setTaxonomyOptions( 'category' ) . '</div>'
					. '<div>' . $form->number( 'berita_jumlah' )->setLabel( 'Jumlah Berita' )->setAttribute( 'min', '1' )->setAttribute( 'max', '12' )->setAttribute( 'placeholder', '3' )->setHelp( 'Kosongkan untuk 3 berita terbaru.' ) . '</div>'
					. '</div>'
					. '</div>'
					. '<div style="padding:1rem;background:#fff8e1;border-radius:8px;border-left:3px solid #f0b429">'
					. '<h4 style="margin:.2rem 0 .6rem">🏛️ Struktur Organisasi (Opsional — Gambar Alternatif)</h4>'
					. '<p style="margin:0 0 .8rem;font-size:.85em;color:#666">Upload gambar bagan struktur organisasi dari Media Library. <strong>Jika diisi, gambar ini akan menggantikan bagan default (pohon)</strong> di section Struktur Organisasi. Kosongkan untuk tetap pakai bagan default.</p>'
					. $form->image( 'struktur_organisasi_image' )->setLabel( 'Gambar Struktur Organisasi' )->setHelp( 'Format: PNG / JPG / SVG. Disarankan rasio landscape & lebar minimal 1000 px agar tajam.' )
					. '</div>'
				) );

				/* ─── TAB 3: Profil & Visi ─── */
				$tabs->tab( 'Profil & Visi', 'dashicons-text-page', array(
					'<div style="margin-bottom:.6rem">' . $form->wpEditor( 'profil' )->setLabel( 'Profil Singkat' )->setHelp( 'Tampil di panel Profil Prodi, di atas Sejarah & Timeline.' ) . '</div>'
					. '<div style="margin-bottom:.6rem">' . $form->wpEditor( 'visi' )->setLabel( 'Visi (rich text)' )->setHelp( 'Tampil di panel Visi & Misi. Boleh pakai bold/paragraf.' ) . '</div>'
					. '<div style="margin-bottom:.8rem">' . $form->textarea( 'tujuan_text' )->setLabel( 'Tujuan & Kompetensi (LEGACY — tidak dirender, pakai repeater Tujuan di tab Misi & Lulusan)' ) . '</div>'
				) );

				/* ─── TAB 4: Misi, Tujuan, Kompetensi, Lulusan ─── */
				$misi = $form->repeater( 'misi' )->setFields(
					array(
						$form->text( 'Icon' )->setAttribute( 'placeholder', 'bi-bullseye' ),
						$form->textarea( 'Teks Misi' )->setAttribute( 'rows', 3 ),
					)
				);
				$tujuan = $form->repeater( 'tujuan' )->setFields(
					array(
						$form->text( 'Icon' )->setAttribute( 'placeholder', 'bi-bullseye' ),
						$form->textarea( 'Teks Tujuan' )->setAttribute( 'rows', 3 ),
					)
				);
				$kompetensi = $form->repeater( 'kompetensi' )->setFields(
					array(
						$form->text( 'Icon' )->setAttribute( 'placeholder', 'bi-patch-check' ),
						$form->text( 'Nama Kompetensi' )->setAttribute( 'placeholder', 'Pengelolaan Budidaya Tanaman Kelapa Sawit' ),
					)
				);
				$lulusan = $form->repeater( 'lulusan' )->setFields(
					array(
						$form->text( 'Icon' )->setAttribute( 'placeholder', 'bi-briefcase' ),
						$form->text( 'Nama Karir' )->setAttribute( 'placeholder', 'Asisten Kebun Kelapa Sawit' ),
						$form->textarea( 'Deskripsi' )->setAttribute( 'rows', 2 ),
					)
				);
				$tabs->tab( 'Misi & Lulusan', 'dashicons-list-view', array(
					'<h4 style="margin:.4rem 0 .5rem">📜 Misi (poin per item)</h4>' . $misi
					. '<h4 style="margin:1.2rem 0 .5rem">🎯 Tujuan Program Studi (icon + text)</h4>' . $tujuan
					. '<h4 style="margin:1.2rem 0 .5rem">✅ Kompetensi Utama (icon + nama)</h4>' . $kompetensi
					. '<h4 style="margin:1.2rem 0 .5rem">🎓 Profil Lulusan / Karir (icon + nama + deskripsi)</h4>' . $lulusan
				) );

				/* ─── TAB 5: Dosen ─── */
				$dosen = $form->repeater( 'dosen' )->setFields(
					array(
						$form->text( 'Inisial (2 huruf)' )->setAttribute( 'placeholder', 'AF' )->setAttribute( 'maxlength', 3 ),
						$form->text( 'Nama Lengkap + Gelar' )->setAttribute( 'placeholder', 'Dr. Ahmad Fauzi' ),
						$form->text( 'NIDN' )->setAttribute( 'placeholder', '0117128903' ),
						//$form->text( 'Universitas' )->setAttribute( 'placeholder', 'Institut Teknologi Bandung' ),
						$form->text( 'Bidang Keilmuan' )->setAttribute( 'placeholder', 'Pertanian' ),
						$form->select( 'Jenjang' )->setOptions( array( 's3' => 'S3 — Doktor', 's2' => 'S2 — Magister' ) )->setAttribute( 'style', 'width:100%' ),
						$form->image( 'Foto Dosen' )->setLabel( 'Foto Dosen (opsional)' )->setHelp( 'Pilih/upload foto dari Media Library. Jika kosong, kartu dosen menampilkan avatar inisial otomatis.' ),
					)
				);
				$tabs->tab( 'Dosen', 'dashicons-groups', array(
					'<h4 style="margin:.4rem 0 .5rem">👨‍🏫 Dosen &amp; Tenaga Pengajar</h4>' . $dosen
				) );

				/* ─── TAB 6: Mata Kuliah ─── */
				$mk = $form->repeater( 'mk_semesters' )->setFields(
					array(
						$form->text( 'No Semester' )->setAttribute( 'placeholder', '1' ),
						$form->select( 'Tipe Semester' )->setOptions( array( 'ganjil' => 'Ganjil', 'genap' => 'Genap' ) )->setAttribute( 'style', 'width:100%' ),
						$form->number( 'Total SKS' )->setAttribute( 'placeholder', '20' ),
						$form->repeater( 'Daftar Mata Kuliah' )->setFields(
							array(
								$form->text( 'Kode' )->setAttribute( 'placeholder', 'BDP101' ),
								$form->text( 'Nama Mata Kuliah' ),
								$form->number( 'SKS' )->setAttribute( 'placeholder', '3' ),
								$form->select( 'Jenis' )->setOptions( array( 'Wajib' => 'Wajib', 'Pilihan' => 'Pilihan', 'Praktik' => 'Praktik' ) )->setAttribute( 'style', 'width:100%' ),
							)
						),
					)
				);
				$tabs->tab( 'Mata Kuliah', 'dashicons-book', array(
					'<h4 style="margin:.4rem 0 .5rem">📚 Mata Kuliah per Semester</h4>' . $mk
					. '<p style="font-size:.85em;color:#666;margin-top:.6rem"><em>Catatan: isi repeater ini untuk menampilkan kurikulum dari data admin (per semester: No, Tipe Ganjil/Genap, Total SKS, daftar Kode/Nama/SKS/Jenis). Bila repeater <strong>kosong</strong>, template otomatis memuat fallback kurikulum BDP default (8 semester / 144 SKS) sehingga halaman tidak pernah kosong.</em></p>'
				) );

				/* ─── TAB 7: Sejarah & Timeline ─── */
				$timeline = $form->repeater( 'timeline' )->setFields(
					array(
						$form->text( 'Tahun' )->setAttribute( 'placeholder', '2005' ),
						$form->text( 'Judul' )->setAttribute( 'placeholder', 'Pendirian Prodi' ),
						$form->textarea( 'Deskripsi' )->setAttribute( 'rows', 2 ),
						$form->checkbox( 'Highlight' )->setLabel( 'Tandai sebagai milestone emas (gold)' )->setAttribute( 'style', 'width:auto' ),
					)
				);
				$tabs->tab( 'Sejarah & Timeline', 'dashicons-backup', array(
					'<div style="margin-bottom:.8rem">' . $form->wpEditor( 'sejarah' )->setLabel( '📖 Sejarah (rich text)' ) . '</div>'
					. '<h4 style="margin:1rem 0 .5rem">⏳ Timeline Sejarah (tahun + judul + deskripsi)</h4>' . $timeline
				) );

				/* ─── TAB 8: Fasilitas & Mitra ─── */
				$fas = $form->repeater( 'fasilitas' )->setFields(
					array(
						$form->text( 'Icon' )->setAttribute( 'placeholder', 'bi-buildings' ),
						$form->text( 'Nama Fasilitas' )->setAttribute( 'placeholder', 'Laboratorium Kultur Jaringan' ),
						$form->textarea( 'Deskripsi' )->setAttribute( 'rows', 2 ),
					)
				);
				$mit = $form->repeater( 'mitra' )->setFields(
					array(
						$form->text( 'Nama Mitra' )->setAttribute( 'placeholder', 'PT Perkebunan Nusantara III' ),
						$form->text( 'URL Logo' )->setAttribute( 'placeholder', 'https://...' ),
						$form->text( 'Website' )->setAttribute( 'placeholder', 'https://...' ),
					)
				);
				$tabs->tab( 'Fasilitas & Mitra', 'dashicons-building', array(
					'<h4 style="margin:.4rem 0 .5rem">🏢 Fasilitas (icon + nama + deskripsi)</h4>' . $fas
					. '<h4 style="margin:1.2rem 0 .5rem">🤝 Mitra Industri / Kerjasama</h4>' . $mit
				) );

				/* ─── TAB 9: Prestasi & Testimoni ─── */
				$pres = $form->repeater( 'prestasi' )->setFields(
					array(
						$form->text( 'Tahun' )->setAttribute( 'placeholder', '2024' ),
						$form->text( 'Judul Prestasi' ),
						$form->textarea( 'Deskripsi' )->setAttribute( 'rows', 2 ),
					)
				);
				$test = $form->repeater( 'testimoni' )->setFields(
					array(
						$form->text( 'Nama Alumni' ),
						$form->text( 'Angkatan / Profesi' )->setAttribute( 'placeholder', 'Angkatan 2018 · Manager PT X' ),
						$form->textarea( 'Quote Testimoni' )->setAttribute( 'rows', 3 ),
					)
				);
				$tabs->tab( 'Prestasi & Alumni', 'dashicons-awards', array(
					'<h4 style="margin:.4rem 0 .5rem">🏆 Prestasi Mahasiswa (tahun + judul + deskripsi)</h4>' . $pres
					. '<h4 style="margin:1.2rem 0 .5rem">💬 Testimoni Alumni (nama + angkatan + quote)</h4>' . $test
				) );

				/* ─── TAB 10: CPL ─── */
				$tabs->tab( 'Capaian Pembelajaran', 'dashicons-welcome-learn-more', array(
					'<p style="color:#666;margin-top:0">Kompetensi lulusan sesuai standar KKNI level 6 &amp; OBE.</p>'
					. '<div style="margin-bottom:.6rem">' . $form->wpEditor( 'cpl_pengetahuan' )->setLabel( '📚 Pengetahuan' ) . '</div>'
					. '<div style="margin-bottom:.6rem">' . $form->wpEditor( 'cpl_keterampilan' )->setLabel( '🛠️ Keterampilan Khusus' ) . '</div>'
					. '<div style="margin-bottom:.6rem">' . $form->wpEditor( 'cpl_sikap' )->setLabel( '🌟 Sikap &amp; Tanggung Jawab' ) . '</div>'
				) );

				$tabs->layoutLeftEnclosed()->render();
			}
		);

	tr_meta_box( 'Detail Permohonan' )
		->addPostType( 'permohonan_informasi' )
		->setCallback(
			function () {
				$form = \TypeRocket\Utility\Helper::form();
				echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">';
				echo '<div><label style="display:block;font-weight:600;margin-bottom:.4rem">Nama Lengkap</label>' . $form->text( 'nama' ) . '</div>';
				echo '<div><label style="display:block;font-weight:600;margin-bottom:.4rem">NIK</label>' . $form->text( 'nik' ) . '</div>';
				echo '<div><label style="display:block;font-weight:600;margin-bottom:.4rem">Email</label>' . $form->text( 'email' ) . '</div>';
				echo '<div><label style="display:block;font-weight:600;margin-bottom:.4rem">No. HP</label>' . $form->text( 'no_hp' ) . '</div>';
				echo '<div><label style="display:block;font-weight:600;margin-bottom:.4rem">Pekerjaan</label>' . $form->text( 'pekerjaan' ) . '</div>';
				echo '<div><label style="display:block;font-weight:600;margin-bottom:.4rem">Cara Penerimaan</label>' . $form->select( 'cara_penerimaan' )->setOptions( array( 'email' => 'Email', 'pos' => 'Pos', 'langsung' => 'Diambil Langsung' ) ) . '</div>';
				echo '</div>';
				echo '<div style="margin-top:.8rem"><label style="display:block;font-weight:600;margin-bottom:.4rem">Tujuan Permohonan Informasi</label>' . $form->textarea( 'tujuan' ) . '</div>';
			}
		);

	// ═══ META BOX — Detail Hibah ════════════════════════════
	tr_meta_box( 'Detail Hibah' )
		->addPostType( 'hibah' )
		->setCallback(
			function () {
				$form = \TypeRocket\Utility\Helper::form();
				$tabs = \TypeRocket\Elements\Tabs::new();

				/* ─── TAB 1: Info Dasar ─── */
				$tabs->tab( 'Info Dasar', 'dashicons-info', array(
					'<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">'
					. '<div>' . $form->select( 'status_hibah' )->setLabel( 'Status Event' )
						->setOptions( array(
							'aktif'   => 'Aktif (sedang dibuka)',
							'ditutup' => 'Ditutup',
							'arsip'   => 'Arsip',
						) )->setAttribute( 'style', 'width:100%' ) . '</div>'
					. '<div>' . $form->date( 'deadline' )->setLabel( 'Deadline Tanggal' )
						->setFormatYearMonthDay()
						->setHelp( 'Tanggal terakhir pendaftaran.' ) . '</div>'
					. '<div>' . $form->time( 'deadline_time' )->setLabel( 'Deadline Jam' )
						->setHelp( 'Opsional. Kosongkan = 23:59:59.' ) . '</div>'
					. '<div>' . $form->checkbox( 'allow_after_deadline' )
						->setLabel( 'Boleh Daftar Setelah Deadline' )
						->setHelp( 'Centang jika event ini TETAP menerima pendaftaran meski sudah lewat deadline (perpanjangan/darurat). Tidak dicentang = ikuti pengaturan global di LP2M → Settings.' )
						->setAttribute( 'style', 'width:auto' ) . '</div>'
					. '<div>' . $form->text( 'event_eyebrow' )->setLabel( 'Tahun Akademik' )
						->setAttribute( 'placeholder', 'mis. TA 2026/2027' ) . '</div>'
					. '<div>' . $form->text( 'dana_maks' )->setLabel( 'Dana Maksimal' )
						->setAttribute( 'placeholder', 'mis. 35000000' ) . '</div>'
					. '<div>' . $form->text( 'jumlah_tim_maks' )->setLabel( 'Jumlah Tim Maksimal' )
						->setAttribute( 'placeholder', 'mis. 3' ) . '</div>'
					. '<div>' . $form->search( 'program_studi_id' )->setPostTypeOptions( 'program_studi' )
						->setLabel( 'Program Studi Terkait' )
						->setHelp( 'Cari & pilih program studi (CPT). Simpan ID post.' ) . '</div>'
					. '</div>'
					. '<div style="margin-top:1rem">'
					. $form->textarea( 'info_tambahan' )->setLabel( 'Info Tambahan (satu per baris)' )
						->setAttribute( 'rows', 4 )->setAttribute( 'placeholder', "Maks. 3 anggota tim per usulan\nDana s.d. Rp 35 juta / skema penelitian" )
					. '</div>'
				) );

				/* ─── TAB 2: Timeline ─── */
				$timeline_rpt = $form->repeater( 'timeline_items' )->setFields(
					array(
						$form->date( 'Tanggal' )->setLabel( 'Tanggal' )->setFormatYearMonthDay()
							->setAttribute( 'placeholder', 'YYYY-MM-DD' ),
						$form->textarea( 'Deskripsi' )->setAttribute( 'rows', 2 )
							->setAttribute( 'placeholder', 'Sosialisasi & pembukaan pendaftaran usulan' ),
					)
				);
				$tabs->tab( 'Timeline', 'dashicons-backup', array(
					'<h4 style="margin:.4rem 0 .5rem">⏳ Timeline Event</h4>' . $timeline_rpt
				) );

				/* ─── TAB 3: Panduan & Template ─── */
				$tabs->tab( 'Panduan & Template', 'dashicons-media-document', array(
					'<div style="margin-bottom:1rem">'
					. '<h4 style="margin:.4rem 0 .5rem">📘 Panduan Penulisan (DOCX/PDF)</h4>'
					. $form->file( 'file_panduan' )->setLabel( 'Upload File Panduan' )
						->setHelp( 'File panduan penulisan proposal (DOCX/PDF). Field ini single-file. File tambahan dari dashboard LP2M tetap tersimpan & ditampilkan di situs.' )
					. itsi_hibah_metabox_file_note( 'file_panduan' )
					. '</div>'
					. '<div style="margin-bottom:1rem">'
					. '<h4 style="margin:.4rem 0 .5rem">📝 Template Dokumen (DOCX/XLSX)</h4>'
					. $form->file( 'file_template' )->setLabel( 'Upload File Template' )
						->setHelp( 'File template proposal/laporan yang siap diisi. Field ini single-file.' )
					. itsi_hibah_metabox_file_note( 'file_template' )
					. '</div>'
					. '<div style="margin-bottom:1rem">'
					. '<h4 style="margin:.4rem 0 .5rem">👥 Template Kelompok Keahlian (DOCX/PDF)</h4>'
					. $form->file( 'file_kelompok_keahlian' )->setLabel( 'Upload File Template Kelompok Keahlian' )
						->setHelp( 'File template/berkas kelompok keahlian yang siap diisi (DOCX/PDF). Field ini single-file.' )
					. itsi_hibah_metabox_file_note( 'file_kelompok_keahlian' )
					. '</div>'
					. '<div style="margin-bottom:1rem;padding:10px;background:#f0f6fc;border:1px solid #c3d9ef;border-radius:4px">'
					. '<h4 style="margin:0 0 .5rem">✍️ Template Surat Kesanggupan (PDF)</h4>'
					. '<p style="margin:0 0 .5rem;font-size:12px;color:#50575e">Diunggah sekali di sini (level EVENT). Semua peserta tahap revisi cukup mengunduh template ini — tidak perlu diunggah ulang per pendaftaran.</p>'
					. $form->file( 'file_surat_kesanggupan' )->setLabel( 'Upload Template Surat Kesanggupan' )
						->setHelp( 'File template surat kesanggupan (PDF). Field ini single-file; file tambahan dari dashboard LP2M tetap tersimpan & ditampilkan ke peserta.' )
					. itsi_hibah_metabox_file_note( 'file_surat_kesanggupan' )
					. '</div>'
					. '<div style="margin-bottom:1rem;padding:10px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:4px">'
					. '<h4 style="margin:0 0 .5rem">📑 Template Laporan (Lap. Kemajuan & Lap. Akhir)</h4>'
					. '<p style="margin:0 0 .5rem;font-size:12px;color:#50575e">Diunggah <strong>sekali di event ini</strong>. Semua peserta cukup mengunduh lewat halaman Track Status (template tampil sebagai tautan unduh) — peserta <strong>tidak</strong> pernah mengunggah template, sehingga berkas tidak berulang per pendaftaran.</p>'
					. '<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">'
					. '<div>' . $form->file( 'file_template_lapkem' )->setLabel( 'Template Laporan Kemajuan (DOCX)' )
						->setHelp( 'Template laporan kemajuan. Disarankan DOC/DOCX.' ) . '</div>'
					. '<div>' . $form->file( 'file_template_sptb' )->setLabel( 'Template SPTB (DOCX)' )
						->setHelp( 'Template Surat Pernyataan Tanggung Jawab Belanja. Disarankan DOC/DOCX.' ) . '</div>'
					. '<div>' . $form->file( 'file_template_lapakhir' )->setLabel( 'Template Laporan Akhir (DOCX)' )
						->setHelp( 'Template laporan akhir. Disarankan DOC/DOCX.' ) . '</div>'
					. '<div>' . $form->file( 'file_template_berita_acara' )->setLabel( 'Template Berita Acara (DOCX)' )
						->setHelp( 'Template berita acara. Disarankan DOC/DOCX.' ) . '</div>'
					. '<div>' . $form->file( 'file_template_bpp' )->setLabel( 'Template Berita Penyelesaian Pekerjaan (DOCX)' )
						->setHelp( 'Template berita penyelesaian pekerjaan. Disarankan DOC/DOCX.' ) . '</div>'
					. '<div>' . $form->file( 'file_template_anggaran' )->setLabel( 'Template Penggunaan Anggaran (DOCX)' )
						->setHelp( 'Template laporan penggunaan anggaran. Disarankan DOC/DOCX.' ) . '</div>'
					. '</div>'
					. itsi_hibah_metabox_file_note( 'file_template_lapkem' )
					. itsi_hibah_metabox_file_note( 'file_template_sptb' )
					. itsi_hibah_metabox_file_note( 'file_template_lapakhir' )
					. itsi_hibah_metabox_file_note( 'file_template_berita_acara' )
					. itsi_hibah_metabox_file_note( 'file_template_bpp' )
					. itsi_hibah_metabox_file_note( 'file_template_anggaran' )
					. '</div>'
				) );

				$tabs->layoutLeftEnclosed()->render();
			}
		);

	// Detail Pendaftaran — disatukan ke inc/lp2m/class-hibah-receiver.php
	// (register_tr_cpt_and_metabox) agar konsisten TypeRocket form + file
	// proposal sinkron TR ↔ REST. Dihapus dari functions.php untuk hindari
	// double metabox.
} );
