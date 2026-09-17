<?php
/**
 * LP2M SMTP — Konfigurasi phpmailer global untuk seluruh email WordPress.
 *
 * Kredensial disimpan di wp_options (via halaman LP2M Settings), bukan di
 * source code, agar tidak terekspos di repo publik.
 *
 * @package itsi
 */

defined( 'ABSPATH' ) || exit;

/**
 * Konfigurasi PHPMailer dari option `lp2m_smtp_*`.
 *
 * Menggunakan SMTP Gmail (smtp.gmail.com:587, STARTTLS). Kosongkan
 * host/username untuk memakai default wp_mail (PHP mail).
 *
 * @param PHPMailer\PHPMailer\PHPMailer $phpmailer Instance PHPMailer.
 */
function itsi_lp2m_smtp( $phpmailer ) {
	$host      = get_option( 'lp2m_smtp_host', '' );
	$username  = get_option( 'lp2m_smtp_username', '' );
	$password  = get_option( 'lp2m_smtp_password', '' );
	$port      = (int) get_option( 'lp2m_smtp_port', 587 );
	$secure    = get_option( 'lp2m_smtp_secure', 'tls' );
	$from      = get_option( 'lp2m_smtp_from', '' );
	$from_name = get_option( 'lp2m_smtp_from_name', '' );

	if ( empty( $host ) || empty( $username ) ) {
		return; // SMTP belum dikonfigurasi — pakai wp_mail default.
	}

	$phpmailer->isSMTP();
	$phpmailer->Host       = sanitize_text_field( $host );
	$phpmailer->Port       = ( $port > 0 && $port <= 65535 ) ? $port : 587;
	$phpmailer->SMTPAuth   = true;
	$phpmailer->Username   = sanitize_email( $username );
	$phpmailer->Password   = (string) $password;
	$phpmailer->SMTPSecure = ( 'ssl' === $secure ) ? 'ssl' : 'tls';
	$phpmailer->Timeout    = 15;

	// From: gunakan option `lp2m_smtp_from` jika valid, selain itu fallback
	// ke username SMTP agar domain pengirim konsisten dengan akun SMTP.
	$from_email = ( ! empty( $from ) && is_email( $from ) ) ? $from : $phpmailer->Username;
	if ( is_email( $from_email ) ) {
		$phpmailer->From   = $from_email;
		$phpmailer->Sender = $from_email;
	}

	// FromName: selalu diterapkan, agar alias (mis. "Tim IT LP2M") tampil
	// di semua email. Fallback ke nama situs bila kosong.
	$phpmailer->FromName = ! empty( $from_name )
		? sanitize_text_field( $from_name )
		: get_bloginfo( 'name' );
}
add_action( 'phpmailer_init', 'itsi_lp2m_smtp' );
