<?php
/**
 * LP2M Hibah Receiver — Form pendaftaran + REST API
 *
 * Semua data disimpan sebagai CPT `pendaftaran_hibah` + post meta.
 *
 * @package itsi
 */

defined( 'ABSPATH' ) || exit;

class ITSI_LP2M_Hibah_Receiver {

	public function init(): void {
		add_action( 'init', [ $this, 'register_cpt' ] );
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		// Admin list columns — tampilkan meta yang disubmit di ?post_type=pendaftaran_hibah.
		add_filter( 'manage_pendaftaran_hibah_posts_columns', [ $this, 'admin_columns' ] );
		add_action( 'manage_pendaftaran_hibah_posts_custom_column', [ $this, 'admin_column_content' ], 10, 2 );
	}

	public function register_cpt(): void {
		register_post_type( 'pendaftaran_hibah', [
			'label'               => 'Pendaftaran Hibah',
			'labels'              => [
				'name'          => 'Pendaftaran Hibah',
				'singular_name' => 'Pendaftaran Hibah',
				'menu_name'     => 'Pendaftaran Hibah',
				'all_items'     => 'Semua Pendaftaran',
			],
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_icon'           => 'dashicons-email-alt',
			'supports'            => [ 'title', 'editor' ],
			'exclude_from_search' => true,
			'show_in_rest'        => false,
			'title_placeholder'   => 'Otomatis — jangan edit manual',
		] );
	}

	/* ────────────────────────────────────────────────────────────
	 *  ADMIN LIST COLUMNS (?post_type=pendaftaran_hibah)
	 * ──────────────────────────────────────────────────────────── */

	public function admin_columns( array $columns ): array {
		$new = [];
		foreach ( $columns as $key => $label ) {
			if ( 'date' === $key ) {
				$new['ph_reg_no']     = 'Reg No';
				$new['ph_nama']       = 'Nama';
				$new['ph_nip']        = 'NIDN/NIDK/NIM';
				$new['ph_prodi']      = 'Prodi';
				$new['ph_model']      = 'Model Hibah';
				$new['ph_jenis_hibah'] = 'Jenis Hibah';
				$new['ph_sdgs']       = 'SDGs';
				$new['ph_kk']         = 'Kel. Keahlian';
				$new['ph_anggota']    = 'Anggota Tim';
				$new['ph_judul']      = 'Judul';
				$new['ph_kontak']     = 'Kontak';
			}
			$new[ $key ] = $label;
		}
		return $new;
	}

	public function admin_column_content( string $column, int $post_id ): void {
		$meta = function ( $key ) use ( $post_id ) {
			return get_post_meta( $post_id, $key, true );
		};

		switch ( $column ) {
			case 'ph_reg_no':
				echo '<code>' . esc_html( (string) $meta( '_reg_no' ) ) . '</code>';
				break;
			case 'ph_nama':
				$nama = (string) $meta( '_nama' );
				$link = get_edit_post_link( $post_id );
				if ( $nama && $link ) {
					echo '<a href="' . esc_url( $link ) . '"><strong>' . esc_html( $nama ) . '</strong></a>';
				} else {
					echo esc_html( $nama );
				}
				break;
			case 'ph_nip':
				echo esc_html( (string) $meta( '_nip' ) );
				break;
			case 'ph_prodi':
				echo esc_html( (string) $meta( '_prodi' ) );
				break;
			case 'ph_model':
				echo esc_html( (string) $meta( '_skema' ) );
				break;
			case 'ph_jenis_hibah':
				echo esc_html( (string) $meta( '_jenis_hibah' ) );
				break;
			case 'ph_sdgs':
				echo esc_html( (string) $meta( '_sdgs' ) );
				break;
			case 'ph_kk':
				echo esc_html( (string) $meta( '_kelompok_keahlian' ) );
				break;
			case 'ph_anggota':
				$list = json_decode( (string) $meta( '_anggota_list' ), true );
				if ( ! is_array( $list ) || empty( $list ) ) {
					echo '—';
					break;
				}
				$lines = [];
				foreach ( $list as $m ) {
					$t   = $m['tipe'] ?? '';
					$nid = $m['nomor'] ?? '';
					$nm  = $m['nama'] ?? '';
					if ( 'mahasiswa' === $t ) {
						$lines[] = sprintf( 'Mhs: %s (%s, %s)', $nm, $nid, $m['prodi'] ?? '—' );
					} else {
						$lines[] = sprintf( 'Dosen: %s (%s)', $nm, $nid );
					}
				}
				$escaped = array_map( 'esc_html', $lines );
				echo implode( '<br>', $escaped ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — sudah esc per-item
				break;
			case 'ph_judul':
				echo esc_html( (string) $meta( '_judul' ) );
				break;
			case 'ph_kontak':
				echo esc_html( (string) $meta( '_email' ) ) . '<br>' . esc_html( (string) $meta( '_hp' ) );
				break;
		}
	}

	public function register_routes(): void {
		register_rest_route( 'lp2m/v1', '/hibah', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_submit' ],
			'permission_callback' => [ $this, 'check_rate_limit' ],
		] );

		register_rest_route( 'lp2m/v1', '/hibah', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_list' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( 'lp2m/v1', '/hibah/(?P<id>\d+)', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_detail' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'id' => [
					'required'          => true,
					'validate_callback' => function ( $param ) {
						return is_numeric( $param ) && (int) $param > 0;
					},
					'sanitize_callback' => 'absint',
				],
			],
		] );

		// Konfigurasi form publik: program studi (CPT) + skema (taxonomy hierarchical).
		register_rest_route( 'lp2m/v1', '/hibah/(?P<id>\d+)/form-config', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_form_config' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'id' => [
					'required'          => true,
					'validate_callback' => function ( $param ) {
						return is_numeric( $param ) && (int) $param > 0;
					},
					'sanitize_callback' => 'absint',
				],
			],
		] );
	}

	/**
	 * Form config: daftar program studi (CPT) + skema (taxonomy, parent-child+desc).
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public function handle_form_config( \WP_REST_Request $request ): \WP_REST_Response {
		$id = (int) $request->get_param( 'id' );
		if ( ! get_post( $id ) || 'hibah' !== get_post_type( $id ) ) {
			return new \WP_REST_Response( [ 'success' => false, 'message' => 'Hibah tidak ditemukan.' ], 404 );
		}

		// Program studi: CPT program_studi → id + nama.
		$prodi_posts = get_posts( [
			'post_type'      => 'program_studi',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );
		$prodi_options = [];
		foreach ( $prodi_posts as $p ) {
			$prodi_options[] = [ 'id' => $p->ID, 'name' => get_the_title( $p ) ];
		}

		// Skema: taxonomy model_hibah hierarchical → flatten parent-child + desc.
		// (Legacy: kalau model_hibah belum ada, fallback ke skema_hibah.)
		$skema_tax = taxonomy_exists( 'model_hibah' ) ? 'model_hibah' : 'skema_hibah';
		$skema_terms = get_terms( [
			'taxonomy'   => $skema_tax,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		] );
		$skema_options = [];
		if ( ! is_wp_error( $skema_terms ) ) {
			$by_id = [];
			foreach ( $skema_terms as $t ) {
				$by_id[ $t->term_id ] = $t;
			}
			foreach ( $skema_terms as $t ) {
				$label = $t->name;
				$parent = '';
				if ( $t->parent && isset( $by_id[ $t->parent ] ) ) {
					$label  = $by_id[ $t->parent ]->name . ' — ' . $t->name;
					$parent = $by_id[ $t->parent ]->name;
				}
				$skema_options[] = [
					'id'     => $t->term_id,
					'label'  => $label,
					'name'   => $t->name,
					'parent' => $parent,
					'desc'   => $t->description,
				];
			}
		}

		// Jenis hibah: taxonomy jenis_hibah.
		$jenis_options = [];
		$jenis_terms   = get_terms( [
			'taxonomy'   => 'jenis_hibah',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		] );
		if ( ! is_wp_error( $jenis_terms ) ) {
			foreach ( $jenis_terms as $t ) {
				$jenis_options[] = [ 'id' => $t->term_id, 'name' => $t->name, 'desc' => $t->description ];
			}
		}

		// SDGs: taxonomy sdgs.
		$sdgs_options = [];
		$sdgs_terms   = get_terms( [
			'taxonomy'   => 'sdgs',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		] );
		if ( ! is_wp_error( $sdgs_terms ) ) {
			foreach ( $sdgs_terms as $t ) {
				$sdgs_options[] = [ 'id' => $t->term_id, 'name' => $t->name ];
			}
		}

		// Kelompok keahlian: taxonomy kelompok_keahlian.
		$kk_options = [];
		$kk_terms   = get_terms( [
			'taxonomy'   => 'kelompok_keahlian',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		] );
		if ( ! is_wp_error( $kk_terms ) ) {
			foreach ( $kk_terms as $t ) {
				$kk_options[] = [ 'id' => $t->term_id, 'name' => $t->name ];
			}
		}

		return new \WP_REST_Response( [
			'success'          => true,
			'hibah_id'         => $id,
			'prodi_options'    => $prodi_options,
			'skema_options'    => $skema_options,
			'jenis_options'    => $jenis_options,
			'sdgs_options'     => $sdgs_options,
			'kk_options'       => $kk_options,
			'kelompok_options' => $kk_options,
		], 200 );
	}

	/* ────────────────────────────────────────────────────────────
	 *  RATE LIMITING
	 * ──────────────────────────────────────────────────────────── */

	public function check_rate_limit( \WP_REST_Request $request ): bool|\WP_Error {
		if ( 'GET' === $request->get_method() ) {
			return true;
		}

		$ip    = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
		$key   = 'lp2m_hibah_rate_' . md5( $ip );
		$count = (int) get_transient( $key );

		if ( $count >= 5 ) {
			return new \WP_Error(
				'rate_limit',
				'Terlalu banyak permintaan. Silakan coba lagi dalam 15 menit.',
				[ 'status' => 429 ]
			);
		}

		set_transient( $key, $count + 1, 15 * MINUTE_IN_SECONDS );
		return true;
	}

	/* ────────────────────────────────────────────────────────────
	 *  SANITIZATION
	 * ──────────────────────────────────────────────────────────── */

	private function sanitize_input( array $params ): array {
		$allowed = [
			'hibah_id', 'nama', 'nip', 'jenis', 'prodi', 'skema', 'judul',
			'ringkasan', 'jml_tim', 'anggota', 'email', 'hp',
			'skema_id', 'prodi_id',
			'jenis_hibah', 'jenis_hibah_id', 'sdgs', 'sdgs_id', 'kelompok_keahlian', 'kk_id',
		];

		$clean = [];
		foreach ( $allowed as $field ) {
			$val = $params[ $field ] ?? '';
			if ( is_array( $val ) || is_object( $val ) ) { $val = ''; }
			$clean[ $field ] = is_string( $val ) ? trim( $val ) : (string) $val;
		}

		$clean['hibah_id'] = (string) absint( $clean['hibah_id'] );
		$clean['skema_id'] = (string) absint( $clean['skema_id'] );
		$clean['prodi_id'] = (string) absint( $clean['prodi_id'] );
		$clean['jenis_hibah_id'] = (string) absint( $clean['jenis_hibah_id'] );
		$clean['sdgs_id'] = (string) absint( $clean['sdgs_id'] );
		$clean['kk_id'] = (string) absint( $clean['kk_id'] );

		$jenis_whitelist = [ 'Dosen', 'Mahasiswa', 'Tenaga Kependidikan' ];
		if ( ! in_array( $clean['jenis'], $jenis_whitelist, true ) ) {
			$clean['jenis'] = '';
		}

		if ( $clean['jml_tim'] !== '' ) {
			$clean['jml_tim'] = (string) absint( $clean['jml_tim'] );
		}

		$clean['nip']   = preg_replace( '/[^a-zA-Z0-9\-\\.]/', '', $clean['nip'] );
		$clean['hp']    = preg_replace( '/[^0-9\+\-]/', '', $clean['hp'] );
		$clean['email'] = sanitize_email( $clean['email'] );

		$text_fields = [ 'nama', 'prodi', 'skema', 'judul', 'ringkasan', 'anggota', 'jenis_hibah', 'sdgs', 'kelompok_keahlian' ];
		foreach ( $text_fields as $f ) {
			$clean[ $f ] = wp_strip_all_tags( $clean[ $f ], true );
		}

		$clean['anggota'] = mb_substr( $clean['anggota'], 0, 500 );

		// Anggota tim dinamis: array [{tipe, nomor, nama, prodi}] — max 2.
		$clean['anggota_list'] = [];
		$raw_list = $params['anggota_list'] ?? [];
		if ( is_array( $raw_list ) ) {
			foreach ( $raw_list as $m ) {
				if ( count( $clean['anggota_list'] ) >= 2 ) { break; }
				if ( ! is_array( $m ) ) { continue; }
				$tipe = ( 'mahasiswa' === ( $m['tipe'] ?? '' ) ) ? 'mahasiswa' : 'dosen';
				$nomor = wp_strip_all_tags( (string) ( $m['nomor'] ?? '' ), true );
				$nama  = wp_strip_all_tags( (string) ( $m['nama'] ?? '' ), true );
				$prodi = wp_strip_all_tags( (string) ( $m['prodi'] ?? '' ), true );
				if ( '' === trim( $nomor ) && '' === trim( $nama ) ) { continue; }
				$clean['anggota_list'][] = [
					'tipe'  => $tipe,
					'nomor' => mb_substr( preg_replace( '/[^a-zA-Z0-9\-\\.]/', '', $nomor ), 0, 40 ),
					'nama'  => mb_substr( $nama, 0, 150 ),
					'prodi' => mb_substr( $prodi, 0, 150 ),
				];
			}
		}

		return $clean;
	}

	/* ────────────────────────────────────────────────────────────
	 *  VALIDATION
	 * ──────────────────────────────────────────────────────────── */

	private function validate( array $params ): array {
		$errors = [];

		$labels = [
			'nama' => 'Nama Lengkap & Gelar', 'nip' => 'NIDN / NIDK / NIM',
			'judul' => 'Judul Usulan', 'ringkasan' => 'Ringkasan Usulan',
			'email' => 'Email Aktif', 'hp' => 'Nomor WhatsApp',
		];

		foreach ( $labels as $field => $label ) {
			if ( '' === trim( $params[ $field ] ) ) {
				$errors[ $field ] = $label . ' wajib diisi.';
			}
		}

		if ( '' === trim( $params['jenis'] ) ) { $errors['jenis'] = 'Jenis Pengusul wajib dipilih.'; }
		if ( '' === trim( $params['prodi'] ) ) { $errors['prodi'] = 'Program Studi / Unit Kerja wajib dipilih.'; }
		if ( '' === trim( $params['skema'] ) ) { $errors['skema'] = 'Model Hibah wajib dipilih.'; }

		if ( '' !== $params['email'] && ! is_email( $params['email'] ) ) {
			$errors['email'] = 'Format email tidak valid.';
		}

		$hp_digits = preg_replace( '/[^0-9]/', '', $params['hp'] );
		if ( strlen( $hp_digits ) < 10 ) {
			$errors['hp'] = 'Nomor WhatsApp minimal 10 digit.';
		}

		// Anggota tim dinamis (max 2): tiap entry butuh nomor + nama lengkap.
		foreach ( $params['anggota_list'] as $i => $m ) {
			if ( '' === trim( (string) $m['nomor'] ) || '' === trim( (string) $m['nama'] ) ) {
				$errors[ 'anggota_list_' . $i ] = 'Nomor & nama anggota #' . ( $i + 1 ) . ' wajib diisi.';
			}
		}

		$hibah_id = (int) $params['hibah_id'];
		if ( $hibah_id > 0 ) {
			$hibah_post = get_post( $hibah_id );
			if ( ! $hibah_post || 'hibah' !== $hibah_post->post_type || 'publish' !== $hibah_post->post_status ) {
				$errors['hibah_id'] = 'Event hibah tidak ditemukan atau sudah tidak aktif.';
			}
		}

		return $errors;
	}

	/* ────────────────────────────────────────────────────────────
	 *  HANDLERS
	 * ──────────────────────────────────────────────────────────── */

	public function handle_submit( \WP_REST_Request $request ): \WP_REST_Response {
		$params = $this->sanitize_input( $request->get_params() );

		if ( empty( $params['hibah_id'] ) || '0' === $params['hibah_id'] ) {
			$params['hibah_id'] = (string) $this->get_latest_active_hibah_id();
		}

		$errors = $this->validate( $params );
		if ( ! empty( $errors ) ) {
			return new \WP_REST_Response( [ 'success' => false, 'errors' => $errors ], 400 );
		}

		$hibah_id = (int) $params['hibah_id'];
		$reg_no   = $this->generate_reg_no();
		$post_id  = $this->save_submission( $params, $reg_no, $hibah_id );

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return new \WP_REST_Response( [ 'success' => false, 'message' => 'Gagal menyimpan data.' ], 500 );
		}

		$this->send_admin_email( $params, $reg_no, $hibah_id );

		return new \WP_REST_Response( [
			'success' => true,
			'reg_no'  => $reg_no,
			'message' => 'Pendaftaran dikirim. No. registrasi: ' . $reg_no,
		], 201 );
	}

	public function handle_list( \WP_REST_Request $request ): \WP_REST_Response {
		$per_page = max( 1, min( (int) ( $request->get_param( 'per_page' ) ?? 20 ), 100 ) );
		$page     = max( 1, (int) ( $request->get_param( 'page' ) ?? 1 ) );

		$args = [
			'post_type'      => 'pendaftaran_hibah',
			'post_status'    => 'private',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		$hibah_id = (int) ( $request->get_param( 'hibah_id' ) ?? 0 );
		if ( $hibah_id > 0 ) {
			$args['post_parent'] = $hibah_id;
		}

		$query = new \WP_Query( $args );
		$items = [];

		foreach ( $query->posts as $post ) {
			$items[] = [
				'id'         => $post->ID,
				'reg_no'     => get_post_meta( $post->ID, '_reg_no', true ),
				'hibah_id'   => get_post_meta( $post->ID, '_hibah_id', true ),
				'nama'       => get_post_meta( $post->ID, '_nama', true ),
				'nip'        => get_post_meta( $post->ID, '_nip', true ),
				'jenis'      => get_post_meta( $post->ID, '_jenis', true ),
				'prodi'      => get_post_meta( $post->ID, '_prodi', true ),
				'skema'      => get_post_meta( $post->ID, '_skema', true ),
				'jenis_hibah' => get_post_meta( $post->ID, '_jenis_hibah', true ),
				'sdgs'       => get_post_meta( $post->ID, '_sdgs', true ),
				'kelompok_keahlian' => get_post_meta( $post->ID, '_kelompok_keahlian', true ),
				'judul'      => get_post_meta( $post->ID, '_judul', true ),
				'email'      => get_post_meta( $post->ID, '_email', true ),
				'hp'         => get_post_meta( $post->ID, '_hp', true ),			'anggota_list' => json_decode( (string) get_post_meta( $post->ID, '_anggota_list', true ), true ) ?: [],				'created_at' => $post->post_date,
			];
		}

		return new \WP_REST_Response( [
			'success'     => true,
			'data'        => $items,
			'total'       => (int) $query->found_posts,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => (int) ceil( $query->found_posts / $per_page ),
		], 200 );
	}

	public function handle_detail( \WP_REST_Request $request ): \WP_REST_Response {
		$id   = (int) $request->get_param( 'id' );
		$post = get_post( $id );

		if ( ! $post || 'pendaftaran_hibah' !== $post->post_type ) {
			return new \WP_REST_Response( [ 'success' => false, 'message' => 'Data tidak ditemukan.' ], 404 );
		}

		return new \WP_REST_Response( [ 'success' => true, 'data' => [
			'id'         => $post->ID,
			'reg_no'     => get_post_meta( $post->ID, '_reg_no', true ),
			'hibah_id'   => get_post_meta( $post->ID, '_hibah_id', true ),
			'nama'       => get_post_meta( $post->ID, '_nama', true ),
			'nip'        => get_post_meta( $post->ID, '_nip', true ),
			'jenis'      => get_post_meta( $post->ID, '_jenis', true ),
			'prodi'      => get_post_meta( $post->ID, '_prodi', true ),
			'skema'      => get_post_meta( $post->ID, '_skema', true ),
			'jenis_hibah' => get_post_meta( $post->ID, '_jenis_hibah', true ),
			'sdgs'       => get_post_meta( $post->ID, '_sdgs', true ),
			'kelompok_keahlian' => get_post_meta( $post->ID, '_kelompok_keahlian', true ),
			'judul'      => get_post_meta( $post->ID, '_judul', true ),
			'ringkasan'  => get_post_meta( $post->ID, '_ringkasan', true ),
			'jml_tim'    => get_post_meta( $post->ID, '_jml_tim', true ),
			'anggota'    => get_post_meta( $post->ID, '_anggota', true ),
			'anggota_list' => json_decode( (string) get_post_meta( $post->ID, '_anggota_list', true ), true ) ?: [],
			'email'      => get_post_meta( $post->ID, '_email', true ),
			'hp'         => get_post_meta( $post->ID, '_hp', true ),
			'created_at' => $post->post_date,
		] ], 200 );
	}

	/* ────────────────────────────────────────────────────────────
	 *  HELPERS
	 * ──────────────────────────────────────────────────────────── */

	private function get_latest_active_hibah_id(): int {
		$q = new \WP_Query( [
			'post_type'      => 'hibah',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'orderby'        => 'date', 'order' => 'DESC',
		] );
		return $q->have_posts() ? $q->posts[0]->ID : 0;
	}

	private function generate_reg_no(): string {
		$year = date( 'Y' );
		$q    = new \WP_Query( [
			'post_type'      => 'pendaftaran_hibah',
			'post_status'    => 'private',
			'posts_per_page' => 1,
			'orderby'        => 'date', 'order' => 'DESC',
			'meta_query'     => [ [ 'key' => '_reg_no', 'value' => $year . '-', 'compare' => 'LIKE' ] ],
		] );
		$seq = $q->have_posts() ? ( (int) substr( get_post_meta( $q->posts[0]->ID, '_reg_no', true ), -5 ) + 1 ) : 1;
		return sprintf( '%s-%05d', $year, $seq );
	}

	private function save_submission( array $params, string $reg_no, int $hibah_id ): int|\WP_Error {
		$event_title = $hibah_id > 0 ? get_the_title( $hibah_id ) : '';

		$title = sprintf( '[%s] %s — %s', $reg_no, $params['nama'], $params['judul'] );

		$content = sprintf(
			"<p><strong>Event Hibah:</strong> %s</p>\n" .
			"<p><strong>Nama:</strong> %s</p>\n" .
			"<p><strong>NIP/NIDN:</strong> %s</p>\n" .
			"<p><strong>Jenis:</strong> %s</p>\n" .
			"<p><strong>Prodi:</strong> %s</p>\n" .
			"<p><strong>Model Hibah:</strong> %s</p>\n" .
			"<p><strong>Jenis Hibah:</strong> %s</p>\n" .
			"<p><strong>SDGs:</strong> %s</p>\n" .
			"<p><strong>Kelompok Keahlian:</strong> %s</p>\n" .
			"<p><strong>Judul Usulan:</strong> %s</p>\n" .
			"<p><strong>Ringkasan:</strong> %s</p>\n" .
			"<p><strong>Anggota Tim:</strong></p>\n%s\n" .
			"<p><strong>Email:</strong> %s | <strong>WhatsApp:</strong> %s</p>",
			esc_html( $event_title ?: '—' ),
			esc_html( $params['nama'] ), esc_html( $params['nip'] ),
			esc_html( $params['jenis'] ), esc_html( $params['prodi'] ),
			esc_html( $params['skema'] ),
			esc_html( $params['jenis_hibah'] ?: '—' ),
			esc_html( $params['sdgs'] ?: '—' ),
			esc_html( $params['kelompok_keahlian'] ?: '—' ),
			esc_html( $params['judul'] ),
			esc_html( $params['ringkasan'] ),
			$this->format_anggota_html( $params['anggota_list'] ),
			esc_html( $params['email'] ), esc_html( $params['hp'] )
		);

		$post_id = wp_insert_post( [
			'post_type'    => 'pendaftaran_hibah',
			'post_status'  => 'private',
			'post_title'   => $title,
			'post_content' => $content,
			'post_parent'  => $hibah_id > 0 ? $hibah_id : 0,
		], true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$meta = [
			'_reg_no'    => $reg_no, '_hibah_id' => $hibah_id,
			'_nama'      => $params['nama'], '_nip' => $params['nip'],
			'_jenis'     => $params['jenis'], '_prodi' => $params['prodi'],
			'_prodi_id'  => $params['prodi_id'],
			'_skema'     => $params['skema'], '_skema_id' => $params['skema_id'],
			'_judul'     => $params['judul'],
			'_ringkasan' => $params['ringkasan'], '_jml_tim' => $params['jml_tim'],
			'_anggota'   => $params['anggota'], '_email' => $params['email'],
			'_hp'        => $params['hp'],
			'_jenis_hibah'      => $params['jenis_hibah'], '_jenis_hibah_id' => $params['jenis_hibah_id'],
			'_sdgs'             => $params['sdgs'], '_sdgs_id' => $params['sdgs_id'],
			'_kelompok_keahlian' => $params['kelompok_keahlian'], '_kk_id' => $params['kk_id'],
		];
		$meta['_anggota_list'] = wp_json_encode( $params['anggota_list'] );

		foreach ( $meta as $mk => $mv ) {
			update_post_meta( $post_id, $mk, $mv );
		}

		return $post_id;
	}

	private function send_admin_email( array $params, string $reg_no, int $hibah_id ): void {
		$admin_email = get_option( 'admin_email' );
		if ( empty( $admin_email ) ) { return; }
		$event_name = $hibah_id > 0 ? get_the_title( $hibah_id ) : '';
		wp_mail( $admin_email,
			sprintf( '[LP2M] Pendaftaran Hibah Baru — %s', $reg_no ),
			sprintf(
				"Event Hibah: %s\nNo: %s\nNama: %s\nNIP: %s\nJenis: %s\nModel Hibah: %s\nJenis Hibah: %s\nSDGs: %s\nKel. Keahlian: %s\nJudul: %s\nAnggota Tim:\n%s\n\nCek: %s/wp-admin/",
				$event_name ?: '—', $reg_no, $params['nama'], $params['nip'],
				$params['jenis'], $params['skema'],
				$params['jenis_hibah'] ?: '—', $params['sdgs'] ?: '—', $params['kelompok_keahlian'] ?: '—',
				$params['judul'], $this->format_anggota_text( $params['anggota_list'] ), get_site_url()
			)
		);
	}

	/**
	 * Format daftar anggota tim dinamis → HTML (untuk konten CPT + email HTML).
	 */
	private function format_anggota_html( array $list ): string {
		if ( empty( $list ) ) {
			return '<p>—</p>';
		}
		$html = '<ul style="margin:4px 0 0 20px">';
		foreach ( $list as $i => $m ) {
			$no   = (int) $i + 1;
			$tipe = ( 'mahasiswa' === ( $m['tipe'] ?? '' ) ) ? 'Mahasiswa' : 'Dosen';
			if ( 'mahasiswa' === ( $m['tipe'] ?? '' ) ) {
				$html .= sprintf(
					'<li>%d. %s — %s (NIM: %s, Prodi: %s)</li>',
					$no, esc_html( $m['nama'] ?? '' ), esc_html( $tipe ),
					esc_html( $m['nomor'] ?? '' ), esc_html( $m['prodi'] ?? '—' )
				);
			} else {
				$html .= sprintf(
					'<li>%d. %s — %s (NIDN: %s)</li>',
					$no, esc_html( $m['nama'] ?? '' ), esc_html( $tipe ),
					esc_html( $m['nomor'] ?? '' )
				);
			}
		}
		$html .= '</ul>';
		return $html;
	}

	/**
	 * Format daftar anggota tim dinamis → plain text (untuk email admin).
	 */
	private function format_anggota_text( array $list ): string {
		if ( empty( $list ) ) {
			return '—';
		}
		$lines = [];
		foreach ( $list as $i => $m ) {
			$no   = (int) $i + 1;
			$tipe = ( 'mahasiswa' === ( $m['tipe'] ?? '' ) ) ? 'Mahasiswa' : 'Dosen';
			if ( 'mahasiswa' === ( $m['tipe'] ?? '' ) ) {
				$lines[] = sprintf( '%d. %s (%s, NIM %s, Prodi %s)', $no, $m['nama'] ?? '', $tipe, $m['nomor'] ?? '', $m['prodi'] ?? '—' );
			} else {
				$lines[] = sprintf( '%d. %s (%s, NIDN %s)', $no, $m['nama'] ?? '', $tipe, $m['nomor'] ?? '' );
			}
		}
		return implode( "\n", $lines );
	}
}

( new ITSI_LP2M_Hibah_Receiver() )->init();
