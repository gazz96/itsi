<?php
/**
 * REST API enhancement untuk CPT Hibah.
 *
 * Menambahkan custom fields ke response REST API
 * GET /wp-json/wp/v2/hibah dan GET /wp-json/wp/v2/hibah/{id}
 *
 * Juga: form-config endpoint untuk dynamic form rendering.
 *
 * @package itsi
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register meta fields ke REST API untuk post type `hibah`.
 */
function itsi_hibah_register_rest_fields() {
	$meta_fields = array(
		'jenis_hibah',
		'status_hibah',
		'kategori_hibah',
		'deadline',
		'deadline_label',
		'skema',
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
			'update_callback' => null,
			'schema'          => array(
				'type'        => 'string',
				'description' => 'Meta field: ' . $field,
				'context'     => array( 'view', 'edit' ),
			),
		) );
	}

	// ── Timeline (repeater → normalized JSON array) ──
	register_rest_field( 'hibah', 'timeline_items', array(
		'get_callback' => function ( $post ) {
			$raw = get_post_meta( $post['id'], 'timeline_items', true );
			if ( is_array( $raw ) ) {
				$out = array();
				foreach ( $raw as $item ) {
					if ( ! is_array( $item ) ) {
						continue;
					}
					$out[] = array(
						'date'  => itsi_hibah_field( $item, 'Tanggal', 'date' ),
						'label' => itsi_hibah_field( $item, 'Deskripsi', 'label', 'desc' ),
					);
				}
				return $out;
			}
			if ( is_string( $raw ) && '' !== $raw ) {
				$decoded = json_decode( $raw, true );
				return is_array( $decoded ) ? $decoded : array();
			}
			return array();
		},
		'schema' => array(
			'type'        => 'array',
			'description' => 'Timeline items (repeater)',
			'context'     => array( 'view', 'edit' ),
		),
	) );

	// ── File panduan (attachment IDs → URL array) ──
	register_rest_field( 'hibah', 'file_panduan', array(
		'get_callback' => function ( $post ) {
			return itsi_hibah_attachment_urls( get_post_meta( $post['id'], 'file_panduan', true ) );
		},
		'schema' => array(
			'type'        => 'array',
			'description' => 'File panduan (URLs)',
			'context'     => array( 'view', 'edit' ),
		),
	) );

	// ── File template (attachment IDs → URL array) ──
	register_rest_field( 'hibah', 'file_template', array(
		'get_callback' => function ( $post ) {
			return itsi_hibah_attachment_urls( get_post_meta( $post['id'], 'file_template', true ) );
		},
		'schema' => array(
			'type'        => 'array',
			'description' => 'File template (URLs)',
			'context'     => array( 'view', 'edit' ),
		),
	) );

	// ── Category names (resolved from WP categories) ──
	register_rest_field( 'hibah', 'category_names', array(
		'get_callback' => function ( $post ) {
			$cats = wp_get_post_categories( $post['id'], array( 'fields' => 'names' ) );
			return is_array( $cats ) ? $cats : array();
		},
		'schema' => array(
			'type'        => 'array',
			'description' => 'Category names',
			'context'     => array( 'view' ),
		),
	) );

	// ── Form Fields (custom form builder per hibah) ──
	register_rest_field( 'hibah', 'form_fields', array(
		'get_callback' => function ( $post ) {
			$raw = get_post_meta( $post['id'], 'form_fields', true );
			if ( is_array( $raw ) ) {
				$out = array();
				foreach ( $raw as $item ) {
					if ( ! is_array( $item ) ) {
						continue;
					}
					$key   = itsi_hibah_field( $item, 'Key' );
					// eslint-disable-next-line no-empty
					if ( '' === $key ) { continue; }
					$type  = itsi_hibah_field( $item, 'Tipe', 'type' );
					if ( ! in_array( $type, array( 'text', 'url', 'email', 'number' ), true ) ) {
						$type = 'text';
					}
					$wajib = itsi_hibah_field( $item, 'Wajib', 'required', 'wajib' );
					$out[] = array(
						'label'    => itsi_hibah_field( $item, 'Label' ),
						'key'      => $key,
						'type'     => $type,
						'required' => '1' === $wajib || 'true' === strtolower( $wajib ),
					);
				}
				return array_values( $out );
			}
			if ( is_string( $raw ) && '' !== $raw ) {
				$decoded = json_decode( $raw, true );
				return is_array( $decoded ) ? $decoded : array();
			}
			return array();
		},
		'schema' => array(
			'type'        => 'array',
			'description' => 'Custom form fields (form builder)',
			'context'     => array( 'view', 'edit' ),
		),
	) );
}
add_action( 'rest_api_init', 'itsi_hibah_register_rest_fields' );

/**
 * REST endpoint: form config per hibah (for dynamic form rendering).
 *
 * GET /lp2m/v1/hibah/{id}/form-config
 * Returns all fields (standard + custom) for the LP2M Vue form.
 */
function itsi_hibah_form_config_endpoint( $request ) {
	$hibah_id = (int) $request->get_param( 'id' );
	$post     = get_post( $hibah_id );

	if ( ! $post || 'hibah' !== $post->post_type || 'publish' !== $post->post_status ) {
		return new WP_REST_Response(
			array( 'success' => false, 'message' => 'Event hibah tidak ditemukan.' ),
			404
		);
	}

	// Standard fields (always present).
	$standard = array(
		array( 'key' => 'nama',      'label' => 'Nama Lengkap & Gelar',           'type' => 'text',     'required' => true ),
		array( 'key' => 'nip',       'label' => 'NIDN / NIDK / NIM',              'type' => 'text',     'required' => true ),
		array( 'key' => 'jenis',     'label' => 'Jenis Pengusul',                 'type' => 'radio',    'required' => true,  'options' => array( 'Dosen', 'Mahasiswa', 'Tenaga Kependidikan' ) ),
		array( 'key' => 'prodi',     'label' => 'Program Studi / Unit Kerja',     'type' => 'select',   'required' => true ),
		array( 'key' => 'skema',     'label' => 'Skema Hibah',                    'type' => 'select',   'required' => true ),
		array( 'key' => 'judul',     'label' => 'Judul Usulan',                   'type' => 'text',     'required' => true ),
		array( 'key' => 'ringkasan', 'label' => 'Ringkasan Usulan',               'type' => 'textarea', 'required' => true ),
		array( 'key' => 'jml_tim',   'label' => 'Jumlah Anggota Tim',             'type' => 'number',   'required' => false ),
		array( 'key' => 'anggota',   'label' => 'Nama Anggota Tim',               'type' => 'text',     'required' => false ),
		array( 'key' => 'email',     'label' => 'Email Aktif',                    'type' => 'email',    'required' => true ),
		array( 'key' => 'hp',        'label' => 'Nomor WhatsApp Aktif',           'type' => 'tel',      'required' => true ),
	);

	// Custom fields from meta.
	$custom_raw = get_post_meta( $hibah_id, 'form_fields', true );
	$custom     = array();

	if ( is_array( $custom_raw ) ) {
		$reserved = array( 'nama', 'nip', 'jenis', 'prodi', 'skema', 'judul', 'ringkasan', 'jml_tim', 'anggota', 'email', 'hp', 'hibah_id', 'pernyataan' );
		foreach ( $custom_raw as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$key = trim( itsi_hibah_field( $item, 'Key' ) );
			if ( '' === $key || in_array( $key, $reserved, true ) ) {
				continue;
			}
			$type = trim( itsi_hibah_field( $item, 'Tipe', 'type' ) );
			if ( ! in_array( $type, array( 'text', 'url', 'email', 'number' ), true ) ) {
				$type = 'text';
			}
			$wajib    = itsi_hibah_field( $item, 'Wajib', 'required', 'wajib' );
			$custom[] = array(
				'key'      => $key,
				'label'    => trim( itsi_hibah_field( $item, 'Label' ) ),
				'type'     => $type,
				'required' => '1' === $wajib || 'true' === strtolower( $wajib ),
			);
		}
	}

	return new WP_REST_Response( array(
		'success'       => true,
		'hibah_id'      => $hibah_id,
		'title'         => get_the_title( $hibah_id ),
		'standard'      => $standard,
		'custom'        => $custom,
		'total_fields'  => count( $standard ) + count( $custom ),
	), 200 );
}

/**
 * Register form-config REST route.
 */
function itsi_hibah_register_form_config_route() {
	register_rest_route( 'lp2m/v1', '/hibah/(?P<id>\d+)/form-config', array(
		'methods'             => 'GET',
		'callback'            => 'itsi_hibah_form_config_endpoint',
		'permission_callback' => '__return_true',
		'args'                => array(
			'id' => array(
				'required'          => true,
				'validate_callback' => function ( $param ) {
					return is_numeric( $param ) && (int) $param > 0;
				},
				'sanitize_callback' => 'absint',
			),
		),
	) );
}
add_action( 'rest_api_init', 'itsi_hibah_register_form_config_route' );

/**
 * Helper: resolve TR image field (single ID or comma-separated IDs) to URL array.
 *
 * TypeRocket `image()` menyimpan attachment sebagai:
 *   - integer (single attachment ID)
 *   - string (comma-separated IDs, e.g. "123,456")
 *   - array of IDs
 *
 * @param mixed $value Raw postmeta value.
 * @return array Array of attachment URLs (strings).
 */
function itsi_hibah_attachment_urls( $value ) {
	if ( empty( $value ) ) {
		return array();
	}

	if ( is_numeric( $value ) ) {
		$ids = array( (int) $value );
	} elseif ( is_string( $value ) && '' !== $value ) {
		$ids = array_filter( array_map( 'intval', explode( ',', $value ) ) );
	} elseif ( is_array( $value ) ) {
		$ids = array_map( 'intval', $value );
	} else {
		return array();
	}

	$urls = array();
	foreach ( $ids as $id ) {
		if ( $id <= 0 ) {
			continue;
		}
		$src = wp_get_attachment_url( (int) $id );
		if ( $src ) {
			$urls[] = $src;
		}
	}
	return $urls;
}

/**
 * Helper: ambil nilai dari TR repeater item dengan key case-insensitive.
 *
 * TypeRocket repeater menyimpan data dengan key sesuai label field,
 * e.g. "Tanggal" bukan "date". Function ini mencoba semua alias.
 *
 * @param array  $row  Satu item dari repeater array.
 * @param string ...$keys Satu atau lebih key alias (case-insensitive).
 * @return string
 */
function itsi_hibah_field( $row, ...$keys ) {
	if ( ! is_array( $row ) ) {
		return '';
	}
	foreach ( $keys as $k ) {
		if ( isset( $row[ $k ] ) && '' !== $row[ $k ] ) {
			return $row[ $k ];
		}
		// Case-insensitive fallback.
		foreach ( $row as $rk => $rv ) {
			if ( strcasecmp( (string) $rk, (string) $k ) === 0 && '' !== $rv ) {
				return $rv;
			}
		}
	}
	return '';
}
