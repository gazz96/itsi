<?php
/**
 * REST API enhancement untuk CPT Hibah LP2M.
 *
 * Menambahkan custom fields ke response REST API
 * GET /wp-json/wp/v2/hibah dan GET /wp-json/wp/v2/hibah/{id}
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
}
add_action( 'rest_api_init', 'itsi_hibah_register_rest_fields' );

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
