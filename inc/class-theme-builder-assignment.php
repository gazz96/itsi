<?php
/**
 * Theme Builder — Assignment Manager
 *
 * Layer kedua setelah Scanner: handle CRUD untuk assignment
 * template → kondisi. Tiga scope assignment yang didukung:
 *
 *   1. Per-page (override WP native _wp_page_template post meta)
 *      → TIDAK ditulis di sini, pakai native WP page attribute UI.
 *      → Scanner hanya baca count untuk display.
 *
 *   2. Per-role (theme_mod 'itsi_tb_role_assignments')
 *      → Map role → template file. Diterapkan via template_include filter
 *        di front-end dengan priority tinggi (setelah WP's own resolver).
 *      → Use case: tampilkan single-post.php untuk role 'subscriber'
 *        tapi single-post-member.php untuk role 'itsi_member'.
 *
 *   3. Per-CPT-fallback (theme_mod 'itsi_tb_cpt_assignments')
 *      → Map post_type → template file, override `single-{post_type}.php`
 *        convention. Diterapkan via template_include filter.
 *      → Use case: pakai `single-kampus.php` untuk semua single
 *        program_studi di kampus tertentu, tanpa nge-replace file asli.
 *
 *   4. Per-category (theme_mod 'itsi_tb_term_assignments')
 *      → Map term_id → template file. Diterapkan via template_include filter.
 *      → Use case: pakai archive-kampus-medan.php untuk category=kampus-medan.
 *
 * Front-end filter: see functions.php hook `itsi_apply_theme_builder_assignments`.
 *
 * @package itsi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ITSI_Theme_Builder_Assignment {

	const OPTION_KEY_ROLE   = 'itsi_tb_role_assignments';
	const OPTION_KEY_CPT    = 'itsi_tb_cpt_assignments';
	const OPTION_KEY_TERM   = 'itsi_tb_term_assignments';

	/**
	 * Ambil semua assignments (role + cpt + term).
	 *
	 * @return array{role: array<string,string>, cpt: array<string,string>, term: array<int,string>}
	 */
	public function get_all() {
		return array(
			'role' => $this->get_role_assignments(),
			'cpt'  => $this->get_cpt_assignments(),
			'term' => $this->get_term_assignments(),
		);
	}

	/**
	 * Role → template.
	 *
	 * @return array<string, string> Key = role slug, value = template filename.
	 */
	public function get_role_assignments() {
		$val = get_option( self::OPTION_KEY_ROLE, array() );
		return is_array( $val ) ? $val : array();
	}

	/**
	 * CPT → template.
	 *
	 * @return array<string, string> Key = post_type slug, value = template filename.
	 */
	public function get_cpt_assignments() {
		$val = get_option( self::OPTION_KEY_CPT, array() );
		return is_array( $val ) ? $val : array();
	}

	/**
	 * Term → template.
	 *
	 * @return array<int, string> Key = term_id, value = template filename.
	 */
	public function get_term_assignments() {
		$val = get_option( self::OPTION_KEY_TERM, array() );
		return is_array( $val ) ? $val : array();
	}

	/**
	 * Simpan semua assignment sekaligus (dipanggil dari handler POST).
	 *
	 * @param array<string, mixed> $input Raw $_POST payload.
	 * @return int|false Number of updated options, or false on error.
	 */
	public function save_all( $input ) {
		$role = $this->sanitize_assignment_map(
			isset( $input['role'] ) && is_array( $input['role'] ) ? $input['role'] : array(),
			'role'
		);
		$cpt = $this->sanitize_assignment_map(
			isset( $input['cpt'] ) && is_array( $input['cpt'] ) ? $input['cpt'] : array(),
			'post_type'
		);
		$term = $this->sanitize_assignment_map(
			isset( $input['term'] ) && is_array( $input['term'] ) ? $input['term'] : array(),
			'term'
		);

		$updated = 0;
		$updated += (int) update_option( self::OPTION_KEY_ROLE, $role );
		$updated += (int) update_option( self::OPTION_KEY_CPT, $cpt );
		$updated += (int) update_option( self::OPTION_KEY_TERM, $term );

		return $updated;
	}

	/**
	 * Sanitasi satu map assignment.
	 *
	 * Key harus ada di daftar known identifiers (untuk role/CPT) atau
	 * numeric term_id. Value harus nama file template yang exist di theme.
	 *
	 * @param array<string, string> $raw
	 * @param string $kind 'role' | 'post_type' | 'term'
	 * @return array<string|int, string>
	 */
	private function sanitize_assignment_map( $raw, $kind ) {
		$out = array();
		$known_keys = $this->get_known_keys( $kind );
		$theme_templates = $this->list_theme_template_files();

		foreach ( $raw as $key => $value ) {
			// Skip placeholder / "_empty_" sentinels dari form.
			if ( '' === $key || '_none_' === $key ) {
				continue;
			}

			// Validasi key sesuai kind.
			if ( 'term' === $kind ) {
				$tid = (int) $key;
				if ( $tid <= 0 || ! term_exists( $tid ) ) {
					continue;
				}
				$clean_key = $tid;
			} else {
				if ( ! in_array( $key, $known_keys, true ) ) {
					continue;
				}
				$clean_key = sanitize_key( $key );
				if ( '' === $clean_key ) {
					continue;
				}
			}

			// Validasi value: harus template file yang exist, atau kosong (= hapus assignment).
			$value = is_string( $value ) ? sanitize_text_field( $value ) : '';
			if ( '' === $value || '_none_' === $value ) {
				continue; // skip = hapus assignment (kosongkan)
			}
			if ( ! in_array( $value, $theme_templates, true ) ) {
				continue; // file tidak exist — skip diam-diam
			}

			$out[ $clean_key ] = $value;
		}

		return $out;
	}

	/**
	 * Daftar key yang valid untuk assignment per kind.
	 *
	 * @param string $kind
	 * @return array<int, string>
	 */
	private function get_known_keys( $kind ) {
		if ( 'role' === $kind ) {
			global $wp_roles;
			$roles = is_array( $wp_roles->roles ?? null ) ? array_keys( $wp_roles->roles ) : array();
			return $roles;
		}
		if ( 'post_type' === $kind ) {
			$pts = get_post_types( array( 'public' => true ) );
			// Exclude attachment & internal types.
			unset( $pts['attachment'] );
			return array_values( $pts );
		}
		return array();
	}

	/**
	 * List nama file template (.php) yang exist di theme root.
	 *
	 * @return array<int, string>
	 */
	private function list_theme_template_files() {
		$dir = trailingslashit( get_template_directory() );
		$files = array();
		if ( ! is_dir( $dir ) ) {
			return $files;
		}
		foreach ( scandir( $dir ) as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			if ( substr( $entry, -4 ) === '.php' ) {
				$files[] = $entry;
			}
		}
		return $files;
	}

	/**
	 * Resolve front-end template override. Dipakai oleh filter `template_include`.
	 *
	 * Prioritas assignment (highest first):
	 *   1. Term assignment (kalau di term archive atau single post in term)
	 *   2. Role assignment (kalau current user punya role yang di-assign)
	 *   3. CPT assignment
	 *
	 * @param string $template Current template path.
	 * @return string Maybe-overridden template path.
	 */
	public function resolve_template( $template ) {
		// Skip di admin, AJAX, REST — admin lihat template literal.
		if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return $template;
		}

		$candidates = array();

		// 1. Term-based: term archive page.
		if ( is_category() || is_tag() || is_tax() ) {
			$queried = get_queried_object();
			if ( $queried && isset( $queried->term_id ) ) {
				$term_assignments = $this->get_term_assignments();
				if ( isset( $term_assignments[ $queried->term_id ] ) ) {
					$candidates[] = $term_assignments[ $queried->term_id ];
				}
			}
		}

		// 2. CPT single view.
		if ( is_singular() ) {
			$queried = get_queried_object();
			if ( $queried && isset( $queried->post_type ) ) {
				$cpt_assignments = $this->get_cpt_assignments();
				if ( isset( $cpt_assignments[ $queried->post_type ] ) ) {
					$candidates[] = $cpt_assignments[ $queried->post_type ];
				}

				// 3. Role-based (hanya kalau user login).
				if ( is_user_logged_in() ) {
					$user = wp_get_current_user();
					$role_assignments = $this->get_role_assignments();
					foreach ( (array) $user->roles as $role ) {
						if ( isset( $role_assignments[ $role ] ) ) {
							$candidates[] = $role_assignments[ $role ];
						}
					}
				}
			}
		}

		// Resolve first candidate yang exist di theme root.
		foreach ( $candidates as $candidate ) {
			$path = locate_template( array( $candidate ) );
			if ( $path ) {
				return $path;
			}
		}

		return $template;
	}

	// ─── Label lookups (untuk UI) ─────────────────────────────────────

	/**
	 * Daftar roles dengan label untuk UI.
	 *
	 * @return array<string, string> Slug => label.
	 */
	public static function list_roles_for_ui() {
		global $wp_roles;
		$out = array();
		if ( is_array( $wp_roles->roles ?? null ) ) {
			foreach ( $wp_roles->roles as $slug => $data ) {
				$out[ $slug ] = translate_user_role( $data['name'] ?? $slug );
			}
		}
		return $out;
	}

	/**
	 * Daftar public CPT dengan label untuk UI.
	 *
	 * @return array<string, string>
	 */
	public static function list_cpts_for_ui() {
		$out = array();
		$pts = get_post_types( array( 'public' => true ), 'objects' );
		unset( $pts['attachment'] );
		foreach ( $pts as $slug => $obj ) {
			$out[ $slug ] = $obj->labels->singular_name;
		}
		return $out;
	}

	/**
	 * Daftar top-level terms (categories + tags) untuk UI term assignment.
	 *
	 * @return array<int, string> term_id => "Taxonomy: Term name".
	 */
	public static function list_terms_for_ui() {
		$out = array();
		$taxes = get_taxonomies( array( 'public' => true ), 'objects' );
		foreach ( $taxes as $tax_slug => $tax_obj ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $tax_slug,
					'hide_empty' => false,
					'number'     => 100,
				)
			);
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				$out[ (int) $term->term_id ] = sprintf( '%s: %s', $tax_obj->labels->singular_name, $term->name );
			}
		}
		ksort( $out );
		return $out;
	}

	/**
	 * Daftar template files untuk UI dropdown.
	 *
	 * @return array<string, string> Filename => label.
	 */
	public static function list_templates_for_ui() {
		$scanner = new ITSI_Theme_Builder_Scanner();
		$items = $scanner->scan();
		$out = array();
		foreach ( $items as $item ) {
			if ( 'template-part' === $item['type'] || 'core' === $item['type'] ) {
				continue;
			}
			$out[ $item['file'] ] = sprintf( '%s (%s)', $item['name'], $item['type'] );
		}
		return $out;
	}
}