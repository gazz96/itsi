<?php
/**
 * LP2M Hibah Receiver — Form pendaftaran + REST API
 *
 * Semua logic di dalam theme (bukan plugin).
 *
 * @package itsi
 */

defined( 'ABSPATH' ) || exit;

class ITSI_LP2M_Hibah_Receiver {

	private string $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'lp2m_hibah';
	}

	public function init(): void {
		add_action( 'init', [ $this, 'register_cpt' ] );
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		add_action( 'after_switch_theme', [ $this, 'create_table' ] );
		add_action( 'admin_menu', [ $this, 'maybe_insert_admin_menu' ] );
		// Ensure table exists on first theme load (idempotent dbDelta).
		add_action( 'init', [ $this, 'create_table' ], 1 );
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
	 *  TABLE — custom table for submissions (backward compat)
	 * ──────────────────────────────────────────────────────────── */

	public function create_table(): void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE IF NOT EXISTS {$this->table} (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			reg_no VARCHAR(20) NOT NULL UNIQUE,
			hibah_id BIGINT UNSIGNED DEFAULT NULL,
			nama VARCHAR(255) NOT NULL,
			nip VARCHAR(30) NOT NULL,
			jenis VARCHAR(30) NOT NULL,
			prodi VARCHAR(255) NOT NULL,
			skema VARCHAR(255) NOT NULL,
			judul TEXT NOT NULL,
			ringkasan TEXT NOT NULL,
			jml_tim VARCHAR(5) DEFAULT '',
			anggota TEXT DEFAULT '',
			email VARCHAR(255) NOT NULL,
			hp VARCHAR(30) NOT NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			INDEX idx_reg_no (reg_no),
			INDEX idx_skema (skema),
			INDEX idx_hibah_id (hibah_id)
		) $charset;";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Show "Pendaftaran Hibah" under "Hibah LP2M" for better grouping.
	 */
	public function maybe_insert_admin_menu(): void {
		global $menu, $submenu;
		// If CPT was already registered, move it under Hibah LP2M.
		// Simple approach: let the CPT show at its own position; admin can use.
	}

	/* ────────────────────────────────────────────────────────────
	 *  REST ROUTES
	 * ──────────────────────────────────────────────────────────── */

	public function register_routes(): void {
		// POST — submit pendaftaran.
		register_rest_route( 'lp2m/v1', '/hibah', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_submit' ],
			'permission_callback' => [ $this, 'check_rate_limit' ],
		] );

		// GET — list semua pendaftaran.
		register_rest_route( 'lp2m/v1', '/hibah', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_list' ],
			'permission_callback' => '__return_true',
		] );

		// GET — detail satu pendaftaran.
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

		// hibah_id: integer only.
		$clean['hibah_id'] = (string) absint( $clean['hibah_id'] );

		// Strict whitelist for `jenis`.
		$jenis_whitelist = [ 'Dosen', 'Mahasiswa', 'Tenaga Kependidikan' ];
		if ( ! in_array( $clean['jenis'], $jenis_whitelist, true ) ) {
			$clean['jenis'] = '';
		}

		// jml_tim: integer only.
		if ( $clean['jml_tim'] !== '' ) {
			$clean['jml_tim'] = (string) absint( $clean['jml_tim'] );
		}

		// NIP: alphanumeric + dash + dot.
		$clean['nip'] = preg_replace( '/[^a-zA-Z0-9\-\.]/', '', $clean['nip'] );

		// HP: digits + plus + dash.
		$clean['hp'] = preg_replace( '/[^0-9\+\-]/', '', $clean['hp'] );

		// Email: WP sanitize.
		$clean['email'] = sanitize_email( $clean['email'] );

		// Text fields: strip HTML.
		$text_fields = [ 'nama', 'prodi', 'skema', 'judul', 'ringkasan', 'anggota' ];
		foreach ( $text_fields as $f ) {
			$clean[ $f ] = wp_strip_all_tags( $clean[ $f ], true );
		}

		// anggota: cap 500 chars.
		$clean['anggota'] = mb_substr( $clean['anggota'], 0, 500 );

		return $clean;
	}

	/* ────────────────────────────────────────────────────────────
	 *  VALIDATION
	 * ──────────────────────────────────────────────────────────── */

	private function validate( array $params ): array {
		$errors = [];

		$labels = [
			'nama'      => 'Nama Lengkap & Gelar',
			'nip'       => 'NIDN / NIDK / NIM',
			'jenis'     => 'Jenis Pengusul',
			'prodi'     => 'Program Studi / Unit Kerja',
			'skema'     => 'Skema Hibah',
			'judul'     => 'Judul Usulan',
			'ringkasan' => 'Ringkasan Usulan',
			'email'     => 'Email Aktif',
			'hp'        => 'Nomor WhatsApp',
		];

		foreach ( $labels as $field => $label ) {
			if ( '' === trim( $params[ $field ] ) ) {
				$errors[ $field ] = $label . ' wajib diisi.';
			}
		}

		if ( '' !== $params['email'] && ! is_email( $params['email'] ) ) {
			$errors['email'] = 'Format email tidak valid.';
		}

		$hp_digits = preg_replace( '/[^0-9]/', '', $params['hp'] );
		if ( strlen( $hp_digits ) < 10 ) {
			$errors['hp'] = 'Nomor WhatsApp minimal 10 digit.';
		}

		// Validate hibah_id — must be a published CPT hibah.
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

	/**
	 * POST: Submit pendaftaran hibah.
	 */
	public function handle_submit( \WP_REST_Request $request ): \WP_REST_Response {
		$params = $this->sanitize_input( $request->get_params() );

		// Auto-detect hibah_id from latest active event if empty.
		if ( empty( $params['hibah_id'] ) || '0' === $params['hibah_id'] ) {
			$fallback = $this->get_latest_active_hibah_id();
			if ( $fallback ) {
				$params['hibah_id'] = (string) $fallback;
			}
		}

		$errors = $this->validate( $params );
		if ( ! empty( $errors ) ) {
			return new \WP_REST_Response(
				[ 'success' => false, 'errors' => $errors ],
				400
			);
		}

		$hibah_id   = (int) $params['hibah_id'];
		$reg_no     = $this->generate_reg_no();

		// --- Save to custom table ---
		global $wpdb;
		$inserted = $this->save_to_table( $params, $reg_no, $hibah_id );

		// --- Also save as CPT for admin visibility ---
		$this->save_as_cpt( $params, $reg_no, $hibah_id );

		if ( false === $inserted ) {
			return new \WP_REST_Response(
				[ 'success' => false, 'message' => 'Gagal menyimpan data. Silakan coba lagi.' ],
				500
			);
		}

		$this->send_admin_email( $params, $reg_no, $hibah_id );

		return new \WP_REST_Response( [
			'success' => true,
			'reg_no'  => $reg_no,
			'message' => 'Pendaftaran berhasil dikirim. Nomor registrasi Anda: ' . $reg_no,
		], 201 );
	}

	/**
	 * GET: List pendaftaran (paginated).
	 */
	public function handle_list( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;

		$per_page = (int) ( $request->get_param( 'per_page' ) ?? 20 );
		$per_page = max( 1, min( $per_page, 100 ) );
		$page     = max( 1, (int) ( $request->get_param( 'page' ) ?? 1 ) );
		$offset   = ( $page - 1 ) * $per_page;

		// Optional filter by hibah_id.
		$hibah_filter = '';
		$hibah_id     = (int) ( $request->get_param( 'hibah_id' ) ?? 0 );
		if ( $hibah_id > 0 ) {
			$hibah_filter = $wpdb->prepare( ' WHERE hibah_id = %d', $hibah_id );
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table}{$hibah_filter}" );
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, reg_no, hibah_id, nama, jenis, prodi, skema, judul, email, hp, created_at
				 FROM {$this->table}{$hibah_filter}
				 ORDER BY created_at DESC
				 LIMIT %d OFFSET %d",
				$per_page,
				$offset
			),
			ARRAY_A
		);
		// phpcs:enable

		return new \WP_REST_Response( [
			'success'     => true,
			'data'        => $items ?: [],
			'total'       => $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => (int) ceil( $total / $per_page ),
		], 200 );
	}

	/**
	 * GET: Detail satu pendaftaran.
	 */
	public function handle_detail( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;

		$id   = (int) $request->get_param( 'id' );
		$item = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ),
			ARRAY_A
		);

		if ( ! $item ) {
			return new \WP_REST_Response(
				[ 'success' => false, 'message' => 'Data tidak ditemukan.' ],
				404
			);
		}

		return new \WP_REST_Response( [ 'success' => true, 'data' => $item ], 200 );
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
			$q->the_post();
			$id = get_the_ID();
			wp_reset_postdata();
			return $id;
		}

		// Fallback: just get the latest published hibah.
		$q2 = new \WP_Query( [
			'post_type'      => 'hibah',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		] );

		if ( $q2->have_posts() ) {
			$q2->the_post();
			$id = get_the_ID();
			wp_reset_postdata();
			return $id;
		}

		return 0;
	}

	private function generate_reg_no(): string {
		global $wpdb;
		$year = date( 'Y' );
		$last = $wpdb->get_var( $wpdb->prepare(
			"SELECT reg_no FROM {$this->table} WHERE reg_no LIKE %s ORDER BY id DESC LIMIT 1",
			$year . '-%'
		) );
		$seq = $last ? ( (int) substr( $last, -5 ) + 1 ) : 1;
		return sprintf( '%s-%05d', $year, $seq );
	}

	private function save_to_table( array $params, string $reg_no, int $hibah_id ): int|false {
		global $wpdb;
		return $wpdb->insert( $this->table, [
			'reg_no'    => $reg_no,
			'hibah_id'  => $hibah_id > 0 ? $hibah_id : null,
			'nama'      => $params['nama'],
			'nip'       => $params['nip'],
			'jenis'     => $params['jenis'],
			'prodi'     => $params['prodi'],
			'skema'     => $params['skema'],
			'judul'     => $params['judul'],
			'ringkasan' => $params['ringkasan'],
			'jml_tim'   => $params['jml_tim'],
			'anggota'   => $params['anggota'],
			'email'     => $params['email'],
			'hp'        => $params['hp'],
		] );
	}

	private function save_as_cpt( array $params, string $reg_no, int $hibah_id ): void {
		$event_title = '';
		if ( $hibah_id > 0 ) {
			$event_title = get_the_title( $hibah_id );
		}

		$title = sprintf( '[%s] %s — %s', $reg_no, $params['nama'], $params['judul'] );

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
			"<p><strong>Email:</strong> %s | <strong>WhatsApp:</strong> %s</p>",
			esc_html( $event_title ?: '—' ),
			esc_html( $params['nama'] ),
			esc_html( $params['nip'] ),
			esc_html( $params['jenis'] ),
			esc_html( $params['prodi'] ),
			esc_html( $params['skema'] ),
			esc_html( $params['judul'] ),
			esc_html( $params['ringkasan'] ),
			esc_html( $params['jml_tim'] ?: '—' ),
			esc_html( $params['anggota'] ?: '—' ),
			esc_html( $params['email'] ),
			esc_html( $params['hp'] )
		);

		$post_args = [
			'post_type'    => 'pendaftaran_hibah',
			'post_status'  => 'private',
			'post_title'   => $title,
			'post_content' => $content,
			'post_parent'  => $hibah_id > 0 ? $hibah_id : 0,
		];

		$post_id = wp_insert_post( $post_args, true );

		if ( ! is_wp_error( $post_id ) ) {
			update_post_meta( $post_id, '_reg_no', $reg_no );
			update_post_meta( $post_id, '_hibah_id', $hibah_id > 0 ? $hibah_id : 0 );
		}
	}

	private function send_admin_email( array $params, string $reg_no, int $hibah_id ): void {
		$admin_email = get_option( 'admin_email' );
		if ( empty( $admin_email ) ) {
			return;
		}

		$event_name = '';
		if ( $hibah_id > 0 ) {
			$event_name = get_the_title( $hibah_id );
		}

		$body = sprintf(
			"Event Hibah: %s\nNomor Registrasi: %s\nNama: %s\nNIP/NIDN: %s\nJenis: %s\nSkema: %s\nJudul: %s\nEmail: %s\nWhatsApp: %s\n\nCek dashboard: %s/wp-admin/",
			$event_name ?: '—',
			$reg_no,
			$params['nama'],
			$params['nip'],
			$params['jenis'],
			$params['skema'],
			$params['judul'],
			$params['email'],
			$params['hp'],
			get_site_url()
		);

		wp_mail(
			$admin_email,
			sprintf( '[LP2M] Pendaftaran Hibah Baru — %s', $reg_no ),
			$body
		);
	}
}

// Bootstrap.
( new ITSI_LP2M_Hibah_Receiver() )->init();
