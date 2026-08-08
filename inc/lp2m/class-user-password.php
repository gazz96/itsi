<?php
/**
 * LP2M User Password — Ganti password akun via REST API.
 *
 * Endpoint: POST /lp2m/v1/me/password
 * Memverifikasi password lama (wp_check_password) lalu mengganti password
 * akun user yang sedang login (wp_set_password). Autentikasi dilakukan oleh
 * core (application password) atau fallback password akun
 * (inc/lp2m-auth.php).
 *
 * @package itsi
 */

defined( 'ABSPATH' ) || exit;

class ITSI_User_Password {

	/** Panjang minimal password baru (sama dengan kebijakan wp-admin). */
	public const MIN_PASSWORD_LENGTH = 8;

	public function init(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		register_rest_route( 'lp2m/v1', '/me/password', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_change' ],
			'permission_callback' => [ $this, 'check_permission' ],
			'args'                => [
				'current_password' => [
					'required'          => true,
					// JANGAN pakai sanitize_text_field: ia menghapus pola %xx
					// (mis. "%3C"), merusak password yang mengandung karakter
					// URL-encoded. Password harus diverifikasi apa adanya.
					'sanitize_callback' => function ( $value ) {
						return is_string( $value ) ? $value : '';
					},
				],
				'new_password'      => [
					'required'          => true,
					// Sama: pertahankan string mentah (bisa mengandung %xx).
					'sanitize_callback' => function ( $value ) {
						return is_string( $value ) ? $value : '';
					},
					'validate_callback' => [ $this, 'validate_new_password' ],
				],
			],
		] );
	}

	/**
	 * Permission: hanya user yang bisa mengedit (Editor & Admin), sama pola
	 * endpoint settings LP2M.
	 */
	public function check_permission(): bool|\WP_Error {
		if ( current_user_can( 'edit_posts' ) ) {
			return true;
		}
		return new \WP_Error( 'forbidden', 'Anda tidak memiliki akses.', [ 'status' => 403 ] );
	}

	/**
	 * Validasi password baru: wajib string non-kosong, minimal MIN_PASSWORD_LENGTH.
	 *
	 * @param mixed $value
	 * @return bool
	 */
	public function validate_new_password( $value ): bool {
		return is_string( $value ) && mb_strlen( $value ) >= self::MIN_PASSWORD_LENGTH;
	}

	/**
	 * Ganti password akun user yang sedang login.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_change( \WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return new \WP_Error( 'forbidden', 'Anda harus login terlebih dahulu.', [ 'status' => 403 ] );
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return new \WP_Error( 'user_not_found', 'User tidak ditemukan.', [ 'status' => 404 ] );
		}

		$current = (string) $request->get_param( 'current_password' );
		$new     = (string) $request->get_param( 'new_password' );

		if ( ! wp_check_password( $current, $user->user_pass, $user_id ) ) {
			return new \WP_Error( 'invalid_password', 'Password lama salah.', [ 'status' => 400 ] );
		}

		// Password baru tidak boleh sama dengan yang lama.
		if ( wp_check_password( $new, $user->user_pass, $user_id ) ) {
			return new \WP_Error( 'same_password', 'Password baru tidak boleh sama dengan password lama.', [ 'status' => 400 ] );
		}

		wp_set_password( $new, $user_id );

		// Pastikan cache user yang berisi hash lama dibersihkan agar request
		// berikutnya dengan password baru langsung valid.
		clean_user_cache( $user_id );

		return rest_ensure_response( [
			'success' => true,
			'message' => 'Password berhasil diganti.',
		] );
	}
}

( new ITSI_User_Password() )->init();
