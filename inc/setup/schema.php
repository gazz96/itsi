<?php
/**
 * Schema.org JSON-LD emitter untuk tema ITSI (perguruan tinggi).
 *
 * Scope:
 *   - Front page + homepage statis:  EducationalOrganization + WebSite (+ SearchAction)
 *   - Single post (post_type=post):  Article + BreadcrumbList + publisher reference
 *   - Single page (post_type=page):  WebPage + BreadcrumbList
 *   - Single CPT program_studi:     Course + BreadcrumbList
 *
 * Pendekatan: satu blok JSON-LD per request yang berisi @graph untuk semua
 * entity yang relevan. Google lebih suka satu blok (lebih cepat parse) daripada
 * beberapa blok terpisah.
 *
 * Data sumber:
 *   - theme_mod itsi_schema_* (diisi admin dari tab "Schema / SEO" di menu ITSI)
 *   - post meta + WP core functions (untuk Article / WebPage / Course)
 *   - default fallback kalau field kosong (tidak ngeluarin field yang tidak valid)
 *
 * @package itsi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sanitize URL — hanya izinkan http/https, return '' kalau invalid.
 */
function itsi_schema_safe_url( $url ) {
	if ( ! is_string( $url ) || '' === trim( $url ) ) {
		return '';
	}
	$url = trim( $url );
	if ( ! preg_match( '#^https?://#i', $url ) ) {
		// Auto-prefix // untuk protocol-relative URL.
		if ( 0 === strpos( $url, '//' ) ) {
			$url = 'https:' . $url;
		} else {
			return '';
		}
	}
	return esc_url_raw( $url );
}

/**
 * Build array alamat (PostalAddress) dari theme_mod parts.
 * Skip field kosong — Google tolak schema dengan field ngaco.
 *
 * @return array
 */
function itsi_schema_postal_address() {
	$street   = (string) get_theme_mod( 'itsi_schema_street', '' );
	$city     = (string) get_theme_mod( 'itsi_schema_city', '' );
	$region   = (string) get_theme_mod( 'itsi_schema_region', '' );
	$postal   = (string) get_theme_mod( 'itsi_schema_postal', '' );
	$country  = (string) get_theme_mod( 'itsi_schema_country', 'ID' );

	$addr = array( '@type' => 'PostalAddress' );
	if ( '' !== $street ) {
		$addr['streetAddress'] = $street;
	}
	if ( '' !== $city ) {
		$addr['addressLocality'] = $city;
	}
	if ( '' !== $region ) {
		$addr['addressRegion'] = $region;
	}
	if ( '' !== $postal ) {
		$addr['postalCode'] = $postal;
	}
	if ( '' !== $country ) {
		$addr['addressCountry'] = $country;
	}
	// Minimal: harus ada minimal satu field. Kalau semua kosong, return null
	// supaya caller skip seluruh address block.
	if ( 1 === count( $addr ) ) {
		return null;
	}
	return $addr;
}

/**
 * Build GeoCoordinates kalau lat/lng diisi.
 *
 * @return array|null
 */
function itsi_schema_geo() {
	$lat = (string) get_theme_mod( 'itsi_schema_lat', '' );
	$lng = (string) get_theme_mod( 'itsi_schema_lng', '' );
	if ( '' === $lat || '' === $lng ) {
		return null;
	}
	// Validasi numeric.
	if ( ! is_numeric( $lat ) || ! is_numeric( $lng ) ) {
		return null;
	}
	return array(
		'@type'     => 'GeoCoordinates',
		'latitude'  => (float) $lat,
		'longitude' => (float) $lng,
	);
}

/**
 * Kumpulkan sameAs (social links) — filter URL tidak valid.
 *
 * @return array
 */
function itsi_schema_same_as() {
	$keys = array(
		'itsi_schema_social_facebook',
		'itsi_schema_social_instagram',
		'itsi_schema_social_youtube',
		'itsi_schema_social_tiktok',
		'itsi_schema_social_twitter',
		'itsi_schema_social_linkedin',
	);
	$out = array();
	foreach ( $keys as $k ) {
		$u = itsi_schema_safe_url( (string) get_theme_mod( $k, '' ) );
		if ( '' !== $u ) {
			$out[] = $u;
		}
	}
	return $out;
}

/**
 * Bangun node EducationalOrganization (pusat: muncul di semua halaman sebagai
 * referenced publisher). Kembalikan null kalau name kosong — field wajib.
 *
 * @return array|null
 */
function itsi_schema_organization_node() {
	$name = (string) get_theme_mod( 'itsi_schema_org_name', get_bloginfo( 'name' ) );
	if ( '' === $name ) {
		return null;
	}
	$url = home_url( '/' );

	$org = array(
		'@type'       => 'EducationalOrganization',
		'@id'         => $url . '#organization',
		'name'        => $name,
		'url'         => $url,
		'description' => (string) get_bloginfo( 'description' ),
	);

	// Alternate name (akronim) — mis. ITSI untuk Institut Teknologi Sawit Indonesia.
	$alt = (string) get_theme_mod( 'itsi_schema_org_alt_name', get_theme_mod( 'itsi_brand_short', 'ITSI' ) );
	if ( '' !== $alt && $alt !== $name ) {
		$org['alternateName'] = $alt;
	}

	// Logo — pakai custom_logo kalau ada, fallback ke SVG bundled.
	$logo_id  = (int) get_theme_mod( 'custom_logo', 0 );
	$logo_url = '';
	if ( $logo_id > 0 ) {
		$src = wp_get_attachment_image_src( $logo_id, 'full' );
		if ( $src && ! empty( $src[0] ) ) {
			$logo_url = $src[0];
		}
	}
	if ( '' === $logo_url ) {
		$logo_url = get_template_directory_uri() . '/assets/logo.svg';
	}
	$org['logo'] = array(
		'@type' => 'ImageObject',
		'url'   => $logo_url,
	);

	// Alamat.
	$addr = itsi_schema_postal_address();
	if ( null !== $addr ) {
		$org['address'] = $addr;
	}

	// Geo.
	$geo = itsi_schema_geo();
	if ( null !== $geo ) {
		$org['geo'] = $geo;
	}

	// Kontak (PointOfContact-style) — telephone & email + contactType.
	$tel  = (string) get_theme_mod( 'itsi_schema_phone', '' );
	$mail = (string) get_theme_mod( 'itsi_schema_email', '' );
	if ( '' !== $tel || '' !== $mail ) {
		$contact = array(
			'@type'       => 'ContactPoint',
			'contactType' => 'customer service',
		);
		if ( '' !== $tel ) {
			$contact['telephone'] = $tel;
		}
		if ( '' !== $mail ) {
			$contact['email'] = $mail;
		}
		$org['contactPoint'] = array( $contact );
	}

	// SameAs (social).
	$same_as = itsi_schema_same_as();
	if ( ! empty( $same_as ) ) {
		$org['sameAs'] = $same_as;
	}

	// Untuk EducationalOrganization Google juga butuh "name" kampus + opsional
	// "foundingDate" dan "alumni". foundingDate membantu Google verifikasi entitas.
	$founded = (string) get_theme_mod( 'itsi_schema_founded', '' );
	if ( '' !== $founded && preg_match( '/^\d{4}(-\d{2})?(-\d{2})?$/', $founded ) ) {
		$org['foundingDate'] = $founded;
	}

	return $org;
}

/**
 * Node WebSite + SearchAction (hanya di front page).
 *
 * @return array|null
 */
function itsi_schema_website_node() {
	$url = home_url( '/' );
	return array(
		'@type'       => 'WebSite',
		'@id'         => $url . '#website',
		'name'        => (string) get_bloginfo( 'name' ),
		'url'         => $url,
		'description' => (string) get_bloginfo( 'description' ),
		'inLanguage'  => get_bloginfo( 'language' ),
		'publisher'   => array( '@id' => $url . '#organization' ),
		'potentialAction' => array(
			'@type'       => 'SearchAction',
			'target'      => array(
				'@type'       => 'EntryPoint',
				'urlTemplate' => home_url( '/?s={search_term_string}' ),
			),
			'query-input' => 'required name=search_term_string',
		),
	);
}

/**
 * BreadcrumbList dari array item.
 *
 * @param array $items Array of [name, url].
 * @return array
 */
function itsi_schema_breadcrumb_node( $items ) {
	$list_items = array();
	$pos        = 1;
	foreach ( $items as $item ) {
		if ( empty( $item['name'] ) || empty( $item['url'] ) ) {
			continue;
		}
		$list_items[] = array(
			'@type'    => 'ListItem',
			'position' => $pos,
			'name'     => $item['name'],
			'item'     => $item['url'],
		);
		$pos++;
	}
	if ( empty( $list_items ) ) {
		return null;
	}
	return array(
		'@type'           => 'BreadcrumbList',
		'itemListElement' => $list_items,
	);
}

/**
 * Article node untuk single post (post_type=post).
 *
 * @return array|null
 */
function itsi_schema_article_node() {
	if ( ! is_singular( 'post' ) ) {
		return null;
	}
	$post_id = get_the_ID();
	if ( ! $post_id ) {
		return null;
	}

	$title     = get_the_title();
	$permalink = get_permalink();
	$excerpt   = has_excerpt( $post_id )
		? get_the_excerpt()
		: wp_trim_words( wp_strip_all_tags( get_the_content() ), 40, '…' );

	// Author.
	$author_id   = (int) get_post_field( 'post_author', $post_id );
	$author_name = get_the_author_meta( 'display_name', $author_id );
	$author_url  = get_author_posts_url( $author_id );

	// Featured image.
	$image_url = '';
	if ( has_post_thumbnail( $post_id ) ) {
		$src = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), 'full' );
		if ( $src && ! empty( $src[0] ) ) {
			$image_url = $src[0];
		}
	}

	// Categories.
	$article_section = '';
	$cats            = get_the_category();
	if ( ! empty( $cats ) && ! is_wp_error( $cats ) ) {
		$article_section = $cats[0]->name;
	}

	$article = array(
		'@type'         => 'Article',
		'@id'           => $permalink . '#article',
		'headline'      => $title,
		'url'           => $permalink,
		'datePublished' => get_the_date( 'c' ),
		'dateModified'  => get_the_modified_date( 'c' ),
		'inLanguage'    => get_bloginfo( 'language' ),
		'description'   => wp_strip_all_tags( $excerpt ),
		'author'        => array(
			'@type' => 'Person',
			'name'  => $author_name,
			'url'   => $author_url,
		),
		'publisher'     => array( '@id' => home_url( '/' ) . '#organization' ),
		'mainEntityOfPage' => array(
			'@type' => 'WebPage',
			'@id'   => $permalink,
		),
	);
	if ( '' !== $image_url ) {
		$article['image'] = array(
			'@type' => 'ImageObject',
			'url'   => $image_url,
		);
	}
	if ( '' !== $article_section ) {
		$article['articleSection'] = $article_section;
	}

	return $article;
}

/**
 * WebPage node untuk single page (post_type=page).
 *
 * @return array|null
 */
function itsi_schema_webpage_node() {
	if ( ! is_singular( 'page' ) ) {
		return null;
	}
	$post_id = get_the_ID();
	if ( ! $post_id ) {
		return null;
	}
	$permalink = get_permalink();
	$title     = get_the_title();

	// Deskripsi: excerpt kalau ada, fallback 40 kata pertama konten.
	$desc = has_excerpt( $post_id )
		? get_the_excerpt()
		: wp_trim_words( wp_strip_all_tags( get_the_content() ), 40, '…' );

	$page = array(
		'@type'         => 'WebPage',
		'@id'           => $permalink . '#webpage',
		'name'           => $title,
		'url'            => $permalink,
		'description'   => wp_strip_all_tags( $desc ),
		'inLanguage'    => get_bloginfo( 'language' ),
		'datePublished' => get_the_date( 'c' ),
		'dateModified'  => get_the_modified_date( 'c' ),
		'isPartOf'      => array( '@id' => home_url( '/' ) . '#website' ),
		'publisher'     => array( '@id' => home_url( '/' ) . '#organization' ),
	);

	// Featured image kalau ada.
	if ( has_post_thumbnail( $post_id ) ) {
		$src = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), 'full' );
		if ( $src && ! empty( $src[0] ) ) {
			$page['image'] = array(
				'@type' => 'ImageObject',
				'url'   => $src[0],
			);
		}
	}

	return $page;
}

/**
 * Course node untuk single CPT program_studi (skema perguruan tinggi untuk
 * halaman detail program studi).
 *
 * @return array|null
 */
function itsi_schema_course_node() {
	if ( ! is_singular( 'program_studi' ) ) {
		return null;
	}
	$post_id    = get_the_ID();
	if ( ! $post_id ) {
		return null;
	}
	$permalink  = get_permalink();
	$title      = get_the_title();
	$desc       = has_excerpt( $post_id )
		? get_the_excerpt()
		: wp_trim_words( wp_strip_all_tags( get_the_content() ), 50, '…' );

	$course = array(
		'@type'         => 'Course',
		'@id'           => $permalink . '#course',
		'name'          => $title,
		'url'           => $permalink,
		'description'   => wp_strip_all_tags( $desc ),
		'inLanguage'    => get_bloginfo( 'language' ),
		'provider'      => array( '@id' => home_url( '/' ) . '#organization' ),
	);

	// Akreditasi dari post meta kalau ada (key 'akreditasi').
	$akreditasi = (string) get_post_meta( $post_id, 'akreditasi', true );
	if ( '' === $akreditasi ) {
		// Coba variant case.
		$akreditasi = (string) get_post_meta( $post_id, 'Akreditasi', true );
	}
	if ( '' !== $akreditasi ) {
		$course['credentialCategory'] = 'Akreditasi: ' . $akreditasi;
	}

	return $course;
}

/**
 * Breadcrumb node untuk halaman detail.
 *
 * @return array|null
 */
function itsi_schema_page_breadcrumb() {
	if ( is_singular() ) {
		$items = array(
			array(
				'name' => (string) get_bloginfo( 'name' ),
				'url'  => home_url( '/' ),
			),
		);
		$post_id   = get_the_ID();
		$post_type = get_post_type( $post_id );

		if ( 'post' === $post_type ) {
			$cats = get_the_category();
			if ( ! empty( $cats ) && ! is_wp_error( $cats ) ) {
				$items[] = array(
					'name' => $cats[0]->name,
					'url'  => get_category_link( $cats[0]->term_id ),
				);
			}
		} elseif ( 'program_studi' === $post_type ) {
			$pt_archive = get_post_type_archive_link( 'program_studi' );
			if ( $pt_archive ) {
				$items[] = array(
					'name' => __( 'Program Studi', 'itsi' ),
					'url'  => $pt_archive,
				);
			}
		} elseif ( 'page' === $post_type ) {
			// Pages: tambah parent kalau ada.
			$parents = get_post_ancestors( $post_id );
			if ( ! empty( $parents ) ) {
				$top_parent = end( $parents );
				$items[]    = array(
					'name' => get_the_title( $top_parent ),
					'url'  => get_permalink( $top_parent ),
				);
			}
		}

		$items[] = array(
			'name' => get_the_title(),
			'url'  => get_permalink(),
		);

		return itsi_schema_breadcrumb_node( $items );
	}
	return null;
}

/**
 * Compose @graph dan emit JSON-LD di wp_head.
 * Hanya render di front-end (single + front page + CPT archive jika perlu).
 */
function itsi_schema_emit_jsonld() {
	// Skip di admin, search, 404, preview.
	if ( is_admin() || is_search() || is_404() || is_preview() ) {
		return;
	}
	// Skip di archive / feed (kecuali program_studi archive — debatable; default skip).
	if ( ( is_archive() || is_home() ) && ! is_front_page() ) {
		// Front page sudah ditangani.
		if ( ! is_front_page() ) {
			/* 2026-07-08: Untuk arsip post (default post archive, category, tag,
			 * author archive) JANGAN emit Organization di sini — archive-berita.php
			 * (atau archive post turunannya) sudah emit JSON-LD lengkap via
			 * ItemList + BreadcrumbList + WebSite, yang lebih SEO-rich. Emit
			 * Organization di sini akan duplicate entity dan membengkakkan HTML.
			 *
			 * Probe 2026-07-08: di VPS ini, /index.php/berita matches `is_home()=1`
			 * (bukan is_post_type_archive) karena rewrite rules + page_for_posts=0
			 * mengarahkan ke blog posts index. Maka is_home() juga harus di-skip.
			 *
			 * Tetap emit Organization untuk arsip program_studi (CPT yang tidak
			 * punya template archive sendiri yang override schema).
			 */
			if ( is_post_type_archive( 'post' ) || is_category() || is_tag() || is_author() || is_home() ) {
				return;
			}
			// Untuk arsip post program_studi, emit minimal Organization agar
			// Google tau entity. Skip BreadcrumbList (tidak relevan di archive).
			$org = itsi_schema_organization_node();
			if ( $org ) {
				$graph = array(
					'@context' => 'https://schema.org',
					'@graph'   => array( $org ),
				);
				echo '<script type="application/ld+json">' . wp_json_encode( $graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — JSON-LD, must be raw.
			}
			return;
		}
	}

	$graph = array();

	// 1. Organization (selalu).
	$org = itsi_schema_organization_node();
	if ( $org ) {
		$graph[] = $org;
	}

	// 2. WebSite (front page saja — SearchAction target halaman ini).
	if ( is_front_page() ) {
		$graph[] = itsi_schema_website_node();
	}

	// 3. Article / WebPage / Course sesuai context.
	$article  = itsi_schema_article_node();
	$webpage  = itsi_schema_webpage_node();
	$course   = itsi_schema_course_node();
	$bread    = itsi_schema_page_breadcrumb();

	if ( $article ) {
		$graph[] = $article;
	}
	if ( $webpage ) {
		$graph[] = $webpage;
	}
	if ( $course ) {
		$graph[] = $course;
	}
	if ( $bread ) {
		$graph[] = $bread;
	}

	// Kalau graph kosong (mis. nama org kosong), jangan emit apa-apa.
	if ( count( $graph ) <= 1 ) {
		// Hanya organization. Tetap boleh emit — minimal Organization untuk
		// Knowledge Graph Google. Tapi kalau organization juga null, skip.
		if ( empty( $graph ) ) {
			return;
		}
	}

	$payload = array(
		'@context' => 'https://schema.org',
		'@graph'   => $graph,
	);

	$json = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
	if ( false === $json ) {
		return;
	}

	echo '<!-- ITSI Schema.org JSON-LD (auto) -->' . "\n";
	echo '<script type="application/ld+json">' . $json . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — JSON-LD must be raw, not escaped.
}
add_action( 'wp_head', 'itsi_schema_emit_jsonld', 20 );