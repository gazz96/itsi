<?php
/**
 * General-purpose theme helpers.
 *
 * Logo URL with bundled-SVG fallback, ACF/TypeRocket repeater normalization,
 * icon-list normalization, the "latest pengumuman" query and the Bootstrap Icons
 * class map used by the hero CTA icon picker in TypeRocket.
 *
 * @package itsi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get the site logo URL with a sensible fallback to the bundled SVG.
 */
function itsi_get_logo_url() {
	$custom = get_theme_mod( 'custom_logo' );
	if ( $custom ) {
		$src = wp_get_attachment_image_src( $custom, 'full' );
		if ( $src ) {
			return $src[0];
		}
	}
	return get_template_directory_uri() . '/assets/img/logo.svg';
}

/**
 * Helper: normalize a repeater field value from TypeRocket components.
 *
 * TypeRocket's Matrix/Builder stores repeater data as a JSON-encoded string in
 * post meta. When rendered via `tr_components_field('builder')`, the value can
 * arrive as:
 *   - null / unset  -> use default
 *   - array         -> use as-is
 *   - JSON string   -> decode and use, fallback to default on failure
 *   - empty string  -> use default
 *
 * The `??` operator alone is not enough because `??` only triggers on null.
 *
 * @param mixed $value   The raw value from $data[...] (may be null, string, array).
 * @param array $default The fallback array to use when value is not a usable array.
 * @return array
 */
function itsi_repeater_value( $value, $default = array() ) {
	if ( is_array( $value ) ) {
		return $value;
	}
	if ( is_string( $value ) && '' !== $value ) {
		$decoded = json_decode( $value, true );
		if ( is_array( $decoded ) ) {
			return $decoded;
		}
		// TypeRocket may also use serialized PHP arrays in some storage paths.
		if ( function_exists( 'maybe_unserialize' ) ) {
			$unserialized = maybe_unserialize( $value );
			if ( is_array( $unserialized ) ) {
				return $unserialized;
			}
		}
	}
	return is_array( $default ) ? $default : array();
}

/**
 * Helper: normalize CTA icon list input.
 *
 * Accepts: array of strings, comma-separated string, or single string.
 * Returns: array of trimmed non-empty icon class strings.
 *
 * Used by HeroComponent and other matrix components that read
 * `cta_primary_icon` / `cta_secondary_icon` from builder data.
 *
 * @param mixed $raw The raw value (array | string | null).
 * @return array
 */
function itsi_normalize_icon_list( $raw ) {
	if ( is_array( $raw ) ) {
		$out = array();
		foreach ( $raw as $v ) {
			if ( is_string( $v ) || is_numeric( $v ) ) {
				$v = trim( (string) $v );
				if ( $v !== '' ) { $out[] = $v; }
			}
		}
		return $out;
	}
	if ( is_string( $raw ) ) {
		$parts = array_map( 'trim', explode( ',', $raw ) );
		return array_values( array_filter( $parts, static function( $v ) { return $v !== ''; } ) );
	}
	return array();
}

/**
 * Helper: latest "pengumuman" posts (for the hero card).
 *
 * Sources from the standard `post` post type and, when present, filters by the
 * `pengumuman` category (slug). If that category doesn't exist yet, falls back
 * to recent posts. Returns a `WP_Query` ready to be looped.
 *
 * @param int $count Number of posts to fetch.
 * @return \WP_Query
 */
function itsi_get_latest_pengumuman( $count = 3 ) {
	$count = max( 1, (int) $count );

	$args = array(
		'post_type'      => 'post',
		'posts_per_page' => $count,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'no_found_rows'  => true,
	);

	if ( function_exists( 'get_category_by_slug' ) ) {
		$cat = get_category_by_slug( 'pengumuman' );
		if ( $cat && ! is_wp_error( $cat ) ) {
			$args['cat'] = (int) $cat->term_id;
		}
	}

	return new WP_Query( $args );
}
