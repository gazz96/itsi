<?php
/**
 * Template Name: Program Studi (BDP Style)
 * Slug: single-program_studi
 *
 * Renders a single program_studi post (custom post type) using the
 * BDP-style template. Header/footer pulled in from active theme;
 * page body markup is data-bound to current $post meta fields.
 *
 * @package itsi
 */

get_header();

/* ------------------------------------------------------------------ *
 *  Load current program_studi and its meta fields
 * ------------------------------------------------------------------ */
while ( have_posts() ) : the_post();

$post_id   = get_the_ID();
$hero_title = trim( (string) get_post_meta( $post_id, 'hero_title', true ) );
if ( $hero_title === '' ) {
    $hero_title = get_the_title();
}

$prodi_degree          = (string) get_post_meta( $post_id, 'prodi_degree', true );
if ( $prodi_degree === '' ) {
    $prodi_degree = (string) get_post_meta( $post_id, 'gelar', true ); // legacy key fallback
}
$prodi_jenjang         = (string) get_post_meta( $post_id, 'jenjang', true );
$prodi_accreditation   = (string) get_post_meta( $post_id, 'akreditasi', true );
if ( $prodi_accreditation === '' ) {
    $prodi_accreditation = (string) get_post_meta( $post_id, 'prodi_accreditation', true ); // legacy key fallback
}
$prodi_graduate_title  = (string) get_post_meta( $post_id, 'prodi_graduate_title', true );
$prodi_founding_year   = (string) get_post_meta( $post_id, 'tahun_berdiri', true );
if ( $prodi_founding_year === '' ) {
    $prodi_founding_year = (string) get_post_meta( $post_id, 'prodi_founding_year', true ); // legacy key fallback
}
$prodi_duration        = (string) get_post_meta( $post_id, 'durasi', true );
if ( $prodi_duration === '' ) {
    $prodi_duration = (string) get_post_meta( $post_id, 'prodi_duration', true ); // legacy key fallback
}
$prodi_total_credits   = (string) get_post_meta( $post_id, 'total_sks', true );
if ( $prodi_total_credits === '' ) {
    $prodi_total_credits = (string) get_post_meta( $post_id, 'prodi_total_credits', true ); // legacy key fallback
}
$prodi_lecturers_count = (string) get_post_meta( $post_id, 'jumlah_dosen', true );
if ( $prodi_lecturers_count === '' ) {
    $prodi_lecturers_count = (string) get_post_meta( $post_id, 'prodi_lecturers_count', true ); // legacy key fallback
}

$hero_subtitle   = (string) get_post_meta( $post_id, 'hero_subtitle', true );
$hero_badge_icon = (string) get_post_meta( $post_id, 'hero_badge_icon', true );
$hero_badge_text = (string) get_post_meta( $post_id, 'hero_badge_text', true );

$faks = wp_get_post_terms( $post_id, 'fakultas', array( 'fields' => 'all' ) );
$fakultas_name = ( ! empty( $faks ) && ! is_wp_error( $faks ) ) ? $faks[0]->name : 'Fakultas Vokasi';

/* ------------------------------------------------------------------ *
 *  _use_default_content meta flag (LEGACY — no longer used)
 *
 *  Dihapus: template sekarang pure data-driven, tanpa fallback.
 *  Field ini tetap dibaca untuk backward-compat tapi diabaikan.
 * ------------------------------------------------------------------ */
$_udc        = get_post_meta( $post_id, '_use_default_content', true );
$use_default = '0';   // always off — tidak ada fallback lagi
$is_empty    = false;

/* ----- Sejarah ----- */
$sejarah = get_post_meta( $post_id, 'sejarah', true );
if ( is_array( $sejarah ) || is_object( $sejarah ) ) { $sejarah = ''; }
if ( $sejarah === '' || $sejarah === null ) {
    $sejarah = get_post_meta( $post_id, 'prodi_history', true );
    if ( is_array( $sejarah ) || is_object( $sejarah ) ) { $sejarah = ''; }
}
$sejarah = (string) $sejarah;

$timeline_raw = get_post_meta( $post_id, 'timeline', true );
if ( is_array( $timeline_raw ) || is_object( $timeline_raw ) ) {
    // TR repeater saves as JSON array — handle directly.
    $timeline = is_array( $timeline_raw ) ? $timeline_raw : (array) $timeline_raw;
    $timeline_raw = ''; // marker
} else {
    $timeline_raw = (string) $timeline_raw;
    if ( $timeline_raw === '' ) {
        $timeline_raw = (string) get_post_meta( $post_id, 'prodi_timeline', true );
    }
    $timeline = maybe_unserialize( $timeline_raw );
}
if ( ! is_array( $timeline ) ) {
    $timeline = array();
}

/* ----- Visi & Misi ----- */
$visi = (string) get_post_meta( $post_id, 'visi', true );
if ( $visi === '' ) {
    $visi = (string) get_post_meta( $post_id, 'prodi_vision', true );
}
$misi_raw = (string) get_post_meta( $post_id, 'misi', true );
if ( $misi_raw === '' ) {
    $misi_raw = (string) get_post_meta( $post_id, 'prodi_mission', true );
}
$misi = maybe_unserialize( $misi_raw );
if ( ! is_array( $misi ) ) {
    $misi = array();
}

/* ----- Tujuan & Kompetensi ----- */
$tujuan_raw = (string) get_post_meta( $post_id, 'tujuan', true );
if ( $tujuan_raw === '' ) {
    $tujuan_raw = (string) get_post_meta( $post_id, 'prodi_objectives', true );
}
$tujuan = maybe_unserialize( $tujuan_raw );
if ( ! is_array( $tujuan ) ) {
    $tujuan = array();
}

$kompetensi_raw = (string) get_post_meta( $post_id, 'kompetensi', true );
if ( $kompetensi_raw === '' ) {
    $kompetensi_raw = (string) get_post_meta( $post_id, 'prodi_competencies', true );
}
$kompetensi = maybe_unserialize( $kompetensi_raw );
if ( ! is_array( $kompetensi ) ) {
    $kompetensi = array();
}

/* ----- Profil Lulusan ----- */
$lulusan = get_post_meta( $post_id, 'lulusan', true );
if ( ! is_array( $lulusan ) ) {
    $lulusan = array();
}

/* ----- Dosen ----- */
$dosen = get_post_meta( $post_id, 'dosen', true );
if ( ! is_array( $dosen ) ) {
    $dosen = array();
}

/* ----- Repeater sections (Fasilitas, Mitra, Prestasi, Testimoni) ----- */
$itsi_load_repeater = function ( $key ) use ( $post_id ) {
    $v = get_post_meta( $post_id, $key, true );
    if ( is_array( $v ) ) { return $v; }
    if ( is_string( $v ) && $v !== '' ) {
        $decoded = json_decode( $v, true );
        if ( is_array( $decoded ) ) { return $decoded; }
    }
    return array();
};
$fasilitas = $itsi_load_repeater( 'fasilitas' );
$mitra     = $itsi_load_repeater( 'mitra' );
$prestasi  = $itsi_load_repeater( 'prestasi' );
$testimoni = $itsi_load_repeater( 'testimoni' );

/* ----- CPL rich-text per category ----- */
$itsi_sanitize_wp = function ( $key ) use ( $post_id ) {
    $v = get_post_meta( $post_id, $key, true );
    if ( is_string( $v ) ) { return $v; }
    if ( is_array( $v ) || is_object( $v ) ) { return ''; }
    return '';
};
$cpl_pengetahuan    = $itsi_sanitize_wp( 'cpl_pengetahuan' );
$cpl_keterampilan  = $itsi_sanitize_wp( 'cpl_keterampilan' );
$cpl_sikap         = $itsi_sanitize_wp( 'cpl_sikap' );

/* ----- Mata Kuliah repeater override (nested repeater) ----- */
$mk_semesters_raw = $itsi_load_repeater( 'mk_semesters' );
$mk_use_repeater  = ! empty( $mk_semesters_raw );

/* ----- Mata Kuliah ----- */
$mk_ids_raw = (string) get_post_meta( $post_id, 'prodi_courses', true );
$mk_ids    = maybe_unserialize( $mk_ids_raw );
if ( ! is_array( $mk_ids ) ) {
    $mk_ids = array();
}

$akreditasi_value = (string) get_post_meta( $post_id, 'akreditasi_value', true );
$akreditasi_sub   = (string) get_post_meta( $post_id, 'akreditasi_sub', true );
$sk_akreditasi    = (string) get_post_meta( $post_id, '***', true );
$pmb_url          = (string) get_post_meta( $post_id, 'pmb_url', true );
$pmb_label        = (string) get_post_meta( $post_id, 'pmb_label', true );

/* ----- Struktur Organisasi image (optional override) ----- */
// TR ->image() stores an attachment ID. If set + valid, render the uploaded image
// instead of the default tree chart. Falls back to chart when empty/invalid.
$struktur_img_id  = (int) get_post_meta( $post_id, 'struktur_organisasi_image', true );
$struktur_img_src = '';
$struktur_img_alt = sprintf( 'Struktur Organisasi %s', $hero_title );
if ( $struktur_img_id > 0 ) {
    $src = wp_get_attachment_image_src( $struktur_img_id, 'full' );
    if ( $src && ! empty( $src[0] ) ) {
        $struktur_img_src = $src[0];
    }
}

/* ----- Field fallbacks dihapus -----
 * Template sekarang pure data-driven: kalau admin kosongkan field, ya kosong.
 * Tidak ada fallback otomatis untuk subtitle, badge icon, badge text,
 * akreditasi, founding_year, duration, total_sks, lecturers_count,
 * akreditasi_sub, pmb_label, pmb_url, visi.
 */

/* ----- Chip list (override manual only — tidak ada fallback) -----
 * Chip hero di bawah judul hanya muncul kalau admin mengisi field
 * chip_* di tab Statistik. Tanpa fallback otomatis; sengaja dikosongkan
 * agar teks chip 100% terkontrol admin (tidak lagi keluar "Jenjang D4"
 * mentah dari nilai select).
 */
$chips = array();
foreach ( array( 'chip_gelar', 'chip_jenjang', 'chip_akreditasi', 'chip_berdiri', 'chip_semester' ) as $chip_key ) {
    $val = (string) get_post_meta( $post_id, $chip_key, true );
    if ( $val !== '' ) { $chips[] = $val; }
}

/* ----- Repeater sections (no fallback) -----
 * Kalau repeater kosong, ya kosong — section tidak render item.
 */
$misi       = is_array( $misi ) ? $misi : array();
$tujuan     = is_array( $tujuan ) ? $tujuan : array();
$kompetensi = is_array( $kompetensi ) ? $kompetensi : array();
$lulusan    = is_array( $lulusan ) ? $lulusan : array();
$dosen      = is_array( $dosen ) ? $dosen : array();
$timeline   = is_array( $timeline ) ? $timeline : array();

/* ------------------------------------------------------------------ *
 *  Normalizer — TR v6 repeater saves arrays with key capitalization
 *  matching the field label (e.g. "Tahun", "Judul"), while legacy code
 *  and template default arrays use lowercase English keys. This helper
 *  maps any of the known aliases to canonical lowercase keys.
 * ------------------------------------------------------------------ */
if ( ! function_exists( 'itsi_prodi_field' ) ) {
    function itsi_prodi_field( $row, ...$keys ) {
        if ( ! is_array( $row ) ) { return ''; }
        foreach ( $keys as $k ) {
            if ( isset( $row[ $k ] ) && $row[ $k ] !== '' ) { return $row[ $k ]; }
            // case-insensitive
            foreach ( $row as $rk => $rv ) {
                if ( strcasecmp( (string) $rk, (string) $k ) === 0 && $rv !== '' ) { return $rv; }
            }
        }
        return '';
    }
}

/* Normalize repeater-array rows so templates can rely on canonical keys. */
$itsi_normalize_repeater = function ( array $rows, array $map ) {
    $out = [];
    foreach ( $rows as $row ) {
        if ( ! is_array( $row ) ) { continue; }
        $item = [];
        foreach ( $map as $canonical => $aliases ) {
            $item[ $canonical ] = itsi_prodi_field( $row, ...$aliases );
        }
        $out[] = $item;
    }
    return $out;
};

$map_timeline    = [ 'year' => ['year','tahun','Year','Tahun'], 'title' => ['title','judul','Title','Judul'], 'desc' => ['desc','deskripsi','Desc','Deskripsi'], 'gold' => ['gold','highlight','Gold','Highlight'] ];
$map_misi        = [ 'icon' => ['icon','Icon'], 'text' => ['text','teks_misi','Teks Misi','Text'] ];
$map_tujuan      = [ 'icon' => ['icon','Icon'], 'text' => ['text','teks_tujuan','Teks Tujuan','Text'] ];
$map_kompetensi  = [ 'icon' => ['icon','Icon'], 'name' => ['name','nama_kompetensi','Nama Kompetensi','Name'] ];
$map_lulusan     = [ 'icon' => ['icon','Icon'], 'name' => ['name','nama_karir','Nama Karir','Name'], 'desc' => ['desc','deskripsi','Deskripsi','Desc'] ];
$map_dosen       = [ 'initials' => ['initials','inisial_(2_huruf)','Inisial (2 huruf)','Initials'], 'name' => ['name','nama','nama_lengkap_+_gelar','Nama Lengkap + Gelar','Name'], 'nidn' => ['nidn','NIDN'], 'univ' => ['univ','universitas','Universitas','Univ'], 'bid' => ['bid','bidang_keilmuan','Bidang Keilmuan','Bid'], 'deg' => ['deg','jenjang','Jenjang','Deg'], 'foto' => ['foto','foto_dosen','Foto Dosen','photo','Photo','image','Image','Foto'] ];
$map_fasilitas   = [ 'icon' => ['icon','Icon'], 'name' => ['name','nama_fasilitas','Nama Fasilitas','Name'], 'desc' => ['desc','deskripsi','Deskripsi','Desc'] ];
$map_mitra       = [ 'name' => ['name','nama_mitra','Nama Mitra','Name'], 'image' => ['image','url_logo','URL Logo','Image','logo'], 'website' => ['website','Website'] ];
$map_prestasi    = [ 'year' => ['year','tahun','Tahun','Year'], 'title' => ['title','judul_prestasi','Judul Prestasi','Title'], 'desc' => ['desc','deskripsi','Deskripsi','Desc'] ];
$map_testimoni   = [ 'name' => ['name','nama_alumni','Nama Alumni','Name'], 'position' => ['position','angkatan','angkatan_/_profesi','Angkatan / Profesi','Position'], 'text' => ['text','quote_testimoni','Quote Testimoni','Text'] ];

$dosen   = $itsi_normalize_repeater( $dosen,   $map_dosen );
$misi    = $itsi_normalize_repeater( $misi,    $map_misi );
$tujuan  = $itsi_normalize_repeater( $tujuan,  $map_tujuan );
$kompetensi = $itsi_normalize_repeater( $kompetensi, $map_kompetensi );
$lulusan = $itsi_normalize_repeater( $lulusan, $map_lulusan );
$timeline = $itsi_normalize_repeater( $timeline, $map_timeline );
$fasilitas = $itsi_normalize_repeater( $fasilitas, $map_fasilitas );
$mitra     = $itsi_normalize_repeater( $mitra,     $map_mitra );
$prestasi  = $itsi_normalize_repeater( $prestasi,  $map_prestasi );
$testimoni = $itsi_normalize_repeater( $testimoni, $map_testimoni );

// Normalize nested repeater (mk_semesters) — items inside may have nested forms.
$itsi_normalize_nested_repeater = function ( array $rows, array $map ) use ( $itsi_normalize_repeater ) {
    $out = [];
    foreach ( $rows as $row ) {
        if ( ! is_array( $row ) ) { continue; }
        // 1) normalize top-level keys
        $item = [];
        foreach ( $map as $canonical => $aliases ) {
            if ( $canonical === 'courses' && isset( $row['courses'] ) && is_array( $row['courses'] ) ) {
                $item['courses'] = $itsi_normalize_repeater( $row['courses'], [
                    'kode'  => ['kode', 'Kode'],
                    'name'  => ['name', 'nama', 'nama_mata_kuliah', 'Nama Mata Kuliah'],
                    'sks'   => ['sks', 'SKS'],
                    'jenis' => ['jenis', 'Jenis'],
                ] );
            } else {
                $val = '';
                foreach ( $aliases as $alias ) {
                    if ( isset( $row[ $alias ] ) && $row[ $alias ] !== '' ) { $val = $row[ $alias ]; break; }
                }
                if ( $val === '' ) {
                    foreach ( $row as $rk => $rv ) {
                        foreach ( $aliases as $alias ) {
                            if ( strcasecmp( (string) $rk, (string) $alias ) === 0 && $rv !== '' ) { $val = $rv; break 2; }
                        }
                    }
                }
                $item[ $canonical ] = is_array( $val ) ? '' : (string) $val;
            }
        }
        $out[] = $item;
    }
    return $out;
};
if ( $mk_use_repeater ) {
    $mk_semesters_normalized = $itsi_normalize_nested_repeater( $mk_semesters_raw, [
        'no'      => ['no', 'No Semester', 'no_semester'],
        'tipe'    => ['tipe_semester', 'Tipe Semester', 'tipe'],
        'sks'     => ['total_sks', 'Total SKS', 'sks'],
        'courses' => ['daftar_mata_kuliah', 'Daftar Mata Kuliah', 'courses', 'items'],
    ] );
}

// Make dosen fallback readable too.
foreach ( $dosen as &$dr ) {
    if ( ! isset( $dr['deg'] ) || $dr['deg'] === '' ) { $dr['deg'] = 's2'; }
    $dr['deg'] = strtolower( $dr['deg'] );
}
unset( $dr );

?>

<!-- ══════════════ ACCENT BAR ══════════════ -->
<div class="pg-accent-bar"></div>

<!-- ══════════════ PAGE HERO ══════════════ -->
<section id="pg-phero">
  <div class="pg-ph-bg"></div>
  <div class="pg-ph-dots"></div>
  <div class="pg-ph-blob pg-ph-b1"></div>
  <div class="pg-ph-blob pg-ph-b2"></div>
  <div class="pg-ph-ring"></div>
  <div class="pg-ph-inner pg-container">
    <nav class="pg-bc">
      <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Beranda</a><span>›</span>
      <a href="#">Akademik</a><span>›</span>
      <a href="#"><?php echo esc_html( $fakultas_name ); ?></a><span>›</span>
      <span style="color:rgba(255,255,255,.75)"><?php echo esc_html( $hero_title ); ?></span>
    </nav>
    <div class="pg-ph-layout">
      <div>
        <div class="pg-ph-badge"><?php echo esc_html( $hero_badge_icon . ' ' . $hero_badge_text ); ?></div>
        <h1 class="pg-ph-title">Program Studi<br><em><?php echo esc_html( $hero_title ); ?></em></h1>
        <p class="pg-ph-sub"><?php echo esc_html( $hero_subtitle ); ?></p>
        <div class="pg-ph-chips">
          <?php foreach ( $chips as $chip ) : ?>
            <div class="pg-ph-chip"><?php echo esc_html( $chip ); ?></div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="pg-ph-stats">
        <div class="pg-ph-stat">
          <div class="pg-ph-stat-n"><?php echo esc_html( $prodi_founding_year ); ?></div>
          <div class="pg-ph-stat-l">Tahun Berdiri</div>
        </div>
        <div class="pg-ph-stat">
          <div class="pg-ph-stat-n"><?php echo esc_html( $prodi_lecturers_count ); ?></div>
          <div class="pg-ph-stat-l">Dosen Pengampu</div>
        </div>
        <div class="pg-ph-stat">
          <div class="pg-ph-stat-n"><?php echo esc_html( $prodi_total_credits ); ?></div>
          <div class="pg-ph-stat-l">Total SKS</div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ══════════════ MAIN ══════════════ -->
<div class="pg-main-bg">
  <div class="pg-container">
    <div class="pg-page-layout">

      <!-- SIDEBAR -->
      <aside class="pg-sidebar">
        <div class="pg-side-card">
          <div class="pg-side-head">Navigasi Halaman</div>
          <div class="pg-side-nav">
            <button class="pg-side-btn on" onclick="pgSw('profil',this)">
              <svg class="si" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 9h6M9 13h4"/></svg>Profil Prodi
            </button>
            <button class="pg-side-btn" onclick="pgSw('visi',this)">
              <svg class="si" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>Visi &amp; Misi
            </button>
            <button class="pg-side-btn" onclick="pgSw('tujuan',this)">
              <svg class="si" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>Tujuan &amp; Kompetensi
            </button>
            <button class="pg-side-btn" onclick="pgSw('lulusan',this)">
              <svg class="si" viewBox="0 0 24 24"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>Profil Lulusan
            </button>
            <div class="pg-side-sep"></div>
            <button class="pg-side-btn" onclick="pgSw('struktur',this)">
              <svg class="si" viewBox="0 0 24 24"><rect x="9" y="2" width="6" height="4" rx="1"/><path d="M12 6v4M5 14H3v4h4v-4H5zM14 14h-4v4h4v-4zM21 14h-2v4h4v-4h-2z"/><path d="M3 16H21"/></svg>Struktur Organisasi
            </button>
            <button class="pg-side-btn" onclick="pgSw('dosen',this)">
              <svg class="si" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>Dosen &amp; Tenaga Pengajar
            </button>
            <button class="pg-side-btn" onclick="pgSw('fasilitas',this)">
              <svg class="si" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>Fasilitas
            </button>
            <button class="pg-side-btn" onclick="pgSw('mitra',this)">
              <svg class="si" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 11h-6M16 11v6"/></svg>Mitra Industri
            </button>
            <button class="pg-side-btn" onclick="pgSw('matakuliah',this)">
              <svg class="si" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>Daftar Mata Kuliah
            </button>
            <button class="pg-side-btn" onclick="pgSw('berita',this)">
              <svg class="si" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>Berita &amp; Kegiatan
            </button>
            <button class="pg-side-btn" onclick="pgSw('cpl',this)">
              <svg class="si" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>Capaian Pembelajaran
            </button>
            <button class="pg-side-btn" onclick="pgSw('testimoni',this)">
              <svg class="si" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>Kata Alumni
            </button>
            <button class="pg-side-btn" onclick="pgSw('prestasi',this)">
              <svg class="si" viewBox="0 0 24 24"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/></svg>Prestasi Mahasiswa
            </button>
          </div>
          <div class="pg-side-akred">
            <div class="pg-sa-lbl">Akreditasi BAN-PT</div>
            <div class="pg-sa-val"><?php echo esc_html( $prodi_accreditation ); ?></div>
            <div class="pg-sa-sub"><?php echo esc_html( $akreditasi_sub ); ?></div>
            <?php if ( $sk_akreditasi !== '' ) : ?>
              <div class="pg-sa-sk">No. SK: <?php echo esc_html( $sk_akreditasi ); ?></div>
            <?php endif; ?>
          </div>
          <div class="pg-side-pmb">
            <a href="<?php echo esc_url( $pmb_url ); ?>"><?php echo esc_html( $pmb_label ); ?></a>
          </div>
        </div>
      </aside>

      <!-- CONTENT -->
      <div>

        <!-- ▌ PROFIL ▌ -->
        <div id="pg-panel-profil" class="pg-panel on">
          <div class="pg-block pg-rv">
            <div class="p-head" style="margin-bottom:1.3rem">
              <span class="pg-sec-label">Sejarah Singkat</span>
              <h2 class="pg-sec-title">Perjalanan <em>Prodi <?php echo esc_html( $hero_title ); ?></em></h2>
            </div>
            <div class="pg-hist-box">
              <?php if ( $sejarah !== '' ) : ?>
                <div class="pg-hist-text"><?php echo wpautop( wp_kses_post( $sejarah ) ); ?></div>
              <?php elseif ( $use_default === '1' ) : ?>
                <div class="pg-hist-text">
                  <p>Program Studi Budidaya Perkebunan (BDP) didirikan pada 7 Juli 2005 sebagai bentuk komitmen institusi dalam menyiapkan sumber daya manusia yang kompeten di bidang pengolahan hasil perkebunan, khususnya komoditas strategis kelapa sawit. Pendirian Program Studi BDP secara resmi ditetapkan melalui Surat Keputusan Pendirian Program Studi Nomor 96/D/O/2005.</p>
                  <p>Dalam rangka penguatan tata kelola dan penyelenggaraan pendidikan tinggi, Program Studi BDP memperoleh legalitas penyelenggaraan melalui Surat Keputusan Penyelenggaraan Program Studi Nomor 5828/D/T/K-I/2011, yang menjadi landasan operasional dalam pelaksanaan kegiatan akademik, pengembangan kurikulum, serta peningkatan mutu tridarma perguruan tinggi.</p>
                  <p>Seiring dengan dinamika kebijakan pendidikan tinggi dan tuntutan pengembangan institusi, STIPAP kemudian melakukan transformasi kelembagaan menjadi Institut Teknologi Sawit Indonesia (ITSI) pada 15 Desember 2021, berdasarkan Surat Keputusan Nomor 558/E/0/2021. Transformasi ini menjadi momentum strategis bagi Program Studi BDP untuk memperkuat peran dan kontribusinya dalam pengembangan pendidikan vokasi dan akademik berbasis industri sawit yang berkelanjutan.</p>
                  <p>Hingga saat ini, Program Studi BDP terus berkomitmen untuk menghasilkan lulusan yang unggul, adaptif, dan siap bersaing di dunia industri dan dunia kerja, melalui penguatan kurikulum berbasis Outcome-Based Education (OBE), implementasi Merdeka Belajar Kampus Merdeka (MBKM), serta kolaborasi aktif dengan mitra industri Perkebunan dan pemangku kepentingan.</p>
                </div>
              <?php else : ?>
                <div class="pg-hist-text pg-empty">
                  <p style="font-style:italic;color:var(--tx-mid)">Sejarah program studi belum diisi. Silakan lengkapi di halaman admin → Detail Program Studi → Sejarah &amp; Timeline.</p>
                </div>
              <?php endif; ?>
              <?php if ( ! empty( $timeline ) ) : ?>
                <div class="pg-timeline">
                  <?php foreach ( $timeline as $tl ) :
                    $yr   = isset( $tl['year'] ) ? (string) $tl['year'] : '';
                    $ttl  = isset( $tl['title'] ) ? (string) $tl['title'] : '';
                    $desc = isset( $tl['desc'] ) ? (string) $tl['desc'] : '';
                    $gold = ! empty( $tl['gold'] );
                    ?>
                    <div class="pg-tl-item">
                      <div class="pg-tl-yr"><?php echo esc_html( $yr ); ?></div>
                      <div class="pg-tl-axis">
                        <div class="pg-tl-dot"<?php echo $gold ? ' style="background:var(--gold);border-color:var(--gold-lt)"' : ''; ?>></div>
                        <?php if ( $tl !== end( $timeline ) ) : ?><div class="pg-tl-line"></div><?php endif; ?>
                      </div>
                      <div class="pg-tl-body">
                        <h4><?php echo esc_html( $ttl ); ?></h4>
                        <p><?php echo esc_html( $desc ); ?></p>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
            <?php if ( $sk_akreditasi !== '' ) : ?>
              <div class="pg-sk-row">
                <span class="pg-sk-lbl">📜 No. SK Akreditasi</span>
                <span class="pg-sk-val"><?php echo esc_html( $sk_akreditasi ); ?></span>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- ▌ VISI MISI ▌ -->
        <div id="pg-panel-visi" class="pg-panel">
          <div class="pg-block pg-rv">
            <span class="pg-sec-label">Visi Program Studi</span>
            <h2 class="pg-sec-title" style="margin-bottom:1.2rem">Visi <em><?php echo esc_html( $hero_title ); ?> 2030</em></h2>
            <div class="pg-visi-card">
              <div class="pg-vc-lbl">Visi</div>
              <div class="pg-vc-text"><?php echo esc_html( $visi ); ?></div>
            </div>
          </div>
          <div class="pg-block pg-rv pg-d2">
            <span class="pg-sec-label">Misi Program Studi</span>
            <h2 class="pg-sec-title" style="margin-bottom:1.2rem">Misi <em>Prodi <?php echo esc_html( $hero_title ); ?></em></h2>
            <div class="pg-misi-list">
              <?php foreach ( $misi as $i => $m ) :
                $icon = isset( $m['icon'] ) ? $m['icon'] : '🎯';
                $text = isset( $m['text'] ) ? $m['text'] : ( is_string( $m ) ? $m : '' );
                $n    = sprintf( '%02d', $i + 1 );
                ?>
                <div class="pg-mi-item pg-rv pg-d<?php echo esc_attr( ( $i % 6 ) + 1 ); ?>">
                  <div class="pg-mi-ic"><?php echo esc_html( $icon ); ?></div>
                  <div class="pg-mi-body">
                    <div class="pg-mi-n">Misi <?php echo esc_html( $n ); ?></div>
                    <p class="pg-mi-t"><?php echo esc_html( $text ); ?></p>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <!-- ▌ TUJUAN & KOMPETENSI ▌ -->
        <div id="pg-panel-tujuan" class="pg-panel">
          <div class="pg-block pg-rv">
            <span class="pg-sec-label">Tujuan Program Studi</span>
            <h2 class="pg-sec-title" style="margin-bottom:1.3rem">Tujuan <em>Prodi <?php echo esc_html( $hero_title ); ?></em></h2>
            <div class="pg-tj-grid">
              <?php foreach ( $tujuan as $i => $t ) :
                $icon = isset( $t['icon'] ) ? $t['icon'] : '🎯';
                $text = isset( $t['text'] ) ? $t['text'] : ( is_string( $t ) ? $t : '' );
                ?>
                <div class="pg-tj-card pg-rv pg-d<?php echo esc_attr( ( $i % 6 ) + 1 ); ?>">
                  <div class="pg-tj-ic"><?php echo esc_html( $icon ); ?></div>
                  <p class="pg-tj-text"><?php echo esc_html( $text ); ?></p>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="pg-block pg-rv pg-d2">
            <span class="pg-sec-label">Kompetensi Program Studi</span>
            <h2 class="pg-sec-title" style="margin-bottom:1.3rem"><?php echo count( $kompetensi ); ?> Kompetensi <em>Utama</em></h2>
            <div class="pg-km-grid">
              <?php foreach ( $kompetensi as $i => $k ) :
                $icon = isset( $k['icon'] ) ? $k['icon'] : '✅';
                $name = isset( $k['name'] ) ? $k['name'] : ( is_string( $k ) ? $k : '' );
                $n    = sprintf( '%02d', $i + 1 );
                ?>
                <div class="pg-km-card pg-rv pg-d<?php echo esc_attr( ( $i % 6 ) + 1 ); ?>">
                  <div class="pg-km-ic"><?php echo esc_html( $icon ); ?></div>
                  <div>
                    <div class="pg-km-num">Kompetensi <?php echo esc_html( $n ); ?></div>
                    <div class="pg-km-name"><?php echo esc_html( $name ); ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <!-- ▌ PROFIL LULUSAN ▌ -->
        <div id="pg-panel-lulusan" class="pg-panel">
          <div class="pg-block pg-rv">
            <span class="pg-sec-label">Profil Lulusan</span>
            <h2 class="pg-sec-title" style="margin-bottom:.6rem">Karir <em>Lulusan <?php echo esc_html( $hero_title ); ?></em></h2>
            <p style="font-size:.93rem;color:var(--tx-mid);margin-bottom:1.5rem;line-height:1.78">Lulusan <?php echo esc_html( $prodi_degree ); ?> <?php echo esc_html( $hero_title ); ?> ITSI disiapkan untuk berkarir profesional di berbagai sektor industri perkebunan dan agroindustri kelapa sawit nasional maupun internasional.</p>
            <div class="pg-lul-grid">
              <?php foreach ( $lulusan as $i => $l ) :
                $icon = isset( $l['icon'] ) ? $l['icon'] : '🌱';
                $name = isset( $l['name'] ) ? $l['name'] : ( is_string( $l ) ? $l : '' );
                $desc = isset( $l['desc'] ) ? $l['desc'] : '';
                ?>
                <div class="pg-lul-card pg-rv pg-d<?php echo esc_attr( ( $i % 6 ) + 1 ); ?>">
                  <div class="pg-lul-ic"><?php echo esc_html( $icon ); ?></div>
                  <div class="pg-lul-name"><?php echo esc_html( $name ); ?></div>
                  <p class="pg-lul-desc"><?php echo esc_html( $desc ); ?></p>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <!-- ▌ STRUKTUR ORGANISASI ▌ -->
        <div id="pg-panel-struktur" class="pg-panel">
          <div class="pg-block pg-rv">
            <span class="pg-sec-label">Tata Kelola Prodi</span>
            <h2 class="pg-sec-title" style="margin-bottom:.6rem">Struktur <em>Organisasi</em></h2>
            <p style="font-size:.93rem;color:var(--tx-mid);margin-bottom:1.6rem;line-height:1.78">Struktur tata kelola Program Studi D4 Budidaya Perkebunan di bawah <?php echo esc_html( $fakultas_name ); ?> ITSI.</p>
            <div class="pg-org-wrap">
              <?php if ( $struktur_img_src !== '' ) : ?>
                <figure class="pg-org-image">
                  <img src="<?php echo esc_url( $struktur_img_src ); ?>" alt="<?php echo esc_attr( $struktur_img_alt ); ?>" loading="lazy" />
                  <?php
                  $struktur_img_cap = wp_get_attachment_caption( $struktur_img_id );
                  if ( $struktur_img_cap ) : ?>
                    <figcaption><?php echo esc_html( $struktur_img_cap ); ?></figcaption>
                  <?php endif; ?>
                </figure>
              <?php else : ?>
                <div class="pg-org-chart">
                  <div class="pg-org-row">
                    <div class="pg-org-col">
                      <div class="pg-org-box pg-oL0">
                        <div class="pg-ob-role">Dekan</div>
                        <div class="pg-ob-name"><?php echo esc_html( $fakultas_name ); ?> ITSI</div>
                      </div>
                      <div class="pg-conn-v"></div>
                    </div>
                  </div>
                  <div style="width:62%;height:2px;background:var(--pale);margin-bottom:0"></div>
                  <div class="pg-org-row" style="gap:2.5rem;width:100%;justify-content:center">
                    <div class="pg-org-col">
                      <div class="pg-conn-v" style="height:16px"></div>
                      <div class="pg-org-box pg-oL1">
                        <div class="pg-ob-role">Wakil Dekan I</div>
                        <div class="pg-ob-name">Bidang Akademik</div>
                      </div>
                    </div>
                    <div class="pg-org-col">
                      <div class="pg-conn-v" style="height:16px"></div>
                      <div class="pg-org-box pg-oL1b">
                        <div class="pg-ob-role">Ketua Program Studi</div>
                        <div class="pg-ob-name">D4 <?php echo esc_html( $hero_title ); ?></div>
                      </div>
                      <div class="pg-conn-v"></div>
                    </div>
                    <div class="pg-org-col">
                      <div class="pg-conn-v" style="height:16px"></div>
                      <div class="pg-org-box pg-oL1">
                        <div class="pg-ob-role">Wakil Dekan II</div>
                        <div class="pg-ob-name">Bidang Umum &amp; Keuangan</div>
                      </div>
                    </div>
                  </div>
                  <div style="width:55%;height:2px;background:var(--pale);margin-bottom:0"></div>
                  <div class="pg-org-row" style="gap:1.5rem;width:100%;justify-content:center">
                    <div class="pg-org-col">
                      <div class="pg-conn-v" style="height:16px"></div>
                      <div class="pg-org-box pg-oL2">
                        <div class="pg-ob-role">Sekretaris Prodi</div>
                        <div class="pg-ob-name">Administrasi &amp; Akademik</div>
                      </div>
                    </div>
                    <div class="pg-org-col">
                      <div class="pg-conn-v" style="height:16px"></div>
                      <div class="pg-org-box pg-oL2">
                        <div class="pg-ob-role">Koordinator Lab</div>
                        <div class="pg-ob-name">Laboratorium <?php echo esc_html( $hero_title ); ?></div>
                      </div>
                    </div>
                    <div class="pg-org-col">
                      <div class="pg-conn-v" style="height:16px"></div>
                      <div class="pg-org-box pg-oL2">
                        <div class="pg-ob-role">Gugus Kendali Mutu</div>
                        <div class="pg-ob-name">Penjaminan Mutu</div>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endif; ?>
            </div>
            <div class="pg-org-info">
              <div class="pg-oi-card">
                <div class="pg-oi-ttl">👨‍🏫 Tim Dosen</div>
                <p class="pg-oi-text"><?php echo esc_html( $prodi_lecturers_count ); ?> dosen aktif berkualifikasi S2 dan S3 dari universitas terkemuka nasional dan internasional, mengampu seluruh mata kuliah program studi.</p>
              </div>
              <div class="pg-oi-card">
                <div class="pg-oi-ttl">🏢 Tenaga Kependidikan</div>
                <p class="pg-oi-text">Staf administrasi akademik, teknisi laboratorium, dan tenaga penunjang pendidikan yang profesional dan berdedikasi tinggi.</p>
              </div>
              <div class="pg-oi-card">
                <div class="pg-oi-ttl">🎓 Mahasiswa</div>
                <p class="pg-oi-text">Himpunan Mahasiswa Prodi <?php echo esc_html( $hero_title ); ?> (HIMAPRODI) aktif dalam kegiatan akademik, riset lapangan, dan pengembangan kompetensi industri.</p>
              </div>
            </div>
          </div>
        </div>

        <!-- ▌ DOSEN ▌ -->
        <div id="pg-panel-dosen" class="pg-panel">
          <div class="pg-block pg-rv">
            <span class="pg-sec-label">Dosen &amp; Tenaga Pengajar</span>
            <h2 class="pg-sec-title" style="margin-bottom:.6rem">Tim <em>Pengajar <?php echo esc_html( $hero_title ); ?></em></h2>
            <p style="font-size:.93rem;color:var(--tx-mid);margin-bottom:1.5rem;line-height:1.78"><?php echo count( $dosen ); ?> dosen berpengalaman berkualifikasi S2 dan S3 dari universitas terkemuka nasional dan internasional.</p>
            <?php
            $cnt_s3 = 0; $cnt_s2 = 0;
            foreach ( $dosen as $d ) {
                $deg = isset( $d['deg'] ) ? strtolower( $d['deg'] ) : 's2';
                if ( $deg === 's3' ) { $cnt_s3++; } else { $cnt_s2++; }
            }
            $cnt_total = count( $dosen );
            ?>
            <div class="pg-df-wrap">
              <button class="pg-df-chip on" onclick="pgFD(this,'all')">Semua (<?php echo (int) $cnt_total; ?>)</button>
              <button class="pg-df-chip" onclick="pgFD(this,'s3')">Doktor – S3 (<?php echo (int) $cnt_s3; ?>)</button>
              <button class="pg-df-chip" onclick="pgFD(this,'s2')">Magister – S2 (<?php echo (int) $cnt_s2; ?>)</button>
            </div>
            <div class="pg-dosen-grid" id="pg-dGrid">
              <?php foreach ( $dosen as $i => $d ) :
                $name = isset( $d['nama'] ) ? $d['nama'] : ( isset( $d['name'] ) ? $d['name'] : '' );
                $nidn = isset( $d['nidn'] ) ? $d['nidn'] : '';
                $jab  = isset( $d['jabatan'] ) ? $d['jabatan'] : '';
                $bid  = isset( $d['bid'] ) ? $d['bid'] : $jab;
                $univ = isset( $d['univ'] ) ? $d['univ'] : '';
                $deg  = isset( $d['deg'] ) ? strtolower( $d['deg'] ) : 's2';
                $init = isset( $d['initials'] ) ? $d['initials'] : strtoupper( mb_substr( $name !== '' ? $name : 'X', 0, 2 ) );
                ?>
                <?php
                $d_foto_id  = 0;
                $d_foto_src = '';
                $d_foto_raw = isset( $d['foto'] ) ? $d['foto'] : '';
                if ( is_array( $d_foto_raw ) && isset( $d_foto_raw['id'] ) ) { $d_foto_raw = $d_foto_raw['id']; }
                if ( is_numeric( $d_foto_raw ) && (int) $d_foto_raw > 0 ) {
                    $d_foto_id = (int) $d_foto_raw;
                    $d_foto_src_arr = wp_get_attachment_image_src( $d_foto_id, 'medium' );
                    if ( $d_foto_src_arr && ! empty( $d_foto_src_arr[0] ) ) { $d_foto_src = $d_foto_src_arr[0]; }
                }
                ?>
                <div class="pg-dc pg-rv pg-d<?php echo esc_attr( ( $i % 6 ) + 1 ); ?>" data-lvl="<?php echo esc_attr( $deg ); ?>">
                  <div class="pg-dc-av">
                    <?php if ( $d_foto_src !== '' ) : ?>
                      <img src="<?php echo esc_url( $d_foto_src ); ?>" alt="<?php echo esc_attr( $name ); ?>" loading="lazy" />
                    <?php else : ?>
                      <?php echo esc_html( $init ); ?>
                    <?php endif; ?>
                  </div>
                  <div class="pg-dc-name"><?php echo esc_html( $name ); ?></div>
                  <div class="pg-dc-nidn">NIDN: <?php echo esc_html( $nidn ); ?></div>
                  <?php if ( $univ !== '' ) : ?>
                  <div class="pg-dc-univ"><?php echo esc_html( $univ ); ?></div>
                  <?php else : ?>
                  <div class="pg-dc-univ" style="visibility:hidden">&nbsp;</div>
                  <?php endif; ?>
                  <div class="pg-dc-tags">
                    <?php if ( $bid !== '' ) : ?><span class="pg-dc-bid"><?php echo esc_html( $bid ); ?></span><?php endif; ?>
                    <span class="pg-dc-deg"><?php echo esc_html( strtoupper( $deg ) ); ?></span>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <!-- ▌ FASILITAS ▌ -->
        <div id="pg-panel-fasilitas" class="pg-panel">
          <div class="pg-block pg-rv">
            <span class="pg-sec-label">Fasilitas Program Studi</span>
            <h2 class="pg-sec-title" style="margin-bottom:.6rem">Sarana &amp; <em>Prasarana</em></h2>
            <p style="font-size:.93rem;color:var(--tx-mid);margin-bottom:1.5rem;line-height:1.78">Fasilitas pendukung kegiatan akademik, praktikum, dan riset mahasiswa Program Studi <?php echo esc_html( $hero_title ); ?>.</p>
            <?php if ( ! empty( $fasilitas ) ) : ?>
              <div class="pg-fs-grid">
                <?php foreach ( $fasilitas as $i => $f ) : ?>
                  <div class="pg-fs-card pg-rv pg-d<?php echo esc_attr( ( $i % 6 ) + 1 ); ?>">
                    <div class="pg-fs-ic"><?php echo esc_html( $f['icon'] !== '' ? $f['icon'] : '🏢' ); ?></div>
                    <div class="pg-fs-name"><?php echo esc_html( $f['name'] ); ?></div>
                    <p class="pg-fs-desc"><?php echo esc_html( $f['desc'] ); ?></p>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php elseif ( $use_default !== '1' ) : ?>
              <div class="pg-empty" style="background:#f6f8fc;padding:1rem;border-radius:8px;color:var(--tx-mid);font-style:italic">Belum ada data fasilitas. Silakan lengkapi di admin → Detail Program Studi → Fasilitas.</div>
            <?php endif; ?>
          </div>
        </div>

        <!-- ▌ MITRA ▌ -->
        <div id="pg-panel-mitra" class="pg-panel">
          <div class="pg-block pg-rv">
            <span class="pg-sec-label">Mitra Industri &amp; Kerjasama</span>
            <h2 class="pg-sec-title" style="margin-bottom:.6rem">Kolaborasi <em>Industri</em></h2>
            <p style="font-size:.93rem;color:var(--tx-mid);margin-bottom:1.5rem;line-height:1.78">Mitra kerjasama tempat magang, riset, dan penyerapan lulusan Program Studi <?php echo esc_html( $hero_title ); ?>.</p>
            <?php if ( ! empty( $mitra ) ) : ?>
              <div class="pg-mitra-grid">
                <?php foreach ( $mitra as $i => $m ) : ?>
                  <a class="pg-mitra-card pg-rv pg-d<?php echo esc_attr( ( $i % 6 ) + 1 ); ?>"
                     href="<?php echo esc_url( $m['website'] !== '' ? $m['website'] : '#' ); ?>"
                     target="_blank" rel="noopener">
                    <?php if ( $m['image'] !== '' ) : ?>
                      <img src="<?php echo esc_url( $m['image'] ); ?>" alt="<?php echo esc_attr( $m['name'] ); ?>" loading="lazy" />
                    <?php endif; ?>
                    <div class="pg-mitra-name"><?php echo esc_html( $m['name'] ); ?></div>
                  </a>
                <?php endforeach; ?>
              </div>
            <?php elseif ( $use_default !== '1' ) : ?>
              <div class="pg-empty" style="background:#f6f8fc;padding:1rem;border-radius:8px;color:var(--tx-mid);font-style:italic">Belum ada data mitra. Silakan lengkapi di admin → Detail Program Studi → Mitra Industri.</div>
            <?php endif; ?>
          </div>
        </div>

        <!-- ▌ MATA KULIAH ▌ -->
        <div id="pg-panel-matakuliah" class="pg-panel">
          <div class="pg-block pg-rv">
            <span class="pg-sec-label">Kurikulum D4 Budidaya Perkebunan</span>
            <h2 class="pg-sec-title" style="margin-bottom:.6rem">Daftar <em>Mata Kuliah</em></h2>
            <p style="font-size:.93rem;color:var(--tx-mid);margin-bottom:1.6rem;line-height:1.78">Kurikulum berbasis Outcome-Based Education (OBE) dengan total <strong><?php echo esc_html( $prodi_total_credits ); ?> SKS</strong> selama <?php echo esc_html( $prodi_duration ); ?> semester, dirancang untuk menghasilkan lulusan kompeten di industri perkebunan kelapa sawit nasional.</p>

            <div class="pg-mk-tabs">
              <button class="pg-mk-tab on" onclick="pgSwMK('ganjil',this)">📘 Semester Ganjil (1, 3, 5, 7)</button>
              <button class="pg-mk-tab" onclick="pgSwMK('genap',this)">📗 Semester Genap (2, 4, 6, 8)</button>
            </div>

            <?php
            /* Default BDP curriculum: 8 semesters / 144 SKS.
               mk_ids (prodi_courses meta) is reserved for custom overrides; when empty we render canonical. */
            $mk_semesters = array(
                'ganjil' => array(
                    array( 'no' => 1, 'year' => 1, 'sks' => 20, 'courses' => array(
                        array( 'kode' => 'MPK101', 'name' => 'Pendidikan Agama & Budi Pekerti',           'sks' => 2, 'jenis' => 'Wajib' ),
                        array( 'kode' => 'MPK102', 'name' => 'Pendidikan Pancasila & Kewarganegaraan',  'sks' => 2, 'jenis' => 'Wajib' ),
                        array( 'kode' => 'MPK103', 'name' => 'Bahasa Indonesia',                        'sks' => 2, 'jenis' => 'Wajib' ),
                        array( 'kode' => 'BDP101', 'name' => 'Biologi Dasar',                           'sks' => 3, 'jenis' => 'Wajib' ),
                        array( 'kode' => 'BDP102', 'name' => 'Kimia Dasar',                             'sks' => 3, 'jenis' => 'Wajib' ),
                        array( 'kode' => 'BDP103', 'name' => 'Matematika & Statistika Dasar',           'sks' => 3, 'jenis' => 'Wajib' ),
                        array( 'kode' => 'BDP104', 'name' => 'Dasar-Dasar Agronomi',                    'sks' => 3, 'jenis' => 'Wajib' ),
                        array( 'kode' => 'BDP105', 'name' => 'Pengantar Ilmu Perkebunan',               'sks' => 2, 'jenis' => 'Wajib' ),
                    ) ),
                    array( 'no' => 3, 'year' => 2, 'sks' => 21, 'courses' => array(
                        array( 'kode' => 'BDP301', 'name' => 'Ilmu Tanah & Kesuburan Lahan',            'sks' => 3, 'jenis' => 'Wajib' ),
                        array( 'kode' => 'BDP302', 'name' => 'Fisiologi Tanaman Kelapa Sawit',          'sks' => 3, 'jenis' => 'Wajib' ),
                        array( 'kode' => 'BDP303', 'name' => 'Budidaya Kelapa Sawit I – Pembibitan',    'sks' => 3, 'jenis' => 'Wajib' ),
                        array( 'kode' => 'BDP304', 'name' => 'Pemupukan Tanaman Kelapa Sawit',          'sks' => 3, 'jenis' => 'Wajib' ),
                        array( 'kode' => 'BDP305', 'name' => 'Proteksi Tanaman Perkebunan',             'sks' => 3, 'jenis' => 'Wajib' ),
                        array( 'kode' => 'BDP306', 'name' => 'Mekanisasi Perkebunan Sawit',             'sks' => 2, 'jenis' => 'Wajib' ),
                        array( 'kode' => 'BDP307', 'name' => 'Aplikasi Komputer Pertanian',             'sks' => 2, 'jenis' => 'Wajib' ),
                        array( 'kode' => 'BDP308P','name' => 'Praktikum Identifikasi & Morfologi Tanaman','sks' => 2, 'jenis' => 'Praktik' ),
                    ) ),
                    array( 'no' => 5, 'year' => 3, 'sks' => 20, 'courses' => array(
                        array( 'kode' => 'BDP501', 'name' => 'Manajemen Kebun Kelapa Sawit',            'sks' => 3, 'jenis' => 'Wajib' ),
                        array( 'kode' => 'BDP502', 'name' => 'Pemanenan & Pascapanen Sawit',           'sks' => 3, 'jenis' => 'Wajib' ),
                        array( 'kode' => 'BDP503', 'name' => 'Manajemen SDM Perkebunan',                'sks' => 3, 'jenis' => 'Wajib' ),
                        array( 'kode' => 'BDP504', 'name' => 'Sistem Informasi Manajemen Perkebunan',  'sks' => 3, 'jenis' => 'Wajib' ),
                        array( 'kode' => 'BDP505', 'name' => 'Konservasi Lahan & Lingkungan Perkebunan', 'sks' => 2, 'jenis' => 'Wajib' ),
                        array( 'kode' => 'BDP506P','name' => 'PKL I – Praktik Kerja Lapangan',         'sks' => 4, 'jenis' => 'Praktik' ),
                        array( 'kode' => 'BDP507P','name' => 'Praktikum Manajemen Kebun',              'sks' => 2, 'jenis' => 'Praktik' ),
                    ) ),
                    array( 'no' => 7, 'year' => 4, 'sks' => 19, 'courses' => array(
                        array( 'kode' => 'BDP701', 'name' => 'Sistem Manajemen Mutu (ISO/ISPO/RSPO)',   'sks' => 3, 'jenis' => 'Wajib' ),
                        array( 'kode' => 'BDP702', 'name' => 'Perencanaan Usaha & Investasi Perkebunan','sks' => 3, 'jenis' => 'Wajib' ),
                        array( 'kode' => 'BDP703', 'name' => 'Perkebunan Sawit Berkelanjutan',          'sks' => 3, 'jenis' => 'Wajib' ),
                        array( 'kode' => 'BDP704', 'name' => 'Hukum & Regulasi Perkebunan',             'sks' => 2, 'jenis' => 'Wajib' ),
                        array( 'kode' => 'BDP705P','name' => 'PKL II – Magang Industri Perkebunan',    'sks' => 6, 'jenis' => 'Praktik' ),
                        array( 'kode' => 'BDP706', 'name' => 'Audit Lingkungan Perkebunan',             'sks' => 2, 'jenis' => 'Pilihan' ),
                    ) ),
                ),
                'genap' => array(
                    array( 'no' => 2, 'year' => 1, 'sks' => 19, 'courses' => array(
                        array( 'kode' => 'MPK201', 'name' => 'Bahasa Inggris Pertanian',                'sks' => 2, 'jenis' => 'Wajib' ),
                        array( 'kode' => 'BDP201', 'name' => 'Pengantar Ekonomi Pertanian',             'sks' => 3, 'jenis' => 'Wajib' ),
                        array( 'kode' => 'BDP202', 'name' => 'Klimatologi & Hidrologi Pertanian',       'sks' => 3, 'jenis' => 'Wajib' ),
                        array( 'kode' => 'BDP203', 'name' => 'Botani & Taksonomi Tanaman',              'sks' => 3, 'jenis' => 'Wajib' ),
                        array( 'kode' => 'BDP204', 'name' => 'Genetika & Pemuliaan Tanaman Sawit',     'sks' => 3, 'jenis' => 'Wajib' ),
                        array( 'kode' => 'BDP205', 'name' => 'Keselamatan & Kesehatan Kerja (K3)',      'sks' => 2, 'jenis' => 'Wajib' ),
                        array( 'kode' => 'BDP206P','name' => 'Praktikum Biologi & Kimia Dasar',        'sks' => 3, 'jenis' => 'Praktik' ),
                    ) ),
                    array( 'no' => 4, 'year' => 2, 'sks' => 21, 'courses' => array(
                        array( 'kode' => 'BDP401', 'name' => 'Budidaya Kelapa Sawit II – TBM & TM',    'sks' => 3, 'jenis' => 'Wajib' ),
                        array( 'kode' => 'BDP402', 'name' => 'Teknologi Benih Perkebunan',             'sks' => 3, 'jenis' => 'Wajib' ),
                        array( 'kode' => 'BDP403', 'name' => 'Pengelolaan Gulma Perkebunan Sawit',     'sks' => 3, 'jenis' => 'Wajib' ),
                        array( 'kode' => 'BDP404', 'name' => 'Survey & Pemetaan Lahan Perkebunan',     'sks' => 3, 'jenis' => 'Wajib' ),
                        array( 'kode' => 'BDP405', 'name' => 'Irigasi & Drainase Perkebunan',          'sks' => 2, 'jenis' => 'Wajib' ),
                        array( 'kode' => 'BDP406', 'name' => 'Sosio-Ekonomi Masyarakat Perkebunan',     'sks' => 2, 'jenis' => 'Wajib' ),
                        array( 'kode' => 'BDP407P','name' => 'Praktikum Budidaya Kelapa Sawit',        'sks' => 3, 'jenis' => 'Praktik' ),
                        array( 'kode' => 'BDP408', 'name' => 'Metodologi Penelitian',                  'sks' => 2, 'jenis' => 'Wajib' ),
                    ) ),
                    array( 'no' => 6, 'year' => 3, 'sks' => 20, 'courses' => array(
                        array( 'kode' => 'BDP601', 'name' => 'Pengelolaan Limbah & Lingkungan Perkebunan','sks' => 3, 'jenis' => 'Wajib' ),
                        array( 'kode' => 'BDP602', 'name' => 'GIS & Remote Sensing Pertanian',         'sks' => 3, 'jenis' => 'Wajib' ),
                        array( 'kode' => 'BDP603', 'name' => 'Ekonomi Manajerial Perkebunan',          'sks' => 3, 'jenis' => 'Wajib' ),
                        array( 'kode' => 'BDP604', 'name' => 'Anggaran & Biaya Budidaya Kelapa Sawit', 'sks' => 3, 'jenis' => 'Wajib' ),
                        array( 'kode' => 'BDP605P','name' => 'KKN – Pengabdian Masyarakat',            'sks' => 4, 'jenis' => 'Praktik' ),
                        array( 'kode' => 'BDP606', 'name' => 'Kewirausahaan Perkebunan',               'sks' => 2, 'jenis' => 'Pilihan' ),
                        array( 'kode' => 'BDP607', 'name' => 'Teknologi Informasi untuk Pertanian',    'sks' => 2, 'jenis' => 'Pilihan' ),
                    ) ),
                    array( 'no' => 8, 'year' => 4, 'sks' => 12, 'courses' => array(
                        array( 'kode' => 'BDP801', 'name' => 'Seminar Proposal Penelitian',            'sks' => 2, 'jenis' => 'Wajib' ),
                        array( 'kode' => 'BDP802', 'name' => 'Kapita Selekta Perkebunan',              'sks' => 2, 'jenis' => 'Wajib' ),
                        array( 'kode' => 'BDP803', 'name' => 'Etika Profesi & Hukum Ketenagakerjaan',  'sks' => 2, 'jenis' => 'Wajib' ),
                        array( 'kode' => 'BDP804P','name' => 'Skripsi / Tugas Akhir',                  'sks' => 6, 'jenis' => 'Praktik' ),
                    ) ),
                ),
            );
            ?>

            <div id="pg-mk-ganjil" class="pg-mk-panel on">
              <?php if ( $mk_use_repeater && ! empty( $mk_semesters_normalized ) ) : ?>
                <?php foreach ( $mk_semesters_normalized as $sem ) :
                  $is_ganjil = ( strtolower( (string) ( $sem['tipe'] ?? '' ) ) === 'ganjil' || (int) ( $sem['no'] ?? 0 ) % 2 === 1 );
                  if ( ! $is_ganjil ) { continue; }
                  $_sem_courses = is_array( $sem['courses'] ?? null ) ? $sem['courses'] : array();
                  $_sks          = isset( $sem['sks'] ) ? (int) $sem['sks'] : 0;
                  $_year         = (int) ( $sem['no'] ?? 1 ) <= 4 ? 1 : 0;
                  $_year         = (int) ceil( ( (int) ( $sem['no'] ?? 1 ) ) / 2 );
                ?>
                  <div class="pg-mk-sem">
                    <div class="pg-mk-sem-head pg-msh-g">
                      <div class="pg-mk-sem-ic">📘</div>
                      <div>
                        <div class="pg-mk-sem-title">Semester <?php echo (int) ( $sem['no'] ?? 0 ); ?></div>
                        <div class="pg-mk-sem-sub">Tahun <?php echo (int) $_year; ?> · Semester Ganjil · <?php echo (int) $_sks; ?> SKS</div>
                      </div>
                      <div class="pg-mk-sks-total"><?php echo (int) $_sks; ?> <span>SKS</span></div>
                    </div>
                    <div class="pg-mk-tbl-wrap">
                      <table class="pg-mk-tbl">
                        <thead><tr><th>Kode</th><th>Mata Kuliah</th><th>SKS</th><th>Jenis</th></tr></thead>
                        <tbody>
                          <?php foreach ( $_sem_courses as $c ) :
                            $sks_class_outer = 'pg-sks-g';
                            $jenis_class_outer = ( ( $c['jenis'] ?? '' ) === 'Wajib' ) ? 'pg-mkw' : ( ( $c['jenis'] ?? '' === 'Pilihan' ) ? 'pg-mkp' : 'pg-mkl' );
                          ?>
                            <tr>
                              <td class="pg-mk-kode"><?php echo esc_html( (string) ( $c['kode'] ?? '' ) ); ?></td>
                              <td><?php echo esc_html( (string) ( $c['name'] ?? '' ) ); ?></td>
                              <td><div class="pg-sks <?php echo esc_attr( $sks_class_outer ); ?>"><?php echo (int) ( $c['sks'] ?? 0 ); ?></div></td>
                              <td><span class="pg-mk-badge <?php echo esc_attr( $jenis_class_outer ); ?>"><?php echo esc_html( (string) ( $c['jenis'] ?? '' ) ); ?></span></td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php else : ?>
                <?php foreach ( $mk_semesters['ganjil'] as $sem ) : ?>
                  <div class="pg-mk-sem">
                    <div class="pg-mk-sem-head pg-msh-g">
                      <div class="pg-mk-sem-ic">📘</div>
                      <div>
                        <div class="pg-mk-sem-title">Semester <?php echo (int) $sem['no']; ?></div>
                        <div class="pg-mk-sem-sub">Tahun <?php echo (int) $sem['year']; ?> · Semester Ganjil · <?php echo (int) $sem['sks']; ?> SKS</div>
                      </div>
                      <div class="pg-mk-sks-total"><?php echo (int) $sem['sks']; ?> <span>SKS</span></div>
                    </div>
                    <div class="pg-mk-tbl-wrap">
                      <table class="pg-mk-tbl">
                        <thead><tr><th>Kode</th><th>Mata Kuliah</th><th>SKS</th><th>Jenis</th></tr></thead>
                        <tbody>
                          <?php foreach ( $sem['courses'] as $c ) :
                            $sks_class = ( $sem['no'] % 2 === 1 ) ? 'pg-sks-g' : 'pg-sks-e';
                            $jenis_class = ( $c['jenis'] === 'Wajib' ) ? 'pg-mkw' : ( ( $c['jenis'] === 'Pilihan' ) ? 'pg-mkp' : 'pg-mkl' );
                          ?>
                            <tr>
                              <td class="pg-mk-kode"><?php echo esc_html( $c['kode'] ); ?></td>
                              <td><?php echo esc_html( $c['name'] ); ?></td>
                              <td><div class="pg-sks <?php echo esc_attr( $sks_class ); ?>"><?php echo (int) $c['sks']; ?></div></td>
                              <td><span class="pg-mk-badge <?php echo esc_attr( $jenis_class ); ?>"><?php echo esc_html( $c['jenis'] ); ?></span></td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>

            <div id="pg-mk-genap" class="pg-mk-panel">
              <?php if ( $mk_use_repeater && ! empty( $mk_semesters_normalized ) ) : ?>
                <?php foreach ( $mk_semesters_normalized as $sem ) :
                  $is_genap = ( strtolower( (string) ( $sem['tipe'] ?? '' ) ) === 'genap' || (int) ( $sem['no'] ?? 0 ) % 2 === 0 );
                  if ( ! $is_genap ) { continue; }
                  $_sem_courses = is_array( $sem['courses'] ?? null ) ? $sem['courses'] : array();
                  $_sks          = isset( $sem['sks'] ) ? (int) $sem['sks'] : 0;
                  $_year         = (int) ceil( ( (int) ( $sem['no'] ?? 1 ) ) / 2 );
                ?>
                  <div class="pg-mk-sem">
                    <div class="pg-mk-sem-head pg-msh-e">
                      <div class="pg-mk-sem-ic">📗</div>
                      <div>
                        <div class="pg-mk-sem-title">Semester <?php echo (int) ( $sem['no'] ?? 0 ); ?></div>
                        <div class="pg-mk-sem-sub">Tahun <?php echo (int) $_year; ?> · Semester Genap · <?php echo (int) $_sks; ?> SKS</div>
                      </div>
                      <div class="pg-mk-sks-total"><?php echo (int) $_sks; ?> <span>SKS</span></div>
                    </div>
                    <div class="pg-mk-tbl-wrap">
                      <table class="pg-mk-tbl">
                        <thead><tr><th>Kode</th><th>Mata Kuliah</th><th>SKS</th><th>Jenis</th></tr></thead>
                        <tbody>
                          <?php foreach ( $_sem_courses as $c ) :
                            $sks_class_outer = 'pg-sks-e';
                            $jenis_class_outer = ( ( $c['jenis'] ?? '' ) === 'Wajib' ) ? 'pg-mkw' : ( ( $c['jenis'] ?? '' ) === 'Pilihan' ? 'pg-mkp' : 'pg-mkl' );
                          ?>
                            <tr>
                              <td class="pg-mk-kode"><?php echo esc_html( (string) ( $c['kode'] ?? '' ) ); ?></td>
                              <td><?php echo esc_html( (string) ( $c['name'] ?? '' ) ); ?></td>
                              <td><div class="pg-sks <?php echo esc_attr( $sks_class_outer ); ?>"><?php echo (int) ( $c['sks'] ?? 0 ); ?></div></td>
                              <td><span class="pg-mk-badge <?php echo esc_attr( $jenis_class_outer ); ?>"><?php echo esc_html( (string) ( $c['jenis'] ?? '' ) ); ?></span></td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php else : ?>
                <?php foreach ( $mk_semesters['genap'] as $sem ) : ?>
                  <div class="pg-mk-sem">
                    <div class="pg-mk-sem-head pg-msh-e">
                      <div class="pg-mk-sem-ic">📗</div>
                      <div>
                        <div class="pg-mk-sem-title">Semester <?php echo (int) $sem['no']; ?></div>
                        <div class="pg-mk-sem-sub">Tahun <?php echo (int) $sem['year']; ?> · Semester Genap · <?php echo (int) $sem['sks']; ?> SKS</div>
                      </div>
                      <div class="pg-mk-sks-total"><?php echo (int) $sem['sks']; ?> <span>SKS</span></div>
                    </div>
                    <div class="pg-mk-tbl-wrap">
                      <table class="pg-mk-tbl">
                        <thead><tr><th>Kode</th><th>Mata Kuliah</th><th>SKS</th><th>Jenis</th></tr></thead>
                        <tbody>
                          <?php foreach ( $sem['courses'] as $c ) :
                            $sks_class = ( $sem['no'] % 2 === 0 ) ? 'pg-sks-e' : 'pg-sks-g';
                            $jenis_class = ( $c['jenis'] === 'Wajib' ) ? 'pg-mkw' : ( ( $c['jenis'] === 'Pilihan' ) ? 'pg-mkp' : 'pg-mkl' );
                          ?>
                            <tr>
                              <td class="pg-mk-kode"><?php echo esc_html( $c['kode'] ); ?></td>
                              <td><?php echo esc_html( $c['name'] ); ?></td>
                              <td><div class="pg-sks <?php echo esc_attr( $sks_class ); ?>"><?php echo (int) $c['sks']; ?></div></td>
                              <td><span class="pg-mk-badge <?php echo esc_attr( $jenis_class ); ?>"><?php echo esc_html( $c['jenis'] ); ?></span></td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>

            <div class="pg-mk-legend">
              <div class="pg-mk-leg-item"><span class="pg-mk-badge pg-mkw">Wajib</span> Mata kuliah wajib</div>
              <div class="pg-mk-leg-item"><span class="pg-mk-badge pg-mkp">Pilihan</span> Mata kuliah pilihan</div>
              <div class="pg-mk-leg-item"><span class="pg-mk-badge pg-mkl">Praktik</span> Lapangan / PKL / KKN</div>
              <div class="pg-mk-total">Total Kurikulum: <?php echo esc_html( $prodi_total_credits ); ?> SKS / <?php echo esc_html( $prodi_duration ); ?> Semester</div>
            </div>
          </div>
        </div>

        <!-- ▌ BERITA ▌ -->
        <div id="pg-panel-berita" class="pg-panel">
          <div class="pg-block pg-rv">
            <span class="pg-sec-label">Berita &amp; Dokumentasi</span>
            <h2 class="pg-sec-title" style="margin-bottom:1.3rem">Kegiatan <em>Prodi <?php echo esc_html( $hero_title ); ?></em></h2>
            <div class="pg-bn-grid">
              <div class="pg-bn-card pg-rv pg-d1">
                <div class="pg-bn-img" style="background:linear-gradient(135deg,#04162E,#1459B3)">🏭<span class="pg-bn-cat">Kegiatan</span></div>
                <div class="pg-bn-body">
                  <div class="pg-bn-date">📅 1–2 November 2023</div>
                  <div class="pg-bn-title">Siapkan Mahasiswa Terjun ke Industri, ITSI Gelar Bimbingan Teknis PKL II TA 2023/2024</div>
                  <div class="pg-bn-foot">
                    <div class="pg-bn-meta">👤 Tim Humas ITSI</div>
                    <div class="pg-bn-meta">👁 1.245 views</div>
                    <div class="pg-shr">
                      <button class="pg-shr-btn" title="Share WhatsApp" onclick="pgShare('wa',event)">📱</button>
                      <button class="pg-shr-btn" title="Copy link" onclick="pgCopyLnk(event)">🔗</button>
                    </div>
                  </div>
                </div>
              </div>
              <div class="pg-bn-card pg-rv pg-d2">
                <div class="pg-bn-img" style="background:linear-gradient(135deg,#08274F,#0C3D7A)">🌱<span class="pg-bn-cat">Kegiatan</span></div>
                <div class="pg-bn-body">
                  <div class="pg-bn-date">📅 6–7 November 2024</div>
                  <div class="pg-bn-title">ITSI Selenggarakan Bimbingan Teknis PKL II Semester Ganjil TA 2024/2025 untuk Penguatan Kompetensi Mahasiswa</div>
                  <div class="pg-bn-foot">
                    <div class="pg-bn-meta">👤 Tim Humas ITSI</div>
                    <div class="pg-bn-meta">👁 987 views</div>
                    <div class="pg-shr">
                      <button class="pg-shr-btn" title="Share WhatsApp" onclick="pgShare('wa',event)">📱</button>
                      <button class="pg-shr-btn" title="Copy link" onclick="pgCopyLnk(event)">🔗</button>
                    </div>
                  </div>
                </div>
              </div>
              <div class="pg-bn-card pg-rv pg-d3">
                <div class="pg-bn-img" style="background:linear-gradient(135deg,#032b14,#0a5c2e)">🤝<span class="pg-bn-cat" style="background:#0a5c2e">Pengabdian</span></div>
                <div class="pg-bn-body">
                  <div class="pg-bn-date">📅 20 Juni 2024</div>
                  <div class="pg-bn-title">Perkuat Kompetensi dan Kepedulian Sosial, Fakultas Vokasi Laksanakan Pembekalan Pengabdian Masyarakat TA 2024/2025</div>
                  <div class="pg-bn-foot">
                    <div class="pg-bn-meta">👤 Humas Fak. Vokasi</div>
                    <div class="pg-bn-meta">👁 832 views</div>
                    <div class="pg-shr">
                      <button class="pg-shr-btn" title="Share WhatsApp" onclick="pgShare('wa',event)">📱</button>
                      <button class="pg-shr-btn" title="Copy link" onclick="pgCopyLnk(event)">🔗</button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div style="text-align:center;margin-top:2rem">
              <a href="<?php echo esc_url( home_url( '/berita/' ) ); ?>" style="display:inline-flex;align-items:center;gap:.5rem;padding:.75rem 1.6rem;border-radius:11px;border:1.5px solid #CEE2FF;color:var(--royal);font-size:.85rem;font-weight:700;transition:all .25s" onmouseover="this.style.background='var(--frost)';this.style.borderColor='var(--azure)'" onmouseout="this.style.background='';this.style.borderColor='#CEE2FF'">Lihat Semua Berita &amp; Kegiatan →</a>
            </div>
          </div>
        </div>

        <!-- ▌ PRESTASI ▌ -->
        <div id="pg-panel-prestasi" class="pg-panel">
          <div class="pg-block pg-rv">
            <span class="pg-sec-label">Prestasi Mahasiswa</span>
            <h2 class="pg-sec-title" style="margin-bottom:.6rem">Pencapaian <em>Mahasiswa</em></h2>
            <p style="font-size:.93rem;color:var(--tx-mid);margin-bottom:1.5rem;line-height:1.78">Daftar prestasi akademik &amp; non-akademik mahasiswa Program Studi <?php echo esc_html( $hero_title ); ?>.</p>
            <?php if ( ! empty( $prestasi ) ) : ?>
              <div class="pg-prestasi-grid">
                <?php foreach ( $prestasi as $i => $p ) : ?>
                  <div class="pg-prestasi-card pg-rv pg-d<?php echo esc_attr( ( $i % 6 ) + 1 ); ?>">
                    <div class="pg-prestasi-year"><?php echo esc_html( $p['year'] ); ?></div>
                    <div class="pg-prestasi-title"><?php echo esc_html( $p['title'] ); ?></div>
                    <p class="pg-prestasi-desc"><?php echo esc_html( $p['desc'] ); ?></p>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php elseif ( $use_default !== '1' ) : ?>
              <div class="pg-empty" style="background:#f6f8fc;padding:1rem;border-radius:8px;color:var(--tx-mid);font-style:italic">Belum ada data prestasi. Silakan lengkapi di admin → Detail Program Studi → Prestasi Mahasiswa.</div>
            <?php endif; ?>
          </div>
        </div>

        <!-- ▌ CPL — Capaian Pembelajaran Lulusan ▌ -->
        <div id="pg-panel-cpl" class="pg-panel">
          <div class="pg-block pg-rv">
            <span class="pg-sec-label">Capaian Pembelajaran Lulusan (CPL)</span>
            <h2 class="pg-sec-title" style="margin-bottom:.6rem">Kompetensi <em>Lulusan</em></h2>
            <p style="font-size:.93rem;color:var(--tx-mid);margin-bottom:1.5rem;line-height:1.78">Kompetensi yang dikuasai lulusan Program Studi <?php echo esc_html( $hero_title ); ?> sesuai standar KKNI level 6 &amp; OBE.</p>
            <?php
            $cpl_any = ( $cpl_pengetahuan !== '' || $cpl_keterampilan !== '' || $cpl_sikap !== '' );
            ?>
            <?php if ( $cpl_any ) : ?>
              <div class="pg-cpl-grid">
                <?php if ( $cpl_pengetahuan !== '' ) : ?>
                  <div class="pg-cpl-card pg-rv pg-d1">
                    <div class="pg-cpl-ic">📚</div>
                    <div class="pg-cpl-title">Pengetahuan</div>
                    <div class="pg-cpl-body"><?php echo wpautop( wp_kses_post( $cpl_pengetahuan ) ); ?></div>
                  </div>
                <?php endif; ?>
                <?php if ( $cpl_keterampilan !== '' ) : ?>
                  <div class="pg-cpl-card pg-rv pg-d2">
                    <div class="pg-cpl-ic">🛠️</div>
                    <div class="pg-cpl-title">Keterampilan Khusus</div>
                    <div class="pg-cpl-body"><?php echo wpautop( wp_kses_post( $cpl_keterampilan ) ); ?></div>
                  </div>
                <?php endif; ?>
                <?php if ( $cpl_sikap !== '' ) : ?>
                  <div class="pg-cpl-card pg-rv pg-d3">
                    <div class="pg-cpl-ic">🌟</div>
                    <div class="pg-cpl-title">Sikap &amp; Tanggung Jawab</div>
                    <div class="pg-cpl-body"><?php echo wpautop( wp_kses_post( $cpl_sikap ) ); ?></div>
                  </div>
                <?php endif; ?>
              </div>
            <?php elseif ( $use_default !== '1' ) : ?>
              <div class="pg-empty" style="background:#f6f8fc;padding:1rem;border-radius:8px;color:var(--tx-mid);font-style:italic">Belum ada CPL. Silakan lengkapi di admin → Detail Program Studi → Capaian Pembelajaran Lulusan.</div>
            <?php endif; ?>
          </div>
        </div>

        <!-- ▌ TESTIMONI ALUMNI ▌ -->
        <div id="pg-panel-testimoni" class="pg-panel">
          <div class="pg-block pg-rv">
            <span class="pg-sec-label">Kata Alumni</span>
            <h2 class="pg-sec-title" style="margin-bottom:.6rem">Suara <em>Lulusan</em></h2>
            <p style="font-size:.93rem;color:var(--tx-mid);margin-bottom:1.5rem;line-height:1.78">Apa kata alumni Program Studi <?php echo esc_html( $hero_title ); ?> tentang perjalanan karier mereka setelah lulus.</p>
            <?php if ( ! empty( $testimoni ) ) : ?>
              <div class="pg-testi-grid">
                <?php foreach ( $testimoni as $i => $t ) : ?>
                  <div class="pg-testi-card pg-rv pg-d<?php echo esc_attr( ( $i % 6 ) + 1 ); ?>">
                    <div class="pg-testi-quote">"</div>
                    <p class="pg-testi-text"><?php echo esc_html( $t['text'] ); ?></p>
                    <div class="pg-testi-meta">
                      <div class="pg-testi-name"><?php echo esc_html( $t['name'] ); ?></div>
                      <div class="pg-testi-position"><?php echo esc_html( $t['position'] ); ?></div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php elseif ( $use_default !== '1' ) : ?>
              <div class="pg-empty" style="background:#f6f8fc;padding:1rem;border-radius:8px;color:var(--tx-mid);font-style:italic">Belum ada testimoni alumni. Silakan lengkapi di admin → Detail Program Studi → Testimoni Alumni.</div>
            <?php endif; ?>
          </div>
        </div>

      </div><!-- /content -->
    </div><!-- /page-layout -->
  </div><!-- /container -->
</div><!-- /main-bg -->

<script>
/* ─── BDP page interactions (sidebar tabs, dosen filter, mk tabs, scroll-reveal) ─── */
function pgSw(panel, btn){
  document.querySelectorAll('.pg-panel').forEach(function(p){ p.classList.remove('on'); });
  document.querySelectorAll('.pg-side-btn').forEach(function(b){ b.classList.remove('on'); });
  var el = document.getElementById('pg-panel-' + panel);
  if (el) el.classList.add('on');
  if (btn) btn.classList.add('on');
  try {
    var tgt = el || document.querySelector('.pg-page-layout');
    if (tgt) tgt.scrollIntoView({ behavior: 'smooth', block: 'start' });
  } catch(e) {}
}
function pgSwMK(which, btn){
  document.querySelectorAll('.pg-mk-panel').forEach(function(p){ p.classList.remove('on'); });
  document.querySelectorAll('.pg-mk-tab').forEach(function(b){ b.classList.remove('on'); });
  var el = document.getElementById('pg-mk-' + which);
  if (el) el.classList.add('on');
  if (btn) btn.classList.add('on');
}
function pgFD(btn, lvl){
  document.querySelectorAll('.pg-df-chip').forEach(function(c){ c.classList.remove('on'); });
  if (btn) btn.classList.add('on');
  document.querySelectorAll('#pg-dGrid .pg-dc').forEach(function(c){
    var v = c.getAttribute('data-lvl');
    c.style.display = (lvl === 'all' || v === lvl) ? '' : 'none';
  });
}
function pgShare(channel, e){
  if (e) e.stopPropagation();
  var url = encodeURIComponent(window.location.href);
  var text = encodeURIComponent(document.title);
  if (channel === 'wa') window.open('https://wa.me/?text=' + text + '%20' + url, '_blank');
}
function pgCopyLnk(e){
  if (e) e.stopPropagation();
  var url = window.location.href;
  if (navigator.clipboard) { navigator.clipboard.writeText(url); }
  else { var t = document.createElement('textarea'); t.value = url; document.body.appendChild(t); t.select(); document.execCommand('copy'); document.body.removeChild(t); }
}
(function(){
  if (!('IntersectionObserver' in window)) { document.querySelectorAll('.pg-rv').forEach(function(e){ e.classList.add('on'); }); return; }
  var io = new IntersectionObserver(function(entries){
    entries.forEach(function(en){ if (en.isIntersecting) { en.target.classList.add('on'); io.unobserve(en.target); } });
  }, { threshold: 0.12 });
  document.querySelectorAll('.pg-rv').forEach(function(el){ io.observe(el); });
})();
</script>

<?php endwhile; ?>
<?php get_footer(); ?>