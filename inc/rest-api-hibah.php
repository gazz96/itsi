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
