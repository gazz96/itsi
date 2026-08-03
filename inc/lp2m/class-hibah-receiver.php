<?php
/**
 * LP2M Hibah Receiver — Form pendaftaran + REST API
 *
 * Semua data disimpan sebagai CPT `pendaftaran_hibah` + post meta.
 * Tidak ada custom table.
 *
 * @package itsi
 */

defined( 'ABSPATH' ) || exit;

class ITSI_LP2M_Hibah_Receiver {

	public function init(): void {
		add_action( 'init', [ $this, 'register_cpt' ] );
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/* ────────────────────────────────────────────────────────────
	 *  CPT — pendaftaran_hibah (private, submissions)
	 * ──────────────────────────────────────────────────────────── */

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
	 *  REST ROUTES
	 * ──────────────────────────────────────────────────────────── */

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
		];

		$clean = [];
		foreach ( $allowed as $field ) {
			$val = $params[ $field ] ?? '';
			if ( is_array( $val ) || is_object( $val ) ) {
				$val = '';
			}
			$clean[ $field ] = is_string( $val ) ? trim( $val ) : (string) $val;
		}

		$clean['hibah_id'] = (string) absint( $clean['hibah_id'] );

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

		$text_fields = [ 'nama', 'prodi', 'skema', 'judul', 'ringkasan', 'anggota' ];
		foreach ( $text_fields as $f ) {
			$clean[ $f ] = wp_strip_all_tags( $clean[ $f ], true );
		}

		$clean['anggota'] = mb_substr( $clean['anggota'], 0, 500 );

		// ── Custom fields ──
		$custom      = [];
		$form_config = $this->get_form_fields_config( (int) $clean['hibah_id'] );
		foreach ( $form_config as $field ) {
			$key  = $field['key'];
			$type = $field['type'] ?? 'text';
			$raw  = $params[ $key ] ?? '';
			if ( is_array( $raw ) || is_object( $raw ) ) {
				$raw = '';
			}
			$val = is_string( $raw ) ? trim( $raw ) : (string) $raw;

			switch ( $type ) {
				case 'url':   $val = esc_url_raw( $val ); break;
				case 'email': $val = sanitize_email( $val ); break;
				case 'number': $val = (string) absint( $val ); break;
				default:      $val = wp_strip_all_tags( $val, true );
			}

			$val           = mb_substr( $val, 0, 1000 );
			$custom[ $key ] = $val;
		}

		$clean['custom_data'] = $custom;
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

		if ( '' === trim( $params['jenis'] ) ) {
			$errors['jenis'] = 'Jenis Pengusul wajib dipilih.';
		}
		if ( '' === trim( $params['prodi'] ) ) {
			$errors['prodi'] = 'Program Studi / Unit Kerja wajib dipilih.';
		}
		if ( '' === trim( $params['skema'] ) ) {
			$errors['skema'] = 'Skema Hibah wajib dipilih.';
		}

		if ( '' !== $params['email'] && ! is_email( $params['email'] ) ) {
			$errors['email'] = 'Format email tidak valid.';
		}

		$hp_digits = preg_replace( '/[^0-9]/', '', $params['hp'] );
		if ( strlen( $hp_digits ) < 10 ) {
			$errors['hp'] = 'Nomor WhatsApp minimal 10 digit.';
		}

		$hibah_id = (int) $params['hibah_id'];
		if ( $hibah_id > 0 ) {
			$hibah_post = get_post( $hibah_id );
			if ( ! $hibah_post || 'hibah' !== $hibah_post->post_type || 'publish' !== $hibah_post->post_status ) {
				$errors['hibah_id'] = 'Event hibah tidak ditemukan atau sudah tidak aktif.';
			}
		}

		// ── Custom fields ──
		$form_config = $this->get_form_fields_config( $hibah_id );
		$custom_data = $params['custom_data'] ?? [];
		foreach ( $form_config as $field ) {
			$key      = $field['key'];
			$label    = $field['label'] ?: $key;
			$required = $field['required'] ?? false;
			$type     = $field['type'] ?? 'text';

			$val = $custom_data[ $key ] ?? '';
			if ( $required && '' === $val ) {
				$errors[ $key ] = $label . ' wajib diisi.';
				continue;
			}

			if ( '' !== $val ) {
				if ( 'email' === $type && ! is_email( $val ) ) {
					$errors[ $key ] = $label . ' — format email tidak valid.';
				} elseif ( 'url' === $type && ! preg_match( '#^https?://#', $val ) ) {
					$errors[ $key ] = $label . ' — harus diawali http:// atau https://';
				}
			}
		}

		return $errors;
	}

	/* ────────────────────────────────────────────────────────────
	 *  FORM FIELDS CONFIG
	 * ──────────────────────────────────────────────────────────── */

	private function get_form_fields_config( int $hibah_id ): array {
		if ( $hibah_id <= 0 ) {
			return [];
		}
		$raw = get_post_meta( $hibah_id, 'form_fields', true );
		if ( ! is_array( $raw ) ) {
			if ( is_string( $raw ) && '' !== $raw ) {
				$decoded = json_decode( $raw, true );
				$raw     = is_array( $decoded ) ? $decoded : [];
			} else {
				return [];
			}
		}

		$reserved = [ 'nama', 'nip', 'jenis', 'prodi', 'skema', 'judul', 'ringkasan', 'jml_tim', 'anggota', 'email', 'hp', 'hibah_id', 'pernyataan', 'custom_data' ];
		$out      = [];

		foreach ( $raw as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$key = trim( $item['Key'] ?? '' );
			if ( '' === $key || in_array( $key, $reserved, true ) ) {
				continue;
			}
			$type = trim( $item['Tipe'] ?? 'text' );
			if ( ! in_array( $type, [ 'text', 'url', 'email', 'number' ], true ) ) {
				$type = 'text';
			}
			$wajib = $item['Wajib'] ?? false;
			$out[] = [
				'key'      => $key,
				'label'    => trim( $item['Label'] ?? $key ),
				'type'     => $type,
				'required' => '1' === $wajib || 'true' === strtolower( (string) $wajib ),
			];
		}

		return $out;
	}

	/* ────────────────────────────────────────────────────────────
	 *  HANDLERS
	 * ──────────────────────────────────────────────────────────── */

	public function handle_submit( \WP_REST_Request $request ): \WP_REST_Response {
		$params = $this->sanitize_input( $request->get_params() );

		if ( empty( $params['hibah_id'] ) || '0' === $params['hibah_id'] ) {
			$fallback = $this->get_latest_active_hibah_id();
			if ( $fallback ) {
				$params['hibah_id'] = (string) $fallback;
			}
		}

		$errors = $this->validate( $params );
		if ( ! empty( $errors ) ) {
			return new \WP_REST_Response(
				[ 'success' => false, 'errors' => $errors ], 400
			);
		}

		$hibah_id = (int) $params['hibah_id'];
		$reg_no   = $this->generate_reg_no();
		$post_id  = $this->save_submission( $params, $reg_no, $hibah_id );

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return new \WP_REST_Response(
				[ 'success' => false, 'message' => 'Gagal menyimpan data. Silakan coba lagi.' ], 500
			);
		}

		$this->send_admin_email( $params, $reg_no, $hibah_id );

		return new \WP_REST_Response( [
			'success' => true,
			'reg_no'  => $reg_no,
			'message' => 'Pendaftaran berhasil dikirim. Nomor registrasi Anda: ' . $reg_no,
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
				'id'          => $post->ID,
				'reg_no'      => get_post_meta( $post->ID, '_reg_no', true ),
				'hibah_id'    => get_post_meta( $post->ID, '_hibah_id', true ),
				'nama'        => get_post_meta( $post->ID, '_nama', true ),
				'jenis'       => get_post_meta( $post->ID, '_jenis', true ),
				'prodi'       => get_post_meta( $post->ID, '_prodi', true ),
				'skema'       => get_post_meta( $post->ID, '_skema', true ),
				'judul'       => get_post_meta( $post->ID, '_judul', true ),
				'email'       => get_post_meta( $post->ID, '_email', true ),
				'hp'          => get_post_meta( $post->ID, '_hp', true ),
				'custom_data' => get_post_meta( $post->ID, '_custom_data', true ),
				'created_at'  => $post->post_date,
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
			return new \WP_REST_Response(
				[ 'success' => false, 'message' => 'Data tidak ditemukan.' ], 404
			);
		}

		return new \WP_REST_Response( [ 'success' => true, 'data' => [
			'id'          => $post->ID,
			'reg_no'      => get_post_meta( $post->ID, '_reg_no', true ),
			'hibah_id'    => get_post_meta( $post->ID, '_hibah_id', true ),
			'nama'        => get_post_meta( $post->ID, '_nama', true ),
			'nip'         => get_post_meta( $post->ID, '_nip', true ),
			'jenis'       => get_post_meta( $post->ID, '_jenis', true ),
			'prodi'       => get_post_meta( $post->ID, '_prodi', true ),
			'skema'       => get_post_meta( $post->ID, '_skema', true ),
			'judul'       => get_post_meta( $post->ID, '_judul', true ),
			'ringkasan'   => get_post_meta( $post->ID, '_ringkasan', true ),
			'jml_tim'     => get_post_meta( $post->ID, '_jml_tim', true ),
			'anggota'     => get_post_meta( $post->ID, '_anggota', true ),
			'email'       => get_post_meta( $post->ID, '_email', true ),
			'hp'          => get_post_meta( $post->ID, '_hp', true ),
			'custom_data' => get_post_meta( $post->ID, '_custom_data', true ),
			'created_at'  => $post->post_date,
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
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_key'       => 'status_hibah',
			'meta_value'     => 'aktif',
		] );
		if ( $q->have_posts() ) {
			return $q->posts[0]->ID;
		}
		$q2 = new \WP_Query( [
			'post_type'      => 'hibah',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		] );
		return $q2->have_posts() ? $q2->posts[0]->ID : 0;
	}

	private function generate_reg_no(): string {
		$year = date( 'Y' );
		$q    = new \WP_Query( [
			'post_type'      => 'pendaftaran_hibah',
			'post_status'    => 'private',
			'posts_per_page' => 1,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => [ [
				'key'     => '_reg_no',
				'value'   => $year . '-',
				'compare' => 'LIKE',
			] ],
		] );
		if ( $q->have_posts() ) {
			$last_reg = get_post_meta( $q->posts[0]->ID, '_reg_no', true );
			$seq      = (int) substr( $last_reg, -5 ) + 1;
		} else {
			$seq = 1;
		}
		return sprintf( '%s-%05d', $year, $seq );
	}

	private function save_submission( array $params, string $reg_no, int $hibah_id ): int|\WP_Error {
		$event_title = $hibah_id > 0 ? get_the_title( $hibah_id ) : '';

		$title = sprintf( '[%s] %s — %s', $reg_no, $params['nama'], $params['judul'] );

		$custom_html = '';
		$custom_data = $params['custom_data'] ?? [];
		if ( ! empty( $custom_data ) ) {
			$config       = $this->get_form_fields_config( $hibah_id );
			$key_to_label = [];
			foreach ( $config as $f ) {
				$key_to_label[ $f['key'] ] = $f['label'];
			}
			foreach ( $custom_data as $ck => $cv ) {
				$label        = $key_to_label[ $ck ] ?? $ck;
				$custom_html .= sprintf(
					"<p><strong>%s:</strong> %s</p>\n",
					esc_html( $label ), esc_html( $cv )
				);
			}
		}

		$content = sprintf(
			"<p><strong>Event Hibah:</strong> %s</p>\n" .
			"<p><strong>Nama:</strong> %s</p>\n" .
			"<p><strong>NIP/NIDN:</strong> %s</p>\n" .
			"<p><strong>Jenis:</strong> %s</p>\n" .
			"<p><strong>Prodi:</strong> %s</p>\n" .
			"<p><strong>Skema:</strong> %s</p>\n" .
			"<p><strong>Judul Usulan:</strong> %s</p>\n" .
			"<p><strong>Ringkasan:</strong> %s</p>\n" .
			"<p><strong>Jumlah Tim:</strong> %s</p>\n" .
			"<p><strong>Anggota:</strong> %s</p>\n" .
			"<p><strong>Email:</strong> %s | <strong>WhatsApp:</strong> %s</p>\n" .
			"%s",
			esc_html( $event_title ?: '—' ),
			esc_html( $params['nama'] ), esc_html( $params['nip'] ),
			esc_html( $params['jenis'] ), esc_html( $params['prodi'] ),
			esc_html( $params['skema'] ), esc_html( $params['judul'] ),
			esc_html( $params['ringkasan'] ),
			esc_html( $params['jml_tim'] ?: '—' ),
			esc_html( $params['anggota'] ?: '—' ),
			esc_html( $params['email'] ), esc_html( $params['hp'] ),
			$custom_html
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

		// Store all fields as post meta.
		$meta_fields = [
			'_reg_no'    => $reg_no,
			'_hibah_id'  => $hibah_id,
			'_nama'      => $params['nama'],
			'_nip'       => $params['nip'],
			'_jenis'     => $params['jenis'],
			'_prodi'     => $params['prodi'],
			'_skema'     => $params['skema'],
			'_judul'     => $params['judul'],
			'_ringkasan' => $params['ringkasan'],
			'_jml_tim'   => $params['jml_tim'],
			'_anggota'   => $params['anggota'],
			'_email'     => $params['email'],
			'_hp'        => $params['hp'],
			'_custom_data' => $custom_data,
		];

		foreach ( $meta_fields as $mk => $mv ) {
			update_post_meta( $post_id, $mk, $mv );
		}

		return $post_id;
	}

	private function send_admin_email( array $params, string $reg_no, int $hibah_id ): void {
		$admin_email = get_option( 'admin_email' );
		if ( empty( $admin_email ) ) {
			return;
		}
		$event_name = $hibah_id > 0 ? get_the_title( $hibah_id ) : '';
		wp_mail( $admin_email,
			sprintf( '[LP2M] Pendaftaran Hibah Baru — %s', $reg_no ),
			sprintf(
				"Event Hibah: %s\nNomor Registrasi: %s\nNama: %s\nNIP/NIDN: %s\nJenis: %s\nSkema: %s\nJudul: %s\nEmail: %s\nWhatsApp: %s\n\nCek dashboard: %s/wp-admin/",
				$event_name ?: '—', $reg_no,
				$params['nama'], $params['nip'], $params['jenis'],
				$params['skema'], $params['judul'],
				$params['email'], $params['hp'],
				get_site_url()
			)
		);
	}
}

( new ITSI_LP2M_Hibah_Receiver() )->init();
