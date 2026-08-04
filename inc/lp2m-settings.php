<?php
/**
 * LP2M Settings — Option page + REST API endpoint
 * Settings disimpan di option WordPress, diakses via REST API GET /wp-json/lp2m/v1/settings
 */

defined('ABSPATH') || exit;

// =================================================================
// 1. REGISTER SETTINGS PAGE
// =================================================================
add_action('admin_menu', 'lp2m_add_admin_page');
function lp2m_add_admin_page()
{
    add_options_page(
        'LP2M Settings',
        'LP2M',
        'manage_options',
        'lp2m-settings',
        'lp2m_render_admin_page'
    );
}

// =================================================================
// 2. REGISTER SETTINGS FIELDS
// =================================================================
add_action('admin_init', 'lp2m_register_settings');
function lp2m_register_settings()
{
    $fields = [
        'logo_id'             => 'Logo LP2M (Image ID)',
        'favicon_id'          => 'Favicon (Image ID)',
        'nama_lembaga'        => 'Nama Lembaga',
        'nama_panjang'        => 'Nama Panjang',
        'email'               => 'Email',
        'telepon'             => 'Telepon',
        'alamat'              => 'Alamat',
        'panduan_penulisan_id'=> 'Panduan Penulisan (PDF ID)',
        'template_dokumen_id' => 'Template Dokumen (PDF ID)',
        'hero_headline'       => 'Hero Headline',
        'hero_lead'           => 'Hero Lead',
        'hero_event_title'    => 'Hero Event Title',
        'tentang_title'       => 'Tentang: Judul',
        'tentang_desc'        => 'Tentang: Deskripsi',
        'tentang_quote'       => 'Tentang: Kutipan',
        'tentang_quote_body'  => 'Tentang: Body Kutipan',
        'bidang_title'        => 'Bidang: Judul',
        'bidang_desc'         => 'Bidang: Deskripsi',
        'infografis_title'    => 'Infografis: Judul',
        'infografis_desc'     => 'Infografis: Deskripsi',
        'mitra_title'         => 'Mitra: Judul',
        'cta_title'           => 'CTA: Judul',
        'cta_desc'            => 'CTA: Deskripsi',
        'footer_tagline'      => 'Footer: Tagline',
    ];

    foreach ($fields as $key => $label) {
        register_setting('lp2m_settings_group', 'lp2m_' . $key, ['sanitize_callback' => 'sanitize_text_field']);
    }
}

// =================================================================
// 3. RENDER ADMIN PAGE
// =================================================================
function lp2m_render_admin_page()
{
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <p>Settings ini akan muncul di endpoint <code>GET /wp-json/lp2m/v1/settings</code> dan dipakai oleh dashboard LP2M.</p>
        <form method="post" action="options.php">
            <?php
            settings_fields('lp2m_settings_group');
            do_settings_sections('lp2m_settings_group');
            ?>
            <table class="form-table" role="presentation">
                <tr><th colspan="2"><h2>Identitas & Kontak</h2></th></tr>
                <?php lp2m_media_field('lp2m_logo_id', 'Logo LP2M'); ?>
                <?php lp2m_media_field('lp2m_favicon_id', 'Favicon'); ?>
                <?php lp2m_text_field('lp2m_nama_lembaga', 'Nama Lembaga', 'LP2M ITSI'); ?>
                <?php lp2m_text_field('lp2m_nama_panjang', 'Nama Panjang', ''); ?>
                <?php lp2m_text_field('lp2m_email', 'Email', 'lp2m@itsi.ac.id'); ?>
                <?php lp2m_text_field('lp2m_telepon', 'Telepon', ''); ?>
                <?php lp2m_textarea_field('lp2m_alamat', 'Alamat', ''); ?>

                <tr><th colspan="2"><h2>Dokumen Default</h2></th></tr>
                <?php lp2m_media_field('lp2m_panduan_penulisan_id', 'Panduan Penulisan (PDF)'); ?>
                <?php lp2m_media_field('lp2m_template_dokumen_id', 'Template Dokumen (PDF)'); ?>

                <tr><th colspan="2"><h2>Homepage — Hero</h2></th></tr>
                <?php lp2m_textarea_field('lp2m_hero_headline', 'Headline (HTML allowed)', ''); ?>
                <?php lp2m_textarea_field('lp2m_hero_lead', 'Lead', ''); ?>
                <?php lp2m_text_field('lp2m_hero_event_title', 'Event Title', ''); ?>

                <tr><th colspan="2"><h2>Homepage — Tentang</h2></th></tr>
                <?php lp2m_text_field('lp2m_tentang_title', 'Judul', ''); ?>
                <?php lp2m_textarea_field('lp2m_tentang_desc', 'Deskripsi', ''); ?>
                <?php lp2m_text_field('lp2m_tentang_quote', 'Kutipan', ''); ?>
                <?php lp2m_textarea_field('lp2m_tentang_quote_body', 'Body Kutipan', ''); ?>

                <tr><th colspan="2"><h2>Homepage — Bidang Unggulan</h2></th></tr>
                <?php lp2m_text_field('lp2m_bidang_title', 'Judul', ''); ?>
                <?php lp2m_textarea_field('lp2m_bidang_desc', 'Deskripsi', ''); ?>

                <tr><th colspan="2"><h2>Homepage — Lainnya</h2></th></tr>
                <?php lp2m_text_field('lp2m_infografis_title', 'Infografis: Judul', ''); ?>
                <?php lp2m_textarea_field('lp2m_infografis_desc', 'Infografis: Deskripsi', ''); ?>
                <?php lp2m_text_field('lp2m_mitra_title', 'Mitra: Judul', ''); ?>
                <?php lp2m_text_field('lp2m_cta_title', 'CTA: Judul', ''); ?>
                <?php lp2m_textarea_field('lp2m_cta_desc', 'CTA: Deskripsi', ''); ?>
                <?php lp2m_textarea_field('lp2m_footer_tagline', 'Footer: Tagline', ''); ?>
            </table>
            <?php submit_button('Simpan Semua'); ?>
        </form>
    </div>
    <?php
}

// =================================================================
// HELPER: Text fields
// =================================================================
function lp2m_text_field($name, $label, $placeholder = '')
{
    $val = get_option($name, '');
    ?>
    <tr>
        <th scope="row"><label for="<?php echo esc_attr($name); ?>"><?php echo esc_html($label); ?></label></th>
        <td><input type="text" id="<?php echo esc_attr($name); ?>" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($val); ?>" placeholder="<?php echo esc_attr($placeholder); ?>" class="regular-text" /></td>
    </tr>
    <?php
}

function lp2m_textarea_field($name, $label, $placeholder = '')
{
    $val = get_option($name, '');
    ?>
    <tr>
        <th scope="row"><label for="<?php echo esc_attr($name); ?>"><?php echo esc_html($label); ?></label></th>
        <td><textarea id="<?php echo esc_attr($name); ?>" name="<?php echo esc_attr($name); ?>" rows="4" class="large-text" placeholder="<?php echo esc_attr($placeholder); ?>"><?php echo esc_textarea($val); ?></textarea></td>
    </tr>
    <?php
}

function lp2m_media_field($name, $label)
{
    $id = get_option($name, '');
    $url = $id ? wp_get_attachment_url((int)$id) : '';
    ?>
    <tr>
        <th scope="row"><label for="<?php echo esc_attr($name); ?>"><?php echo esc_html($label); ?></label></th>
        <td>
            <input type="hidden" id="<?php echo esc_attr($name); ?>" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($id); ?>" />
            <div style="display:flex;align-items:center;gap:10px">
                <button type="button" class="button lp2m-media-btn" data-target="<?php echo esc_attr($name); ?>">Pilih File</button>
                <button type="button" class="button lp2m-clear-btn" data-target="<?php echo esc_attr($name); ?>" style="<?php echo !$id ? 'display:none' : ''; ?>">Hapus</button>
                <span class="lp2m-preview" data-target="<?php echo esc_attr($name); ?>"><?php echo $url ? '<a href="' . esc_url($url) . '" target="_blank">' . esc_html(basename($url)) . '</a>' : 'Belum dipilih'; ?></span>
            </div>
        </td>
    </tr>
    <?php
}

// =================================================================
// 4. REST API ENDPOINT
// =================================================================
add_action('rest_api_init', 'lp2m_register_rest');
function lp2m_register_rest()
{
    register_rest_route('lp2m/v1', '/settings', [
        'methods'  => 'GET',
        'callback' => 'lp2m_get_settings',
        'permission_callback' => '__return_true',
    ]);
}

function lp2m_get_settings()
{
    $keys = [
        'logo_id', 'favicon_id', 'nama_lembaga', 'nama_panjang',
        'email', 'telepon', 'alamat',
        'panduan_penulisan_id', 'template_dokumen_id',
        'hero_headline', 'hero_lead', 'hero_event_title',
        'tentang_title', 'tentang_desc', 'tentang_quote', 'tentang_quote_body',
        'bidang_title', 'bidang_desc',
        'infografis_title', 'infografis_desc',
        'mitra_title', 'cta_title', 'cta_desc', 'footer_tagline',
    ];

    $data = [];
    foreach ($keys as $k) {
        $data[$k] = get_option('lp2m_' . $k, '');
    }

    // Expand media IDs to URLs
    $data['logo_url'] = lp2m_get_attachment_url($data['logo_id']);
    $data['favicon_url'] = lp2m_get_attachment_url($data['favicon_id']);
    $data['panduan_penulisan_url'] = lp2m_get_attachment_url($data['panduan_penulisan_id']);
    $data['template_dokumen_url'] = lp2m_get_attachment_url($data['template_dokumen_id']);

    return rest_ensure_response($data);
}

function lp2m_get_attachment_url($id)
{
    if (!$id) return '';
    $url = wp_get_attachment_url((int)$id);
    return $url ? $url : '';
}

// =================================================================
// 5. ENQUEUE MEDIA UPLOADER JS
// =================================================================
add_action('admin_enqueue_scripts', 'lp2m_admin_scripts');
function lp2m_admin_scripts($hook)
{
    if ($hook !== 'settings_page_lp2m-settings') return;
    wp_enqueue_media();
    add_action('admin_footer', 'lp2m_media_js');
}

function lp2m_media_js()
{
    ?>
    <script>
    jQuery(function($) {
        $('.lp2m-media-btn').on('click', function(e) {
            e.preventDefault();
            var target = $(this).data('target');
            var frame = wp.media({
                title: 'Pilih File',
                button: { text: 'Gunakan File Ini' },
                multiple: false
            });
            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                $('#' + target).val(attachment.id);
                $('.lp2m-preview[data-target="' + target + '"]').html('<a href="' + attachment.url + '" target="_blank">' + attachment.filename + '</a>');
                $('.lp2m-clear-btn[data-target="' + target + '"]').show();
            });
            frame.open();
        });

        $('.lp2m-clear-btn').on('click', function() {
            var target = $(this).data('target');
            $('#' + target).val('');
            $('.lp2m-preview[data-target="' + target + '"]').text('Belum dipilih');
            $(this).hide();
        });
    });
    </script>
    <?php
}
