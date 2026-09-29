<?php
/**
 * AJAX handler for the public "Permohonan" (request) form.
 *
 * Registered for both logged-in and anonymous visitors through
 * wp_ajax_* / wp_ajax_nopriv_*, nonce-checked and sanitized before the request
 * is stored / mailed.
 *
 * @package itsi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AJAX handler: create Permohonan Informasi from public form.
 *
 * Guards: nonce → form toggle → rate limit → validation.
 * Email To/From/Subject diatur dari pengaturan "Informasi Publik" (theme_mods).
 */
function itsi_submit_permohonan() {
	check_ajax_referer( 'itsi-permohonan', 'nonce' );

	$settings = itsi_ip_get_form_settings();

	// 1) Form non-aktif → tolak dengan 403.
	if ( ! $settings['form_enabled'] ) {
		wp_send_json_error( array( 'message' => 'Formulir permohonan informasi sedang ditutup. Silakan hubungi PPID secara langsung.' ), 403 );
	}

	// 2) Rate limit per IP.
	$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
	$key   = 'itsi_ip_rate_' . md5( $ip );
	$count = (int) get_transient( $key );
	if ( $count >= $settings['rate_max'] ) {
		wp_send_json_error(
			array(
				'message' => sprintf(
					'Terlalu banyak permohonan dari perangkat ini. Silakan coba lagi dalam %d menit.',
					$settings['rate_window']
				),
			),
			429
		);
	}
	set_transient( $key, $count + 1, $settings['rate_window'] * MINUTE_IN_SECONDS );

	// 3) Sanitasi input.
	$nama      = isset( $_POST['nama'] ) ? sanitize_text_field( wp_unslash( $_POST['nama'] ) ) : '';
	$nik       = isset( $_POST['nik'] ) ? sanitize_text_field( wp_unslash( $_POST['nik'] ) ) : '';
	$email     = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$no_hp     = isset( $_POST['no_hp'] ) ? sanitize_text_field( wp_unslash( $_POST['no_hp'] ) ) : '';
	$tujuan    = isset( $_POST['tujuan'] ) ? sanitize_textarea_field( wp_unslash( $_POST['tujuan'] ) ) : '';
	$pekerjaan = isset( $_POST['pekerjaan'] ) ? sanitize_text_field( wp_unslash( $_POST['pekerjaan'] ) ) : '';
	$deskripsi = isset( $_POST['deskripsi'] ) ? sanitize_textarea_field( wp_unslash( $_POST['deskripsi'] ) ) : '';
	$cara      = isset( $_POST['cara_penerimaan'] ) ? sanitize_text_field( wp_unslash( $_POST['cara_penerimaan'] ) ) : '';

	// 4) Validasi.
	$errors = array();
	if ( '' === $nama ) {
		$errors[] = 'Nama lengkap wajib diisi.';
	} elseif ( mb_strlen( $nama ) > 120 ) {
		$errors[] = 'Nama lengkap terlalu panjang (maks. 120 karakter).';
	}

	if ( '' === $nik ) {
		$errors[] = 'NIK wajib diisi.';
	} elseif ( 1 !== preg_match( '/^\d{16}$/', $nik ) ) {
		$errors[] = 'NIK harus 16 digit angka (sesuai KTP).';
	}

	if ( '' === $email ) {
		$errors[] = 'Alamat email wajib diisi.';
	} elseif ( ! is_email( $email ) ) {
		$errors[] = 'Format email tidak valid.';
	}

	if ( '' === $no_hp ) {
		$errors[] = 'Nomor HP/WhatsApp wajib diisi.';
	} elseif ( mb_strlen( $no_hp ) > 20 || 1 !== preg_match( '/^[0-9+()\-\s]+$/', $no_hp ) ) {
		$errors[] = 'Format nomor HP tidak valid.';
	}

	if ( '' === $deskripsi ) {
		$errors[] = 'Deskripsi informasi yang dimohon wajib diisi.';
	} elseif ( mb_strlen( $deskripsi ) > 2000 ) {
		$errors[] = 'Deskripsi terlalu panjang (maks. 2000 karakter).';
	}

	// Whitelist tujuan (opsional, tapi bila diisi harus salah satu dari daftar).
	$tujuan_whitelist = array(
		'Penelitian / Akademik',
		'Jurnalisme / Media',
		'Kebutuhan Hukum',
		'Pengawasan Publik',
		'Kepentingan Pribadi',
		'Lainnya',
	);
	if ( '' !== $tujuan && ! in_array( $tujuan, $tujuan_whitelist, true ) ) {
		$errors[] = 'Tujuan penggunaan informasi tidak valid.';
	}
	if ( mb_strlen( $tujuan ) > 200 ) {
		$errors[] = 'Tujuan terlalu panjang (maks. 200 karakter).';
	}

	// Whitelist cara penerimaan.
	if ( ! in_array( $cara, array( 'email', 'pos', 'langsung' ), true ) ) {
		$errors[] = 'Cara penerimaan tidak valid.';
	}

	if ( mb_strlen( $pekerjaan ) > 120 ) {
		$errors[] = 'Pekerjaan/instansi terlalu panjang (maks. 120 karakter).';
	}

	if ( ! empty( $errors ) ) {
		wp_send_json_error( array( 'message' => implode( ' ', $errors ) ), 400 );
	}

	$title = sprintf( 'Permohonan Informasi – %s', $nama );
	$body  = sprintf(
		"Nama: %s\nNIK: %s\nEmail: %s\nNo. HP: %s\nPekerjaan: %s\nCara Penerimaan: %s\n\nTujuan:\n%s\n\nDeskripsi:\n%s",
		$nama, $nik, $email, $no_hp, $pekerjaan, $cara, $tujuan, $deskripsi
	);

	$post_id = wp_insert_post(
		array(
			'post_type'    => 'permohonan_informasi',
			'post_status'  => 'private',
			'post_title'   => $title,
			'post_content' => $body,
		),
		true
	);

	if ( is_wp_error( $post_id ) ) {
		wp_send_json_error( array( 'message' => 'Gagal menyimpan permohonan: ' . $post_id->get_error_message() ), 500 );
	}

	update_post_meta( $post_id, 'nama', $nama );
	update_post_meta( $post_id, 'nik', $nik );
	update_post_meta( $post_id, 'email', $email );
	update_post_meta( $post_id, 'no_hp', $no_hp );
	update_post_meta( $post_id, 'tujuan', $tujuan );
	update_post_meta( $post_id, 'pekerjaan', $pekerjaan );
	update_post_meta( $post_id, 'cara_penerimaan', $cara );
	update_post_meta( $post_id, 'deskripsi', $deskripsi );

	// Email ke pengaturan: To/From/Subject dari theme_mods.
	$to       = $settings['email_to'];
	$subject  = $settings['email_subject'];
	$from     = $settings['email_from'];
	$headers  = array();
	if ( is_email( $from ) ) {
		$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$headers[] = 'From: ' . $site_name . ' <' . $from . '>';
	}
	$message = sprintf(
		"Permohonan informasi baru dari %s (%s) telah masuk.\n\nLihat di admin: %s",
		$nama,
		$email,
		admin_url( 'post.php?post=' . $post_id . '&action=edit' )
	);
	wp_mail( $to, $subject, $message, $headers );

	wp_send_json_success(
		array(
			'message' => 'Permohonan Anda berhasil dikirim. Tim PPID akan menindaklanjuti dalam 10 hari kerja.',
			'post_id' => $post_id,
		)
	);
}
add_action( 'wp_ajax_itsi_submit_permohonan', 'itsi_submit_permohonan' );
add_action( 'wp_ajax_nopriv_itsi_submit_permohonan', 'itsi_submit_permohonan' );
