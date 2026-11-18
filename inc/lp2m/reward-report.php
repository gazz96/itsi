<?php
/**
 * LP2M Reward Report — laporan pengajuan Reward Jurnal untuk dashboard SPA.
 *
 * Endpoints (dua-duanya butuh admin login via Basic Auth):
 *   GET /lp2m/v1/reward-jurnal/report      → JSON { success, total, ringkasan, data[] }
 *   GET /lp2m/v1/reward-jurnal/report/pdf  → binary PDF (FPDF, landscape) untuk di-download
 *
 * Filter (query params, opsional):
 *   jenis   = artikel | buku | haki | seminar
 *   status  = nilai meta _status (default: semua)
 *   dari    = Y-m-d (batas awal tanggal pengajuan)
 *   sampai  = Y-m-d (batas akhir tanggal pengajuan)
 *   search  = cari di nama dosen / judul karya / nomor registrasi
 *
 * @package itsi
 */

defined( 'ABSPATH' ) || exit;

/* ════════════════════════════════════════════════════════════
 *  ROUTES
 * ════════════════════════════════════════════════════════════ */

add_action( 'rest_api_init', 'itsi_reward_report_register_routes' );

function itsi_reward_report_register_routes() {

	register_rest_route(
		'lp2m/v1',
		'/reward-jurnal/report',
		array(
			'methods'             => 'GET',
			'callback'            => 'itsi_reward_report_handle',
			'permission_callback' => 'itsi_reward_report_check_permission',
		)
	);

	register_rest_route(
		'lp2m/v1',
		'/reward-jurnal/report/pdf',
		array(
			'methods'             => 'GET',
			'callback'            => 'itsi_reward_report_handle_pdf',
			'permission_callback' => 'itsi_reward_report_check_permission',
		)
	);
}

/**
 * Hanya admin (kelola opsi) boleh melihat laporan semua pengaju.
 */
function itsi_reward_report_check_permission() {
	if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
		return new \WP_Error(
			'rest_forbidden',
			'Anda tidak punya akses ke laporan reward jurnal.',
			array( 'status' => 403 )
		);
	}
	return true;
}

/* ════════════════════════════════════════════════════════════
 *  HELPERS — ambil & bentuk data
 * ════════════════════════════════════════════════════════════ */

/**
 * Label tampilan jenis karya.
 */
function itsi_reward_report_jenis_label( string $jenis ): string {
	$map = array(
		'artikel' => 'Artikel/Jurnal',
		'buku'    => 'Buku',
		'haki'    => 'HAKI',
		'seminar' => 'Seminar',
	);
	return $map[ $jenis ] ?? ( '' !== $jenis ? ucfirst( $jenis ) : '-' );
}

/**
 * Judul karya sesuai jenis (artikel → judul_artikel, dst).
 */
function itsi_reward_report_judul( int $post_id, string $jenis ): string {
	switch ( $jenis ) {
		case 'artikel':
			return (string) get_post_meta( $post_id, 'judul_artikel', true );
		case 'buku':
			return (string) get_post_meta( $post_id, 'judul_buku', true );
		case 'haki':
			$judul = (string) get_post_meta( $post_id, 'judul_haki', true );
			$no    = (string) get_post_meta( $post_id, 'nomor_dokumen_haki', true );
			if ( '' !== $no ) {
				$judul = trim( $judul . ' (No. ' . $no . ')' );
			}
			return $judul;
		case 'seminar':
			$judul  = (string) get_post_meta( $post_id, 'judul_paper_seminar', true );
			$penyel = (string) get_post_meta( $post_id, 'penyelenggara_seminar', true );
			if ( '' === $judul ) {
				$judul = $penyel;
			} elseif ( '' !== $penyel ) {
				$judul .= ' (' . $penyel . ')';
			}
			return $judul;
	}
	return '';
}

/**
 * URL karya (artikel → url_artikel, buku → url_buku, lainnya kosong).
 */
function itsi_reward_report_url( int $post_id, string $jenis ): string {
	if ( 'artikel' === $jenis ) {
		return (string) get_post_meta( $post_id, 'url_artikel', true );
	}
	if ( 'buku' === $jenis ) {
		return (string) get_post_meta( $post_id, 'url_buku', true );
	}
	return '';
}

/**
 * Nama kategori (term) sesuai jenis karya.
 */
function itsi_reward_report_kategori( int $post_id, string $jenis ): string {
	$tax_map = array(
		'artikel' => 'kategori_jurnal',
		'buku'    => 'kategori_buku',
		'haki'    => 'kategori_haki',
		'seminar' => 'kategori_seminar',
	);
	$tax     = $tax_map[ $jenis ] ?? '';
	if ( '' === $tax ) {
		return '';
	}
	$terms = get_the_terms( $post_id, $tax );
	if ( ! is_array( $terms ) || empty( $terms ) ) {
		return '';
	}
	return implode( ', ', wp_list_pluck( $terms, 'name' ) );
}

/**
 * Normalisasi teks UTF-8 → cp1252 agar FPDF core font tidak menghasilkan
 * karakter kacau (copy-paste Word sering membawa curly quote / em-dash).
 */
function itsi_reward_report_pdf_text( string $s ): string {
	$s = html_entity_decode( $s, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$s = wp_strip_all_tags( $s );
	$converted = @iconv( 'UTF-8', 'windows-1252//TRANSLIT//IGNORE', $s );
	if ( false !== $converted && '' !== $converted ) {
		return $converted;
	}
	// Fallback: buang semua non-ASCII.
	return preg_replace( '/[^\x20-\x7E]/', '', $s ) ?? '';
}

/**
 * Bangun daftar baris laporan sesuai filter.
 *
 * @return array{ rows: array[], ringkasan: array, total: int }
 */
function itsi_reward_report_build( \WP_REST_Request $request ): array {
	$jenis  = sanitize_key( (string) $request->get_param( 'jenis' ) );
	$status = sanitize_key( (string) $request->get_param( 'status' ) );
	$dari   = sanitize_text_field( (string) $request->get_param( 'dari' ) );
	$sampai = sanitize_text_field( (string) $request->get_param( 'sampai' ) );
	$search = sanitize_text_field( (string) $request->get_param( 'search' ) );

	$args = array(
		'post_type'      => 'reward_jurnal',
		'post_status'    => 'publish',
		'posts_per_page' => 1000,
		'orderby'        => 'date',
		'order'          => 'DESC',
	);

	$date_query = array();
	$dari_ok    = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $dari );
	$sampai_ok  = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $sampai );
	if ( $dari_ok ) {
		$date_query['after'] = $dari . ' 00:00:00';
	}
	if ( $sampai_ok ) {
		$date_query['before'] = $sampai . ' 23:59:59';
	}
	if ( ! empty( $date_query ) ) {
		$args['date_query'] = array( $date_query );
	}

	$posts = get_posts( $args );

	$rows     = array();
	$ringkasan = array( 'artikel' => 0, 'buku' => 0, 'haki' => 0, 'seminar' => 0 );

	foreach ( $posts as $post ) {
		$pid       = (int) $post->ID;
		$row_jenis = (string) get_post_meta( $pid, 'jenis_reward', true );
		$row_status = (string) get_post_meta( $pid, '_status', true );

		if ( '' !== $jenis && $jenis !== $row_jenis ) {
			continue;
		}
		if ( '' !== $status && $status !== $row_status ) {
			continue;
		}

		$judul = itsi_reward_report_judul( $pid, $row_jenis );
		$nama  = (string) get_post_meta( $pid, 'nama_dosen', true );
		$reg   = (string) get_post_meta( $pid, '_reg_no', true );

		// Filter pencarian: nama / judul / nomor registrasi.
		if ( '' !== $search ) {
			$haystack = mb_strtolower( $nama . ' ' . $judul . ' ' . $reg, 'UTF-8' );
			if ( false === mb_strpos( $haystack, mb_strtolower( $search, 'UTF-8' ) ) ) {
				continue;
			}
		}

		if ( isset( $ringkasan[ $row_jenis ] ) ) {
			++$ringkasan[ $row_jenis ];
		}

		$rows[] = array(
			'id'         => $pid,
			'reg_no'     => $reg,
			'tanggal'    => function_exists( 'wp_date' )
				? wp_date( 'Y-m-d H:i', strtotime( (string) $post->post_date ) )
				: (string) $post->post_date,
			'nama_dosen' => $nama,
			'whatsapp'   => (string) get_post_meta( $pid, 'no_whatsapp', true ),
			'jenis'      => $row_jenis,
			'jenis_label'=> itsi_reward_report_jenis_label( $row_jenis ),
			'kategori'   => itsi_reward_report_kategori( $pid, $row_jenis ),
			'judul'      => $judul,
			'url'        => itsi_reward_report_url( $pid, $row_jenis ),
			'status'     => '' !== $row_status ? $row_status : 'submitted',
		);
	}

	return array(
		'rows'      => $rows,
		'ringkasan' => $ringkasan,
		'total'     => count( $rows ),
	);
}

/* ════════════════════════════════════════════════════════════
 *  JSON ENDPOINT
 * ════════════════════════════════════════════════════════════ */

function itsi_reward_report_handle( \WP_REST_Request $request ) {
	$built = itsi_reward_report_build( $request );

	return new \WP_REST_Response(
		array(
			'success'   => true,
			'total'     => $built['total'],
			'ringkasan' => $built['ringkasan'],
			'data'      => $built['rows'],
		),
		200
	);
}

/* ════════════════════════════════════════════════════════════
 *  PDF ENDPOINT
 * ════════════════════════════════════════════════════════════ */

function itsi_reward_report_handle_pdf( \WP_REST_Request $request ) {
	$built = itsi_reward_report_build( $request );

	require_once get_template_directory() . '/inc/lp2m/pdf/fpdf/fpdf.php';

	$pdf = new FPDF( 'L', 'mm', 'A4' ); // landscape.
	$pdf->SetMargins( 10, 12, 10 );
	$pdf->SetAutoPageBreak( true, 14 );
	$pdf->AddPage();

	$usuable = 277; // 297 - 2 x 10.

	/* ── Kop ── */
	$pdf->SetFont( 'helvetica', 'B', 14 );
	$pdf->SetTextColor( 15, 23, 42 );
	$pdf->Cell( 0, 7, 'LP2M ITSI', 0, 1, 'C' );
	$pdf->SetFont( 'helvetica', '', 9 );
	$pdf->SetTextColor( 100, 116, 139 );
	$pdf->Cell( 0, 5, 'Lembaga Penelitian dan Pengabdian kepada Masyarakat - Institut Teknologi dan Sains Indonesia', 0, 1, 'C' );
	$pdf->SetTextColor( 37, 99, 235 );
	$pdf->SetFont( 'helvetica', 'B', 11 );
	$pdf->Cell( 0, 6, 'Laporan Pengajuan Reward Publikasi Dosen', 0, 1, 'C' );
	$pdf->Ln( 2 );

	$dari   = sanitize_text_field( (string) $request->get_param( 'dari' ) );
	$sampai = sanitize_text_field( (string) $request->get_param( 'sampai' ) );
	$jenis  = sanitize_key( (string) $request->get_param( 'jenis' ) );

	$pdf->SetFont( 'helvetica', '', 8.5 );
	$pdf->SetTextColor( 55, 65, 81 );
	$periode = ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $dari ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $sampai ) )
		? 'Periode: ' . $dari . ' s.d. ' . $sampai
		: 'Periode: Semua tanggal';
	if ( '' !== $jenis ) {
		$periode .= '  |  Jenis: ' . itsi_reward_report_jenis_label( $jenis );
	}
	$pdf->Cell( 0, 5, $periode, 0, 1, 'C' );

	$timestamp = function_exists( 'wp_date' ) ? wp_date( 'd F Y H:i' ) : date( 'd F Y H:i' );
	$pdf->Cell( 0, 5, 'Dicetak: ' . $timestamp . '  |  Total: ' . $built['total'] . ' pengajuan', 0, 1, 'C' );
	$pdf->Ln( 3 );

	/* ── Tabel ── */
	$cols = array(
		'no'      => array( 'label' => 'No',         'w' => 9 ),
		'tanggal' => array( 'label' => 'Tanggal',    'w' => 26 ),
		'reg'     => array( 'label' => 'No. Reg',    'w' => 26 ),
		'nama'    => array( 'label' => 'Nama Dosen', 'w' => 52 ),
		'wa'      => array( 'label' => 'WhatsApp',   'w' => 27 ),
		'jenis'   => array( 'label' => 'Jenis',      'w' => 22 ),
		'kat'     => array( 'label' => 'Kategori',   'w' => 40 ),
		'judul'   => array( 'label' => 'Judul Karya','w' => 60 ),
		'status'  => array( 'label' => 'Status',     'w' => 15 ),
	);

	$pdf->SetFillColor( 37, 99, 235 );
	$pdf->SetTextColor( 255, 255, 255 );
	$pdf->SetFont( 'helvetica', 'B', 8 );
	$pdf->SetDrawColor( 148, 163, 184 );
	foreach ( $cols as $col ) {
		$pdf->Cell( $col['w'], 7, $col['label'], 1, 0, 'C', true );
	}
	$pdf->Ln( 7 );

	$pdf->SetTextColor( 17, 24, 39 );
	$pdf->SetFont( 'helvetica', '', 7.5 );
	$fill  = false;
	$no    = 0;
	$judul_rows = array( 'judul' ); // kolom yang boleh wrap.

	foreach ( $built['rows'] as $row ) {
		++$no;

		// Hitung tinggi baris: kolom judul boleh wrap (MultiCell), lainnya 1 baris.
		$judul_text = itsi_reward_report_pdf_text( (string) $row['judul'] );
		$judul_w    = $cols['judul']['w'] - 2;
		$nlines     = max( 1, (int) ceil( $pdf->GetStringWidth( $judul_text ) / max( $judul_w, 1 ) ) );
		$row_h      = max( 6, $nlines * 4.2 + 1.4 );

		$pdf->SetFillColor( $fill ? 241 : 255, $fill ? 245 : 255, $fill ? 249 : 255 );

		$x = $pdf->GetX();
		$y = $pdf->GetY();

		$cells = array(
			'no'      => (string) $no,
			'tanggal' => (string) $row['tanggal'],
			'reg'     => (string) $row['reg_no'],
			'nama'    => (string) $row['nama_dosen'],
			'wa'      => (string) $row['whatsapp'],
			'jenis'   => (string) $row['jenis_label'],
			'kat'     => (string) $row['kategori'],
			'judul'   => '', // dirender manual pakai MultiCell.
			'status'  => (string) $row['status'],
		);

		foreach ( $cols as $key => $col ) {
			if ( in_array( $key, $judul_rows, true ) ) {
				$pdf->SetXY( $x, $y );
				$pdf->MultiCell( $col['w'], $row_h, $judul_text, 1, 'L', $fill );
				continue;
			}
			$pdf->SetXY( $x, $y );
			$pdf->Cell( $col['w'], $row_h, itsi_reward_report_pdf_text( $cells[ $key ] ), 1, 0, 'L', $fill );
			$x += $col['w'];
		}

		$pdf->SetXY( 10, $pdf->GetY() ); // kembali ke margin kiri.
		$fill = ! $fill;
	}

	if ( 0 === $built['total'] ) {
		$pdf->SetFont( 'helvetica', 'I', 9 );
		$pdf->SetTextColor( 100, 116, 139 );
		$pdf->Cell( 0, 8, 'Tidak ada data untuk filter yang dipilih.', 1, 1, 'C' );
	}

	/* ── Ringkasan ── */
	$pdf->Ln( 4 );
	$pdf->SetFont( 'helvetica', 'B', 8.5 );
	$pdf->SetTextColor( 15, 23, 42 );
	$pdf->Cell( 0, 6, 'Ringkasan', 0, 1 );
	$pdf->SetFont( 'helvetica', '', 8.5 );
	$summary_lines = array();
	foreach ( $built['ringkasan'] as $j => $count ) {
		$summary_lines[] = itsi_reward_report_jenis_label( $j ) . ': ' . $count;
	}
	$summary_lines[] = 'Total: ' . $built['total'];
	$pdf->MultiCell( 0, 5, implode( '   |   ', $summary_lines ), 0, 'L' );

	$content = $pdf->Output( 'S' );

	if ( '' === $content ) {
		return new \WP_Error( 'pdf_failed', 'Gagal membuat PDF.', array( 'status' => 500 ) );
	}

	$stamp    = function_exists( 'wp_date' ) ? wp_date( 'Ymd_His' ) : date( 'Ymd_His' );
	$filename = 'LP2M-Reward-Jurnal_' . $stamp . '.pdf';

	// Binary keluar langsung (REST akan membungkus string jadi JSON kalau di-return).
	header( 'Content-Type: application/pdf' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	header( 'Content-Length: ' . strlen( $content ) );
	header( 'X-Content-Type-Options: nosniff' );
	echo $content; // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
	exit;
}
