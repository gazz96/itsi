<?php
/**
 * Footer layout definition + accessors.
 *
 * The layout is stored in a theme_mod as rows of [label, width]; the helpers
 * normalize it and derive the CSS grid column count used by footer.php, the
 * Customizer and the widget auto-populate routine.
 *
 * @package itsi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Default footer layout — 4 kolom: Brand 2fr + Prodi/Info/Kontak 1fr.
 *
 * Dipakai oleh: itsi_get_footer_layout(), widget registration (functions.php),
 * populate tabs (admin-menu.php), dan render (footer.php). Semua merujuk ke
 * sini agar default konsisten.
 *
 * @return array<int,array{label:string,width:string}>
 */
function itsi_footer_layout_default() {
	return array(
		array( 'label' => 'Footer 1', 'width' => '2fr' ),
		array( 'label' => 'Footer 2', 'width' => '1fr' ),
		array( 'label' => 'Footer 3', 'width' => '1fr' ),
		array( 'label' => 'Footer 4', 'width' => '1fr' ),
	);
}

/**
 * Get the footer layout from theme_mod, normalized to rows [label, width].
 *
 * Handles: absent (null), array, serialized string (set_theme_mod auto-serializes
 * arrays), and JSON string (TypeRocket repeater can post JSON). Invalid/empty
 * rows dropped; missing width → '1fr'; empty result → default.
 *
 * @return array<int,array{label:string,width:string}>
 */
function itsi_get_footer_layout() {
	$raw = get_theme_mod( 'itsi_footer_layout', null );

	if ( is_string( $raw ) ) {
		$decoded = json_decode( $raw, true );
		if ( is_array( $decoded ) ) {
			$raw = $decoded;
		} else {
			$unser = maybe_unserialize( $raw );
			$raw   = is_array( $unser ) ? $unser : array();
		}
	}
	if ( ! is_array( $raw ) ) {
		$raw = array();
	}

	$rows = array();
	foreach ( $raw as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$label = isset( $row['label'] ) ? (string) $row['label'] : '';
		$width = isset( $row['width'] ) ? trim( (string) $row['width'] ) : '';
		if ( '' === $label && '' === $width ) {
			continue;
		}
		$rows[] = array(
			'label' => '' !== $label ? $label : sprintf( 'Footer %d', count( $rows ) + 1 ),
			'width' => '' !== $width ? $width : '1fr',
		);
	}

	return ! empty( $rows ) ? $rows : itsi_footer_layout_default();
}

/**
 * Build a sanitized CSS `grid-template-columns` value from layout rows.
 *
 * Only whitelisted tokens pass: angka+unit (fr, %, px, em, rem, vw, vh),
 * auto, min-content, max-content, minmax(...). Token tak dikenal → '1fr'.
 * Empty rows → default '2fr 1fr 1fr 1fr'.
 *
 * @param array $rows Rows from itsi_get_footer_layout().
 * @return string CSS value, e.g. "2fr 1fr 1fr 1fr".
 */
function itsi_footer_grid_columns( $rows ) {
	if ( ! is_array( $rows ) || empty( $rows ) ) {
		return '2fr 1fr 1fr 1fr';
	}

	$tokens = array();
	foreach ( $rows as $row ) {
		$width = isset( $row['width'] ) ? trim( (string) $row['width'] ) : '';
		if ( '' === $width ) {
			$tokens[] = '1fr';
			continue;
		}
		$parts = preg_split( '/[\s,]+/', $width, -1, PREG_SPLIT_NO_EMPTY );
		foreach ( $parts as $tok ) {
			if ( 1 === preg_match( '#^(\d+(\.\d+)?(fr|%|px|em|rem|vw|vh))|auto|min-content|max-content|minmax\([^)]*\)$#i', $tok ) ) {
				$tokens[] = $tok;
			} else {
				$tokens[] = '1fr';
			}
		}
	}

	return implode( ' ', $tokens );
}
