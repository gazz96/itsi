<?php
/**
 * Bootstrap Icons map for the TypeRocket hero component icon picker.
 *
 * @package itsi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper: list of Bootstrap Icons class names for the hero CTA icon picker.
 *
 * Used by HeroComponent::fields() to populate the `<select>` for
 * `cta_primary_icon` and `cta_secondary_icon`. The values are the actual
 * `<i>` class names (bi-<name>) that will be rendered on the front-end when
 * Bootstrap Icons CSS is loaded. Keys are also bi-<name>; the label is a
 * human-readable name (Indonesian).
 *
 * To add or remove icons: edit this map. The UI re-reads it on each admin
 * page load (no caching).
 *
 * @return array<string,string>
 */
function itsi_bootstrap_icons_map() {
	return array(
		'bi-arrow-right'             => 'Panah kanan (bi-arrow-right)',
		'bi-arrow-right-short'       => 'Panah kanan pendek (bi-arrow-right-short)',
		'bi-arrow-right-circle'      => 'Panah kanan lingkaran (bi-arrow-right-circle)',
		'bi-chevron-right'           => 'Chevron kanan (bi-chevron-right)',
		'bi-chevron-double-right'    => 'Chevron ganda kanan (bi-chevron-double-right)',
		'bi-arrow-up-right'          => 'Panah naik-kanan (bi-arrow-up-right)',
		'bi-box-arrow-up-right'      => 'Box arrow up-right (bi-box-arrow-up-right)',
		'bi-arrow-bar-right'         => 'Bar panah kanan (bi-arrow-bar-right)',
		'bi-arrow-clockwise'         => 'Panah searah jarum jam (bi-arrow-clockwise)',
		'bi-arrow-counterclockwise'  => 'Panah berlawanan jarum jam (bi-arrow-counterclockwise)',
		'bi-book'                    => 'Buku (bi-book)',
		'bi-mortarboard'             => 'Topi wisuda (bi-mortarboard)',
		'bi-building'                => 'Gedung (bi-building)',
		'bi-buildings'               => 'Gedung-gedung (bi-buildings)',
		'bi-easel'                   => 'Easel (bi-easel)',
		'bi-info-circle'             => 'Info lingkaran (bi-info-circle)',
		'bi-question-circle'         => 'Tanya lingkaran (bi-question-circle)',
		'bi-telephone'               => 'Telepon (bi-telephone)',
		'bi-envelope'                => 'Amplop (bi-envelope)',
		'bi-geo-alt'                 => 'Pin lokasi (bi-geo-alt)',
		'bi-people'                  => 'Orang-orang (bi-people)',
		'bi-person'                  => 'Orang (bi-person)',
		'bi-search'                  => 'Cari (bi-search)',
		'bi-newspaper'               => 'Koran (bi-newspaper)',
		'bi-file-earmark-text'       => 'File teks (bi-file-earmark-text)',
		'bi-download'                => 'Unduh (bi-download)',
		'bi-cloud-download'          => 'Unduh awan (bi-cloud-download)',
		'bi-play-circle'             => 'Putar lingkaran (bi-play-circle)',
		'bi-youtube'                 => 'YouTube (bi-youtube)',
		'bi-instagram'               => 'Instagram (bi-instagram)',
		'bi-facebook'                => 'Facebook (bi-facebook)',
		'bi-twitter-x'               => 'Twitter/X (bi-twitter-x)',
		'bi-whatsapp'                => 'WhatsApp (bi-whatsapp)',
		'bi-linkedin'                => 'LinkedIn (bi-linkedin)',
		'bi-globe'                   => 'Globe (bi-globe)',
		'bi-link-45deg'              => 'Tautan 45deg (bi-link-45deg)',
		'bi-share'                   => 'Bagikan (bi-share)',
		'bi-send'                    => 'Kirim (bi-send)',
		'bi-check-circle'            => 'Centang lingkaran (bi-check-circle)',
		'bi-check2-circle'           => 'Centang 2 lingkaran (bi-check2-circle)',
		'bi-hand-thumbs-up'          => 'Jempol (bi-hand-thumbs-up)',
		'bi-heart'                   => 'Hati (bi-heart)',
		'bi-star'                    => 'Bintang (bi-star)',
		'bi-trophy'                  => 'Trofi (bi-trophy)',
		'bi-award'                   => 'Penghargaan (bi-award)',
		'bi-lightbulb'               => 'Bohlam (bi-lightbulb)',
		'bi-graph-up'                => 'Grafik naik (bi-graph-up)',
		'bi-calendar-event'          => 'Kalender acara (bi-calendar-event)',
		'bi-broadcast'               => 'Siaran (bi-broadcast)',
		'bi-megaphone'               => 'Megaphone (bi-megaphone)',
		'bi-mic'                     => 'Mikrofon (bi-mic)',
	);
}
