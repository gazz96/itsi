<?php
/**
 * Reward Jurnal / Publikasi Dosen — LP2M CPT support.
 *
 * Mendaftarkan metabox TypeRocket untuk CPT `reward_jurnal` dan mengekspos
 * meta fields ke REST API sehingga SPA LP2M (formulir reward publikasi dosen)
 * bisa menulis/membaca pengajuan secara langsung.
 *
 * Field yang didukung (terbagi 5 tab di metabox):
 *   Data Diri       : jenis_reward, nama_dosen, no_whatsapp
 *   Artikel/Jurnal  : judul_artikel, url_artikel, kategori_jurnal,
 *                     file_artikel (≤5 PDF), file_proposal_artikel (≤5 PDF),
 *                     bukti_bayar (opsional)
 *   Buku            : judul_buku, url_buku, kategori_buku, cover_depan_buku,
 *                     halaman_isbn, bukti_bayar_buku
 *   HAKI            : judul_haki, nomor_dokumen_haki, kategori_haki, sertifikat_haki
 *   Seminar         : penyelenggara_seminar, judul_paper_seminar, kategori_seminar,
 *                     sertifikat_pemateri, dokumentasi_seminar
 *
 * File disimpan sebagai attachment ID (metabox = single int di `{key}`) dengan
 * cadangan multi-file `{key}_ids` (array int) untuk formulir publik — pola yang
 * sama dengan hibah di rest-api-hibah.php.
 *
 * @package itsi
 */

defined( 'ABSPATH' ) || exit;

/* ════════════════════════════════════════════════════════════
 *  METABOX — Detail Reward Jurnal
 * ════════════════════════════════════════════════════════════ */

add_action( 'typerocket_loaded', function () {

	tr_meta_box( 'Detail Reward Jurnal' )
		->addPostType( 'reward_jurnal' )
		->setCallback(
			function () {
				$form = \TypeRocket\Utility\Helper::form();
				$tabs = \TypeRocket\Elements\Tabs::new();

				/* ─── TAB 1: Data Diri ─── */
				$tabs->tab( 'Data Diri', 'dashicons-id', array(
					'<div style="display:grid;grid-template-columns:1fr;gap:1rem">'
					. '<div>' . $form->select( 'jenis_reward' )->setLabel( 'Jenis Karya / Publikasi' )->setOptions( array(
						'artikel' => 'Artikel / Jurnal',
						'buku'    => 'Buku',
						'haki'    => 'HAKI (Hak Kekayaan Intelektual)',
						'seminar' => 'Seminar',
					) )->setAttribute( 'style', 'width:100%' ) . '</div>'
					. '</div>'
					. '<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-top:1rem">'
					. '<div>' . $form->text( 'nama_dosen' )->setLabel( 'Nama Dosen' )->setAttribute( 'placeholder', 'Dr. Nama Lengkap, M.Pd.' ) . '</div>'
					. '<div>' . $form->text( 'no_whatsapp' )->setLabel( 'Nomor HP / WhatsApp' )->setAttribute( 'placeholder', '08xxxxxxxxxx' ) . '</div>'
					. '</div>'
				) );

				/* ─── TAB 2: Artikel / Jurnal ─── */
				$tabs->tab( 'Artikel / Jurnal', 'dashicons-media-document', array(
					'<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">'
					. '<div>' . $form->text( 'judul_artikel' )->setLabel( 'Judul Artikel' )->setAttribute( 'placeholder', 'Judul lengkap artikel' ) . '</div>'
					. '<div>' . $form->text( 'url_artikel' )->setLabel( 'URL Artikel' )->setAttribute( 'placeholder', 'https://doi.org/...' ) . '</div>'
					. '</div>'
					. '<div style="margin-top:1rem">' . $form->file( 'file_artikel' )->setLabel( 'File Artikel' )
						->setHelp( 'PDF, maks 5 file (masing-masing maks 10 MB). Metabox menyimpan 1 file utama; unggah multi-file lewat formulir publik.' ) . '</div>'
					. '<div style="margin-top:1rem">' . $form->file( 'file_proposal_artikel' )->setLabel( 'File Proposal Penelitian dari Artikel' )
						->setHelp( 'PDF, sampai 5 file (masing-masing maks 10 MB).' ) . '</div>'
					. '<div style="margin-top:1rem">' . $form->file( 'bukti_bayar' )->setLabel( 'Bukti Bayar (jika bayar)' )
						->setHelp( 'Opsional. PDF/surel pembayaran, maks 10 MB.' ) . '</div>'
				) );

				/* ─── TAB 3: Buku ─── */
				$tabs->tab( 'Buku', 'dashicons-book', array(
					'<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">'
					. '<div>' . $form->text( 'judul_buku' )->setLabel( 'Judul Buku' )->setAttribute( 'placeholder', 'Judul lengkap buku' ) . '</div>'
					. '<div>' . $form->text( 'url_buku' )->setLabel( 'URL Buku' )->setAttribute( 'placeholder', 'https://...' ) . '</div>'
					. '</div>'
					. '<div style="margin-top:1rem">' . $form->file( 'cover_depan_buku' )->setLabel( 'Cover Depan Buku' )
						->setHelp( '1 file PDF, maks 10 MB.' ) . '</div>'
					. '<div style="margin-top:1rem">' . $form->file( 'halaman_isbn' )->setLabel( 'Halaman ISBN' )
						->setHelp( '1 file PDF atau gambar, maks 10 MB.' ) . '</div>'
					. '<div style="margin-top:1rem">' . $form->file( 'bukti_bayar_buku' )->setLabel( 'Bukti Bayar Biaya Penerbitan Buku' )
						->setHelp( 'PDF, maks 10 MB.' ) . '</div>'
				) );

				/* ─── TAB 4: HAKI ─── */
				$tabs->tab( 'HAKI', 'dashicons-shield', array(
					'<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">'
					. '<div>' . $form->text( 'judul_haki' )->setLabel( 'Judul HAKI (lengkap)' )->setAttribute( 'placeholder', 'Tulis judul pada HAKI secara lengkap' ) . '</div>'
					. '<div>' . $form->text( 'nomor_dokumen_haki' )->setLabel( 'Nomor Dokumen / Sertifikat HAKI' )->setAttribute( 'placeholder', 'EC00xxxxxxxx' ) . '</div>'
					. '</div>'
					. '<div style="margin-top:1rem">' . $form->file( 'sertifikat_haki' )->setLabel( 'Sertifikat HAKI' )
						->setHelp( 'PDF, maks 10 MB.' ) . '</div>'
						. '<div style="margin-top:1rem">' . $form->file( 'bukti_bayar_haki' )->setLabel( 'Bukti Pembayaran Biaya Penerbitan HAKI' )
						->setHelp( 'Opsional. PDF/gambar, maks 10 MB.' ) . '</div>'
					) );

				/* ─── TAB 5: Seminar ─── */
				$tabs->tab( 'Seminar', 'dashicons-megaphone', array(
					'<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">'
					. '<div>' . $form->text( 'penyelenggara_seminar' )->setLabel( 'Penyelenggara Seminar' )->setAttribute( 'placeholder', 'Nama penyelenggara' ) . '</div>'
					. '<div>' . $form->text( 'judul_paper_seminar' )->setLabel( 'Judul Paper Seminar' )->setAttribute( 'placeholder', 'Judul paper/makalah' ) . '</div>'
					. '</div>'
					. '<div style="margin-top:1rem">' . $form->file( 'sertifikat_pemateri' )->setLabel( 'Sertifikat sebagai Pemateri Seminar' )
						->setHelp( 'PDF, maks 10 MB.' ) . '</div>'
					. '<div style="margin-top:1rem">' . $form->file( 'dokumentasi_seminar' )->setLabel( 'Dokumentasi Seminar' )
						->setHelp( 'PDF/gambar, maks 10 MB.' ) . '</div>'
				) );

				$tabs->layoutLeftEnclosed()->render();
			}
		);
} );

/* ════════════════════════════════════════════════════════════
 *  REST — expose meta fields
 * ════════════════════════════════════════════════════════════ */

add_action( 'rest_api_init', 'itsi_reward_register_rest_fields' );

function itsi_reward_register_rest_fields() {

	$text_fields = array(
		'nama_dosen', 'no_whatsapp', 'judul_artikel', 'judul_buku',
		'judul_haki', 'nomor_dokumen_haki', 'penyelenggara_seminar', 'judul_paper_seminar',
	);
	$url_fields = array( 'url_artikel', 'url_buku' );
	$select_fields = array( 'jenis_reward' );
	$file_fields = array(
		'file_artikel', 'file_proposal_artikel', 'bukti_bayar', 'cover_depan_buku',
		'halaman_isbn', 'bukti_bayar_buku', 'sertifikat_haki', 'bukti_bayar_haki', 'sertifikat_pemateri', 'dokumentasi_seminar',
	);

	foreach ( $text_fields as $field ) {
		register_rest_field( 'reward_jurnal', $field, array(
			'get_callback'    => fn( $post ) => get_post_meta( $post['id'], $field, true ),
			'update_callback' => function ( $value, $post ) use ( $field ) {
				if ( ! current_user_can( 'edit_post', $post->ID ) ) { return false; }
				update_post_meta( $post->ID, $field, is_string( $value ) ? sanitize_text_field( $value ) : '' );
				return true;
			},
			'schema' => array( 'type' => 'string', 'description' => 'Meta: ' . $field, 'context' => array( 'view', 'edit' ) ),
		) );
	}

	foreach ( $url_fields as $field ) {
		register_rest_field( 'reward_jurnal', $field, array(
			'get_callback'    => fn( $post ) => get_post_meta( $post['id'], $field, true ),
			'update_callback' => function ( $value, $post ) use ( $field ) {
				if ( ! current_user_can( 'edit_post', $post->ID ) ) { return false; }
				update_post_meta( $post->ID, $field, is_string( $value ) ? esc_url_raw( $value ) : '' );
				return true;
			},
			'schema' => array( 'type' => 'string', 'description' => 'Meta (URL): ' . $field, 'context' => array( 'view', 'edit' ) ),
		) );
	}

	foreach ( $select_fields as $field ) {
		register_rest_field( 'reward_jurnal', $field, array(
			'get_callback'    => fn( $post ) => get_post_meta( $post['id'], $field, true ),
			'update_callback' => function ( $value, $post ) use ( $field ) {
				if ( ! current_user_can( 'edit_post', $post->ID ) ) { return false; }
				update_post_meta( $post->ID, $field, is_string( $value ) ? sanitize_text_field( $value ) : '' );
				return true;
			},
			'schema' => array( 'type' => 'string', 'description' => 'Meta (select): ' . $field, 'context' => array( 'view', 'edit' ) ),
		) );
	}

	foreach ( $file_fields as $field ) {
		register_rest_field( 'reward_jurnal', $field, array(
			'get_callback'    => fn( $post ) => itsi_reward_attachment_urls( itsi_reward_read_file_meta( $post['id'], $field ) ),
			'update_callback' => function ( $value, $post ) use ( $field ) {
				if ( ! current_user_can( 'edit_post', $post->ID ) ) { return false; }
				return itsi_reward_write_file_meta( $post->ID, $field, $value );
			},
			'schema' => array( 'type' => 'array', 'description' => 'Meta (file URLs): ' . $field, 'context' => array( 'view', 'edit' ) ),
		) );
	}

	// Kategori reward = taxonomy (dinamis). Expose nama term untuk tampilan SPA.
	$reward_taxonomies = array(
		'kategori_jurnal'  => 'Kategori Jurnal',
		'kategori_buku'    => 'Kategori Buku',
		'kategori_haki'    => 'Kategori HAKI',
		'kategori_seminar' => 'Kategori Seminar',
	);
	foreach ( $reward_taxonomies as $tax => $desc ) {
		register_rest_field( 'reward_jurnal', $tax . '_names', array(
			'get_callback' => function ( $post ) use ( $tax ) {
				$terms = wp_get_post_terms( $post['id'], $tax, array( 'fields' => 'names' ) );
				return is_wp_error( $terms ) ? array() : $terms;
			},
			'schema' => array( 'type' => 'array', 'description' => $desc . ' (names)', 'context' => array( 'view' ) ),
		) );
	}
}

/* ════════════════════════════════════════════════════════════
 *  HELPERS — file meta (mirror pola hibah, prefix itsi_reward_)
 * ════════════════════════════════════════════════════════════ */

function itsi_reward_attachment_ids( $value ) {
	if ( empty( $value ) ) { return array(); }

	if ( is_numeric( $value ) ) {
		$ids = array( (int) $value );
	} elseif ( is_string( $value ) && '' !== trim( $value ) ) {
		if ( is_numeric( trim( $value ) ) ) {
			$ids = array( (int) trim( $value ) );
		} else {
			$ids = array();
			foreach ( array_filter( array_map( 'trim', explode( ',', $value ) ) ) as $piece ) {
				if ( is_numeric( $piece ) ) {
					$ids[] = (int) $piece;
				} else {
					$att = attachment_url_to_postid( $piece );
					if ( $att ) { $ids[] = $att; }
				}
			}
		}
	} elseif ( is_array( $value ) ) {
		$ids = array();
		foreach ( $value as $item ) {
			if ( is_numeric( $item ) ) {
				$ids[] = (int) $item;
			} elseif ( is_string( $item ) && '' !== trim( $item ) ) {
				if ( is_numeric( trim( $item ) ) ) {
					$ids[] = (int) trim( $item );
				} else {
					$att = attachment_url_to_postid( trim( $item ) );
					if ( $att ) { $ids[] = $att; }
				}
			}
		}
	} else {
		return array();
	}

	return array_values( array_unique( array_filter( $ids, fn( $id ) => (int) $id > 0 ) ) );
}

function itsi_reward_attachment_urls( $value ) {
	$ids = itsi_reward_attachment_ids( $value );
	$urls = array();
	foreach ( $ids as $id ) {
		$src = wp_get_attachment_url( (int) $id );
		if ( $src ) { $urls[] = $src; }
	}
	return array_values( $urls );
}

/**
 * Baca meta file: {key}_ids (multi) dulu, fallback {key} (single int).
 *
 * @return array Normalized attachment IDs.
 */
function itsi_reward_read_file_meta( $post_id, $key ) {
	$ids_meta = get_post_meta( $post_id, $key . '_ids', true );
	if ( ! empty( $ids_meta ) ) {
		return itsi_reward_attachment_ids( $ids_meta );
	}

	$main = get_post_meta( $post_id, $key, true );
	if ( ! empty( $main ) ) {
		return itsi_reward_attachment_ids( $main );
	}

	return array();
}

/**
 * Tulis meta file: {key} = int pertama (metabox), {key}_ids = array lengkap.
 *
 * @return bool
 */
function itsi_reward_write_file_meta( $post_id, $key, $value ) {
	$ids = itsi_reward_attachment_ids( $value );

	if ( empty( $ids ) ) {
		delete_post_meta( $post_id, $key );
		delete_post_meta( $post_id, $key . '_ids' );
		return true;
	}

	update_post_meta( $post_id, $key, (int) $ids[0] );
	update_post_meta( $post_id, $key . '_ids', array_map( 'intval', $ids ) );

	return true;
}