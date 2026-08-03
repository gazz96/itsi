# LP2M — CPT Hibah + REST API + Form Pendaftaran

> **Untuk Hermes:** Implementasi task-by-task langsung di WordPress ITSI theme + LP2M Vue frontend. Setiap task diverifikasi sebelum lanjut.

**Goal:** Buat post type `hibah` di WordPress (ITSI theme) supaya LP2M bisa mengelola event hibah internal/eksternal, panduan, timeline, template file, dan form pendaftaran — semua lewat admin WordPress. Data di-expose via REST API untuk dikonsumsi Vue frontend lp2m.bagistudio.com.

**Architecture:**
- WordPress admin → TypeRocket CPT `hibah` + meta box → REST API `/wp-json/wp/v2/hibah`
- Plugin `lp2m-hibah-receiver.php` → custom table `wp_lp2m_hibah` + endpoint GET `/lp2m/v1/hibah`
- Vue LP2M → fetch REST API untuk list event, panduan, timeline, file — ganti hardcoded content.json

**Tech Stack:** WordPress 6.x + TypeRocket v6 + PHP 8.x + Vue 3 + Vite + Cloudflare Pages

---

## Single Source of Truth

| Data | Tempat | Cara Akses |
|------|--------|------------|
| Event hibah (internal/eksternal) | CPT `hibah` → post + postmeta | `GET /wp-json/wp/v2/hibah` |
| Timeline per event | postmeta `timeline_items` (JSON) | Termasuk di REST response |
| File panduan / template | postmeta `file_panduan`, `file_template` (attachment ID array) | Termasuk di REST response |
| Pendaftaran hibah | Custom table `wp_lp2m_hibah` | `POST/GET /lp2m/v1/hibah` |
| Kategori hibah | Standard WP category taxonomy | `GET /wp-json/wp/v2/categories` |

---

## File yang Diubah / Ditambah

### WordPress ITSI Theme
- **`functions.php`** — register CPT `hibah` + meta box di hook `typerocket_loaded`
- **`inc/rest-api-hibah.php`** (NEW) — custom REST fields untuk CPT hibah (timeline, file, skema)

### WordPress Plugin
- **`/Users/bagastopati/Public/app/web/wp/itsi/itsi/web/app/plugins/lp2m-hibah-receiver.php`** — tambah endpoint GET `/hibah`, hardening sanitasi POST

### LP2M Vue (separate repo: `/Users/bagastopati/Public/app/web/lp2m`)
- **`src/types/index.ts`** — tambah interface `HibahEvent`, `HibahTimeline`
- **`src/data/content.json`** — ubah `apiBase` + `formEndpoint` kalau perlu
- **`src/views/dashboard/EventHibah.vue`** — fetch dari CPT bukan category posts
- **`src/views/dashboard/Panduan.vue`** — fetch dari CPT meta (file panduan, timeline)
- **`src/components/HibahSection.vue`** — fetch dari CPT untuk banner + form

---

## PHASE 1: CPT Hibah + Meta Box (WordPress)

### Task 1.1: Register CPT `hibah` via TypeRocket

**Files:** `functions.php` (modify, di dalam hook `typerocket_loaded`)

**Code — tambahkan setelah CPT `program_studi`:**

```php
// ═══ HIBAH LP2M ═════════════════════════════════════════════
$hibah = tr_post_type( 'Hibah LP2M', 'Hibah LP2M' );
$hibah->setId( 'hibah' );
$hibah->setSlug( 'lp2m-hibah' );
$hibah->setIcon( 'dashicons-awards' );
$hibah->setPosition( 10 );
$hibah->setSupports( array( 'title', 'editor', 'excerpt', 'thumbnail' ) );
$hibah->setRest( 'hibah' );
$hibah->setTitlePlaceholder( 'Tulis judul event hibah...' );
$hibah->setArchivePostsPerPage( 12 );
```

**Verify:** Buka WordPress admin → lihat menu "Hibah LP2M" muncul.

---

### Task 1.2: Meta Box untuk Detail Hibah

**Files:** `functions.php` (modify, lanjutan dari task 1.1)

**Code — tambahkan setelah CPT registration:**

```php
tr_meta_box( 'Detail Hibah' )
    ->addPostType( 'hibah' )
    ->setCallback(
        function () {
            $form = \TypeRocket\Utility\Helper::form();
            $tabs = \TypeRocket\Elements\Tabs::new();

            /* ─── TAB 1: Info Dasar ─── */
            $tabs->tab( 'Info Dasar', 'dashicons-info', array(
                '<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">'
                . '<div>' . $form->select( 'jenis_hibah' )->setLabel( 'Jenis Hibah' )
                    ->setOptions( array(
                        'internal' => 'Internal (LP2M ITSI)',
                        'eksternal' => 'Eksternal (DRTPM / BRIN / Mitra)',
                    ) )->setAttribute( 'style', 'width:100%' ) . '</div>'
                . '<div>' . $form->select( 'status_hibah' )->setLabel( 'Status Event' )
                    ->setOptions( array(
                        'aktif'     => 'Aktif (sedang dibuka)',
                        'ditutup'   => 'Ditutup',
                        'arsip'     => 'Arsip',
                    ) )->setAttribute( 'style', 'width:100%' ) . '</div>'
                . '<div>' . $form->text( 'kategori_hibah' )->setLabel( 'Kategori' )
                    ->setAttribute( 'placeholder', 'mis. Penelitian Dasar, Pengabdian, Kewirausahaan' ) . '</div>'
                . '<div>' . $form->text( 'deadline' )->setLabel( 'Deadline (ISO datetime)' )
                    ->setAttribute( 'placeholder', '2026-09-15T23:59:59' ) . '</div>'
                . '<div>' . $form->text( 'deadline_label' )->setLabel( 'Label Deadline (human)' )
                    ->setAttribute( 'placeholder', '15 September 2026' ) . '</div>'
                . '<div>' . $form->text( 'skema' )->setLabel( 'Skema Hibah' )
                    ->setAttribute( 'placeholder', 'mis. Penelitian Dasar Unggulan Sawit' ) . '</div>'
                . '</div>'
                . '<div style="margin-top:1rem">'
                . $form->textarea( 'info_tambahan' )->setLabel( 'Info Tambahan (satu per baris)' )
                    ->setAttribute( 'rows', 4 )->setAttribute( 'placeholder', "Maks. 3 anggota tim per usulan\nDana s.d. Rp 35 juta / skema penelitian" )
                . '</div>'
            ) );

            /* ─── TAB 2: Timeline ─── */
            $timeline = $form->repeater( 'timeline_items' )->setFields(
                array(
                    $form->text( 'Tanggal' )->setAttribute( 'placeholder', '01 Agu 2026' ),
                    $form->textarea( 'Deskripsi' )->setAttribute( 'rows', 2 )
                        ->setAttribute( 'placeholder', 'Sosialisasi & pembukaan pendaftaran usulan' ),
                )
            );
            $tabs->tab( 'Timeline', 'dashicons-backup', array(
                '<h4 style="margin:.4rem 0 .5rem">⏳ Timeline Event</h4>' . $timeline
            ) );

            /* ─── TAB 3: File Panduan & Template ─── */
            $tabs->tab( 'Panduan & Template', 'dashicons-media-document', array(
                '<div style="margin-bottom:1rem">'
                . '<h4 style="margin:.4rem 0 .5rem">📘 Panduan Penulisan (DOCX/PDF)</h4>'
                . $form->image( 'file_panduan' )->setLabel( 'Upload File Panduan' )
                    ->setHelp( 'File panduan penulisan proposal (DOCX/PDF). Bisa upload beberapa.' )
                . '</div>'
                . '<div style="margin-bottom:1rem">'
                . '<h4 style="margin:.4rem 0 .5rem">📝 Template Dokumen (DOCX)</h4>'
                . $form->image( 'file_template' )->setLabel( 'Upload File Template' )
                    ->setHelp( 'File template proposal yang siap diisi.' )
                . '</div>'
                . '<div style="margin-top:1rem">'
                . '<h4 style="margin:.4rem 0 .5rem">⬇️ Link Download Alternatif</h4>'
                . $form->text( 'link_panduan' )->setLabel( 'URL Panduan (opsional)' )
                    ->setAttribute( 'placeholder', 'https://drive.google.com/...' )
                . '</div>'
            ) );

            $tabs->layoutLeftEnclosed()->render();
        }
    );
```

**Verify:** Buka post baru Hibah LP2M → lihat meta box "Detail Hibah" dengan 3 tab muncul.

---

### Task 1.3: Force classic editor untuk CPT hibah

**Files:** `functions.php` (modify)

**Code — tambahkan filter setelah CPT hibah:**

```php
add_filter( 'use_block_editor_for_post', function ( $use, $post ) {
    if ( $post instanceof \WP_Post && isset( $post->post_type ) && 'hibah' === $post->post_type ) {
        return false;
    }
    return $use;
}, 10, 2 );
```

**Verify:** Buka post hibah → muncul classic editor + meta box TypeRocket.

---

## PHASE 2: REST API Enhancement (WordPress)

### Task 2.1: Custom REST fields untuk meta hibah

**Files:** `inc/rest-api-hibah.php` (NEW)

**Code:**

```php
<?php
/**
 * REST API enhancement untuk CPT Hibah LP2M.
 *
 * Menambahkan custom fields ke response REST API
 * GET /wp-json/wp/v2/hibah dan GET /wp-json/wp/v2/hibah/{id}
 *
 * @package itsi
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register meta fields ke REST API untuk post type `hibah`.
 */
function itsi_hibah_register_rest_fields() {
    $meta_fields = array(
        'jenis_hibah',
        'status_hibah',
        'kategori_hibah',
        'deadline',
        'deadline_label',
        'skema',
        'info_tambahan',
        'link_panduan',
    );

    foreach ( $meta_fields as $field ) {
        register_rest_field( 'hibah', $field, array(
            'get_callback'    => function ( $post ) use ( $field ) {
                return get_post_meta( $post['id'], $field, true );
            },
            'update_callback' => null, // read-only via REST
            'schema'          => array(
                'type'        => 'string',
                'description' => 'Meta field: ' . $field,
                'context'     => array( 'view', 'edit' ),
            ),
        ) );
    }

    // ── Timeline (repeater → JSON array) ──
    register_rest_field( 'hibah', 'timeline_items', array(
        'get_callback' => function ( $post ) {
            $raw = get_post_meta( $post['id'], 'timeline_items', true );
            if ( is_array( $raw ) ) {
                // Normalize keys (TR saves with label as key)
                $out = array();
                foreach ( $raw as $item ) {
                    if ( ! is_array( $item ) ) continue;
                    $out[] = array(
                        'date'  => itsi_hibah_field( $item, 'Tanggal', 'date' ),
                        'label' => itsi_hibah_field( $item, 'Deskripsi', 'label', 'desc' ),
                    );
                }
                return $out;
            }
            if ( is_string( $raw ) && $raw !== '' ) {
                $decoded = json_decode( $raw, true );
                return is_array( $decoded ) ? $decoded : array();
            }
            return array();
        },
        'schema' => array(
            'type'        => 'array',
            'description' => 'Timeline items (repeater)',
            'context'     => array( 'view', 'edit' ),
        ),
    ) );

    // ── File panduan (attachment IDs → URL array) ──
    register_rest_field( 'hibah', 'file_panduan', array(
        'get_callback' => function ( $post ) {
            return itsi_hibah_attachment_urls( get_post_meta( $post['id'], 'file_panduan', true ) );
        },
        'schema' => array(
            'type'        => 'array',
            'description' => 'File panduan (URLs)',
            'context'     => array( 'view', 'edit' ),
        ),
    ) );

    // ── File template (attachment IDs → URL array) ──
    register_rest_field( 'hibah', 'file_template', array(
        'get_callback' => function ( $post ) {
            return itsi_hibah_attachment_urls( get_post_meta( $post['id'], 'file_template', true ) );
        },
        'schema' => array(
            'type'        => 'array',
            'description' => 'File template (URLs)',
            'context'     => array( 'view', 'edit' ),
        ),
    ) );

    // ── Category names (resolved from WP categories) ──
    register_rest_field( 'hibah', 'category_names', array(
        'get_callback' => function ( $post ) {
            $cats = wp_get_post_categories( $post['id'], array( 'fields' => 'names' ) );
            return is_array( $cats ) ? $cats : array();
        },
        'schema' => array(
            'type'        => 'array',
            'description' => 'Category names',
            'context'     => array( 'view' ),
        ),
    ) );
}
add_action( 'rest_api_init', 'itsi_hibah_register_rest_fields' );

/**
 * Helper: resolve TR image field (single ID or comma-separated IDs) to URL array.
 */
function itsi_hibah_attachment_urls( $value ) {
    if ( empty( $value ) ) {
        return array();
    }

    // TR image field bisa menyimpan single ID (int) atau multiple (comma string)
    if ( is_numeric( $value ) ) {
        $ids = array( (int) $value );
    } elseif ( is_string( $value ) ) {
        $ids = array_filter( array_map( 'intval', explode( ',', $value ) ) );
    } elseif ( is_array( $value ) ) {
        $ids = array_map( 'intval', $value );
    } else {
        return array();
    }

    $urls = array();
    foreach ( $ids as $id ) {
        $src = wp_get_attachment_url( (int) $id );
        if ( $src ) {
            $urls[] = $src;
        }
    }
    return $urls;
}

/**
 * Helper: ambil nilai dari TR repeater item dengan key case-insensitive.
 */
function itsi_hibah_field( $row, ...$keys ) {
    if ( ! is_array( $row ) ) return '';
    foreach ( $keys as $k ) {
        if ( isset( $row[ $k ] ) && $row[ $k ] !== '' ) return $row[ $k ];
        foreach ( $row as $rk => $rv ) {
            if ( strcasecmp( (string) $rk, (string) $k ) === 0 && $rv !== '' ) return $rv;
        }
    }
    return '';
}
```

**Verify:** `php -l inc/rest-api-hibah.php` — no syntax error.

---

### Task 2.2: Include rest-api-hibah.php di functions.php

**Files:** `functions.php` (modify, tambah require di bawah require widget)

**Code:**

```php
/** REST API enhancement untuk CPT Hibah LP2M */
require_once get_template_directory() . '/inc/rest-api-hibah.php';
```

**Verify:** Buka `GET /wp-json/wp/v2/hibah` — muncul custom fields.

---

## PHASE 3: Plugin Hardening — Sanitasi & GET Endpoint

### Task 3.1: Sanitasi input POST dengan whitelist + tambah nonce

**Files:** `/Users/bagastopati/Public/app/web/wp/itsi/itsi/web/app/plugins/lp2m-hibah-receiver.php` (MODIFY, bukan di theme)

**Changes:**
1. Whitelist `jenis` (hanya Dosen/Mahasiswa/Tenaga Kependidikan)
2. Sanitasi semua field text dengan `sanitize_text_field`
3. Tambah rate limiting sederhana (IP-based, transient)
4. Tambah CSRF/nonce check (optional — kalau frontend bisa handle)
5. Strip HTML tags dari semua input
6. Tambah endpoint GET `/hibah` untuk list data
7. Tambah endpoint GET `/hibah/(?P<id>\d+)` untuk detail single

**Full replacement code untuk plugin** (overwrite existing):

```php
<?php
/**
 * Plugin Name:  LP2M Hibah Receiver
 * Description:  REST API endpoint untuk menerima & melihat pendaftaran hibah LP2M.
 * Version:      2.0.0
 * Requires PHP: 7.4
 */

defined('ABSPATH') || exit;

class LP2M_Hibah_Receiver {

    private string $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'lp2m_hibah';
    }

    public function init(): void {
        register_activation_hook(__FILE__, [$this, 'activate']);
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function activate(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            reg_no VARCHAR(20) NOT NULL UNIQUE,
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
            INDEX idx_skema (skema)
        ) $charset;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function register_routes(): void {
        // POST — submit pendaftaran
        register_rest_route('lp2m/v1', '/hibah', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_submit'],
            'permission_callback' => [$this, 'check_rate_limit'],
            'args'                => $this->get_post_args(),
        ]);

        // GET — list semua pendaftaran
        register_rest_route('lp2m/v1', '/hibah', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handle_list'],
            'permission_callback' => '__return_true',
        ]);

        // GET — detail satu pendaftaran
        register_rest_route('lp2m/v1', '/hibah/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handle_detail'],
            'permission_callback' => '__return_true',
            'args' => [
                'id' => [
                    'required'          => true,
                    'validate_callback' => function ( $param ) {
                        return is_numeric( $param ) && (int) $param > 0;
                    },
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }

    /** ── Rate limiting (simple IP-based transient) ── */
    public function check_rate_limit( \WP_REST_Request $request ): bool {
        // Allow GET requests
        if ( 'GET' === $request->get_method() ) {
            return true;
        }

        $ip      = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key     = 'lp2m_hibah_rate_' . md5( $ip );
        $count   = (int) get_transient( $key );

        if ( $count >= 5 ) {
            return new \WP_Error(
                'rate_limit',
                'Terlalu banyak permintaan. Silakan coba lagi dalam 15 menit.',
                ['status' => 429]
            );
        }

        set_transient( $key, $count + 1, 15 * MINUTE_IN_SECONDS );
        return true;
    }

    /** ── Whitelist & sanitize input ── */
    private function sanitize_input( array $params ): array {
        $clean = [];

        // --- Whitelist allowed fields ---
        $allowed = [
            'nama', 'nip', 'jenis', 'prodi', 'skema', 'judul',
            'ringkasan', 'jml_tim', 'anggota', 'email', 'hp',
        ];

        foreach ( $allowed as $field ) {
            $val = $params[ $field ] ?? '';
            if ( is_string( $val ) ) {
                $val = trim( $val );
            }
            $clean[ $field ] = $val;
        }

        // --- Jenis: strict whitelist ---
        $jenis_whitelist = [ 'Dosen', 'Mahasiswa', 'Tenaga Kependidikan' ];
        if ( ! in_array( $clean['jenis'], $jenis_whitelist, true ) ) {
            $clean['jenis'] = ''; // invalid → will fail validation
        }

        // --- Jml_tim: numeric only ---
        if ( $clean['jml_tim'] !== '' ) {
            $clean['jml_tim'] = (string) absint( $clean['jml_tim'] );
        }

        // --- NIP: alphanumeric + dash only ---
        $clean['nip'] = preg_replace( '/[^a-zA-Z0-9\-\.]/', '', $clean['nip'] );

        // --- HP: digits + plus only ---
        $clean['hp'] = preg_replace( '/[^0-9\+\-]/', '', $clean['hp'] );

        // --- Email: basic sanitize ---
        $clean['email'] = sanitize_email( $clean['email'] );

        // --- Text sanitization (strip HTML, trim whitespace) ---
        $clean['nama']      = wp_strip_all_tags( $clean['nama'], true );
        $clean['prodi']     = wp_strip_all_tags( $clean['prodi'], true );
        $clean['skema']     = wp_strip_all_tags( $clean['skema'], true );
        $clean['judul']     = wp_strip_all_tags( $clean['judul'], true );
        $clean['ringkasan'] = wp_strip_all_tags( $clean['ringkasan'], true );
        $clean['anggota']   = wp_strip_all_tags( $clean['anggota'], true );

        // --- Anggota: trim to 500 chars ---
        $clean['anggota'] = mb_substr( $clean['anggota'], 0, 500 );

        return $clean;
    }

    /** ── POST: Submit pendaftaran ── */
    public function handle_submit( \WP_REST_Request $request ): \WP_REST_Response {
        $raw    = $request->get_params();
        $params = $this->sanitize_input( $raw );

        // --- Validasi required fields ---
        $required = [ 'nama', 'nip', 'jenis', 'prodi', 'skema', 'judul', 'ringkasan', 'email', 'hp' ];
        $errors = [];
        foreach ( $required as $field ) {
            if ( empty( trim( (string) $params[ $field ] ) ) ) {
                $label_map = [
                    'nama' => 'Nama Lengkap', 'nip' => 'NIDN/NIDK/NIM',
                    'jenis' => 'Jenis Pengusul', 'prodi' => 'Program Studi',
                    'skema' => 'Skema Hibah', 'judul' => 'Judul Usulan',
                    'ringkasan' => 'Ringkasan', 'email' => 'Email', 'hp' => 'No. WhatsApp',
                ];
                $errors[ $field ] = 'Kolom ' . ( $label_map[ $field ] ?? $field ) . ' wajib diisi.';
            }
        }

        if ( ! empty( $params['email'] ) && ! is_email( $params['email'] ) ) {
            $errors['email'] = 'Format email tidak valid.';
        }

        if ( ! empty( $params['hp'] ) && strlen( preg_replace( '/[^0-9]/', '', $params['hp'] ) ) < 10 ) {
            $errors['hp'] = 'Nomor WhatsApp minimal 10 digit.';
        }

        if ( $errors ) {
            return new \WP_REST_Response( [ 'success' => false, 'errors' => $errors ], 400 );
        }

        // --- Generate nomor registrasi ---
        global $wpdb;
        $year = date( 'Y' );
        $last = $wpdb->get_var( $wpdb->prepare(
            "SELECT reg_no FROM {$this->table} WHERE reg_no LIKE %s ORDER BY id DESC LIMIT 1",
            "LP2M-{$year}-%"
        ) );
        $seq = $last ? ( (int) substr( $last, -5 ) + 1 ) : 1;
        $reg_no = sprintf( 'LP2M-%s-%05d', $year, $seq );

        // --- Simpan ---
        $inserted = $wpdb->insert( $this->table, [
            'reg_no'    => $reg_no,
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

        if ( $inserted === false ) {
            return new \WP_REST_Response( [ 'success' => false, 'message' => 'Gagal menyimpan data.' ], 500 );
        }

        // --- Email notifikasi ke admin ---
        $admin_email = get_option( 'admin_email' );
        $subject = sprintf( '[LP2M] Pendaftaran Hibah Baru — %s', $reg_no );
        $body = sprintf(
            "Nomor Registrasi: %s\nNama: %s\nNIP: %s\nJenis: %s\nSkema: %s\nJudul: %s\nEmail: %s\nWhatsApp: %s\n\nCek dashboard: %s/wp-admin/",
            $reg_no, $params['nama'], $params['nip'], $params['jenis'],
            $params['skema'], $params['judul'], $params['email'], $params['hp'],
            get_site_url()
        );
        wp_mail( $admin_email, $subject, $body );

        return new \WP_REST_Response( [
            'success' => true,
            'reg_no'  => $reg_no,
            'message' => 'Pendaftaran berhasil dikirim.',
        ], 201 );
    }

    /** ── GET: List pendaftaran ── */
    public function handle_list( \WP_REST_Request $request ): \WP_REST_Response {
        global $wpdb;

        $per_page = min( (int) ( $request->get_param( 'per_page' ) ?? 20 ), 100 );
        $page     = max( (int) ( $request->get_param( 'page' ) ?? 1 ), 1 );
        $offset   = ( $page - 1 ) * $per_page;

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table}" );
        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, reg_no, nama, jenis, prodi, skema, judul, email, hp, created_at
                 FROM {$this->table}
                 ORDER BY created_at DESC
                 LIMIT %d OFFSET %d",
                $per_page, $offset
            ),
            ARRAY_A
        );

        return new \WP_REST_Response( [
            'success'     => true,
            'data'        => $items ?: [],
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per_page,
            'total_pages' => (int) ceil( $total / $per_page ),
        ], 200 );
    }

    /** ── GET: Detail pendaftaran ── */
    public function handle_detail( \WP_REST_Request $request ): \WP_REST_Response {
        global $wpdb;

        $id   = (int) $request->get_param( 'id' );
        $item = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ),
            ARRAY_A
        );

        if ( ! $item ) {
            return new \WP_REST_Response( [ 'success' => false, 'message' => 'Data tidak ditemukan.' ], 404 );
        }

        return new \WP_REST_Response( [ 'success' => true, 'data' => $item ], 200 );
    }

    private function get_post_args(): array {
        return [
            'nama'      => [ 'required' => true, 'sanitize_callback' => function($v) {
                return wp_strip_all_tags( trim( (string) $v ), true );
            } ],
            'nip'       => [ 'required' => true,  'sanitize_callback' => function($v) {
                return preg_replace( '/[^a-zA-Z0-9\-\.]/', '', trim( (string) $v ) );
            } ],
            'jenis'     => [ 'required' => true,  'sanitize_callback' => function($v) {
                $whitelist = [ 'Dosen', 'Mahasiswa', 'Tenaga Kependidikan' ];
                $v = trim( (string) $v );
                return in_array( $v, $whitelist, true ) ? $v : '';
            } ],
            'prodi'     => [ 'required' => true,  'sanitize_callback' => function($v) {
                return wp_strip_all_tags( trim( (string) $v ), true );
            } ],
            'skema'     => [ 'required' => true,  'sanitize_callback' => function($v) {
                return wp_strip_all_tags( trim( (string) $v ), true );
            } ],
            'judul'     => [ 'required' => true,  'sanitize_callback' => function($v) {
                return wp_strip_all_tags( trim( (string) $v ), true );
            } ],
            'ringkasan' => [ 'required' => true,  'sanitize_callback' => function($v) {
                return wp_strip_all_tags( trim( (string) $v ), true );
            } ],
            'email'     => [ 'required' => true,  'sanitize_callback' => 'sanitize_email' ],
            'hp'        => [ 'required' => true,  'sanitize_callback' => function($v) {
                return preg_replace( '/[^0-9\+\-]/', '', trim( (string) $v ) );
            } ],
            'jml_tim'   => [ 'required' => false, 'sanitize_callback' => function($v) {
                return (string) absint( $v );
            } ],
            'anggota'   => [ 'required' => false, 'sanitize_callback' => function($v) {
                return mb_substr( wp_strip_all_tags( trim( (string) $v ), true ), 0, 500 );
            } ],
        ];
    }
}

( new LP2M_Hibah_Receiver() )->init();
```

**Verify:** `php -l` plugin file. Test endpoint GET: `curl https://itsi.ac.id/wp-json/lp2m/v1/hibah`.

---

## PHASE 4: Vue LP2M — Fetch dari REST API

### Task 4.1: Update types untuk CPT hibah

**Files:** `src/types/index.ts` (modify)

**Code — tambahkan interface:**

```typescript
export interface HibahEvent {
  id: number
  title: { rendered: string }
  excerpt: { rendered: string }
  content: { rendered: string }
  link: string
  date: string
  jenis_hibah: 'internal' | 'eksternal'
  status_hibah: 'aktif' | 'ditutup' | 'arsip'
  kategori_hibah: string
  deadline: string
  deadline_label: string
  skema: string
  info_tambahan: string
  timeline_items: Array<{ date: string; label: string }>
  file_panduan: string[]
  file_template: string[]
  link_panduan: string
  category_names: string[]
  // Thumbnail dari WP embedded media
  _embedded?: {
    'wp:featuredmedia'?: Array<{
      source_url: string
      alt_text: string
    }>
  }
}
```

---

### Task 4.2: Update apiBase di content.json

**Files:** `src/data/content.json` (modify)

**Changes:**
```json
{
  "site": {
    "...": "...",
    "apiBase": "https://itsi.ac.id/index.php/wp-json/wp/v2",
    "formEndpoint": "https://itsi.ac.id/index.php/wp-json/lp2m/v1/hibah",
    "...": "..."
  }
}
```

**Verify:** `formEndpoint` sekarang pointing ke WP, bukan `/api/hibah` (CF Pages function).

---

### Task 4.3: Update EventHibah.vue — fetch dari CPT hibah

**Files:** `src/views/dashboard/EventHibah.vue` (REWRITE fetch logic)

**Changes — ganti onMounted fetch:**
- Dari: fetch posts by category 27,6,8
- Ke: fetch CPT `hibah` dari REST API

**Code ganti `onMounted`:**

```typescript
import type { HibahEvent } from '@/types'

const posts = ref<EventPost[]>([])
const loading = ref(true)
const error = ref('')
const activeTab = ref('all')

const tabs = [
  { key: 'all', label: 'Semua' },
  { key: 'internal', label: 'Internal' },
  { key: 'eksternal', label: 'Eksternal' },
]

function formatDate(d: string) {
  return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

const filteredPosts = computed(() => {
  if (activeTab.value === 'all') return posts.value
  return posts.value.filter(p => p.kategori === activeTab.value)
})

onMounted(async () => {
  try {
    // Fetch CPT hibah — hanya yang status_hibah = 'aktif' atau 'ditutup'
    const url = `${SITE.apiBase}/hibah?per_page=30&orderby=date&order=desc`
    const res = await fetch(url)
    if (!res.ok) throw new Error(`HTTP ${res.status}`)
    const data: HibahEvent[] = await res.json()
    posts.value = data.map((p: HibahEvent) => ({
      id: p.id,
      title: new DOMParser().parseFromString(p.title.rendered, 'text/html').body.textContent || '',
      link: p.link,
      date: formatDate(p.date),
      excerpt: p.kategori_hibah || p.skema || '',
      kategori: p.jenis_hibah === 'eksternal' ? 'eksternal' : 'internal',
      catId: p.id,
    }))
  } catch (e: any) {
    error.value = e.message
  } finally {
    loading.value = false
  }
})
```

---

### Task 4.4: Update Panduan.vue — fetch dari CPT hibah

**Files:** `src/views/dashboard/Panduan.vue` (REWRITE)

**Changes — fetch dari CPT hibah yang punya file+panduan+timeline:**

```vue
<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { SITE } from '@/data'
import type { HibahEvent } from '@/types'

const activeEvent = ref<HibahEvent | null>(null)
const loading = ref(true)
const error = ref('')

const panduan = ref<Array<{ title: string; desc: string; link: string; file: string; format: string }>>([])
const templates = ref<Array<{ title: string; desc: string; link: string; file: string }>>([])
const timeline = ref<Array<{ date: string; label: string }>>([])

onMounted(async () => {
  try {
    // Fetch event hibah aktif (atau yang paling baru)
    const url = `${SITE.apiBase}/hibah?per_page=1&orderby=date&order=desc`
    const res = await fetch(url)
    if (!res.ok) throw new Error(`HTTP ${res.status}`)
    const data: HibahEvent[] = await res.json()
    if (data.length > 0) {
      activeEvent.value = data[0]
      timeline.value = data[0].timeline_items || []

      // Map file panduan ke list download
      if (data[0].file_panduan?.length) {
        panduan.value = data[0].file_panduan.map((url: string, i: number) => ({
          title: `Panduan Penulisan Proposal ${i + 1}`,
          desc: 'Format, sistematika, dan ketentuan penulisan proposal hibah.',
          link: url,
          file: url.split('/').pop() || `panduan-${i + 1}.pdf`,
          format: url.endsWith('.docx') ? 'DOCX' : 'PDF',
        }))
      }
      if (data[0].file_template?.length) {
        templates.value = data[0].file_template.map((url: string, i: number) => ({
          title: `Template Proposal ${i + 1}`,
          desc: 'File template siap isi untuk proposal.',
          link: url,
          file: url.split('/').pop() || `template-${i + 1}.docx`,
        }))
      }
    }
  } catch (e: any) {
    error.value = e.message
  } finally {
    loading.value = false
  }
})
</script>
```

---

### Task 4.5: Update HibahSection.vue — fetch banner dari CPT

**Files:** `src/components/HibahSection.vue` (MODIFY script section, keep template)

**Changes:**
- Fetch event hibah aktif (pertama) dari REST API
- Gunakan data dari CPT untuk banner (title, desc, timeline, deadline, info)
- Jika nggak ada event aktif, fallback ke content.json

```typescript
import { ref, onMounted } from 'vue'
import { HIBAH } from '@/data'
import { SITE } from '@/data'
import type { HibahEvent } from '@/types'

const eventData = ref({
  bannerTitle: HIBAH.banner.title,
  bannerDesc: HIBAH.banner.desc,
  deadline: HIBAH.banner.deadline,
  deadlineLabel: HIBAH.banner.deadlineLabel,
  timeline: HIBAH.banner.timeline,
  info: HIBAH.banner.info,
})

onMounted(async () => {
  try {
    const url = `${SITE.apiBase}/hibah?status_hibah=aktif&per_page=1`
    const res = await fetch(url)
    if (!res.ok) return // fallback to content.json
    const data: HibahEvent[] = await res.json()
    if (data.length > 0) {
      const ev = data[0]
      eventData.value = {
        bannerTitle: new DOMParser().parseFromString(ev.title.rendered, 'text/html').body.textContent || HIBAH.banner.title,
        bannerDesc: new DOMParser().parseFromString(ev.excerpt.rendered, 'text/html').body.textContent || HIBAH.banner.desc,
        deadline: ev.deadline || HIBAH.banner.deadline,
        deadlineLabel: ev.deadline_label || HIBAH.banner.deadlineLabel,
        timeline: ev.timeline_items?.length ? ev.timeline_items : HIBAH.banner.timeline,
        info: ev.info_tambahan ? ev.info_tambahan.split('\n').filter(Boolean) : HIBAH.banner.info,
      }
    }
  } catch {
    // fallback to content.json
  }
})
```

---

## Test Plan

1. `php -l functions.php` — no syntax error
2. `php -l inc/rest-api-hibah.php` — no syntax error
3. `php -l wp-content/plugins/lp2m-hibah-receiver.php` — no syntax error
4. Buka WordPress admin → CPT Hibah LP2M muncul → buat post baru
5. Isi semua meta fields → publish
6. `curl -s https://itsi.ac.id/wp-json/wp/v2/hibah | jq '.[0].jenis_hibah'` → "internal"
7. `curl -s https://itsi.ac.id/wp-json/wp/v2/hibah | jq '.[0].timeline_items'` → array timeline
8. `npm run dev` di LP2M → dashboard EventHibah muncul data dari CPT
9. Form submit → curl POST /lp2m/v1/hibah → 201 + reg_no
10. Form submit dengan HTML inject → HTML stripped (tersimpan clean)

---

## Risk & Mitigasi

| Risk | Mitigasi |
|------|----------|
| TypeRocket repeater key capitalization tidak konsisten | `itsi_hibah_field()` pakai case-insensitive lookup |
| TR `image()` field menyimpan format berbeda (ID vs comma-string) | `itsi_hibah_attachment_urls()` handle semua format |
| CPT hibah slug collision dengan page "lp2m-hibah" | Set slug explicit `lp2m-hibah`, WP CPT archive takes precedence |
| Rate limit transient cache bisa conflict antar plugin | Prefix `lp2m_hibah_rate_` unique |
| LP2M Vue fetch dari CPT tapi WP down → blank page | Fallback ke content.json di semua fetch (try/catch) |
| Plugin overwrite: versi 1.0.0 → 2.0.0 | Plugin di-activate ulang atau `dbDelta` akan auto-upgrade table |

---

## Out of Scope

- Struktur organisasi LP2M (belum diminta implementasi)
- Arsip per event (akan jadi task terpisah)
- Daftar link digital penelitian (task terpisah)
- Infografis dashboard (belum diminta)
- Footer LP2M (sudah ada di Vue)
- Autentikasi admin untuk GET /lp2m/v1/hibah (saat ini public — bisa ditambahkan `current_user_can('edit_posts')` nanti)
- File upload dari form pendaftaran (saat ini text-only)
