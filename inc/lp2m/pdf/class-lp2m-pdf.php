<?php
/**
 * LP2M PDF Generator — buat PDF detail pendaftaran hibah.
 *
 * Dipakai sebagai lampiran email notifikasi ke admin & pendaftar.
 * Menggunakan FPDF (lisensi MIT — Olivier Plathey).
 *
 * @package itsi
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'ITSI_LP2M_PDF' ) ) {

	class ITSI_LP2M_PDF {

		/**
		 * Hasilkan PDF detail pendaftaran → string konten PDF.
		 *
		 * @param array  $params     Data pendaftaran (format sama dengan save_submission).
		 * @param string $reg_no     Nomor registrasi.
		 * @param string $event_name Nama event hibah.
		 * @return string Konten biner PDF.
		 */
		public static function generate( array $params, string $reg_no, string $event_name = '' ): string {
			require_once __DIR__ . '/fpdf/fpdf.php';

			$pdf = new FPDF();
			$pdf->SetMargins( 14, 14, 14 );
			$pdf->AddPage();

			// ── Header: kop surat ──
			$pdf->SetFont( 'helvetica', 'B', 15 );
			$pdf->SetTextColor( 15, 23, 42 );
			$pdf->Cell( 0, 8, 'LP2M ITSI', 0, 1, 'C' );
			$pdf->SetFont( 'helvetica', '', 9 );
			$pdf->SetTextColor( 100, 116, 139 );
			$pdf->Cell( 0, 5, 'Lembaga Penelitian dan Pengabdian kepada Masyarakat', 0, 1, 'C' );
			$pdf->Cell( 0, 5, 'Institut Teknologi dan Sains Indonesia', 0, 1, 'C' );
			$pdf->SetTextColor( 37, 99, 235 );
			$pdf->Cell( 0, 5, 'Kartu Registrasi Pendaftaran Hibah', 0, 1, 'C' );
			$pdf->Ln( 2 );

			// Garis pemisah.
			$pdf->SetDrawColor( 37, 99, 235 );
			$pdf->SetLineWidth( 0.6 );
			$pdf->Line( 14, $pdf->GetY(), 196, $pdf->GetY() );
			$pdf->Ln( 4 );

			// ── Info registrasi ──
			$pdf->SetFont( 'helvetica', 'B', 12 );
			$pdf->SetTextColor( 15, 23, 42 );
			$pdf->Cell( 0, 8, 'Detail Pendaftaran', 0, 1 );

			$rows = self::build_rows( $params, $reg_no, $event_name );

			foreach ( $rows as $row ) {
				self::pdf_row( $pdf, $row[0], $row[1] );
			}

			$pdf->Ln( 4 );
			$pdf->SetFont( 'helvetica', '', 8 );
			$pdf->SetTextColor( 156, 163, 175 );
			$timestamp = function_exists( 'wp_date' ) ? wp_date( 'd F Y H:i' ) : date( 'd F Y H:i' );
			$pdf->MultiCell( 0, 4, 'Dokumen ini dibuat otomatis oleh sistem LP2M ITSI pada ' . $timestamp . '. Simpan sebagai bukti pendaftaran Anda.', 0, 'C' );

			return $pdf->Output( 'S' );
		}

		/**
		 * Buat file temp lampiran PDF (ekstensi .pdf agar MIME type benar) →
		 * path file, atau '' jika gagal. Panggil ITSI_LP2M_PDF::cleanup() setelah wp_mail.
		 *
		 * Nama file = REGNO-detail.pdf (reg_no unik, jadi aman tanpa suffix acak).
		 *
		 * @param array  $params     Data pendaftaran.
		 * @param string $reg_no     Nomor registrasi.
		 * @param string $event_name Nama event hibah.
		 * @return string
		 */
		public static function create_attachment( array $params, string $reg_no, string $event_name = '' ): string {
			$content = self::generate( $params, $reg_no, $event_name );
			if ( '' === $content ) {
				return '';
			}

			// Temp file dengan ekstensi .pdf (bukan .tmp) supaya PHPMailer
			// mendeteksi MIME type application/pdf.
			$dir = function_exists( 'get_temp_dir' ) ? get_temp_dir() : sys_get_temp_dir();
			if ( ! is_dir( $dir ) || ! is_writable( $dir ) ) {
				$dir = sys_get_temp_dir();
			}

			$name = preg_replace( '/[^A-Za-z0-9\-]/', '', $reg_no ) . '-detail.pdf';
			$path = $dir . $name;

			$handle = @fopen( $path, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
			if ( ! $handle ) {
				return '';
			}
			fwrite( $handle, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return $path;
		}

		/**
		 * Hapus file temp lampiran.
		 *
		 * @param string $path
		 */
		public static function cleanup( string $path ): void {
			if ( '' !== $path && file_exists( $path ) ) {
				@unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}

		/**
		 * Bangun baris label→nilai untuk tabel PDF.
		 */
		private static function build_rows( array $params, string $reg_no, string $event_name ): array {
			$rows = [
				[ 'No. Registrasi', $reg_no ],
				[ 'Nama Lengkap & Gelar', $params['nama'] ?? '' ],
				[ 'NIP / NIDN', $params['nip'] ?? '' ],
				[ 'Jenis Pengusul', $params['jenis'] ?? '' ],
				[ 'Program Studi / Unit Kerja', $params['prodi'] ?? '' ],
				[ 'Model Hibah', $params['skema'] ?? '' ],
				[ 'Jenis Hibah', $params['jenis_hibah'] ?? '' ],
				[ 'SDGs', $params['sdgs'] ?? '' ],
				[ 'Kelompok Keahlian', $params['kelompok_keahlian'] ?? '' ],
				[ 'Judul Usulan', $params['judul'] ?? '' ],
				[ 'Ringkasan Usulan', $params['ringkasan'] ?? '' ],
				[ 'Email', $params['email'] ?? '' ],
				[ 'WhatsApp', $params['hp'] ?? '' ],
			];

			// Anggota tim dinamis.
			$anggota = $params['anggota_list'] ?? [];
			if ( is_string( $anggota ) ) {
				$anggota = json_decode( $anggota, true ) ?: [];
			}
			if ( ! is_array( $anggota ) ) {
				$anggota = [];
			}

			$anggota_lines = [];
			foreach ( $anggota as $i => $m ) {
				$tipe = ( 'mahasiswa' === ( $m['tipe'] ?? '' ) ) ? 'Mahasiswa' : 'Dosen';
				if ( 'mahasiswa' === $tipe ) {
					$anggota_lines[] = sprintf(
						'%d. %s — %s (NIM: %s, Prodi: %s)',
						(int) $i + 1, $m['nama'] ?? '', $tipe, $m['nomor'] ?? '', $m['prodi'] ?? '—'
					);
				} else {
					$anggota_lines[] = sprintf(
						'%d. %s — %s (NIDN: %s)',
						(int) $i + 1, $m['nama'] ?? '', $tipe, $m['nomor'] ?? ''
					);
				}
			}
			if ( ! empty( $anggota_lines ) ) {
				$rows[] = [ 'Anggota Tim', implode( "\n", $anggota_lines ) ];
			}

			if ( '' !== $event_name ) {
				array_unshift( $rows, [ 'Event Hibah', $event_name ] );
			}

			return $rows;
		}

		/**
		 * Render satu baris label→nilai di PDF, dengan auto-wrap nilai panjang.
		 */
		private static function pdf_row( FPDF $pdf, string $label, string $value ): void {
			$pdf->SetFont( 'helvetica', 'B', 9 );
			$pdf->SetTextColor( 55, 65, 81 );
			$pdf->SetFillColor( 243, 244, 246 );
			$pdf->Cell( 48, 6, $label, 0, 0, 'L', true );
			$pdf->SetFont( 'helvetica', '', 9 );
			$pdf->SetTextColor( 17, 24, 39 );

			// Hitung tinggi baris: 1 baris = 6, setiap 100 karakter +1 baris (kira-kira).
			$lines  = explode( "\n", $value );
			$nlines = 0;
			foreach ( $lines as $line ) {
				$nlines += max( 1, (int) ceil( ( strlen( $line ) + 1 ) / 96 ) );
			}
			$height = max( 6, $nlines * 5.5 );

			$x = $pdf->GetX();
			$y = $pdf->GetY();
			$pdf->Cell( 0, $height, '', 0, 1 ); // dorong ke baris berikutnya
			$pdf->SetXY( $x + 48, $y );
			$pdf->MultiCell( 0, 5.5, $value, 0, 'L' );
		}
	}
}
