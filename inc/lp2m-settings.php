<?php
/**
 * LP2M Settings — TypeRocket v6
 *
 * Akses: WP Admin → LP2M → Settings
 * REST API grouped by component:
 *   GET /wp-json/lp2m/v1/settings/hero
 *   GET /wp-json/lp2m/v1/settings/site
 *   GET /wp-json/lp2m/v1/settings/dokumen
 *   GET /wp-json/lp2m/v1/settings/homepage
 *   GET /wp-json/lp2m/v1/settings (semua)
 */

defined('ABSPATH') || exit;

// =================================================================
// 1. REGISTER ADMIN PAGE
// =================================================================
add_action('admin_menu', 'lp2m_tr_add_page');
function lp2m_tr_add_page()
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
// 2. REGISTER SETTINGS
// =================================================================
add_action('admin_init', 'lp2m_tr_register');
function lp2m_tr_register()
{
    $fields = [
        'site_logo_id', 'site_favicon_id', 'site_nama', 'site_nama_panjang',
        'site_email', 'site_telepon', 'site_alamat',
        'dok_panduan_id', 'dok_template_id',
        'hero_headline', 'hero_title', 'hero_caption',
        'hero_btn_primary_text', 'hero_btn_primary_url',
        'hero_btn_secondary_text', 'hero_btn_secondary_url',
        'hero_infografis',
        'home_tentang_title', 'home_tentang_desc', 'home_tentang_quote', 'home_tentang_quote_body',
        'home_bidang_title', 'home_bidang_desc',
        'home_mitra_title',
        'home_cta_title', 'home_cta_desc',
        'home_footer_tagline',
    ];
    foreach ($fields as $f) {
        register_setting('lp2m_group', 'lp2m_' . $f, ['sanitize_callback' => 'sanitize_text_field']);
    }
}

// =================================================================
// 3. HELPER: media + text fields
// =================================================================
function lp2m_media_row($name, $label)
{
    $id = get_option($name, '');
    $url = $id ? wp_get_attachment_url((int)$id) : '';
    ?>
    <tr>
        <th scope="row"><?php echo esc_html($label); ?></th>
        <td>
            <input type="hidden" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($id); ?>" />
            <button type="button" class="button lp2m-media-btn" data-target="<?php echo esc_attr($name); ?>">Pilih File</button>
            <button type="button" class="button lp2m-clear-btn" data-target="<?php echo esc_attr($name); ?>" style="<?php echo !$id ? 'display:none' : ''; ?>">Hapus</button>
            <span class="lp2m-preview" data-target="<?php echo esc_attr($name); ?>"><?php echo $url ? esc_html(basename($url)) : 'Belum dipilih'; ?></span>
        </td>
    </tr>
    <?php
}

function lp2m_text_row($name, $label, $placeholder = '')
{
    $val = get_option($name, '');
    ?>
    <tr>
        <th scope="row"><?php echo esc_html($label); ?></th>
        <td><input type="text" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($val); ?>" class="regular-text" placeholder="<?php echo esc_attr($placeholder); ?>" /></td>
    </tr>
    <?php
}

function lp2m_textarea_row($name, $label, $placeholder = '')
{
    $val = get_option($name, '');
    ?>
    <tr>
        <th scope="row"><?php echo esc_html($label); ?></th>
        <td><textarea name="<?php echo esc_attr($name); ?>" rows="4" class="large-text" placeholder="<?php echo esc_attr($placeholder); ?>"><?php echo esc_textarea($val); ?></textarea></td>
    </tr>
    <?php
}

// =================================================================
// 4. INFOGRAFIS REPEATER
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

// =================================================================
// 5. RENDER PAGE
// =================================================================
function lp2m_tr_render()
{
    ?>
    <div class="wrap">
        <h1>LP2M Settings</h1>
        <p>Endpoint REST API grouped by component.</p>
        <form method="post" action="options.php">
            <?php settings_fields('lp2m_group'); ?>

            <!-- SITE -->
            <div class="postbox" style="margin-top:20px">
                <div class="postbox-header"><h2>SITE — Identitas & Kontak</h2></div>
                <div class="inside">
                    <table class="form-table">
                        <?php lp2m_media_row('lp2m_site_logo_id', 'Logo'); ?>
                        <?php lp2m_media_row('lp2m_site_favicon_id', 'Favicon'); ?>
                        <?php lp2m_text_row('lp2m_site_nama', 'Nama Lembaga', 'LP2M ITSI'); ?>
                        <?php lp2m_text_row('lp2m_site_nama_panjang', 'Nama Panjang'); ?>
                        <?php lp2m_text_row('lp2m_site_email', 'Email', 'lp2m@itsi.ac.id'); ?>
                        <?php lp2m_text_row('lp2m_site_telepon', 'Telepon'); ?>
                        <?php lp2m_textarea_row('lp2m_site_alamat', 'Alamat'); ?>
                    </table>
                </div>
            </div>

            <!-- DOKUMEN -->
            <div class="postbox">
                <div class="postbox-header"><h2>DOKUMEN — Default</h2></div>
                <div class="inside">
                    <table class="form-table">
                        <?php lp2m_media_row('lp2m_dok_panduan_id', 'Panduan Penulisan (PDF)'); ?>
                        <?php lp2m_media_row('lp2m_dok_template_id', 'Template Dokumen (PDF)'); ?>
                    </table>
                </div>
            </div>

            <!-- HERO -->
            <div class="postbox">
                <div class="postbox-header"><h2>HERO — Homepage Section</h2></div>
                <div class="inside">
                    <table class="form-table">
                        <?php lp2m_textarea_row('lp2m_hero_headline', 'Headline (HTML)'); ?>
                        <?php lp2m_text_row('lp2m_hero_title', 'Judul'); ?>
                        <?php lp2m_textarea_row('lp2m_hero_caption', 'Caption'); ?>
                        <?php lp2m_text_row('lp2m_hero_btn_primary_text', 'Button Primary — Text', 'Lihat Event'); ?>
                        <?php lp2m_text_row('lp2m_hero_btn_primary_url', 'Button Primary — URL'); ?>
                        <?php lp2m_text_row('lp2m_hero_btn_secondary_text', 'Button Secondary — Text', 'Panduan'); ?>
                        <?php lp2m_text_row('lp2m_hero_btn_secondary_url', 'Button Secondary — URL'); ?>
                    </table>
                </div>
            </div>

            <!-- HERO - INFOGRAFIS -->
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
                        <?php lp2m_text_row('lp2m_home_tentang_title', 'Judul'); ?>
                        <?php lp2m_textarea_row('lp2m_home_tentang_desc', 'Deskripsi'); ?>
                        <?php lp2m_text_row('lp2m_home_tentang_quote', 'Kutipan'); ?>
                        <?php lp2m_textarea_row('lp2m_home_tentang_quote_body', 'Body Kutipan'); ?>

                        <tr><td colspan="2"><h3>Bidang Unggulan</h3></td></tr>
                        <?php lp2m_text_row('lp2m_home_bidang_title', 'Judul'); ?>
                        <?php lp2m_textarea_row('lp2m_home_bidang_desc', 'Deskripsi'); ?>

                        <tr><td colspan="2"><h3>Mitra</h3></td></tr>
                        <?php lp2m_text_row('lp2m_home_mitra_title', 'Judul'); ?>

                        <tr><td colspan="2"><h3>CTA</h3></td></tr>
                        <?php lp2m_text_row('lp2m_home_cta_title', 'Judul'); ?>
                        <?php lp2m_textarea_row('lp2m_home_cta_desc', 'Deskripsi'); ?>

                        <tr><td colspan="2"><h3>Footer</h3></td></tr>
                        <?php lp2m_textarea_row('lp2m_home_footer_tagline', 'Tagline'); ?>
                    </table>
                </div>
            </div>

            <?php submit_button('Simpan Semua Pengaturan'); ?>
        </form>
    </div>
    <?php
    lp2m_enqueue_media_js();
}

// =================================================================
// 6. MEDIA JS
// =================================================================
function lp2m_enqueue_media_js()
{
    wp_enqueue_media();
    ?>
    <script>
    jQuery(function($) {
        // Media picker
        $('.lp2m-media-btn').on('click', function(e) {
            e.preventDefault();
            var target = $(this).data('target');
            var frame = wp.media({ title: 'Pilih File', button: { text: 'Gunakan' }, multiple: false });
            frame.on('select', function() {
                var a = frame.state().get('selection').first().toJSON();
                $('#' + target).val(a.id);
                $('.lp2m-preview[data-target="' + target + '"]').text(a.filename);
                $('.lp2m-clear-btn[data-target="' + target + '"]').show();
            });
            frame.open();
        });
        $('.lp2m-clear-btn').on('click', function() {
            var t = $(this).data('target');
            $('#' + t).val('');
            $('.lp2m-preview[data-target="' + t + '"]').text('Belum dipilih');
            $(this).hide();
        });

        // Infografis repeater
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
// 7. REST API — GROUPED BY COMPONENT
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

function lp2m_get_opt($k) { return get_option('lp2m_' . $k, ''); }
function lp2m_media_url($id) { return $id ? wp_get_attachment_url((int)$id) : ''; }

function lp2m_rest_site_data()
{
    return [
        'logo_id'       => lp2m_get_opt('site_logo_id'),
        'logo_url'      => lp2m_media_url(lp2m_get_opt('site_logo_id')),
        'favicon_id'    => lp2m_get_opt('site_favicon_id'),
        'favicon_url'   => lp2m_media_url(lp2m_get_opt('site_favicon_id')),
        'nama'          => lp2m_get_opt('site_nama') ?: 'LP2M ITSI',
        'nama_panjang'  => lp2m_get_opt('site_nama_panjang'),
        'email'         => lp2m_get_opt('site_email'),
        'telepon'       => lp2m_get_opt('site_telepon'),
        'alamat'        => lp2m_get_opt('site_alamat'),
    ];
}

function lp2m_rest_dokumen_data()
{
    return [
        'panduan_id'  => lp2m_get_opt('dok_panduan_id'),
        'panduan_url' => lp2m_media_url(lp2m_get_opt('dok_panduan_id')),
        'template_id'  => lp2m_get_opt('dok_template_id'),
        'template_url' => lp2m_media_url(lp2m_get_opt('dok_template_id')),
    ];
}

function lp2m_rest_hero_data()
{
    return [
        'headline'              => lp2m_get_opt('hero_headline'),
        'title'                 => lp2m_get_opt('hero_title'),
        'caption'               => lp2m_get_opt('hero_caption'),
        'btn_primary_text'      => lp2m_get_opt('hero_btn_primary_text'),
        'btn_primary_url'       => lp2m_get_opt('hero_btn_primary_url'),
        'btn_secondary_text'    => lp2m_get_opt('hero_btn_secondary_text'),
        'btn_secondary_url'     => lp2m_get_opt('hero_btn_secondary_url'),
        'infografis'            => json_decode(lp2m_get_opt('hero_infografis'), true) ?: [],
    ];
}

function lp2m_rest_homepage_data()
{
    return [
        'tentang_title'       => lp2m_get_opt('home_tentang_title'),
        'tentang_desc'        => lp2m_get_opt('home_tentang_desc'),
        'tentang_quote'       => lp2m_get_opt('home_tentang_quote'),
        'tentang_quote_body'  => lp2m_get_opt('home_tentang_quote_body'),
        'bidang_title'        => lp2m_get_opt('home_bidang_title'),
        'bidang_desc'         => lp2m_get_opt('home_bidang_desc'),
        'mitra_title'         => lp2m_get_opt('home_mitra_title'),
        'cta_title'           => lp2m_get_opt('home_cta_title'),
        'cta_desc'            => lp2m_get_opt('home_cta_desc'),
        'footer_tagline'      => lp2m_get_opt('home_footer_tagline'),
    ];
}
