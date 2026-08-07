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
	register_rest_field( 'hibah', 'file_panduan', array(
		'get_callback'    => function ( $post ) {
			return itsi_hibah_attachment_urls( get_post_meta( $post['id'], 'file_panduan', true ) );
		},
		'update_callback' => function ( $value, $post ) {
			if ( ! current_user_can( 'edit_post', $post->ID ) ) { return false; }
			update_post_meta( $post->ID, 'file_panduan', itsi_hibah_attachment_ids( $value ) );
			return true;
		},
		'schema' => array( 'type' => 'array', 'description' => 'File panduan (URLs)', 'context' => array( 'view', 'edit' ) ),
	) );

	// ── File template ──
	register_rest_field( 'hibah', 'file_template', array(
		'get_callback'    => function ( $post ) {
			return itsi_hibah_attachment_urls( get_post_meta( $post['id'], 'file_template', true ) );
		},
		'update_callback' => function ( $value, $post ) {
			if ( ! current_user_can( 'edit_post', $post->ID ) ) { return false; }
			update_post_meta( $post->ID, 'file_template', itsi_hibah_attachment_ids( $value ) );
			return true;
		},
		'schema' => array( 'type' => 'array', 'description' => 'File template (URLs)', 'context' => array( 'view', 'edit' ) ),
	) );

	// ── File kelompok keahlian ──
	register_rest_field( 'hibah', 'file_kelompok_keahlian', array(
		'get_callback'    => function ( $post ) {
			return itsi_hibah_attachment_urls( get_post_meta( $post['id'], 'file_kelompok_keahlian', true ) );
		},
		'update_callback' => function ( $value, $post ) {
			if ( ! current_user_can( 'edit_post', $post->ID ) ) { return false; }
			update_post_meta( $post->ID, 'file_kelompok_keahlian', itsi_hibah_attachment_ids( $value ) );
			return true;
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
	elseif ( is_string( $value ) && '' !== $value ) { $ids = array_filter( array_map( 'intval', explode( ',', $value ) ) ); }
	elseif ( is_array( $value ) ) { $ids = array_map( 'intval', $value ); }
	else { return array(); }

	$urls = array();
	foreach ( $ids as $id ) {
		if ( $id <= 0 ) { continue; }
		$src = wp_get_attachment_url( (int) $id );
		if ( $src ) { $urls[] = $src; }
	}
	return $urls;
}

/**
 * Normalisasi input file field (dari REST) menjadi daftar attachment ID.
 * Menerima: array ID, array URL, atau string CSV ID/URL.
 */
function itsi_hibah_attachment_ids( $value ): array {
	if ( empty( $value ) ) { return array(); }
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

/* ────────────────────────────────────────────────────────────
 *  CUSTOM ENDPOINT: hibah dengan deadline terdekat
 *  GET /wp-json/itsi/v1/hibah/nearest-deadline
 * ──────────────────────────────────────────────────────────── */

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
		'file_panduan'   => itsi_hibah_attachment_urls( get_post_meta( $id, 'file_panduan', true ) ),
		'file_template'  => itsi_hibah_attachment_urls( get_post_meta( $id, 'file_template', true ) ),
		'file_kelompok_keahlian' => itsi_hibah_attachment_urls( get_post_meta( $id, 'file_kelompok_keahlian', true ) ),
		'timeline_items' => $timeline,
		'category_names' => is_array( $cats ) ? $cats : array(),
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
