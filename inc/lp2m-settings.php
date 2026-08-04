<?php
/**
 * LP2M Settings — TypeRocket v6 (tr_form + WPOption)
 *
 * Akses: WP Admin → LP2M → Settings
 * REST API grouped by component:
 *   GET /wp-json/lp2m/v1/settings/site
 *   GET /wp-json/lp2m/v1/settings/dokumen
 *   GET /wp-json/lp2m/v1/settings/hero
 *   GET /wp-json/lp2m/v1/settings/homepage
 *   GET /wp-json/lp2m/v1/settings (semua)
 */

defined('ABSPATH') || exit;

// =================================================================
// 1. REGISTER ADMIN MENU — pakai TypeRocket Page API
// =================================================================
add_action('admin_menu', 'lp2m_tr_register_page');
function lp2m_tr_register_page()
{
    add_menu_page(
        'LP2M',
        'LP2M',
        'manage_options',
        'lp2m-settings',
        'lp2m_tr_render',
        'dashicons-welcome-learn-more',
        30
    );
}

// =================================================================
// 2. RENDER PAGE — tr_form('options') + postbox grouping
// =================================================================
function lp2m_tr_render()
{
    $form = tr_form('options', 'update');
    ?>
    <div class="wrap">
        <h1>LP2M Settings</h1>
        <p>Endpoint REST API grouped by component. Simpan untuk menerapkan perubahan.</p>

        <?php echo $form->open(); ?>

        <!-- SITE -->
        <div class="postbox" style="margin-top:20px">
            <div class="postbox-header"><h2>SITE — Identitas & Kontak</h2></div>
            <div class="inside">
                <table class="form-table">
                    <tr>
                        <th scope="row">Logo</th>
                        <td><?php echo $form->image('lp2m_site_logo_id')->setLabel(''); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Favicon</th>
                        <td><?php echo $form->image('lp2m_site_favicon_id')->setLabel(''); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Nama Lembaga</th>
                        <td><?php echo $form->text('lp2m_site_nama')->setAttribute('placeholder', 'LP2M ITSI'); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Nama Panjang</th>
                        <td><?php echo $form->text('lp2m_site_nama_panjang'); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Email</th>
                        <td><?php echo $form->text('lp2m_site_email')->setAttribute('placeholder', 'lp2m@itsi.ac.id'); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Telepon</th>
                        <td><?php echo $form->text('lp2m_site_telepon'); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Alamat</th>
                        <td><?php echo $form->textarea('lp2m_site_alamat')->setAttribute('rows', 3); ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- DOKUMEN -->
        <div class="postbox">
            <div class="postbox-header"><h2>DOKUMEN — Default</h2></div>
            <div class="inside">
                <table class="form-table">
                    <tr>
                        <th scope="row">Panduan Penulisan (PDF)</th>
                        <td><?php echo $form->image('lp2m_dok_panduan_id')->setLabel(''); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Template Dokumen (PDF)</th>
                        <td><?php echo $form->image('lp2m_dok_template_id')->setLabel(''); ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- HERO -->
        <div class="postbox">
            <div class="postbox-header"><h2>HERO — Homepage Section</h2></div>
            <div class="inside">
                <table class="form-table">
                    <tr>
                        <th scope="row">Headline (HTML)</th>
                        <td><?php echo $form->textarea('lp2m_hero_headline')->setAttribute('rows', 3); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Judul</th>
                        <td><?php echo $form->text('lp2m_hero_title'); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Caption</th>
                        <td><?php echo $form->textarea('lp2m_hero_caption')->setAttribute('rows', 3); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Button Primary — Text</th>
                        <td><?php echo $form->text('lp2m_hero_btn_primary_text')->setAttribute('placeholder', 'Lihat Event'); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Button Primary — URL</th>
                        <td><?php echo $form->text('lp2m_hero_btn_primary_url')->setAttribute('placeholder', '#hibah'); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Button Secondary — Text</th>
                        <td><?php echo $form->text('lp2m_hero_btn_secondary_text')->setAttribute('placeholder', 'Panduan'); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Button Secondary — URL</th>
                        <td><?php echo $form->text('lp2m_hero_btn_secondary_url'); ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- HERO - INFOGRAFIS Repeater -->
        <div class="postbox">
            <div class="postbox-header"><h2>HERO — Infografis</h2></div>
            <div class="inside">
                <?php lp2m_infografis_repeater(); ?>
            </div>
        </div>

        <!-- HOMEPAGE -->
        <div class="postbox">
            <div class="postbox-header"><h2>HOMEPAGE — Sections</h2></div>
            <div class="inside">
                <table class="form-table">
                    <tr><td colspan="2"><h3>Tentang</h3></td></tr>
                    <tr>
                        <th scope="row">Judul</th>
                        <td><?php echo $form->text('lp2m_home_tentang_title'); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Deskripsi</th>
                        <td><?php echo $form->textarea('lp2m_home_tentang_desc')->setAttribute('rows', 3); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Kutipan</th>
                        <td><?php echo $form->text('lp2m_home_tentang_quote'); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Body Kutipan</th>
                        <td><?php echo $form->textarea('lp2m_home_tentang_quote_body')->setAttribute('rows', 3); ?></td>
                    </tr>

                    <tr><td colspan="2"><h3>Bidang Unggulan</h3></td></tr>
                    <tr>
                        <th scope="row">Judul</th>
                        <td><?php echo $form->text('lp2m_home_bidang_title'); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Deskripsi</th>
                        <td><?php echo $form->textarea('lp2m_home_bidang_desc')->setAttribute('rows', 3); ?></td>
                    </tr>

                    <tr><td colspan="2"><h3>Mitra</h3></td></tr>
                    <tr>
                        <th scope="row">Judul</th>
                        <td><?php echo $form->text('lp2m_home_mitra_title'); ?></td>
                    </tr>

                    <tr><td colspan="2"><h3>CTA</h3></td></tr>
                    <tr>
                        <th scope="row">Judul</th>
                        <td><?php echo $form->text('lp2m_home_cta_title'); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Deskripsi</th>
                        <td><?php echo $form->textarea('lp2m_home_cta_desc')->setAttribute('rows', 3); ?></td>
                    </tr>

                    <tr><td colspan="2"><h3>Footer</h3></td></tr>
                    <tr>
                        <th scope="row">Tagline</th>
                        <td><?php echo $form->textarea('lp2m_home_footer_tagline')->setAttribute('rows', 3); ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <?php echo $form->submit('Simpan Semua Pengaturan'); ?>
        <?php echo $form->close(); ?>
    </div>
    <?php
    lp2m_infografis_js();
}

// =================================================================
// 3. INFOGRAFIS REPEATER (manual JS, disimpan via hidden input di tr_form)
// =================================================================
function lp2m_infografis_repeater()
{
    $json = get_option('lp2m_hero_infografis', '[]');
    $items = json_decode($json, true) ?: [];
    ?>
    <div id="lp2m-infografis">
        <table class="widefat striped" style="margin-bottom:8px">
            <thead><tr><th>Label</th><th>Angka</th><th style="width:60px"></th></tr></thead>
            <tbody id="lp2m-ig-rows">
                <?php if (empty($items)): ?>
                <tr class="no-items"><td colspan="3" style="text-align:center;padding:20px;color:#999">Belum ada item.</td></tr>
                <?php else: ?>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><input type="text" name="lp2m_ig_label[]" value="<?php echo esc_attr($item['label'] ?? ''); ?>" class="regular-text" placeholder="Label" /></td>
                    <td><input type="text" name="lp2m_ig_angka[]" value="<?php echo esc_attr($item['angka'] ?? ''); ?>" class="small-text" placeholder="0" /></td>
                    <td><button type="button" class="button lp2m-remove-row">✕</button></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <button type="button" class="button lp2m-add-row">+ Tambah Item</button>
        <input type="hidden" name="lp2m_hero_infografis" id="lp2m_hero_infografis_json" value="<?php echo esc_attr($json); ?>" />
    </div>
    <?php
}

function lp2m_infografis_js()
{
    ?>
    <script>
    jQuery(function($) {
        function rebuildJson() {
            var items = [];
            $('#lp2m-ig-rows tr').not('.no-items').each(function() {
                var label = $(this).find('input[name="lp2m_ig_label[]"]').val();
                var angka = $(this).find('input[name="lp2m_ig_angka[]"]').val();
                if (label) items.push({label: label, angka: angka});
            });
            $('#lp2m_hero_infografis_json').val(JSON.stringify(items));
        }
        $('.lp2m-add-row').on('click', function(e) {
            e.preventDefault(); $('.no-items').remove();
            $('#lp2m-ig-rows').append('<tr><td><input type="text" name="lp2m_ig_label[]" class="regular-text" placeholder="Label" /></td><td><input type="text" name="lp2m_ig_angka[]" class="small-text" placeholder="0" /></td><td><button type="button" class="button lp2m-remove-row">✕</button></td></tr>');
        });
        $(document).on('click', '.lp2m-remove-row', function() {
            $(this).closest('tr').remove();
            if ($('#lp2m-ig-rows tr').length === 0) $('#lp2m-ig-rows').append('<tr class="no-items"><td colspan="3" style="text-align:center;padding:20px;color:#999">Belum ada item.</td></tr>');
            rebuildJson();
        });
        $(document).on('change input', '#lp2m-infografis input[type="text"]', rebuildJson);
    });
    </script>
    <?php
}

// =================================================================
// 4. REST API — GROUPED BY COMPONENT
// =================================================================
add_action('rest_api_init', 'lp2m_rest_routes');
function lp2m_rest_routes()
{
    // All settings
    register_rest_route('lp2m/v1', '/settings', [
        'methods' => 'GET', 'callback' => 'lp2m_rest_all', 'permission_callback' => '__return_true',
    ]);
    // Per component
    foreach (['site', 'dokumen', 'hero', 'homepage'] as $group) {
        register_rest_route('lp2m/v1', '/settings/' . $group, [
            'methods' => 'GET', 'callback' => 'lp2m_rest_' . $group, 'permission_callback' => '__return_true',
        ]);
    }
}

function lp2m_rest_all()
{
    return rest_ensure_response([
        'site'     => lp2m_rest_site_data(),
        'dokumen'  => lp2m_rest_dokumen_data(),
        'hero'     => lp2m_rest_hero_data(),
        'homepage' => lp2m_rest_homepage_data(),
    ]);
}
function lp2m_rest_site()    { return rest_ensure_response(lp2m_rest_site_data()); }
function lp2m_rest_dokumen() { return rest_ensure_response(lp2m_rest_dokumen_data()); }
function lp2m_rest_hero()    { return rest_ensure_response(lp2m_rest_hero_data()); }
function lp2m_rest_homepage(){ return rest_ensure_response(lp2m_rest_homepage_data()); }

function lp2m_opt($k) { return get_option('lp2m_' . $k, ''); }
function lp2m_url($id) { return $id ? wp_get_attachment_url((int)$id) : ''; }

function lp2m_rest_site_data()
{
    return [
        'logo_id'       => lp2m_opt('site_logo_id'), 'logo_url' => lp2m_url(lp2m_opt('site_logo_id')),
        'favicon_id'    => lp2m_opt('site_favicon_id'), 'favicon_url' => lp2m_url(lp2m_opt('site_favicon_id')),
        'nama'          => lp2m_opt('site_nama') ?: 'LP2M ITSI', 'nama_panjang' => lp2m_opt('site_nama_panjang'),
        'email'         => lp2m_opt('site_email'), 'telepon' => lp2m_opt('site_telepon'), 'alamat' => lp2m_opt('site_alamat'),
    ];
}

function lp2m_rest_dokumen_data()
{
    return [
        'panduan_id' => lp2m_opt('dok_panduan_id'), 'panduan_url' => lp2m_url(lp2m_opt('dok_panduan_id')),
        'template_id' => lp2m_opt('dok_template_id'), 'template_url' => lp2m_url(lp2m_opt('dok_template_id')),
    ];
}

function lp2m_rest_hero_data()
{
    return [
        'headline'              => lp2m_opt('hero_headline'),
        'title'                 => lp2m_opt('hero_title'),
        'caption'               => lp2m_opt('hero_caption'),
        'btn_primary_text'      => lp2m_opt('hero_btn_primary_text'),
        'btn_primary_url'       => lp2m_opt('hero_btn_primary_url'),
        'btn_secondary_text'    => lp2m_opt('hero_btn_secondary_text'),
        'btn_secondary_url'     => lp2m_opt('hero_btn_secondary_url'),
        'infografis'            => json_decode(lp2m_opt('hero_infografis'), true) ?: [],
    ];
}

function lp2m_rest_homepage_data()
{
    return [
        'tentang_title'       => lp2m_opt('home_tentang_title'),
        'tentang_desc'        => lp2m_opt('home_tentang_desc'),
        'tentang_quote'       => lp2m_opt('home_tentang_quote'),
        'tentang_quote_body'  => lp2m_opt('home_tentang_quote_body'),
        'bidang_title'        => lp2m_opt('home_bidang_title'),
        'bidang_desc'         => lp2m_opt('home_bidang_desc'),
        'mitra_title'         => lp2m_opt('home_mitra_title'),
        'cta_title'           => lp2m_opt('home_cta_title'),
        'cta_desc'            => lp2m_opt('home_cta_desc'),
        'footer_tagline'      => lp2m_opt('home_footer_tagline'),
    ];
}
