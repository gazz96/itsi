<?php
/**
 * Seeder: Informasi Publik / PPID documents
 *
 * Populates the `info_publik` post type with 9 taxonomy categories and 22
 * documents, matching the original `info-publik.html` landing page template.
 *
 * USAGE (from project root):
 *   wp eval-file web/app/themes/itsi/seeders/seed-info-publik.php
 *
 * Safe to re-run: existing categories and documents are skipped. To force
 * re-seed, delete the `itsi_info_publik_seeded` option first:
 *   wp option delete itsi_info_publik_seeded
 *
 * To also wipe all existing data and re-seed from scratch:
 *   wp eval-file web/app/themes/itsi/seeders/seed-info-publik.php -- --force
 */

if (!defined('ABSPATH')) {
    echo "This script must be run via WP-CLI: wp eval-file seed-info-publik.php\n";
    return;
}

$force = in_array('--force', $argv ?? [], true);

if (!post_type_exists('info_publik')) {
    echo "✗ Post type 'info_publik' is not registered. Aborting.\n";
    return;
}

if (!taxonomy_exists('kategori_info')) {
    echo "✗ Taxonomy 'kategori_info' is not registered. Aborting.\n";
    return;
}

if (!$force && get_option('itsi_info_publik_seeded')) {
    $ts = (int) get_option('itsi_info_publik_seeded');
    echo "ⓘ Info Publik already seeded on " . date('Y-m-d H:i:s', $ts) . ".\n";
    echo "  To re-run, delete the option: wp option delete itsi_info_publik_seeded\n";
    echo "  To wipe + re-seed:           wp eval-file seed-info-publik.php -- --force\n";
    return;
}

if ($force) {
    echo "⚠ --force: deleting all existing info_publik posts and kategori_info terms…\n";
    $existing = get_posts(['post_type' => 'info_publik', 'post_status' => 'any', 'posts_per_page' => -1, 'no_found_rows' => true]);
    foreach ($existing as $p) {
        wp_delete_post($p->ID, true);
    }
    $existing_terms = get_terms(['taxonomy' => 'kategori_info', 'hide_empty' => false]);
    if (!is_wp_error($existing_terms)) {
        foreach ($existing_terms as $t) {
            wp_delete_term($t->term_id, 'kategori_info');
        }
    }
    delete_option('itsi_info_publik_seeded');
}

echo "Seeding Informasi Publik data...\n\n";

// 1. Create categories ───────────────────────────────────────────────
$categories = [
    'profil-institusi' => 'Profil Institusi',
    'akademik'         => 'Akademik',
    'keuangan'         => 'Keuangan',
    'kemahasiswaan'    => 'Kemahasiswaan',
    'sdm'              => 'SDM',
    'penelitian'       => 'Penelitian',
    'akreditasi'       => 'Akreditasi',
    'kerja-sama'       => 'Kerja Sama',
    'ppid'             => 'PPID',
];

$term_ids = [];
foreach ($categories as $slug => $name) {
    $term = term_exists($slug, 'kategori_info');
    if (!$term) {
        $created = wp_insert_term($name, 'kategori_info', ['slug' => $slug]);
        if (is_wp_error($created)) {
            echo "  ✗ Error creating category $name: " . $created->get_error_message() . "\n";
            continue;
        }
        $term_ids[$slug] = (int) $created['term_id'];
        echo "  ✓ Created category: $name\n";
    } else {
        $term_ids[$slug] = (int) (is_array($term) ? $term['term_id'] : $term);
        echo "  → Category exists: $name\n";
    }
}

echo "\n";

// 2. Create documents ────────────────────────────────────────────────
$documents = [
    ['cat' => 'profil-institusi', 'title' => 'Statuta Institut Teknologi Sawit Indonesia',                              'tahun' => 2024, 'size' => '1.8 MB'],
    ['cat' => 'profil-institusi', 'title' => 'Rencana Strategis (Renstra) ITSI 2022–2026',                                'tahun' => 2022, 'size' => '2.4 MB'],
    ['cat' => 'profil-institusi', 'title' => 'Profil Singkat dan Sejarah Pendirian ITSI',                                'tahun' => 2021, 'size' => '950 KB'],
    ['cat' => 'akademik',         'title' => 'Pedoman Akademik dan Peraturan Perkuliahan',                               'tahun' => 2024, 'size' => '3.2 MB'],
    ['cat' => 'akademik',         'title' => 'Kalender Akademik Tahun 2024/2025',                                       'tahun' => 2024, 'size' => '420 KB'],
    ['cat' => 'akademik',         'title' => 'Kurikulum dan RPS Program Studi',                                          'tahun' => 2024, 'size' => '5.1 MB'],
    ['cat' => 'keuangan',         'title' => 'Laporan Keuangan Audited Tahun 2023',                                     'tahun' => 2023, 'size' => '4.7 MB'],
    ['cat' => 'keuangan',         'title' => 'RKAT (Rencana Kerja dan Anggaran Tahunan) 2025',                          'tahun' => 2025, 'size' => '1.9 MB'],
    ['cat' => 'kemahasiswaan',    'title' => 'Pedoman Kegiatan Kemahasiswaan dan Beasiswa',                             'tahun' => 2024, 'size' => '1.2 MB'],
    ['cat' => 'kemahasiswaan',    'title' => 'Laporan Kegiatan Organisasi Kemahasiswaan 2023',                         'tahun' => 2023, 'size' => '2.8 MB'],
    ['cat' => 'sdm',              'title' => 'Daftar Dosen Tetap dan Tenaga Kependidikan',                              'tahun' => 2024, 'size' => '1.5 MB'],
    ['cat' => 'sdm',              'title' => 'Formasi dan Rekrutmen Dosen Baru Tahun 2023',                             'tahun' => 2023, 'size' => '680 KB'],
    ['cat' => 'penelitian',       'title' => 'Roadmap Penelitian ITSI 2025–2030',                                        'tahun' => 2025, 'size' => '2.1 MB'],
    ['cat' => 'penelitian',       'title' => 'Laporan Penelitian dan Pengabdian kepada Masyarakat 2023',                 'tahun' => 2023, 'size' => '6.3 MB'],
    ['cat' => 'akreditasi',       'title' => 'Sertifikat Akreditasi Institusi',                                          'tahun' => 2023, 'size' => '320 KB'],
    ['cat' => 'akreditasi',       'title' => 'Sertifikat Akreditasi Program Studi',                                      'tahun' => 2025, 'size' => '410 KB'],
    ['cat' => 'akreditasi',       'title' => 'Laporan Kinerja Program Studi',                                            'tahun' => 2024, 'size' => '2.9 MB'],
    ['cat' => 'kerja-sama',       'title' => 'Daftar Mitra Kerjasama Nasional dan Internasional',                       'tahun' => 2024, 'size' => '1.7 MB'],
    ['cat' => 'kerja-sama',       'title' => 'Memorandum of Understanding (MoU) Aktif',                                 'tahun' => 2025, 'size' => '1.1 MB'],
    ['cat' => 'ppid',             'title' => 'Laporan Layanan Informasi Publik Tahun 2024',                             'tahun' => 2024, 'size' => '890 KB'],
    ['cat' => 'ppid',             'title' => 'SK Pejabat Pengelola Informasi dan Dokumentasi (PPID)',                   'tahun' => 2024, 'size' => '540 KB'],
    ['cat' => 'ppid',             'title' => 'SOP Layanan Permohonan Informasi Publik',                                 'tahun' => 2023, 'size' => '720 KB'],
];

$created = 0;
$skipped = 0;
foreach ($documents as $doc) {
    $existing = get_posts([
        'post_type'      => 'info_publik',
        'title'          => $doc['title'],
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'no_found_rows'  => true,
    ]);

    if (!empty($existing)) {
        echo "  → Document exists: {$doc['title']}\n";
        $skipped++;
        continue;
    }

    $post_id = wp_insert_post([
        'post_title'   => $doc['title'],
        'post_type'    => 'info_publik',
        'post_status'  => 'publish',
        'post_content' => 'Dokumen resmi ' . $doc['title'] . ' yang dapat diunduh oleh masyarakat umum. ' .
                          'Dokumen ini dipublikasikan sebagai bagian dari transparansi informasi publik ITSI sesuai UU KIP No. 14 Tahun 2008.',
    ]);

    if (is_wp_error($post_id)) {
        echo "  ✗ Error creating {$doc['title']}: " . $post_id->get_error_message() . "\n";
        continue;
    }

    // Assign category
    if (isset($term_ids[$doc['cat']])) {
        wp_set_object_terms($post_id, [$term_ids[$doc['cat']]], 'kategori_info');
    }

    // Set meta fields (registered in theme functions.php meta box)
    update_post_meta($post_id, 'tahun',       (int) $doc['tahun']);
    update_post_meta($post_id, 'ukuran_file', $doc['size']);
    update_post_meta($post_id, 'file_url',    '#'); // Placeholder — admin can update later

    echo "  ✓ Created: {$doc['title']}\n";
    $created++;
}

update_option('itsi_info_publik_seeded', time());

$counts = wp_count_posts('info_publik');
$term_counts = wp_count_terms(['taxonomy' => 'kategori_info', 'hide_empty' => false]);
$total_published = isset($counts->publish) ? (int) $counts->publish : 0;

echo "\n";
echo "═══════════════════════════════════════════════════\n";
echo "  ✓ Done!\n";
echo "  • Created:    $created new documents\n";
echo "  • Skipped:    $skipped existing documents\n";
echo "  • Categories: " . (is_wp_error($term_counts) ? '?' : (int) $term_counts) . "\n";
echo "  • Total published in info_publik: $total_published\n";
echo "═══════════════════════════════════════════════════\n";
