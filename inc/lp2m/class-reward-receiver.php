<?php
/**
 * LP2M Reward Receiver — public submission endpoint for Reward Jurnal.
 *
 * Accepts POST /lp2m/v1/reward-jurnal (multipart/form-data) and stores each
 * submission as a `reward_jurnal` post + meta + taxonomy terms + uploaded file
 * attachments. Categories are read from the dynamic taxonomies
 * (kategori_jurnal / kategori_buku / kategori_haki / kategori_seminar).
 *
 * @package itsi
 */

defined( 'ABSPATH' ) || exit;

class ITSI_LP2M_Reward_Receiver {

	/** Batas ukuran file upload (byte) — 10 MB per file. */
	public const MAX_UPLOAD_BYTES = 10 * 1024 * 1024;

	/** Jenis karya yang diperbolehkan. */
	public const JENIS_REWARD = array( 'artikel', 'buku', 'haki', 'seminar' );

	/** Batas submit per IP per window (menit & jumlah). */
	private const RATE_LIMIT_COUNT = 5;
	private const RATE_LIMIT_MINUTES = 15;

	/** Tipe MIME gambar yang diizinkan (untuk field pdf/image). */
	private const IMAGE_MIMES = array( 'image/jpeg', 'image/png' );

	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			'lp2m/v1',
			'/reward-jurnal',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_submit' ),
				'permission_callback' => array( $this, 'check_rate_limit' ),
			)
		);

		register_rest_route(
			'lp2m/v1',
			'/reward-jurnal/categories',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_categories' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'lp2m/v1',
			'/reward-jurnal/period',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_period' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/** Rate limit sederhana per IP lewat transient. */
	public function check_rate_limit( \WP_REST_Request $request ): bool|\WP_Error {
		$ip    = (string) ( $_SERVER['REMOTE_ADDR'] ?? 'unknown' );
		$key   = 'lp2m_reward_rl_' . md5( $ip );
		$count = (int) get_transient( $key );

		if ( $count >= self::RATE_LIMIT_COUNT ) {
			return new \WP_Error( 'rate_limited', 'Terlalu banyak pengajuan. Coba lagi nanti.', array( 'status' => 429 ) );
		}

		set_transient( $key, $count + 1, self::RATE_LIMIT_MINUTES * MINUTE_IN_SECONDS );
		return true;
	}

	/** Kategori dinamis + pilihan jenis reward untuk wizard SPA. */
	public function handle_categories(): \WP_REST_Response {
		$taxonomies = array( 'kategori_jurnal', 'kategori_buku', 'kategori_haki', 'kategori_seminar' );
		$out        = array(
			'success'      => true,
			'jenis_reward' => array(
				array( 'value' => 'artikel', 'label' => 'Artikel / Jurnal' ),
				array( 'value' => 'buku', 'label' => 'Buku' ),
				array( 'value' => 'haki', 'label' => 'HAKI (Hak Kekayaan Intelektual)' ),
				array( 'value' => 'seminar', 'label' => 'Seminar' ),
			),
		);

		foreach ( $taxonomies as $tax ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $tax,
					'hide_empty' => false,
				)
			);
			$list  = array();
			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$list[] = array(
						'id'    => (int) $term->term_id,
						'name'  => $term->name,
						'slug'  => $term->slug,
						'count' => (int) $term->count,
					);
				}
			}
			$out[ $tax ] = $list;
		}

		return new \WP_REST_Response( $out, 200 );
	}

	/** Periode pengajuan (open/closed) — dipakai wizard SPA & enforcement submit. */
	public function handle_period(): \WP_REST_Response {
		return new \WP_REST_Response( $this->period_status(), 200 );
	}

	/**
	 * Hitung status periode dari option `lp2m_reward_period_start` / `_end`
	 * (format Y-m-d, diatur di LP2M → Settings → Reward).
	 * Kosong = tidak dibatasi (selalu terbuka).
	 */
	private function period_status(): array {
		$start = trim( (string) get_option( 'lp2m_reward_period_start', '' ) );
		$end   = trim( (string) get_option( 'lp2m_reward_period_end', '' ) );
		$today = wp_date( 'Y-m-d' );

		$open    = true;
		$message = 'Masa pengajuan sedang dibuka.';

		if ( '' !== $start && $today < $start ) {
			$open    = false;
			$message = 'Masa pengajuan belum dibuka. Pengajuan dibuka mulai tanggal ' . $start . '.';
		} elseif ( '' !== $end && $today > $end ) {
			$open    = false;
			$message = 'Masa pengajuan telah berakhir pada tanggal ' . $end . '.';
		}

		return array(
			'success' => true,
			'open'    => $open,
			'start'   => $start,
			'end'     => $end,
			'message' => $message,
		);
	}

	public function handle_submit( \WP_REST_Request $request ): \WP_REST_Response {
		// Periode pengajuan — tolak bila di luar jendela yang diatur admin.
		$period = $this->period_status();
		if ( ! $period['open'] ) {
			return new \WP_REST_Response(
				array( 'success' => false, 'closed' => true, 'message' => $period['message'] ),
				403
			);
		}

		$params = $this->sanitize_input( $request->get_params() );
		$errors = $this->validate( $params );

		if ( ! empty( $errors ) ) {
			return new \WP_REST_Response( array( 'success' => false, 'errors' => $errors ), 400 );
		}

		// ── File upload (multipart/form-data) ──
		$files   = $request->get_file_params();
		$uploads = array(); // key => array of attachment IDs (atau WP_Error).
		foreach ( $this->file_fields() as $key => $cfg ) {
			$entries = $this->normalize_files( $files, $key );
			if ( empty( $entries ) ) {
				continue;
			}
			if ( count( $entries ) > $cfg['max'] ) {
				$errors[ $key ] = $cfg['label'] . ' maksimal ' . $cfg['max'] . ' file.';
				continue;
			}
			$ids = array();
			foreach ( $entries as $entry ) {
				if ( UPLOAD_ERR_NO_FILE === (int) ( $entry['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
					continue;
				}
				$up = $this->handle_file_upload( $entry, $cfg, $params['nama_dosen'] ?? '' );
				if ( is_wp_error( $up ) ) {
					$errors[ $key ] = $up->get_error_message();
					continue 2; // berhenti memproses field ini.
				}
				$ids[] = $up;
			}
			if ( ! empty( $ids ) ) {
				$uploads[ $key ] = $ids;
			}
		}

		// File wajib (kecuali bukti bayar & seluruh bagian seminar).
		$required_files = array( 'file_artikel', 'file_proposal_artikel', 'cover_depan_buku', 'halaman_isbn', 'sertifikat_haki' );
		foreach ( $required_files as $req ) {
			if ( empty( $uploads[ $req ] ) ) {
				$errors[ $req ] = $this->file_fields()[ $req ]['label'] . ' wajib diunggah.';
			}
		}

		if ( ! empty( $errors ) ) {
			// Bersihkan file yang sudah terlanjur terunggah pada request yang gagal.
			foreach ( $uploads as $ids ) {
				foreach ( $ids as $id ) {
					wp_delete_attachment( $id, true );
				}
			}
			return new \WP_REST_Response( array( 'success' => false, 'errors' => $errors ), 400 );
		}

		$post_id = $this->save_submission( $params, $uploads );
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			$err = is_wp_error( $post_id ) ? $post_id->get_error_message() : 'Gagal menyimpan data.';
			return new \WP_REST_Response( array( 'success' => false, 'message' => $err ), 500 );
		}

		$this->send_admin_email( $params, $post_id );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => 'Pengajuan Reward Jurnal berhasil dikirim.',
				'post_id' => $post_id,
			),
			201
		);
	}

	/* ── Sanitasi & validasi ── */

	private function sanitize_input( array $params ): array {
		$text    = array( 'nama_dosen', 'no_whatsapp', 'judul_artikel', 'judul_buku', 'judul_haki', 'nomor_dokumen_haki', 'penyelenggara_seminar', 'judul_paper_seminar' );
		$urls    = array( 'url_artikel', 'url_buku' );
		$term_ids = array( 'kategori_jurnal_id', 'kategori_buku_id', 'kategori_haki_id', 'kategori_seminar_id' );

		$out = array();
		foreach ( $text as $k ) {
			$out[ $k ] = isset( $params[ $k ] ) && is_string( $params[ $k ] ) ? sanitize_text_field( $params[ $k ] ) : '';
		}
		foreach ( $urls as $k ) {
			$out[ $k ] = isset( $params[ $k ] ) && is_string( $params[ $k ] ) ? esc_url_raw( $params[ $k ] ) : '';
		}
		foreach ( $term_ids as $k ) {
			$out[ $k ] = isset( $params[ $k ] ) ? absint( $params[ $k ] ) : 0;
		}
		$jenis = isset( $params['jenis_reward'] ) ? sanitize_text_field( $params['jenis_reward'] ) : '';
		$out['jenis_reward'] = in_array( $jenis, self::JENIS_REWARD, true ) ? $jenis : '';

		return $out;
	}

	private function validate( array $params ): array {
		$errors = array();

		// Data diri (wajib).
		if ( '' === $params['nama_dosen'] ) {
			$errors['nama_dosen'] = 'Nama dosen wajib diisi.';
		}
		if ( '' === $params['no_whatsapp'] ) {
			$errors['no_whatsapp'] = 'Nomor HP / WhatsApp wajib diisi.';
		}

		// Artikel / Jurnal (wajib).
		if ( '' === $params['judul_artikel'] ) { $errors['judul_artikel'] = 'Judul artikel wajib diisi.'; }
		if ( '' === $params['url_artikel'] ) { $errors['url_artikel'] = 'URL artikel wajib diisi.'; }
		if ( empty( $params['kategori_jurnal_id'] ) ) { $errors['kategori_jurnal_id'] = 'Kategori jurnal wajib dipilih.'; }

		// Buku (wajib).
		if ( '' === $params['judul_buku'] ) { $errors['judul_buku'] = 'Judul buku wajib diisi.'; }
		if ( '' === $params['url_buku'] ) { $errors['url_buku'] = 'URL buku wajib diisi.'; }
		if ( empty( $params['kategori_buku_id'] ) ) { $errors['kategori_buku_id'] = 'Kategori buku wajib dipilih.'; }

		// HAKI (wajib).
		if ( '' === $params['judul_haki'] ) { $errors['judul_haki'] = 'Judul HAKI wajib diisi.'; }
		if ( '' === $params['nomor_dokumen_haki'] ) { $errors['nomor_dokumen_haki'] = 'Nomor dokumen/sertifikat HAKI wajib diisi.'; }
		if ( empty( $params['kategori_haki_id'] ) ) { $errors['kategori_haki_id'] = 'Kategori HAKI wajib dipilih.'; }

		// Seminar = opsional (tidak ada field wajib).

		return $errors;
	}

	/* ── File helpers ── */

	private function file_fields(): array {
		return array(
			'file_artikel'          => array( 'max' => 5, 'types' => array( 'pdf' ), 'label' => 'File Artikel' ),
			'file_proposal_artikel' => array( 'max' => 5, 'types' => array( 'pdf' ), 'label' => 'File Proposal Penelitian dari Artikel' ),
			'bukti_bayar'           => array( 'max' => 1, 'types' => array( 'pdf', 'image' ), 'label' => 'Bukti Bayar' ),
			'cover_depan_buku'      => array( 'max' => 1, 'types' => array( 'pdf' ), 'label' => 'Cover Depan Buku' ),
			'halaman_isbn'          => array( 'max' => 1, 'types' => array( 'pdf', 'image' ), 'label' => 'Halaman ISBN' ),
			'bukti_bayar_buku'      => array( 'max' => 1, 'types' => array( 'pdf', 'image' ), 'label' => 'Bukti Bayar Biaya Penerbitan Buku' ),
			'sertifikat_haki'       => array( 'max' => 1, 'types' => array( 'pdf', 'image' ), 'label' => 'Sertifikat HAKI' ),
			'bukti_bayar_haki'      => array( 'max' => 1, 'types' => array( 'pdf', 'image' ), 'label' => 'Bukti Pembayaran Biaya Penerbitan HAKI' ),
			'sertifikat_pemateri'   => array( 'max' => 1, 'types' => array( 'pdf', 'image' ), 'label' => 'Sertifikat Pemateri Seminar' ),
			'dokumentasi_seminar'   => array( 'max' => 1, 'types' => array( 'pdf', 'image' ), 'label' => 'Dokumentasi Seminar' ),
		);
	}

	/** Normalisasi entry $_FILES (single atau multi `[]`) menjadi array of file. */
	private function normalize_files( array $file_params, string $key ): array {
		if ( empty( $file_params[ $key ] ) ) {
			return array();
		}
		$entry = $file_params[ $key ];

		if ( isset( $entry['name'] ) && is_string( $entry['name'] ) ) {
			$err = (int) ( $entry['error'] ?? UPLOAD_ERR_NO_FILE );
			if ( UPLOAD_ERR_NO_FILE === $err ) {
				return array();
			}
			return array( $entry );
		}

		if ( isset( $entry['name'] ) && is_array( $entry['name'] ) ) {
			$out   = array();
			$count = count( $entry['name'] );
			for ( $i = 0; $i < $count; $i++ ) {
				$out[] = array(
					'name'     => $entry['name'][ $i ] ?? '',
					'type'     => $entry['type'][ $i ] ?? '',
					'tmp_name' => $entry['tmp_name'][ $i ] ?? '',
					'error'    => (int) ( $entry['error'][ $i ] ?? UPLOAD_ERR_NO_FILE ),
					'size'     => (int) ( $entry['size'][ $i ] ?? 0 ),
				);
			}
			return $out;
		}

		return array();
	}

	/** Validasi + simpan satu file ke media library. Return attachment ID atau WP_Error. */
	private function handle_file_upload( array $file, array $cfg, string $nama ): int|\WP_Error {
		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$error = (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE );
		if ( UPLOAD_ERR_OK !== $error ) {
			if ( UPLOAD_ERR_INI_SIZE === $error || UPLOAD_ERR_FORM_SIZE === $error ) {
				return new \WP_Error( 'upload_too_large', $cfg['label'] . ' melebihi batas server.' );
			}
			return new \WP_Error( 'upload_failed', 'Upload ' . $cfg['label'] . ' gagal (kode ' . $error . ').' );
		}

		$tmp = (string) ( $file['tmp_name'] ?? '' );
		if ( '' === $tmp || ! is_file( $tmp ) || ! is_readable( $tmp ) || ! is_uploaded_file( $tmp ) ) {
			return new \WP_Error( 'upload_invalid', 'File ' . $cfg['label'] . ' tidak valid.' );
		}

		$size = (int) ( $file['size'] ?? 0 );
		if ( $size <= 0 ) {
			return new \WP_Error( 'upload_empty', 'File ' . $cfg['label'] . ' kosong.' );
		}
		if ( $size > self::MAX_UPLOAD_BYTES ) {
			return new \WP_Error( 'upload_too_large', $cfg['label'] . ' maksimal 10 MB.' );
		}

		// Deteksi isi file: PDF (magic bytes) atau gambar (getimagesize).
		$head  = (string) file_get_contents( $tmp, false, null, 0, 5 );
		$is_pdf = ( '%PDF-' === $head );
		$img    = @getimagesize( $tmp );
		$is_img = false !== $img && in_array( (string) ( $img['mime'] ?? '' ), self::IMAGE_MIMES, true );

		// Lapisan kedua (defense-in-depth): deteksi MIME via finfo & tolak tegas
		// tipe berbahaya (PHP/HTML/JS/SVG/shell/executable) — cegah injeksi dari
		// dalam bila server salah menuliskan berkas kiriman.
		if ( function_exists( 'finfo_open' ) ) {
			$finfo     = finfo_open( FILEINFO_MIME_TYPE );
			$mime_type = $finfo ? (string) finfo_file( $finfo, $tmp ) : '';
			if ( $finfo ) {
				finfo_close( $finfo );
			}
			$dangerous = array(
				'application/x-php', 'application/x-httpd-php', 'text/x-php',
				'text/html', 'application/xhtml+xml',
				'application/javascript', 'text/javascript', 'text/x-javascript',
				'image/svg+xml',
				'application/x-sh', 'text/x-shellscript', 'application/x-executable',
				'application/x-msdownload', 'application/x-dosexec',
			);
			if ( '' !== $mime_type && in_array( $mime_type, $dangerous, true ) ) {
				return new \WP_Error( 'upload_dangerous', 'File ' . $cfg['label'] . ' tidak diizinkan (tipe berbahaya).' );
			}
			if ( $is_pdf && '' !== $mime_type && 'application/pdf' !== $mime_type ) {
				return new \WP_Error( 'upload_bad_type', $cfg['label'] . ' bukan PDF asli.' );
			}
			if ( $is_img && '' !== $mime_type && ! in_array( $mime_type, self::IMAGE_MIMES, true ) ) {
				return new \WP_Error( 'upload_bad_type', $cfg['label'] . ' bukan gambar asli.' );
			}
		}

		$allowed_pdf   = in_array( 'pdf', $cfg['types'], true );
		$allowed_image = in_array( 'image', $cfg['types'], true );

		if ( $is_pdf && ! $allowed_pdf ) {
			return new \WP_Error( 'upload_bad_type', $cfg['label'] . ' tidak boleh PDF.' );
		}
		if ( $is_img && ! $allowed_image ) {
			return new \WP_Error( 'upload_bad_type', $cfg['label'] . ' harus PDF.' );
		}
		if ( ! $is_pdf && ! $is_img ) {
			return new \WP_Error( 'upload_bad_type', $cfg['label'] . ' harus PDF' . ( $allowed_image ? ' atau gambar (JPG/PNG)' : '' ) . '.' );
		}

		// Tentukan ekstensi + MIME yang diizinkan untuk wp_handle_upload.
		$ext   = $is_pdf ? 'pdf' : ( ( 'image/png' === (string) ( $img['mime'] ?? '' ) ) ? 'png' : 'jpg' );
		$mimes = array();
		if ( $allowed_pdf ) { $mimes['pdf'] = 'application/pdf'; }
		if ( $allowed_image ) {
			$mimes['jpg|jpeg'] = 'image/jpeg';
			$mimes['png']      = 'image/png';
		}

		$slug      = sanitize_title( $nama );
		$id_suffix = wp_rand( 1000, 9999 );
		$file['name'] = sanitize_file_name( sprintf( 'reward-%s-%s-%d.%s', $slug, $cfg['label'] ? sanitize_title( $cfg['label'] ) : 'file', $id_suffix, $ext ) );

		$moved = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
				'test_type' => true,
				'mimes'     => $mimes,
			)
		);
		if ( is_wp_error( $moved ) ) {
			return $moved;
		}

		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => (string) ( $moved['type'] ?? '' ),
				'post_title'     => $cfg['label'] . ' — ' . $nama,
				'post_content'   => '',
				'post_status'    => 'inherit',
			),
			$moved['file'],
			0
		);
		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			wp_delete_file( (string) ( $moved['file'] ?? '' ) );
			return new \WP_Error( 'upload_failed', 'Gagal menyimpan file ' . $cfg['label'] . '.' );
		}

		$meta = wp_generate_attachment_metadata( $attachment_id, (string) $moved['file'] );
		wp_update_attachment_metadata( $attachment_id, $meta );

		return (int) $attachment_id;
	}

	/* ── Simpan submission ── */

	/**
	 * Tentukan jenis_reward dari bagian yang terisi (frontend tidak lagi mengirim
	 * selector jenis). Prioritas: artikel → buku → HAKI → seminar.
	 */
	private function derive_jenis( array $params ): string {
		if ( '' !== $params['judul_artikel'] ) {
			return 'artikel';
		}
		if ( '' !== $params['judul_buku'] ) {
			return 'buku';
		}
		if ( '' !== $params['judul_haki'] ) {
			return 'haki';
		}
		if ( '' !== $params['penyelenggara_seminar'] || '' !== $params['judul_paper_seminar'] ) {
			return 'seminar';
		}
		return '';
	}

	private function save_submission( array $params, array $uploads ): int|\WP_Error {
		$jenis = $this->derive_jenis( $params );

		$title = trim( 'Pengajuan Reward — ' . $params['nama_dosen'] );

		$post_id = wp_insert_post(
			array(
				'post_type'   => 'reward_jurnal',
				'post_status' => 'publish',
				'post_title'  => '' !== $title ? $title : 'Pengajuan Reward Jurnal',
			)
		);
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return is_wp_error( $post_id ) ? $post_id : new \WP_Error( 'save_failed', 'Gagal membuat data.' );
		}

		// Teks & URL.
		foreach ( array( 'nama_dosen', 'no_whatsapp', 'judul_artikel', 'url_artikel', 'judul_buku', 'url_buku', 'judul_haki', 'nomor_dokumen_haki', 'penyelenggara_seminar', 'judul_paper_seminar' ) as $k ) {
			if ( '' !== $params[ $k ] ) {
				update_post_meta( $post_id, $k, $params[ $k ] );
			}
		}
		update_post_meta( $post_id, 'jenis_reward', $jenis );
		update_post_meta( $post_id, '_status', 'submitted' );
		update_post_meta( $post_id, '_reg_no', 'RW' . current_time( 'YmdHis' ) );

		// Taxonomy term (by ID) — kategori dinamis.
		$tax_map = array(
			'kategori_jurnal'   => $params['kategori_jurnal_id'] ?? 0,
			'kategori_buku'     => $params['kategori_buku_id'] ?? 0,
			'kategori_haki'     => $params['kategori_haki_id'] ?? 0,
			'kategori_seminar'  => $params['kategori_seminar_id'] ?? 0,
		);
		foreach ( $tax_map as $tax => $term_id ) {
			if ( $term_id > 0 ) {
				wp_set_object_terms( $post_id, array( (int) $term_id ), $tax );
			}
		}

		// File → meta (key + _ids) via helper yang sama dengan REST/metabox.
		foreach ( $uploads as $key => $ids ) {
			if ( function_exists( 'itsi_reward_write_file_meta' ) ) {
				itsi_reward_write_file_meta( $post_id, $key, $ids );
			} else {
				update_post_meta( $post_id, $key, (int) $ids[0] );
				update_post_meta( $post_id, $key . '_ids', array_map( 'intval', $ids ) );
			}
		}

		return (int) $post_id;
	}

	private function send_admin_email( array $params, int $post_id ): void {
		$admin_email = get_option( 'lp2m_site_admin_email' ) ?: get_option( 'admin_email' );
		if ( ! $admin_email ) {
			return;
		}

		$jenis = $this->derive_jenis( $params );
		$lines = array(
			'Pengajuan Reward Jurnal baru telah masuk.',
			'',
			'Nama Dosen: ' . $params['nama_dosen'],
			'No. WhatsApp: ' . $params['no_whatsapp'],
			'Jenis Karya: ' . ( '' !== $jenis ? $jenis : '-' ),
		);
		if ( '' !== $params['judul_artikel'] ) {
			$lines[] = 'Judul Artikel: ' . $params['judul_artikel'];
		}
		if ( '' !== $params['judul_buku'] ) {
			$lines[] = 'Judul Buku: ' . $params['judul_buku'];
		}
		if ( '' !== $params['judul_haki'] ) {
			$lines[] = 'Judul HAKI: ' . $params['judul_haki'];
		}
		if ( '' !== $params['judul_paper_seminar'] ) {
			$lines[] = 'Judul Paper: ' . $params['judul_paper_seminar'];
		}
		$lines[] = '';
		$lines[] = 'Lihat di admin: ' . admin_url( 'post.php?post=' . $post_id . '&action=edit' );

		wp_mail( $admin_email, '[LP2M] Pengajuan Reward Jurnal baru', implode( "\n", $lines ) );
	}
}

( new ITSI_LP2M_Reward_Receiver() )->init();