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

	/** Status yang diperbolehkan untuk field `_status`. */
	public const STATUSES = [
		'submitted', 'under_review', 'revised', 'approved', 'rejected', 'done',
	];

	/** ID post terakhir yang disimpan (dipakai untuk link admin di email). */
	private int $last_post_id = 0;

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
			'supports'            => [ 'title' ],
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
				$new['ph_nip']        = 'NIDN/NIDK';
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

		// Update status / data pendaftaran (admin, via Basic auth).
		register_rest_route( 'lp2m/v1', '/hibah/(?P<id>\d+)', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_update' ],
			'permission_callback' => [ $this, 'check_admin' ],
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

		// Hapus pendaftaran (admin, via Basic auth).
		register_rest_route( 'lp2m/v1', '/hibah/(?P<id>\d+)', [
			'methods'             => 'DELETE',
			'callback'            => [ $this, 'handle_delete' ],
			'permission_callback' => [ $this, 'check_admin' ],
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
	 * Permission: hanya administrator (termasuk via Application Password/Basic auth).
	 */
	public function check_admin(): bool|\WP_Error {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		return new \WP_Error( 'forbidden', 'Anda tidak memiliki akses.', [ 'status' => 403 ] );
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

		// Jenis hibah: taxonomy jenis_hibah (hierarchical → flatten parent-child).
		$jenis_options = [];
		$jenis_terms   = get_terms( [
			'taxonomy'   => 'jenis_hibah',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		] );
		if ( ! is_wp_error( $jenis_terms ) ) {
			$jenis_by_id = [];
			foreach ( $jenis_terms as $t ) {
				$jenis_by_id[ $t->term_id ] = $t;
			}
			foreach ( $jenis_terms as $t ) {
				if ( '' === trim( (string) $t->name ) ) { continue; }
				$label  = $t->name;
				$parent = '';
				if ( $t->parent && isset( $jenis_by_id[ $t->parent ] ) ) {
					$label  = $jenis_by_id[ $t->parent ]->name . ' — ' . $t->name;
					$parent = $jenis_by_id[ $t->parent ]->name;
				}
				$jenis_options[] = [
					'id'     => $t->term_id,
					'label'  => $label,
					'name'   => $t->name,
					'parent' => $parent,
					'desc'   => $t->description,
				];
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
				if ( '' === trim( (string) $t->name ) ) { continue; }
				$sdgs_options[] = [ 'id' => $t->term_id, 'name' => $t->name ];
			}
		}

		// Kelompok keahlian: taxonomy kelompok_keahlian (hierarchical → flatten parent-child).
		$kk_options = [];
		$kk_terms   = get_terms( [
			'taxonomy'   => 'kelompok_keahlian',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		] );
		if ( ! is_wp_error( $kk_terms ) ) {
			$kk_by_id = [];
			foreach ( $kk_terms as $t ) {
				$kk_by_id[ $t->term_id ] = $t;
			}
			foreach ( $kk_terms as $t ) {
				if ( '' === trim( (string) $t->name ) ) { continue; }
				$label  = $t->name;
				$parent = '';
				if ( $t->parent && isset( $kk_by_id[ $t->parent ] ) ) {
					$label  = $kk_by_id[ $t->parent ]->name . ' — ' . $t->name;
					$parent = $kk_by_id[ $t->parent ]->name;
				}
				$kk_options[] = [
					'id'     => $t->term_id,
					'label'  => $label,
					'name'   => $t->name,
					'parent' => $parent,
					'desc'   => $t->description,
				];
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

		// Anggota tim dinamis: array [{tipe, nomor, nama, prodi}] — max 2 dosen + 2 mahasiswa.
		$clean['anggota_list'] = [];
		$raw_list = $params['anggota_list'] ?? [];
		$dosen_count = 0;
		$mhs_count   = 0;
		if ( is_array( $raw_list ) ) {
			foreach ( $raw_list as $m ) {
				if ( ! is_array( $m ) ) { continue; }
				$tipe = ( 'mahasiswa' === ( $m['tipe'] ?? '' ) ) ? 'mahasiswa' : 'dosen';
				if ( 'mahasiswa' === $tipe ) {
					if ( $mhs_count >= 2 ) { continue; }
					$mhs_count++;
				} else {
					if ( $dosen_count >= 2 ) { continue; }
					$dosen_count++;
				}
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
			'nama' => 'Nama Lengkap & Gelar', 'nip' => 'NIDN / NIDK',
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

		// Anggota tim dinamis (max 2 dosen + 2 mahasiswa): tiap entry butuh nomor + nama lengkap.
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

		// File proposal (multipart/form-data) — validasi PDF + ukuran.
		$proposal_file = $request->get_file_params()['proposal'] ?? null;
		if ( is_array( $proposal_file ) && isset( $proposal_file['error'] ) && UPLOAD_ERR_OK === (int) $proposal_file['error'] ) {
			if ( ! empty( $proposal_file['type'] ) && 'application/pdf' !== $proposal_file['type'] ) {
				$errors['proposal'] = 'Hanya file PDF yang diperbolehkan.';
			} elseif ( ! empty( $proposal_file['size'] ) && $proposal_file['size'] > 10 * 1024 * 1024 ) {
				$errors['proposal'] = 'Ukuran file maksimal 10MB.';
			}
		} else {
			$errors['proposal'] = 'File proposal (PDF) wajib diunggah.';
		}

		if ( ! empty( $errors ) ) {
			return new \WP_REST_Response( [ 'success' => false, 'errors' => $errors ], 400 );
		}

		$hibah_id = (int) $params['hibah_id'];
		$reg_no   = $this->generate_reg_no();
		$post_id  = $this->save_submission( $params, $reg_no, $hibah_id );

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return new \WP_REST_Response( [ 'success' => false, 'message' => 'Gagal menyimpan data.' ], 500 );
		}

		// Simpan file proposal sebagai attachment WP + meta _proposal_id/_proposal_url.
		if ( is_array( $proposal_file ) && isset( $proposal_file['error'] ) && UPLOAD_ERR_OK === (int) $proposal_file['error'] ) {
			$att_id = $this->upload_proposal( $proposal_file, $reg_no );
			if ( is_wp_error( $att_id ) ) {
				wp_delete_post( $post_id, true );
				return new \WP_REST_Response( [ 'success' => false, 'errors' => [ 'proposal' => $att_id->get_error_message() ] ], 400 );
			}
			update_post_meta( $post_id, '_proposal_id', $att_id );
			update_post_meta( $post_id, '_proposal_url', wp_get_attachment_url( $att_id ) );
		}

		$this->last_post_id = (int) $post_id;
		$this->send_admin_email( $params, $reg_no, $hibah_id );

		return new \WP_REST_Response( [
			'success' => true,
			'reg_no'  => $reg_no,
			'message' => 'Pendaftaran dikirim. No. registrasi: ' . $reg_no,
		], 201 );
	}

	/**
	 * Simpan file proposal (PDF) ke media library WP.
	 *
	 * @param array  $file   Entry $_FILES['proposal'] (name, type, tmp_name, error, size).
	 * @param string $reg_no Nomor registrasi (untuk penamaan file).
	 * @return int|\WP_Error Attachment ID.
	 */
	private function upload_proposal( array $file, string $reg_no ): int|\WP_Error {
		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! function_exists( 'media_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$ext  = pathinfo( (string) $file['name'], PATHINFO_EXTENSION );
		$ext  = strtolower( (string) $ext ) ?: 'pdf';
		$base = sanitize_file_name( sprintf( 'proposal-%s.%s', $reg_no, $ext ) );

		$overrides = [
			'test_form' => false,
			'test_type' => true,
			'mimes'     => [ 'pdf' => 'application/pdf' ],
		];
		$moved = wp_handle_upload( $file, $overrides );
		if ( is_wp_error( $moved ) ) {
			return $moved;
		}

		$attachment_id = wp_insert_attachment( [
			'post_mime_type' => $moved['type'],
			'post_title'     => 'Proposal ' . $reg_no,
			'post_content'   => '',
			'post_status'    => 'inherit',
		], $moved['file'], 0 );

		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			return new \WP_Error( 'upload_failed', 'Gagal menyimpan file proposal.' );
		}

		$meta = wp_generate_attachment_metadata( $attachment_id, $moved['file'] );
		wp_update_attachment_metadata( $attachment_id, $meta );

		return $attachment_id;
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
			$status = (string) get_post_meta( $post->ID, '_status', true );
			if ( '' === $status ) {
				$status = 'submitted';
			}
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
				'hp'         => get_post_meta( $post->ID, '_hp', true ),
				'status'     => $status,
				'anggota_list' => json_decode( (string) get_post_meta( $post->ID, '_anggota_list', true ), true ) ?: [],
				'proposal_id'  => get_post_meta( $post->ID, '_proposal_id', true ),
				'proposal_url' => get_post_meta( $post->ID, '_proposal_url', true ),
				'created_at' => $post->post_date,
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
			'status'     => (string) ( get_post_meta( $post->ID, '_status', true ) ?: 'submitted' ),
			'proposal_id'  => get_post_meta( $post->ID, '_proposal_id', true ),
			'proposal_url' => get_post_meta( $post->ID, '_proposal_url', true ),
			'created_at' => $post->post_date,
		] ], 200 );
	}

	/**
	 * Update status + data pendaftaran (admin).
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public function handle_update( \WP_REST_Request $request ): \WP_REST_Response {
		$id   = (int) $request->get_param( 'id' );
		$post = get_post( $id );

		if ( ! $post || 'pendaftaran_hibah' !== $post->post_type ) {
			return new \WP_REST_Response( [ 'success' => false, 'message' => 'Data tidak ditemukan.' ], 404 );
		}

		$params = $request->get_params();

		// Status: whitelist.
		if ( isset( $params['status'] ) ) {
			$status = sanitize_text_field( (string) $params['status'] );
			if ( ! in_array( $status, self::STATUSES, true ) ) {
				return new \WP_REST_Response( [ 'success' => false, 'message' => 'Status tidak valid.' ], 400 );
			}
			update_post_meta( $id, '_status', $status );
		}

		// Field opsional lain (semua divalidasi ulang lewat sanitize_input + whitelist).
		$editable = [
			'nama', 'nip', 'jenis', 'prodi', 'skema', 'judul', 'ringkasan',
			'jml_tim', 'anggota', 'email', 'hp', 'jenis_hibah', 'sdgs', 'kelompok_keahlian',
		];
		foreach ( $editable as $f ) {
			if ( ! isset( $params[ $f ] ) ) {
				continue;
			}
			$clean = $this->sanitize_input( [ $f => $params[ $f ] ] );
			$val   = $clean[ $f ];
			$map   = [
				'nama' => '_nama', 'nip' => '_nip', 'jenis' => '_jenis',
				'prodi' => '_prodi', 'skema' => '_skema', 'judul' => '_judul',
				'ringkasan' => '_ringkasan', 'jml_tim' => '_jml_tim',
				'anggota' => '_anggota', 'email' => '_email', 'hp' => '_hp',
				'jenis_hibah' => '_jenis_hibah', 'sdgs' => '_sdgs',
				'kelompok_keahlian' => '_kelompok_keahlian',
			];
			update_post_meta( $id, $map[ $f ], $val );
		}

		return new \WP_REST_Response( [ 'success' => true, 'message' => 'Data diperbarui.' ], 200 );
	}

	/**
	 * Hapus pendaftaran (admin).
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public function handle_delete( \WP_REST_Request $request ): \WP_REST_Response {
		$id   = (int) $request->get_param( 'id' );
		$post = get_post( $id );

		if ( ! $post || 'pendaftaran_hibah' !== $post->post_type ) {
			return new \WP_REST_Response( [ 'success' => false, 'message' => 'Data tidak ditemukan.' ], 404 );
		}

		$deleted = wp_delete_post( $id, true );
		if ( ! $deleted ) {
			return new \WP_REST_Response( [ 'success' => false, 'message' => 'Gagal menghapus data.' ], 500 );
		}

		return new \WP_REST_Response( [ 'success' => true, 'message' => 'Data dihapus.' ], 200 );
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
			'_status'    => 'submitted',
		];
		$meta['_anggota_list'] = wp_json_encode( $params['anggota_list'] );

		foreach ( $meta as $mk => $mv ) {
			update_post_meta( $post_id, $mk, $mv );
		}

		return $post_id;
	}

	private function send_admin_email( array $params, string $reg_no, int $hibah_id ): void {
		$admin_email = get_option( 'lp2m_site_admin_email', '' ) ?: get_option( 'admin_email' );
		$admin_email = is_email( $admin_email ) ? $admin_email : get_option( 'admin_email' );
		if ( empty( $admin_email ) ) { return; }

		$event_name = $hibah_id > 0 ? get_the_title( $hibah_id ) : '';
		$site_url   = get_site_url();
		$admin_link = $site_url . '/wp-admin/post.php?post=' . $this->last_post_id . '&action=edit';

		// Lampiran PDF detail pendaftaran (nama file: REGNO-detail.pdf).
		// PDF berisi data pribadi pendaftar → dipakai temp file yang langsung
		// dihapus setelah kirim, TIDAK disimpan permanen di folder uploads.
		$attachment = ITSI_LP2M_PDF::create_attachment( $params, $reg_no, $event_name );
		$attachments = $attachment ? [ $attachment ] : [];

		$to        = [ $admin_email ];
		$subject   = sprintf( '[LP2M] Pendaftaran Hibah Baru — %s', $reg_no );
		$body      = $this->email_html( $params, $reg_no, $event_name, $admin_link );
		$headers   = [ 'Content-Type: text/html; charset=UTF-8' ];
		wp_mail( $to, $subject, $body, $headers, $attachments );

		// Email konfirmasi ke pendaftar (sama konten, tanpa link admin + lampiran PDF).
		if ( is_email( $params['email'] ) ) {
			wp_mail(
				$params['email'],
				sprintf( 'Konfirmasi Pendaftaran Hibah — %s', $reg_no ),
				$this->email_html( $params, $reg_no, $event_name, '' ),
				[ 'Content-Type: text/html; charset=UTF-8' ],
				$attachments
			);
		}

		// Bersihkan file temp lampiran.
		ITSI_LP2M_PDF::cleanup( $attachment );
	}

	/**
	 * Template email HTML untuk admin + pendaftar.
	 */
	private function email_html( array $params, string $reg_no, string $event_name, string $admin_link ): string {
		$row = function ( string $label, string $value ): string {
			return '<tr><td style="padding:8px 12px;border-bottom:1px solid #e5e7eb;font-weight:600;color:#374151;white-space:nowrap;vertical-align:top">'
				. esc_html( $label )
				. '</td><td style="padding:8px 12px;border-bottom:1px solid #e5e7eb;color:#111827">'
				. esc_html( $value ?: '—' ) . '</td></tr>';
		};

		$anggota_rows = '';
		foreach ( $params['anggota_list'] as $i => $m ) {
			$tipe = ( 'mahasiswa' === ( $m['tipe'] ?? '' ) ) ? 'Mahasiswa' : 'Dosen';
			if ( 'mahasiswa' === ( $m['tipe'] ?? '' ) ) {
				$anggota_rows .= $row( 'Anggota #' . ( $i + 1 ), sprintf( '%s — %s (NIM: %s, Prodi: %s)', $m['nama'] ?? '', $tipe, $m['nomor'] ?? '', $m['prodi'] ?? '—' ) );
			} else {
				$anggota_rows .= $row( 'Anggota #' . ( $i + 1 ), sprintf( '%s — %s (NIDN: %s)', $m['nama'] ?? '', $tipe, $m['nomor'] ?? '' ) );
			}
		}

		$admin_btn = '';
		if ( '' !== $admin_link ) {
			$admin_btn = '<p style="margin:20px 0 0"><a href="' . esc_url( $admin_link ) . '" style="display:inline-block;padding:10px 18px;background:#2563eb;color:#fff;text-decoration:none;border-radius:6px;font-weight:600">Lihat Detail Pendaftaran</a></p>';
		}

		// Link "Cek Status" untuk pendaftar — ambil dari setting URL frontend.
		$frontend_url = untrailingslashit( (string) get_option( 'lp2m_site_frontend_url', '' ) );
		$track_btn    = '';
		if ( '' !== $frontend_url ) {
			$track_url = $frontend_url . '/daftar/status/' . rawurlencode( $reg_no );
			$track_btn = '<p style="margin:20px 0 0"><a href="' . esc_url( $track_url ) . '" style="display:inline-block;padding:10px 18px;background:#1f4d36;color:#fff;text-decoration:none;border-radius:6px;font-weight:600">Cek Status Pendaftaran</a></p>';
		}

		return '<div style="background:#f3f4f6;padding:24px;font-family:Segoe UI,Arial,sans-serif">'
			. '<div style="max-width:640px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb">'
			. '<div style="background:#0f172a;padding:20px 24px"><h2 style="margin:0;color:#fff;font-size:18px">' . esc_html( $event_name ?: 'LP2M ITSI' ) . '</h2>'
			. '<p style="margin:4px 0 0;color:#94a3b8;font-size:13px">No. Registrasi: <strong style="color:#f8fafc">' . esc_html( $reg_no ) . '</strong></p></div>'
			. '<div style="padding:24px">'
			. '<table style="width:100%;border-collapse:collapse;font-size:14px">'
			. $row( 'Nama', $params['nama'] )
			. $row( 'NIP/NIDN', $params['nip'] )
			. $row( 'Jenis Pengusul', $params['jenis'] )
			. $row( 'Program Studi', $params['prodi'] )
			. $row( 'Model Hibah', $params['skema'] )
			. $row( 'Jenis Hibah', $params['jenis_hibah'] )
			. $row( 'SDGs', $params['sdgs'] )
			. $row( 'Kelompok Keahlian', $params['kelompok_keahlian'] )
			. $row( 'Judul Usulan', $params['judul'] )
			. $row( 'Ringkasan', $params['ringkasan'] )
			. $anggota_rows
			. $row( 'Email', $params['email'] )
			. $row( 'WhatsApp', $params['hp'] )
			. '</table>'
			. $track_btn
			. $admin_btn
			. '<p style="margin:20px 0 0;color:#6b7280;font-size:12px">Email ini dikirim otomatis oleh sistem LP2M ITSI.</p>'
			. '</div></div></div>';
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
