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
				if ( in_array( $field, array( 'info_tambahan', 'link_panduan' ), true ) ) {
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
		'get_callback' => function ( $post ) {
			return itsi_hibah_attachment_urls( get_post_meta( $post['id'], 'file_panduan', true ) );
		},
		'schema' => array( 'type' => 'array', 'description' => 'File panduan (URLs)', 'context' => array( 'view' ) ),
	) );

	// ── File template ──
	register_rest_field( 'hibah', 'file_template', array(
		'get_callback' => function ( $post ) {
			return itsi_hibah_attachment_urls( get_post_meta( $post['id'], 'file_template', true ) );
		},
		'schema' => array( 'type' => 'array', 'description' => 'File template (URLs)', 'context' => array( 'view' ) ),
	) );

	// ── Category names ──
	register_rest_field( 'hibah', 'category_names', array(
		'get_callback' => function ( $post ) {
			$cats = wp_get_post_categories( $post['id'], array( 'fields' => 'names' ) );
			return is_array( $cats ) ? $cats : array();
		},
		'schema' => array( 'type' => 'array', 'description' => 'Category names', 'context' => array( 'view' ) ),
	) );

	// ── Jenis Hibah (taxonomy) → expose string 'internal'|'eksternal' agar
	//    frontend lp2m (dashboard Hibah/Form.vue, KelolaHibah.vue) tetap jalan.
	register_rest_field( 'hibah', 'jenis_hibah', array(
		'get_callback' => function ( $post ) {
			$terms = get_the_terms( $post['id'], 'jenis_hibah' );
			if ( ! is_array( $terms ) || empty( $terms ) ) { return ''; }
			$slugs = wp_list_pluck( $terms, 'slug' );
			return in_array( 'internal', $slugs, true ) ? 'internal' : ( in_array( 'eksternal', $slugs, true ) ? 'eksternal' : '' );
		},
		'schema' => array( 'type' => 'string', 'description' => 'Jenis hibah (dari taxonomy)', 'context' => array( 'view', 'edit' ) ),
	) );
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

	// Jenis hibah dari taxonomy (bukan meta lagi).
	$jenis_terms = get_the_terms( $id, 'jenis_hibah' );
	$jenis_slugs  = is_array( $jenis_terms ) ? wp_list_pluck( $jenis_terms, 'slug' ) : array();
	$jenis        = in_array( 'internal', $jenis_slugs, true ) ? 'internal' : ( in_array( 'eksternal', $jenis_slugs, true ) ? 'eksternal' : '' );

	$data = array(
		'id'             => $id,
		'slug'           => $post->post_name,
		'title'          => get_the_title( $post ),
		'excerpt'        => get_the_excerpt( $post ),
		'permalink'      => get_permalink( $post ),
		'jenis_hibah'    => $jenis,
		'deadline'       => get_post_meta( $id, 'deadline', true ),
		'deadline_label' => get_post_meta( $id, 'deadline_label', true ),
		'event_eyebrow'  => get_post_meta( $id, 'event_eyebrow', true ),
		'dana_maks'      => get_post_meta( $id, 'dana_maks', true ),
		'jumlah_tim_maks'=> get_post_meta( $id, 'jumlah_tim_maks', true ),
		'info_tambahan'  => get_post_meta( $id, 'info_tambahan', true ),
		'link_panduan'   => get_post_meta( $id, 'link_panduan', true ),
		'file_panduan'   => itsi_hibah_attachment_urls( get_post_meta( $id, 'file_panduan', true ) ),
		'file_template'  => itsi_hibah_attachment_urls( get_post_meta( $id, 'file_template', true ) ),
		'timeline_items' => $timeline,
		'category_names' => is_array( $cats ) ? $cats : array(),
	);

	return new WP_REST_Response( array(
		'found' => true,
		'data'  => $data,
	), 200 );
}
