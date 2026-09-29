<?php
/**
 * Post view counter: atomic increment, the `post_views_count` meta key and the
 * "Paling Banyak Dibaca" query used by the sidebar widget.
 *
 * Also disables browser caching on single posts so a refresh actually counts as
 * a new view.
 *
 * @package itsi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Atomically bump a view counter stored in post meta.
 *
 * @param int    $post_id Post ID.
 * @param string $key     Meta key to increment.
 */
function itsi_bump_post_views( $post_id, $key ) {
	global $wpdb;

	if ( metadata_exists( 'post', $post_id, $key ) ) {
		// Atomic UPDATE — safe under concurrent requests.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta}
				 SET meta_value = CAST( meta_value AS UNSIGNED ) + 1
				 WHERE post_id = %d AND meta_key = %s",
				$post_id,
				$key
			)
		);
	} else {
		// First view — insert with `$unique = true` so the meta is never duplicated.
		add_post_meta( $post_id, $key, 1, true );
	}

	wp_cache_delete( $post_id, 'post_meta' );
}

/**
 * Classic server-side view counter.
 *
 * Runs on every single-post page load via `template_redirect` — no AJAX, no
 * JS. Every human visitor (including logged-in admins/editors) counts as one
 * view; bots / link-preview crawlers are excluded so the counter stays human.
 *
 * Writes both `_itsi_views` (canonical key, read by BeritaComponent) and
 * `post_views_count` (read by single.php, widgets, archive-berita.php,
 * search.php) so every consumer stays in sync.
 */
function itsi_count_post_views() {
	if ( ! is_singular( 'post' ) ) {
		return;
	}

	$post_id = (int) get_queried_object_id();
	$post    = $post_id ? get_post( $post_id ) : null;

	// Only count real, published articles — ignore other post types, drafts.
	if ( ! $post || 'post' !== $post->post_type || 'publish' !== $post->post_status ) {
		return;
	}

	// Skip known crawlers / link-preview bots (WhatsApp, Facebook, Google…)
	// so the counter stays human.
	if ( isset( $_SERVER['HTTP_USER_AGENT'] ) && preg_match( '/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|whatsapp|telegrambot|curl|wget|headless/i', $_SERVER['HTTP_USER_AGENT'] ) ) {
		return;
	}

	itsi_bump_post_views( $post_id, '_itsi_views' );
	itsi_bump_post_views( $post_id, 'post_views_count' );
}
add_action( 'template_redirect', 'itsi_count_post_views' );

/**
 * Prevent Cloudflare / proxy caches from serving single-post HTML from cache.
 *
 * The classic PHP view counter runs on `template_redirect` — it only fires
 * when WordPress actually renders the page. If the article HTML were served
 * straight from a page cache (Cloudflare), PHP never runs and the counter
 * would never increment. Sending no-cache headers on single posts tells any
 * shared cache to revalidate with the origin on every request.
 */
function itsi_single_post_no_cache() {
	if ( is_singular( 'post' ) ) {
		nocache_headers();
	}
}
add_action( 'send_headers', 'itsi_single_post_no_cache' );

/**
 * Format a view count for display.
 *
 * Examples:
 *   0      → "— views"
 *   1      → "1 view"
 *   1.234  → "1.234 views"
 *   10.234 → "10.2K"
 *   15.000 → "15K"
 *
 * @param int $n Raw view count.
 * @return string
 */
function itsi_format_views( $n ) {
	$n = max( 0, (int) $n );

	if ( $n === 0 ) {
		return '— views';
	}

	if ( $n >= 10000 ) {
		$k       = round( $n / 1000, 1 );
		$k_str   = number_format( $k, 1, '.', '' );
		if ( substr( $k_str, -2 ) === '.0' ) {
			$k_str = substr( $k_str, 0, -2 );
		}
		return $k_str . 'K';
	}

	return number_format_i18n( $n ) . ( 1 === $n ? ' view' : ' views' );
}
