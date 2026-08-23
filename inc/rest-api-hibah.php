<?php
/**
 * REST API untuk CPT Hibah.
 *
 * Register meta fields ke REST dengan sanitized update_callback,
 * sehingga dashboard LP2M bisa POST/PUT event hibah.
 *
 * @package itsi
 */

defined( 'ABSPATH' ) || exit;

function itsi_hibah_register_rest_fields() {
	$meta_fields = array(
		'status_hibah',
		'event_eyebrow',
		'dana_maks',
		'jumlah_tim_maks',
		'info_tambahan',
		'link_panduan',
		'program_studi_id',
		'allow_after_deadline',
	);

	foreach ( $meta_fields as $field ) {
		register_rest_field( 'hibah', $field, array(
			'get_callback'    => function ( $post ) use ( $field ) {
				return get_post_meta( $post['id'], $field, true );
			},
			'update_callback' => function ( $value, $post ) use ( $field ) {
				if ( ! current_user_can( 'edit_post', $post->ID ) ) {
					return false;
				}
				$clean = is_string( $value ) ? sanitize_text_field( $value ) : '';
				if ( 'info_tambahan' === $field ) {
					// Textarea multi-baris: pertahankan newline agar tampil satu per baris.
					$clean = is_string( $value ) ? sanitize_textarea_field( $value ) : '';
				}
				if ( 'link_panduan' === $field ) {
					$clean = is_string( $value ) ? wp_strip_all_tags( $value, true ) : '';
				}
				update_post_meta( $post->ID, $field, $clean );
				return true;
			},
			'schema' => array(
				'type'        => 'string',
				'description' => 'Meta field: ' . $field,
				'context'     => array( 'view', 'edit' ),
			),
		) );
	}

	// ── Deadline (tanggal + jam opsional; auto-normalize + auto-label) ──
	register_rest_field( 'hibah', 'deadline', array(
		'get_callback' => function ( $post ) {
			return get_post_meta( $post['id'], 'deadline', true );
		},
		'update_callback' => function ( $value, $post ) {
			if ( ! current_user_can( 'edit_post', $post->ID ) ) { return false; }
			$raw = is_string( $value ) ? trim( $value ) : '';
			if ( '' === $raw ) { delete_post_meta( $post->ID, 'deadline' ); delete_post_meta( $post->ID, 'deadline_label' ); return true; }
			// Normalisasi tanggal: YYYY-MM-DD → gabung jam dari deadline_time (atau 23:59:59).
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ) {
				$time = sanitize_text_field( get_post_meta( $post->ID, 'deadline_time', true ) );
				$raw .= preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $time ) ? 'T' . $time . ':00' : 'T23:59:59';
			}
			update_post_meta( $post->ID, 'deadline', $raw );
			$ts = strtotime( $raw );
			if ( $ts ) { update_post_meta( $post->ID, 'deadline_label', date_i18n( 'j F Y', $ts ) ); }
			return true;
		},
		'schema' => array( 'type' => 'string', 'description' => 'Deadline (ISO datetime)', 'context' => array( 'view', 'edit' ) ),
	) );

	// ── Deadline time (HH:MM opsional, dibaca metabox) ──
	register_rest_field( 'hibah', 'deadline_time', array(
		'get_callback' => function ( $post ) {
			return get_post_meta( $post['id'], 'deadline_time', true );
		},
		'update_callback' => function ( $value, $post ) {
			if ( ! current_user_can( 'edit_post', $post->ID ) ) { return false; }
			$t = is_string( $value ) ? sanitize_text_field( $value ) : '';
			if ( '' !== $t && ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $t ) ) { return false; }
			update_post_meta( $post->ID, 'deadline_time', $t );
			// Sinkronkan jam ke deadline tersimpan (kalau ada).
			$dl = get_post_meta( $post->ID, 'deadline', true );
			if ( $dl && preg_match( '/^(\d{4}-\d{2}-\d{2})T\d{2}:\d{2}:\d{2}$/', $dl, $m ) ) {
				$new = $t ? $m[1] . 'T' . $t . ':00' : $m[1] . 'T23:59:59';
				update_post_meta( $post->ID, 'deadline', $new );
				$ts = strtotime( $new );
				if ( $ts ) { update_post_meta( $post->ID, 'deadline_label', date_i18n( 'j F Y', $ts ) ); }
			}
			return true;
		},
		'schema' => array( 'type' => 'string', 'description' => 'Deadline time (HH:MM)', 'context' => array( 'view', 'edit' ) ),
	) );

	// ── Deadline label (derived dari deadline kalau kosong) ──
	register_rest_field( 'hibah', 'deadline_label', array(
		'get_callback' => function ( $post ) {
			$label = get_post_meta( $post['id'], 'deadline_label', true );
			if ( $label ) { return $label; }
			$dl = get_post_meta( $post['id'], 'deadline', true );
			return $dl ? date_i18n( 'j F Y', strtotime( $dl ) ) : '';
		},
		'schema' => array( 'type' => 'string', 'description' => 'Deadline label (human, derived)', 'context' => array( 'view' ) ),
	) );

	// ── Timeline (repeater) ──
	register_rest_field( 'hibah', 'timeline_items', array(
		'get_callback' => function ( $post ) {
			$raw = get_post_meta( $post['id'], 'timeline_items', true );
			if ( is_array( $raw ) ) {
				$out = array();
				foreach ( $raw as $item ) {
					if ( ! is_array( $item ) ) { continue; }
					$out[] = array(
						'date'  => itsi_hibah_field( $item, 'Tanggal', 'date' ),
						'label' => itsi_hibah_field( $item, 'Deskripsi', 'label', 'desc' ),
					);
				}
				return $out;
			}
			return is_string( $raw ) && '' !== $raw ? itsi_hibah_json_decode( $raw ) : array();
		},
		'update_callback' => function ( $value, $post ) {
			if ( ! current_user_can( 'edit_post', $post->ID ) ) {
				return false;
			}
			$sanitized = is_array( $value )
				? array_map( fn( $i ) => array(
					'Tanggal'    => sanitize_text_field( $i['date'] ?? $i['Tanggal'] ?? '' ),
					'Deskripsi'  => sanitize_text_field( $i['label'] ?? $i['Deskripsi'] ?? '' ),
				), $value )
				: array();
			update_post_meta( $post->ID, 'timeline_items', $sanitized );
			return true;
		},
		'schema' => array(
			'type'        => 'array',
			'description' => 'Timeline items',
			'context'     => array( 'view', 'edit' ),
		),
	) );

	// ── File panduan ──
	// Meta key utama (metabox TypeRocket) = `file_panduan` (single attachment ID int).
	// Meta key cadangan (dashboard LP2M, array multi-file) = `file_panduan_ids`.
	// Get baca `*_ids` dulu (bisa beberapa file), fallback ke key metabox.
	// Update tulis keduanya agar metabox (itsi.ac.id) dan dashboard sinkron.
	register_rest_field( 'hibah', 'file_panduan', array(
		'get_callback'    => function ( $post ) {
			return itsi_hibah_attachment_urls( itsi_hibah_read_file_meta( $post['id'], 'file_panduan' ) );
		},
		'update_callback' => function ( $value, $post ) {
			if ( ! current_user_can( 'edit_post', $post->ID ) ) { return false; }
			return itsi_hibah_write_file_meta( $post->ID, 'file_panduan', $value );
		},
		'schema' => array( 'type' => 'array', 'description' => 'File panduan (URLs)', 'context' => array( 'view', 'edit' ) ),
	) );

	// ── File template ──
	register_rest_field( 'hibah', 'file_template', array(
		'get_callback'    => function ( $post ) {
			return itsi_hibah_attachment_urls( itsi_hibah_read_file_meta( $post['id'], 'file_template' ) );
		},
		'update_callback' => function ( $value, $post ) {
			if ( ! current_user_can( 'edit_post', $post->ID ) ) { return false; }
			return itsi_hibah_write_file_meta( $post->ID, 'file_template', $value );
		},
		'schema' => array( 'type' => 'array', 'description' => 'File template (URLs)', 'context' => array( 'view', 'edit' ) ),
	) );

	// ── File kelompok keahlian ──
	register_rest_field( 'hibah', 'file_kelompok_keahlian', array(
		'get_callback'    => function ( $post ) {
			return itsi_hibah_attachment_urls( itsi_hibah_read_file_meta( $post['id'], 'file_kelompok_keahlian' ) );
		},
		'update_callback' => function ( $value, $post ) {
			if ( ! current_user_can( 'edit_post', $post->ID ) ) { return false; }
			return itsi_hibah_write_file_meta( $post->ID, 'file_kelompok_keahlian', $value );
		},
		'schema' => array( 'type' => 'array', 'description' => 'File template kelompok keahlian (URLs)', 'context' => array( 'view', 'edit' ) ),
	) );

	// ── ID media panduan (legacy dashboard form) ──
	register_rest_field( 'hibah', 'panduan_penulisan_id', array(
		'get_callback'    => function ( $post ) {
			$v = get_post_meta( $post['id'], 'panduan_penulisan_id', true );
			return $v ? (int) $v : null;
		},
		'update_callback' => function ( $value, $post ) {
			if ( ! current_user_can( 'edit_post', $post->ID ) ) { return false; }
			if ( empty( $value ) ) { delete_post_meta( $post->ID, 'panduan_penulisan_id' ); return true; }
			update_post_meta( $post->ID, 'panduan_penulisan_id', (int) $value );
			return true;
		},
		'schema' => array( 'type' => 'integer', 'description' => 'Attachment ID panduan (legacy)', 'context' => array( 'view', 'edit' ) ),
	) );

	// ── ID media template (legacy dashboard form) ──
	register_rest_field( 'hibah', 'template_dokumen_id', array(
		'get_callback'    => function ( $post ) {
			$v = get_post_meta( $post['id'], 'template_dokumen_id', true );
			return $v ? (int) $v : null;
		},
		'update_callback' => function ( $value, $post ) {
			if ( ! current_user_can( 'edit_post', $post->ID ) ) { return false; }
			if ( empty( $value ) ) { delete_post_meta( $post->ID, 'template_dokumen_id', '' ); return true; }
			update_post_meta( $post->ID, 'template_dokumen_id', (int) $value );
			return true;
		},
		'schema' => array( 'type' => 'integer', 'description' => 'Attachment ID template (legacy)', 'context' => array( 'view', 'edit' ) ),
	) );

	// ── Thumbnail URL ──
	register_rest_field( 'hibah', 'thumbnail_url', array(
		'get_callback' => function ( $post ) {
			$thumb_id = get_post_thumbnail_id( $post['id'] );
			if ( ! $thumb_id ) { return ''; }
			$url = wp_get_attachment_image_url( $thumb_id, 'large' );
			return $url ? $url : '';
		},
		'schema' => array( 'type' => 'string', 'description' => 'Featured image URL', 'context' => array( 'view' ) ),
	) );

	// ── Category names ──
	register_rest_field( 'hibah', 'category_names', array(
		'get_callback' => function ( $post ) {
			$cats = wp_get_post_categories( $post['id'], array( 'fields' => 'names' ) );
			return is_array( $cats ) ? $cats : array();
		},
		'schema' => array( 'type' => 'array', 'description' => 'Category names', 'context' => array( 'view' ) ),
	) );

	// ── Taxonomy names (SDGs, Jenis Hibah, Kelompok Keahlian, Model Hibah) ──
	$tax_name_fields = array(
		'sdgs'               => 'SDGs terms',
		'jenis_hibah'        => 'Jenis Hibah terms',
		'kelompok_keahlian'  => 'Kelompok Keahlian terms',
		'model_hibah'        => 'Model Hibah terms',
	);
	foreach ( $tax_name_fields as $tax => $desc ) {
		register_rest_field( 'hibah', $tax . '_names', array(
			'get_callback' => function ( $post ) use ( $tax ) {
				$terms = wp_get_post_terms( $post['id'], $tax, array( 'fields' => 'names' ) );
				return is_wp_error( $terms ) ? array() : $terms;
			},
			'schema' => array( 'type' => 'array', 'description' => $desc . ' (names)', 'context' => array( 'view' ) ),
		) );
	}
}
add_action( 'rest_api_init', 'itsi_hibah_register_rest_fields' );

/* ────────────────────────────────────────────────────────────
 *  HELPERS
 * ──────────────────────────────────────────────────────────── */

function itsi_hibah_json_decode( $value ): array {
	$decoded = json_decode( $value, true );
	return is_array( $decoded ) ? $decoded : array();
}

function itsi_hibah_attachment_urls( $value ) {
	if ( empty( $value ) ) { return array(); }
	if ( is_numeric( $value ) ) { $ids = array( (int) $value ); }
	elseif ( is_string( $value ) && '' !== $value ) {
		$ids = array_filter( array_map( 'intval', explode( ',', $value ) ) );
	}
	elseif ( is_array( $value ) ) {
		// Bisa berisi ID int, string ID, atau URL.
		$ids = array();
		foreach ( $value as $item ) {
			if ( is_numeric( $item ) ) {
				$ids[] = (int) $item;
			} elseif ( is_string( $item ) && '' !== trim( $item ) ) {
				if ( is_numeric( trim( $item ) ) ) {
					$ids[] = (int) trim( $item );
				} else {
					$att_id = attachment_url_to_postid( trim( $item ) );
					if ( $att_id ) { $ids[] = $att_id; }
				}
			}
		}
	}
	else { return array(); }

	$urls = array();
	foreach ( array_unique( array_filter( $ids ) ) as $id ) {
		if ( $id <= 0 ) { continue; }
		$src = wp_get_attachment_url( (int) $id );
		if ( $src ) { $urls[] = $src; }
	}
	return array_values( $urls );
}

/**
 * Baca meta file gabungan (metabox + dashboard).
 *
 * Prioritas:
 *  1. `{key}_ids` — array multi-file dari dashboard LP2M (dan sync dari metabox).
 *  2. `{key}`    — single int / CSV dari metabox TypeRocket (itsi.ac.id).
 *  3. `_tr_{key}` / `tr_{key}` — legacy prefix TypeRocket (jika ada).
 *
 * Data lama (sebelum fitur sync) yang menyimpan array/CSV/URL langsung di
 * `{key}` otomatis dimigrasi ke format baru agar metabox TypeRocket tidak
 * salah menampilkan (cast array → int 1).
 *
 * @param int    $post_id
 * @param string $key
 * @return array Normalized array of attachment IDs.
 */
function itsi_hibah_read_file_meta( $post_id, $key ) {
	// 1. Multi-file dari dashboard (array ID).
	$ids_meta = get_post_meta( $post_id, $key . '_ids', true );
	if ( ! empty( $ids_meta ) ) {
		if ( is_array( $ids_meta ) ) {
			return array_values( array_filter( array_map( 'intval', $ids_meta ) ) );
		}
		if ( is_numeric( $ids_meta ) ) {
			return array( (int) $ids_meta );
		}
		if ( is_string( $ids_meta ) && '' !== trim( $ids_meta ) ) {
			return array_values( array_filter( array_map( 'intval', explode( ',', $ids_meta ) ) ) );
		}
	}

	// 2. Meta utama (metabox TypeRocket): single int / CSV / array / URL.
	$main = get_post_meta( $post_id, $key, true );
	if ( ! empty( $main ) ) {
		$ids = itsi_hibah_attachment_ids( $main );

		// Migrasi data lama: `{key}` bukan int tunggal (array/CSV/URL lama)
		// → pindahkan ke `{key}_ids` & simpan int pertama di `{key}`.
		$is_clean_single = is_int( $main ) || ( is_string( $main ) && is_numeric( $main ) );
		if ( ! $is_clean_single && ! empty( $ids ) ) {
			update_post_meta( $post_id, $key, (int) $ids[0] );
			update_post_meta( $post_id, $key . '_ids', array_map( 'intval', $ids ) );
		}
		return $ids;
	}

	// 3. Legacy prefix TypeRocket.
	foreach ( array( '_tr_' . $key, 'tr_' . $key ) as $legacy ) {
		$v = get_post_meta( $post_id, $legacy, true );
		if ( ! empty( $v ) ) {
			$ids = itsi_hibah_attachment_ids( $v );
			// Migrasi ke format baru.
			update_post_meta( $post_id, $key, ! empty( $ids ) ? (int) $ids[0] : '' );
			update_post_meta( $post_id, $key . '_ids', array_map( 'intval', $ids ) );
			delete_post_meta( $post_id, $legacy );
			return $ids;
		}
	}

	return array();
}

/**
 * Tulis meta file agar sinkron metabox (itsi.ac.id) + dashboard LP2M.
 *
 * - `{key}`     : single int attachment ID pertama (format metabox TypeRocket).
 * - `{key}_ids` : array semua attachment ID (format dashboard, multi-file).
 *
 * @param int         $post_id
 * @param string      $key
 * @param mixed       $value Array URL/ID, int, atau string CSV.
 * @return bool
 */
function itsi_hibah_write_file_meta( $post_id, $key, $value ) {
	$ids = itsi_hibah_attachment_ids( $value );
	if ( empty( $ids ) ) {
		delete_post_meta( $post_id, $key );
		delete_post_meta( $post_id, $key . '_ids' );
		// Bersihkan juga legacy prefix.
		delete_post_meta( $post_id, '_tr_' . $key );
		delete_post_meta( $post_id, 'tr_' . $key );
		return true;
	}

	// Metabox TypeRocket (File field) = single int (attachment ID pertama).
	update_post_meta( $post_id, $key, (int) $ids[0] );

	// Dashboard multi-file = array ID lengkap.
	update_post_meta( $post_id, $key . '_ids', array_map( 'intval', $ids ) );

	// Bersihkan legacy prefix agar tidak bentrok.
	delete_post_meta( $post_id, '_tr_' . $key );
	delete_post_meta( $post_id, 'tr_' . $key );

	return true;
}

/**
 * Sinkronkan meta file saat metabox TypeRocket menyimpan (hook save_post).
 *
 * Metabox TypeRocket menulis `{key}` = single attachment ID (int) ke postmeta.
 * Dashboard LP2M memakai `{key}_ids` = array multi-file.
 *
 * Strategi sinkron (ikuti itsi.ac.id = metabox single-file):
 *  - Metabox mengirim `tr[file_panduan]` → set `{key}_ids` sesuai isi metabox
 *    (file utama = satu-satunya sumber dari sisi metabox).
 *  - REST/dashboard menulis `{key}_ids` + `{key}` (int pertama) secara langsung
 *    di update_callback (tidak lewat save_post).
 *
 * @param int    $post_id
 * @param WP_Post $post
 * @param bool   $update
 */
function itsi_hibah_sync_metabox_files_on_save( $post_id, $post, $update ) {
	// Hanya hibah & hindari autosave/revision.
	if ( get_post_type( $post_id ) !== 'hibah' ) { return; }
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
	if ( wp_is_post_revision( $post_id ) ) { return; }
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) { return; }

	// Field metabox dikirim dalam $_POST['tr'] (prefix TypeRocket).
	if ( empty( $_POST['tr'] ) || ! is_array( $_POST['tr'] ) ) { return; }

	// Checkbox boolean metabox: selalu simpan 1/0 (unchecked → 0, bukan stale).
	// Nilai 0/1 per-event; kalau kosong (= belum pernah diatur) dibiarkan kosong
	// agar frontend fallback ke pengaturan global LP2M.
	if ( array_key_exists( 'allow_after_deadline', $_POST['tr'] ) ) {
		update_post_meta( $post_id, 'allow_after_deadline', ! empty( $_POST['tr']['allow_after_deadline'] ) ? '1' : '0' );
	}

	$file_keys = array( 'file_panduan', 'file_template', 'file_kelompok_keahlian' );
	foreach ( $file_keys as $key ) {
		if ( ! array_key_exists( $key, $_POST['tr'] ) ) {
			continue; // field ini tidak diedit di metabox → jangan sentuh.
		}
		$value = $_POST['tr'][ $key ];
		$ids   = itsi_hibah_attachment_ids( $value );

		// Bandingkan dengan meta `{key}` tersimpan (sumber metabox saat ini).
		$current_main = get_post_meta( $post_id, $key, true );
		$current_ids  = itsi_hibah_read_file_meta( $post_id, $key );

		if ( empty( $ids ) ) {
			// Metabox dikosongkan (tombol Clear) → hapus dari dashboard juga.
			if ( ! empty( $current_main ) || ! empty( $current_ids ) ) {
				delete_post_meta( $post_id, $key );
				delete_post_meta( $post_id, $key . '_ids' );
				delete_post_meta( $post_id, '_tr_' . $key );
				delete_post_meta( $post_id, 'tr_' . $key );
			}
			continue;
		}

		// Jika metabox tidak berubah DAN `{key}_ids` sudah ada (file dari
		// dashboard sudah tersimpan), biarkan — file tambahan dashboard tidak
		// boleh hilang. Jika `{key}_ids` belum ada (upload pertama dari
		// metabox), tulis agar format konsisten.
		$ids_meta       = get_post_meta( $post_id, $key . '_ids', true );
		$current_first  = ! empty( $current_ids ) ? (int) $current_ids[0] : 0;
		$new_first      = (int) $ids[0];
		if ( ! empty( $ids_meta ) && $current_first === $new_first ) {
			continue;
		}

		// Metabox berubah → file utama lama digantikan file baru, file lain
		// dari dashboard dipertahankan (tidak ada data yang hilang).
		$existing = array_values( array_filter( $current_ids, function ( $id ) use ( $current_first ) {
			return (int) $id !== $current_first;
		} ) );
		$merged   = array_values( array_unique( array_merge( array( $new_first ), $existing ) ) );
		update_post_meta( $post_id, $key, $new_first );
		update_post_meta( $post_id, $key . '_ids', $merged );
	}
}
add_action( 'save_post', 'itsi_hibah_sync_metabox_files_on_save', 999, 3 );

/**
 * Normalisasi input file field (dari REST/metabox) menjadi daftar attachment ID.
 * Menerima: array ID, array URL, string CSV ID/URL, atau int tunggal.
 */
function itsi_hibah_attachment_ids( $value ): array {
	if ( empty( $value ) ) { return array(); }
	if ( is_int( $value ) ) {
		return array( (int) $value );
	}
	$items = is_array( $value ) ? $value : explode( ',', (string) $value );
	$ids   = array();
	foreach ( $items as $item ) {
		$item = trim( (string) $item );
		if ( '' === $item ) { continue; }
		if ( is_numeric( $item ) ) {
			$ids[] = (int) $item;
			continue;
		}
		// URL → cari attachment by URL.
		$att_id = attachment_url_to_postid( $item );
		if ( $att_id ) { $ids[] = $att_id; }
	}
	return array_values( array_unique( array_filter( $ids ) ) );
}

function itsi_hibah_field( $row, ...$keys ) {
	if ( ! is_array( $row ) ) { return ''; }
	foreach ( $keys as $k ) {
		if ( isset( $row[ $k ] ) && '' !== $row[ $k ] ) { return $row[ $k ]; }
		foreach ( $row as $rk => $rv ) {
			if ( strcasecmp( (string) $rk, (string) $k ) === 0 && '' !== $rv ) { return $rv; }
		}
	}
	return '';
}

/**
 * Render daftar read-only semua file aktif untuk metabox.
 *
 * Metabox TypeRocket File field hanya menampilkan 1 file (single int).
 * Dashboard LP2M bisa menyimpan beberapa file di `{key}_ids` — tampilkan
 * semuanya di sini agar admin itsi tahu file yang terpasang.
 *
 * @param string $key Meta key (file_panduan / file_template / file_kelompok_keahlian)
 * @return string
 */
function itsi_hibah_metabox_file_note( $key ) {
	$post_id = get_the_ID();
	if ( ! $post_id ) {
		global $post;
		$post_id = isset( $post->ID ) ? (int) $post->ID : 0;
	}
	if ( ! $post_id ) { return ''; }

	$ids = itsi_hibah_read_file_meta( $post_id, $key );
	if ( empty( $ids ) ) { return ''; }

	$rows = array();
	foreach ( $ids as $i => $id ) {
		$url = wp_get_attachment_url( (int) $id );
		if ( ! $url ) { continue; }
		$rows[] = '<li style="margin:2px 0"><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener" style="text-decoration:none">'
			. esc_html( basename( $url ) ) . '</a>'
			. ( 0 === $i ? ' <em style="color:#888">(file utama)</em>' : '' )
			. '</li>';
	}
	if ( empty( $rows ) ) { return ''; }

	return '<div style="margin-top:6px;padding:8px 10px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px">'
		. '<strong style="font-size:12px;display:block;margin-bottom:2px">File terpasang saat ini:</strong>'
		. '<ul style="margin:0;padding-left:16px;font-size:12px">' . implode( '', $rows ) . '</ul>'
		. '</div>';
}

/* ────────────────────────────────────────────────────────────
 *  CUSTOM ENDPOINT: hibah dengan deadline terdekat
 *  GET /wp-json/itsi/v1/hibah/nearest-deadline
 * ──────────────────────────────────────────────────────────── */

/**
 * Property per-event "boleh daftar setelah deadline" (checkbox di metabox
 * Detail Hibah). Tanpa fallback ke pengaturan global — murni dari data hibah.
 *
 * @param int $post_id ID post hibah.
 * @return bool
 */
function itsi_hibah_allow_after_deadline( $post_id ) {
	return '1' === (string) get_post_meta( (int) $post_id, 'allow_after_deadline', true );
}

function itsi_hibah_register_routes() {
	register_rest_route( 'itsi/v1', '/hibah/nearest-deadline', array(
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'itsi_hibah_get_nearest_deadline',
		'permission_callback' => '__return_true',
	) );
}
add_action( 'rest_api_init', 'itsi_hibah_register_routes' );

/**
 * Konversi info_tambahan (textarea "satu per baris") menjadi array baris.
 * Menerima string multi-baris, atau array (bila sudah terlanjur array).
 */
function itsi_hibah_lines( $value ) {
	if ( is_array( $value ) ) {
		return array_values( array_filter( array_map( 'trim', $value ), 'strlen' ) );
	}
	if ( is_string( $value ) && '' !== trim( $value ) ) {
		return array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $value ) ), 'strlen' ) );
	}
	return array();
}

function itsi_hibah_get_nearest_deadline( WP_REST_Request $request ) {
	$today = current_time( 'Y-m-d' );

	$query = new WP_Query( array(
		'post_type'      => 'hibah',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'meta_key'       => 'deadline',
		'orderby'        => 'meta_value',
		'order'          => 'ASC',
		'meta_query'     => array(
			array(
				'key'     => 'deadline',
				'value'   => $today,
				'compare' => '>=',
				'type'    => 'DATE',
			),
		),
	) );

	if ( ! $query->have_posts() ) {
		return new WP_REST_Response( array(
			'found' => false,
			'data'  => null,
		), 200 );
	}

	$post = $query->posts[0];
	$id   = $post->ID;

	// Timeline items
	$raw_timeline = get_post_meta( $id, 'timeline_items', true );
	$timeline     = array();
	if ( is_array( $raw_timeline ) ) {
		foreach ( $raw_timeline as $item ) {
			if ( ! is_array( $item ) ) { continue; }
			$timeline[] = array(
				'date'  => itsi_hibah_field( $item, 'Tanggal', 'date' ),
				'label' => itsi_hibah_field( $item, 'Deskripsi', 'label', 'desc' ),
			);
		}
	} elseif ( is_string( $raw_timeline ) && '' !== $raw_timeline ) {
		$timeline = itsi_hibah_json_decode( $raw_timeline );
	}

	$cats = wp_get_post_categories( $id, array( 'fields' => 'names' ) );

	$thumb_id  = get_post_thumbnail_id( $id );
	$thumb_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'large' ) : '';

	$data = array(
		'id'             => $id,
		'slug'           => $post->post_name,
		'title'          => get_the_title( $post ),
		'excerpt'        => get_the_excerpt( $post ),
		'permalink'      => get_permalink( $post ),
		'thumbnail_url'  => $thumb_url ? $thumb_url : '',
		'deadline'       => get_post_meta( $id, 'deadline', true ),
		'deadline_label' => get_post_meta( $id, 'deadline_label', true ),
		'event_eyebrow'  => get_post_meta( $id, 'event_eyebrow', true ),
		'dana_maks'      => get_post_meta( $id, 'dana_maks', true ),
		'jumlah_tim_maks'=> get_post_meta( $id, 'jumlah_tim_maks', true ),
		'info_tambahan'  => itsi_hibah_lines( get_post_meta( $id, 'info_tambahan', true ) ),
		'link_panduan'   => get_post_meta( $id, 'link_panduan', true ),
		'file_panduan'   => itsi_hibah_attachment_urls( itsi_hibah_read_file_meta( $id, 'file_panduan' ) ),
		'file_template'  => itsi_hibah_attachment_urls( itsi_hibah_read_file_meta( $id, 'file_template' ) ),
		'file_kelompok_keahlian' => itsi_hibah_attachment_urls( itsi_hibah_read_file_meta( $id, 'file_kelompok_keahlian' ) ),
		'timeline_items' => $timeline,
		'category_names' => is_array( $cats ) ? $cats : array(),
		// Pengaturan LP2M: boleh daftar setelah deadline (per-event → fallback global).
		'allow_after_deadline' => itsi_hibah_allow_after_deadline( $id ),
	);

	return new WP_REST_Response( array(
		'found' => true,
		'data'  => $data,
	), 200 );
}

/* ────────────────────────────────────────────────────────────
 *  QUERY FILTER — dukung ?status_hibah=aktif & ?program_studi_id=
 * ──────────────────────────────────────────────────────────── */
add_filter( 'rest_hibah_query', function ( $args, $request ) {
	$status = $request->get_param( 'status_hibah' );
	if ( ! empty( $status ) ) {
		$args['meta_query'][] = array(
			'key'   => 'status_hibah',
			'value' => sanitize_text_field( $status ),
		);
	}
	$prodi = $request->get_param( 'program_studi_id' );
	if ( ! empty( $prodi ) && is_numeric( $prodi ) ) {
		$args['meta_query'][] = array(
			'key'   => 'program_studi_id',
			'value' => (int) $prodi,
		);
	}
	return $args;
}, 10, 2 );
