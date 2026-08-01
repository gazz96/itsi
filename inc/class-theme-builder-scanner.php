<?php
/**
 * Theme Builder — Template Scanner
 *
 * Memetakan seluruh file template di theme (root + template-parts/) dan
 * menurunkan "kondisi" yang dicakup tiap file, plus mendeteksi template
 * yang hilang untuk post type / taxonomy tertentu.
 *
 * Konvensi penamaan template WP:
 *   - single-{post_type}.php      → CPT archive single
 *   - archive-{post_type}.php     → CPT archive listing
 *   - taxonomy-{tax}-{term}.php   → specific term
 *   - taxonomy-{tax}.php          → any term in taxonomy
 *   - page-{slug}.php             → specific page by slug
 *   - {custom}.php                → dipakai via page_template attribute
 *   - front-page.php              → static front page
 *
 * "Kondisi" yang disimpan per template adalah daftar condition types
 * (post_type, taxonomy, page, dll), bukan specific IDs — itu masuk ke
 * assignment editor (class-theme-builder-assignment.php).
 *
 * @package itsi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ITSI_Theme_Builder_Scanner {

	/** @var string Absolute path ke theme root. */
	private $theme_root;

	/** @var string URL ke theme root (untuk link editor file). */
	private $theme_uri;

	public function __construct() {
		$this->theme_root = trailingslashit( get_template_directory() );
		$this->theme_uri  = trailingslashit( get_template_directory_uri() );
	}

	/**
	 * Scan seluruh template di theme root dan template-parts/.
	 *
	 * @return array<int, array<string, mixed>> Daftar template terurut by name.
	 *   Struktur per item:
	 *     - name        (string)  basename tanpa .php
	 *     - file        (string)  filename saja (foo.php)
	 *     - path        (string)  absolute path ke file
	 *     - uri         (string)  URL ke file
	 *     - exists      (bool)    true kalau file ada di disk
	 *     - size        (int)     bytes
	 *     - modified    (int)     unix timestamp
	 *     - type        (string)  salah satu: 'front-page'|'single'|'archive'|'taxonomy'|'page'|'search'|'404'|'index'|'template-part'|'custom'|'core'
	 *     - conditions  (array)   daftar kondisi yang dicakup (string[])
	 *     - post_types  (array)   post_types yang relevant (string[])
	 *     - taxonomies  (array)   taxonomies yang relevant (string[])
	 *     - used_by_pages (int)   jumlah page yang pakai page_template=name
	 */
	public function scan() {
		$items = array();

		// Template di root theme (selain functions.php & style.css & WP native).
		$root_files = $this->list_php_files( $this->theme_root );

		foreach ( $root_files as $file ) {
			$name = basename( $file, '.php' );

			// Skip file non-template (functions.php, style.css sudah di-filter di list_php_files).
			$type = $this->classify_template( $name );
			if ( null === $type ) {
				continue;
			}

			$items[] = $this->describe_template( $name, $file, $type );
		}

		// template-parts/ — file partial, tidak punya kondisi assignment sendiri
		// (dipakai via get_template_part()), tapi tetap muncul di tree di bawah
		// node "Template Parts".
		$parts_dir = $this->theme_root . 'template-parts/';
		if ( is_dir( $parts_dir ) ) {
			$part_files = $this->list_php_files( $parts_dir );
			foreach ( $part_files as $file ) {
				$name = basename( $file, '.php' );
				$items[] = array(
					'name'        => $name,
					'file'        => basename( $file ),
					'path'        => $file,
					'uri'         => str_replace( $this->theme_root, $this->theme_uri, $file ),
					'exists'      => true,
					'size'        => (int) filesize( $file ),
					'modified'    => (int) filemtime( $file ),
					'type'        => 'template-part',
					'conditions'  => array( 'get_template_part()' ),
					'post_types'  => array(),
					'taxonomies'  => array(),
					'used_by_pages' => 0,
					'part_group'  => 'template-parts',
				);
			}
		}

		// Sort by type group lalu name — biar tree view stabil.
		usort(
			$items,
			function ( $a, $b ) {
				if ( $a['type'] === $b['type'] ) {
					return strcmp( $a['name'], $b['name'] );
				}
				return strcmp( $this->type_group_rank( $a['type'] ), $this->type_group_rank( $b['type'] ) );
			}
		);

		return $items;
	}

	/**
	 * Group template by WP condition scope (Crocoblock-style horizontal tree).
	 *
	 * Returns 5 top-level scopes yang dipakai Crocoblock JetThemeCore:
	 *   - entire_site    → applies to everything (front-page, 404, search, index)
	 *   - all_archives   → all archives fallback (archive.php, archive-{cpt}.php)
	 *   - blog_posts     → standard `post` post type (single + archive + category + tag)
	 *   - singular_page  → static pages (page.php + page-{slug}.php + page-{id}.php)
	 *   - cpt_specific   → any other CPT singular + archive
	 *
	 * @param array|null $items Optional pre-scanned items; jika null akan scan().
	 * @return array<string, array{label:string, color:string, items:array}>
	 */
	public function group_by_scope( $items = null ) {
		if ( null === $items ) {
			$items = $this->scan();
		}

		$scopes = array(
			'entire_site'  => array(
				'label'  => __( 'Entire Site', 'itsi' ),
				'color'  => 'green', // green per Crocoblock
				'items'  => array(),
			),
			'all_archives' => array(
				'label'  => __( 'All Archives', 'itsi' ),
				'color'  => 'orange',
				'items'  => array(),
			),
			'blog_posts'   => array(
				'label'  => __( 'Blog Posts', 'itsi' ),
				'color'  => 'orange',
				'items'  => array(),
			),
			'taxonomies'   => array(
				'label'  => __( 'Taxonomies', 'itsi' ),
				'color'  => 'orange',
				'items'  => array(),
			),
			'singular_page' => array(
				'label'  => __( 'Singular Page', 'itsi' ),
				'color'  => 'green',
				'items'  => array(),
			),
			'cpt_singular' => array(
				'label'  => __( 'CPT Singular', 'itsi' ),
				'color'  => 'blue',
				'items'  => array(),
			),
			'cpt_archive'  => array(
				'label'  => __( 'CPT Archive', 'itsi' ),
				'color'  => 'blue',
				'items'  => array(),
			),
		);

		foreach ( $items as $tpl ) {
			// Skip template-parts and core partials — they don't get scopes
			// (they're called via get_template_part() from a parent template).
			if ( in_array( $tpl['type'], array( 'template-part', 'core' ), true ) ) {
				continue;
			}

			$scope = $this->resolve_scope( $tpl );
			if ( $scope && isset( $scopes[ $scope ] ) ) {
				$scopes[ $scope ]['items'][] = $tpl;
			}
		}

		// Drop empty scopes so tree doesn't show empty banners.
		foreach ( $scopes as $key => $data ) {
			if ( empty( $data['items'] ) ) {
				unset( $scopes[ $key ] );
			}
		}

		return $scopes;
	}

	/**
	 * Map satu template ke scope key di atas.
	 *
	 * Logic disusun dari specific ke general: taxonomy-{x}.php spesifik ke
	 * taxonomies scope; single-{cpt} spesifik ke cpt_singular; dst.
	 *
	 * @param array<string, mixed> $tpl
	 * @return string|null Scope key atau null kalau tidak masuk scope manapun.
	 */
	private function resolve_scope( $tpl ) {
		$name = $tpl['name'];
		$type = $tpl['type'];

		// Entire Site: front-page, 404, search, index.
		if ( in_array( $name, array( 'front-page', '404', 'search', 'index' ), true ) ) {
			return 'entire_site';
		}

		// Singular Page: page.php default + page-{slug} + page-{id}.
		if ( 'page' === $type ) {
			return 'singular_page';
		}

		// CPT singular: single-{cpt} where cpt != 'post'.
		if ( 'single' === $type && strpos( $name, 'single-' ) === 0 ) {
			$slug = substr( $name, 7 );
			if ( 'post' !== $slug && post_type_exists( $slug ) ) {
				return 'cpt_singular';
			}
		}

		// Standard single (post type = 'post') → blog_posts.
		if ( 'single' === $name ) {
			return 'blog_posts';
		}

		// CPT archive: archive-{cpt} where cpt != 'post'.
		if ( 'archive' === $type && strpos( $name, 'archive-' ) === 0 ) {
			$slug = substr( $name, 8 );
			if ( 'post' !== $slug && post_type_exists( $slug ) ) {
				return 'cpt_archive';
			}
		}

		// Standard archive (post type = 'post' archive, default fallback).
		if ( 'archive' === $name ) {
			return 'all_archives';
		}

		// CPT taxonomy: taxonomy-{x} where x is a public taxonomy.
		if ( 'taxonomy' === $type ) {
			return 'taxonomies';
		}

		// Custom page templates (template-home-static.php dll) → singular_page scope.
		if ( 'custom' === $type ) {
			return 'singular_page';
		}

		// Author archives.
		if ( 'author' === $type ) {
			return 'all_archives';
		}

		// Anything else: entire_site fallback.
		return 'entire_site';
	}

	/**
	 * Detect missing templates: post type dengan `has_archive=true` tapi tidak
	 * punya `archive-{post_type}.php`, ATAU taxonomy dengan `public=true` tapi
	 * tidak punya `taxonomy-{tax}.php` ATAU `archive-{post_type}.php` fallback.
	 *
	 * @return array<int, array<string, string>>
	 */
	public function detect_missing() {
		$missing = array();
		$existing = wp_list_pluck( $this->scan(), 'name' );

		// CPT archive.
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		foreach ( $post_types as $pt ) {
			// Skip built-in types yang archive-nya di-handle index.php.
			if ( in_array( $pt->name, array( 'post', 'page', 'attachment' ), true ) ) {
				continue;
			}
			// has_archive=true → butuh archive-{post_type}.php idealnya.
			if ( $pt->has_archive ) {
				$expected = 'archive-' . $pt->name;
				if ( ! in_array( $expected, $existing, true ) ) {
					$missing[] = array(
						'kind'       => 'cpt-archive',
						'identifier' => $pt->name,
						'label'      => $pt->labels->singular_name . ' (' . $pt->name . ')',
						'expected'   => $expected . '.php',
						'reason'     => sprintf(
							/* translators: %s: post type slug */
							__( 'Post type "%s" punya has_archive=true tapi archive-{slug}.php tidak ditemukan.', 'itsi' ),
							$pt->name
						),
					);
				}
			}
		}

		// Taxonomy archive — kalau ada CPT yang pakai taxonomy ini, perlu
		// archive-{cpt}.php sudah cukup (taxonomy term akan route ke sana).
		// Tapi idealnya ada taxonomy-{tax}.php sebagai fallback eksplisit.
		$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
		foreach ( $taxonomies as $tax ) {
			$expected = 'taxonomy-' . $tax->name;
			if ( ! in_array( $expected, $existing, true ) ) {
				$missing[] = array(
					'kind'       => 'taxonomy',
					'identifier' => $tax->name,
					'label'      => $tax->labels->singular_name . ' (' . $tax->name . ')',
					'expected'   => $expected . '.php',
					'reason'     => sprintf(
						/* translators: %s: taxonomy slug */
						__( 'Taxonomy "%s" tidak punya taxonomy-{slug}.php. WP akan fallback ke archive.php / index.php.', 'itsi' ),
						$tax->name
					),
				);
			}
		}

		return $missing;
	}

	// ─── Helpers ───────────────────────────────────────────────────────

	/**
	 * List semua file .php di sebuah direktori (1 level, tidak recursive).
	 *
	 * @param string $dir Absolute path.
	 * @return array<int, string>
	 */
	private function list_php_files( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return array();
		}
		$out = array();
		$entries = scandir( $dir );
		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$path = $dir . $entry;
			if ( is_file( $path ) && substr( $entry, -4 ) === '.php' ) {
				$out[] = $path;
			}
		}
		sort( $out );
		return $out;
	}

	/**
	 * Klasifikasi template by naming convention.
	 *
	 * @return string|null Type atau null kalau file bukan template.
	 */
	private function classify_template( $name ) {
		// Skip WP core files & non-template files di theme root.
		$skip = array( 'functions', 'style', 'screenshot', 'readme', 'license', 'rtl', 'composer' );
		if ( in_array( strtolower( $name ), $skip, true ) ) {
			return null;
		}

		// WP-recognized template types.
		if ( 'front-page' === $name ) {
			return 'front-page';
		}
		if ( 'single' === $name ) {
			return 'single';
		}
		if ( 'page' === $name ) {
			return 'page';
		}
		if ( 'archive' === $name ) {
			return 'archive';
		}
		if ( 'search' === $name ) {
			return 'search';
		}
		if ( '404' === $name ) {
			return '404';
		}
		if ( 'index' === $name ) {
			return 'index';
		}
		if ( 'comments' === $name || 'comment' === $name ) {
			return 'core';
		}
		if ( 'header' === $name || 'footer' === $name || 'sidebar' === $name ) {
			return 'core';
		}

		// WP-recognized prefix patterns.
		if ( strpos( $name, 'single-' ) === 0 ) {
			return 'single';
		}
		if ( strpos( $name, 'archive-' ) === 0 ) {
			return 'archive';
		}
		if ( strpos( $name, 'taxonomy-' ) === 0 ) {
			return 'taxonomy';
		}
		if ( strpos( $name, 'page-' ) === 0 ) {
			return 'page';
		}
		if ( strpos( $name, 'category-' ) === 0 ) {
			return 'taxonomy';
		}
		if ( strpos( $name, 'tag-' ) === 0 ) {
			return 'taxonomy';
		}
		if ( strpos( $name, 'author-' ) === 0 ) {
			return 'author';
		}

		// Custom page templates (dipakai via page attribute → Template).
		return 'custom';
	}

	/**
	 * Bangun description lengkap untuk satu template.
	 *
	 * @return array<string, mixed>
	 */
	private function describe_template( $name, $file, $type ) {
		$conditions = array();
		$post_types = array();
		$taxonomies = array();
		$used_by    = 0;

		switch ( $type ) {
			case 'front-page':
				$conditions[] = __( 'Static front page (Settings → Reading → Front page)', 'itsi' );
				break;

			case 'single':
				if ( 'single' === $name ) {
					$conditions[] = __( 'Semua single post + CPT default fallback', 'itsi' );
				} else {
					// single-{post_type}
					$slug = substr( $name, 7 );
					$post_types[] = $slug;
					if ( post_type_exists( $slug ) ) {
						$pt_obj = get_post_type_object( $slug );
						$conditions[] = sprintf(
							/* translators: %s: post type label */
							__( 'Single view untuk post type: %s', 'itsi' ),
							$pt_obj->labels->singular_name
						);
					} else {
						$conditions[] = sprintf(
							/* translators: %s: post type slug */
							__( 'Single view untuk post type "%s" (tidak ter-register — mungkin orphan)', 'itsi' ),
							$slug
						);
					}
				}
				break;

			case 'archive':
				if ( 'archive' === $name ) {
					$conditions[] = __( 'Archive fallback (author, date, semua CPT archive)', 'itsi' );
				} else {
					// archive-{post_type}
					$slug = substr( $name, 8 );
					$post_types[] = $slug;
					if ( post_type_exists( $slug ) ) {
						$pt_obj = get_post_type_object( $slug );
						$conditions[] = sprintf(
							/* translators: %s: post type label */
							__( 'Archive listing untuk post type: %s', 'itsi' ),
							$pt_obj->labels->singular_name
						);
					} else {
						$conditions[] = sprintf(
							/* translators: %s: post type slug */
							__( 'Archive listing untuk post type "%s" (tidak ter-register — orphan)', 'itsi' ),
							$slug
						);
					}
				}
				break;

			case 'taxonomy':
				if ( strpos( $name, 'taxonomy-' ) === 0 ) {
					$slug = substr( $name, 9 );
				} elseif ( strpos( $name, 'category-' ) === 0 ) {
					$slug = 'category';
				} elseif ( strpos( $name, 'tag-' ) === 0 ) {
					$slug = 'post_tag';
				} else {
					$slug = '';
				}
				if ( $slug && taxonomy_exists( $slug ) ) {
					$tax_obj = get_taxonomy( $slug );
					$taxonomies[] = $slug;
					$conditions[] = sprintf(
						/* translators: %s: taxonomy label */
						__( 'Term archive untuk taxonomy: %s', 'itsi' ),
						$tax_obj->labels->singular_name
					);
				} else {
					$conditions[] = sprintf(
						/* translators: %s: taxonomy slug */
						__( 'Term archive untuk taxonomy "%s" (tidak ter-register — orphan)', 'itsi' ),
						$slug
					);
				}
				break;

			case 'page':
				if ( 'page' === $name ) {
					$conditions[] = __( 'Semua page (default page template)', 'itsi' );
				} else {
					// page-{slug} atau page-{id}
					$slug = substr( $name, 5 );
					$page = get_page_by_path( $slug );
					if ( $page ) {
						$conditions[] = sprintf(
							/* translators: 1: page ID, 2: page slug */
							__( 'Page khusus: #%1$d "%2$s"', 'itsi' ),
							$page->ID,
							$page->post_title
						);
					} else {
						$conditions[] = sprintf(
							/* translators: %s: page slug */
							__( 'Page khusus (slug "%s" — orphan jika page tidak ada)', 'itsi' ),
							$slug
						);
					}
				}
				break;

			case 'search':
				$conditions[] = __( 'Search results page', 'itsi' );
				break;

			case '404':
				$conditions[] = __( '404 not-found page', 'itsi' );
				break;

			case 'index':
				$conditions[] = __( 'WP fallback ultimate (semua kondisi unmatched)', 'itsi' );
				break;

			case 'author':
				$slug = substr( $name, 7 );
				$conditions[] = sprintf(
					/* translators: %s: author nicename */
					__( 'Author archive: %s', 'itsi' ),
					$slug
				);
				break;

			case 'core':
				// header/footer/sidebar/comments → dipanggil via get_header() dll.
				$conditions[] = sprintf( __( 'Core partial — dipakai via get_%s()', 'itsi' ), $name );
				break;

			case 'custom':
			default:
				// Custom page templates (di-set via page attribute).
				$used_by = $this->count_pages_using_template( $name . '.php' );
				$conditions[] = sprintf(
					/* translators: 1: count, 2: plural */
					__( 'Custom page template — dipakai %1$d %2$s', 'itsi' ),
					$used_by,
					_n( 'page', 'pages', $used_by, 'itsi' )
				);
				break;
		}

		return array(
			'name'        => $name,
			'file'        => basename( $file ),
			'path'        => $file,
			'uri'         => str_replace( $this->theme_root, $this->theme_uri, $file ),
			'exists'      => true,
			'size'        => (int) filesize( $file ),
			'modified'    => (int) filemtime( $file ),
			'type'        => $type,
			'conditions'  => $conditions,
			'post_types'  => $post_types,
			'taxonomies'  => $taxonomies,
			'used_by_pages' => $used_by,
			'part_group'  => '',
		);
	}

	/**
	 * Hitung jumlah page yang menggunakan page_template = "{name}.php".
	 *
	 * @return int
	 */
	private function count_pages_using_template( $template_file ) {
		$cache_key = 'itsi_tb_count_' . md5( $template_file );
		$count = wp_cache_get( $cache_key, 'itsi_theme_builder' );
		if ( false !== $count ) {
			return (int) $count;
		}
		$q = new WP_Query(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'     => '_wp_page_template',
						'value'   => $template_file,
						'compare' => '=',
					),
				),
				'no_found_rows'  => true,
			)
		);
		$count = is_array( $q->posts ) ? count( $q->posts ) : 0;
		wp_cache_set( $cache_key, $count, 'itsi_theme_builder', MINUTE_IN_SECONDS * 5 );
		return $count;
	}

	/**
	 * Rank untuk ordering tree (semakin kecil = semakin atas).
	 *
	 * @return int
	 */
	private function type_group_rank( $type ) {
		$ranks = array(
			'front-page'    => 10,
			'single'        => 20,
			'archive'       => 30,
			'taxonomy'      => 40,
			'author'        => 50,
			'page'          => 60,
			'search'        => 70,
			'404'           => 80,
			'index'         => 90,
			'custom'        => 100,
			'core'          => 110,
			'template-part' => 120,
		);
		return isset( $ranks[ $type ] ) ? $ranks[ $type ] : 999;
	}
}