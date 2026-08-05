<?php
/**
 * LP2M Pendaftaran Hibah — REST API + Email Notifikasi
 */

defined('ABSPATH') || exit;

// Fallback if lp2m-settings.php not loaded
if (!function_exists('lp2m_opt')) {
    function lp2m_opt($k) { return get_option('lp2m_'.$k, ''); }
}
add_action('rest_api_init', function() {
    register_rest_route('lp2m/v1', '/pendaftaran', [
        'methods'               => 'POST',
        'callback'              => 'lp2m_pendaftaran_submit',
        'permission_callback'   => '__return_true',
    ]);
    register_rest_route('lp2m/v1', '/pendaftaran/status/(?P<no>[^/]+)', [
        'methods'               => 'GET',
        'callback'              => 'lp2m_pendaftaran_status',
        'permission_callback'   => '__return_true',
    ]);
    register_rest_route('lp2m/v1', '/pendaftaran/export', [
        'methods'               => 'GET',
        'callback'              => 'lp2m_pendaftaran_export',
        'permission_callback'   => '__return_true',
    ]);
    register_rest_route('lp2m/v1', '/pendaftaran/check-email', [
        'methods'               => 'GET',
        'callback'              => 'lp2m_pendaftaran_test_email',
        'permission_callback'   => '__return_true',
    ]);
    register_rest_route('lp2m/v1', '/pendaftaran', [
        'methods'               => 'GET',
        'callback'              => 'lp2m_pendaftaran_list',
        'permission_callback'   => '__return_true',
    ]);
});

function lp2m_pendaftaran_submit(WP_REST_Request $request) {
    $nama      = sanitize_text_field($request->get_param('nama')   ?? '');
    $nip       = sanitize_text_field($request->get_param('nip')    ?? '');
    $email     = sanitize_email($request->get_param('email')       ?? '');
    $hp        = sanitize_text_field($request->get_param('hp')     ?? '');
    $judul     = sanitize_text_field($request->get_param('judul')  ?? '');
    $prodi     = sanitize_text_field($request->get_param('prodi')  ?? '');
    $skema     = sanitize_text_field($request->get_param('skema')  ?? '');
    $ringkasan = sanitize_textarea_field($request->get_param('ringkasan') ?? '');
    $jenis     = sanitize_text_field($request->get_param('jenis')  ?? 'Dosen');
    $jml_tim   = sanitize_text_field($request->get_param('jml_tim') ?? '');
    $anggota   = sanitize_text_field($request->get_param('anggota') ?? '');
    $hibah_id  = absint($request->get_param('hibah_id') ?? 0);
    $custom    = $request->get_json_params();
    $custom_json = json_encode(array_diff_key($custom, array_flip([
        'nama','nip','email','hp','judul','prodi','skema','ringkasan',
        'jenis','jml_tim','anggota','pernyataan','hibah_id'
    ])));
    $user_agent = sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '');
    $ip         = sanitize_text_field($_SERVER['REMOTE_ADDR']       ?? '');

    if (!$nama || !$email || !$judul) {
        return new WP_Error('invalid', 'Nama, email, dan judul wajib diisi.', ['status' => 400]);
    }

    // Generate No Registrasi: YYYYMMDDNNNN → contoh: 202608040001
    $date_prefix = date('Ymd');
    $seq_key     = 'lp2m_reg_seq_' . $date_prefix;
    $seq         = (int) get_option($seq_key, 0) + 1;
    update_option($seq_key, $seq, '', 'no');
    $reg_no      = $date_prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);

    // Simpan ke wp_options
    $data = compact('nama','nip','email','hp','judul','prodi','skema','ringkasan','jenis','jml_tim','anggota','hibah_id','custom_json','user_agent','ip');
    $data['status']  = 'submitted';
    $data['tanggal'] = current_time('mysql');
    add_option('lp2m_reg_' . $reg_no, $data, '', 'no');

    // Simpan list untuk admin
    $list   = get_option('lp2m_reg_list', []);
    $list[] = $reg_no;
    update_option('lp2m_reg_list', $list, '', 'no');

    // ————————————————————————————————————————
    // kirim email notifikasi
    // ————————————————————————————————————————
    $admin_email = lp2m_opt('site_admin_email') ?: get_option('admin_email');
    $from_name   = get_bloginfo('name');

    // 1) Notif admin
    $subject_admin = "[LP2M] Pendaftaran Hibah Baru: {$reg_no}";
    $msg_admin     = implode("\n", [
        "Pendaftaran hibah baru masuk.",
        "",
        "Nomor Registrasi : {$reg_no}",
        "Nama             : {$nama}",
        "Email            : {$email}",
        "Judul            : {$judul}",
        "Prodi            : {$prodi}",
        "Skema            : {$skema}",
        "Jenis            : {$jenis}",
        "",
        "Silakan login dashboard: https://lp2m.bagistudio.com/dashboard/pendaftaran",
    ]);
    wp_mail($admin_email, $subject_admin, $msg_admin, ['From: ' . $from_name . ' <noreply@' . $_SERVER['SERVER_NAME'] . '>']);

    // 2) Notif penerima
    $subject_user = "Konfirmasi Pendaftaran Hibah LP2M ITSI — {$reg_no}";
    $msg_user     = implode("\n", [
        "Terima kasih, {$nama}! Pendaftaran hibah Anda telah kami terima.",
        "",
        "Nomor Registrasi : {$reg_no}",
        "Judul            : {$judul}",
        "Status           : SUBMITTED",
        "",
        "Simpan nomor ini. Cek status sewaktu-waktu:",
        "https://lp2m.bagistudio.com/daftar/status/{$reg_no}",
        "",
        "Tim LP2M ITSI",
    ]);
    wp_mail($email, $subject_user, $msg_user);

    return rest_ensure_response([
        'success' => true,
        'reg_no'  => $reg_no,
        'email'   => $email,
        'status'  => 'submitted',
    ]);
}

function lp2m_pendaftaran_status(WP_REST_Request $request) {
    $no  = sanitize_text_field($request->get_param('no'));
    $key = 'lp2m_reg_' . $no;
    $data = get_option($key);
    if ( ! $data ) {
        return new WP_Error('not_found', 'Nomor pendaftaran tidak ditemukan.', ['status' => 404]);
    }
    return rest_ensure_response(array_merge(['reg_no' => $no], (array) $data));
}

function lp2m_pendaftaran_test_email(WP_REST_Request $request) {
    $to = sanitize_text_field(
        $request->get_param('to') ?: lp2m_opt('site_admin_email') ?: get_option('admin_email')
    );
    wp_mail($to, 'LP2M Test Email', 'Ini email test dari LP2M settings.', 'From: LP2M ITSI <noreply@' . $_SERVER['SERVER_NAME'] . '>');
    return rest_ensure_response(['success' => true, 'to' => $to, 'message' => 'Email test terkirim']);
}

function lp2m_pendaftaran_export(WP_REST_Request $request) {
    $dari    = sanitize_text_field($request->get_param('dari'))    ?: date('Y-m-d');
    $sampai  = sanitize_text_field($request->get_param('sampai')) ?: date('Y-m-d');
    $status  = sanitize_text_field($request->get_param('status'))  ?: '';
    $perPage = absint($request->get_param('per_page'))             ?: 1000;
    if ($perPage > 1000) $perPage = 1000;

    $list   = get_option('lp2m_reg_list', []);
    $dateFrom = substr(str_replace('-', '', $dari),   0, 8);
    $dateTo   = substr(str_replace('-', '', $sampai),  0, 8);
    $rows = [];

    foreach ($list as $no) {
        $d = get_option('lp2m_reg_' . $no);
        if (!$d) continue;
        $tgl = substr($d['tanggal']  ?? '', 0, 8);                          // YYYYMMDD
        if (!$tgl) continue;
        if ($tgl < $dateFrom || $tgl > $dateTo) continue;
        if ($status && ($d['status'] ?? '') !== $status) continue;
        $rows[] = array_merge(['reg_no' => $no], (array) $d);
    }
    usort($rows, fn($a, $b) => strcmp($b['reg_no'] ?? '', $a['reg_no'] ?? ''));
    $rows = array_slice($rows, 0, $perPage);
    header('X-Total-Count: ' . count($rows));
    return rest_ensure_response($rows);
}

function lp2m_pendaftaran_list() {
    $list = get_option('lp2m_reg_list', []);
    $rows = [];
    foreach ($list as $no) {
        $d = get_option('lp2m_reg_' . $no);
        if ($d) $rows[] = array_merge(['reg_no' => $no], (array) $d);
    }
    usort($rows, fn($a, $b) => strtotime($b['tanggal']) <=> strtotime($a['tanggal']));
    return rest_ensure_response($rows);
}
