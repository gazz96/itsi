<?php
/**
 * LP2M Settings — TypeRocket v6 Style + WordPress Native Save
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
// 1. REGISTER ADMIN MENU + SETTINGS
// =================================================================
add_action('admin_menu', function() {
    add_menu_page('LP2M', 'LP2M', 'manage_options', 'lp2m-settings', 'lp2m_render', 'dashicons-welcome-learn-more', 30);
});

add_action('admin_init', function() {
    $keys = ['site_logo_id','site_favicon_id','site_nama','site_nama_panjang','site_email','site_telepon','site_alamat','dok_panduan_id','dok_template_id','hero_headline','hero_title','hero_caption','hero_btn_primary_text','hero_btn_primary_url','hero_btn_secondary_text','hero_btn_secondary_url','hero_infografis','home_tentang_title','home_tentang_desc','home_tentang_quote','home_tentang_quote_body','home_bidang_title','home_bidang_desc','home_mitra_title','home_cta_title','home_cta_desc','home_footer_tagline'];
    foreach ($keys as $k) register_setting('lp2m_group', 'lp2m_'.$k);
});

// =================================================================
// 2. RENDER PAGE — postbox grouping + TypeRocket image fields
// =================================================================
function lp2m_render() {
    $form = tr_form();
    ?>
    <div class="wrap">
        <h1>LP2M Settings</h1>
        <p>REST API: <code>/wp-json/lp2m/v1/settings/{site|dokumen|hero|homepage}</code></p>
        <form method="post" action="options.php">
            <?php settings_fields('lp2m_group'); ?>

            <!-- SITE -->
            <div class="postbox" style="margin-top:20px">
                <div class="postbox-header"><h2>SITE — Identitas & Kontak</h2></div>
                <div class="inside">
                    <table class="form-table">
                        <tr><th>Logo</th><td><?php echo $form->image('lp2m_site_logo_id'); ?></td></tr>
                        <tr><th>Favicon</th><td><?php echo $form->image('lp2m_site_favicon_id'); ?></td></tr>
                        <?php lp2m_row('lp2m_site_nama','Nama Lembaga','LP2M ITSI'); ?>
                        <?php lp2m_row('lp2m_site_nama_panjang','Nama Panjang'); ?>
                        <?php lp2m_row('lp2m_site_email','Email','lp2m@itsi.ac.id'); ?>
                        <?php lp2m_row('lp2m_site_telepon','Telepon'); ?>
                        <?php lp2m_row('lp2m_site_alamat','Alamat','','textarea'); ?>
                    </table>
                </div>
            </div>

            <!-- DOKUMEN -->
            <div class="postbox">
                <div class="postbox-header"><h2>DOKUMEN — Default</h2></div>
                <div class="inside">
                    <table class="form-table">
                        <tr><th>Panduan Penulisan (PDF)</th><td><?php echo $form->image('lp2m_dok_panduan_id'); ?></td></tr>
                        <tr><th>Template Dokumen (PDF)</th><td><?php echo $form->image('lp2m_dok_template_id'); ?></td></tr>
                    </table>
                </div>
            </div>

            <!-- HERO -->
            <div class="postbox">
                <div class="postbox-header"><h2>HERO — Homepage Section</h2></div>
                <div class="inside">
                    <table class="form-table">
                        <?php lp2m_row('lp2m_hero_headline','Headline (HTML)','','textarea'); ?>
                        <?php lp2m_row('lp2m_hero_title','Judul'); ?>
                        <?php lp2m_row('lp2m_hero_caption','Caption','','textarea'); ?>
                        <?php lp2m_row('lp2m_hero_btn_primary_text','Button Primary — Text','Lihat Event'); ?>
                        <?php lp2m_row('lp2m_hero_btn_primary_url','Button Primary — URL','#hibah'); ?>
                        <?php lp2m_row('lp2m_hero_btn_secondary_text','Button Secondary — Text','Panduan'); ?>
                        <?php lp2m_row('lp2m_hero_btn_secondary_url','Button Secondary — URL'); ?>
                    </table>
                </div>
            </div>

            <!-- HERO - Infografis (TypeRocket Repeater, manual save via hidden input) -->
            <div class="postbox">
                <div class="postbox-header"><h2>HERO — Infografis</h2></div>
                <div class="inside">
                    <?php lp2m_repeater(); ?>
                </div>
            </div>

            <!-- HOMEPAGE -->
            <div class="postbox">
                <div class="postbox-header"><h2>HOMEPAGE — Sections</h2></div>
                <div class="inside">
                    <table class="form-table">
                        <tr><td colspan="2"><h3>Tentang</h3></td></tr>
                        <?php lp2m_row('lp2m_home_tentang_title','Judul'); ?>
                        <?php lp2m_row('lp2m_home_tentang_desc','Deskripsi','','textarea'); ?>
                        <?php lp2m_row('lp2m_home_tentang_quote','Kutipan'); ?>
                        <?php lp2m_row('lp2m_home_tentang_quote_body','Body Kutipan','','textarea'); ?>
                        <tr><td colspan="2"><h3>Bidang Unggulan</h3></td></tr>
                        <?php lp2m_row('lp2m_home_bidang_title','Judul'); ?>
                        <?php lp2m_row('lp2m_home_bidang_desc','Deskripsi','','textarea'); ?>
                        <tr><td colspan="2"><h3>Mitra</h3></td></tr>
                        <?php lp2m_row('lp2m_home_mitra_title','Judul'); ?>
                        <tr><td colspan="2"><h3>CTA</h3></td></tr>
                        <?php lp2m_row('lp2m_home_cta_title','Judul'); ?>
                        <?php lp2m_row('lp2m_home_cta_desc','Deskripsi','','textarea'); ?>
                        <tr><td colspan="2"><h3>Footer</h3></td></tr>
                        <?php lp2m_row('lp2m_home_footer_tagline','Tagline','','textarea'); ?>
                    </table>
                </div>
            </div>

            <?php submit_button('Simpan Semua Pengaturan'); ?>
        </form>
    </div>
    <?php
    lp2m_media_js();
}

// =================================================================
// 3. HELPERS
// =================================================================
function lp2m_row($name, $label, $placeholder='', $type='text') {
    $val = get_option($name, '');
    $ph = $placeholder ? ' placeholder="'.esc_attr($placeholder).'"' : '';
    echo '<tr><th scope="row">'.esc_html($label).'</th><td>';
    if ($type === 'textarea') {
        echo '<textarea name="'.esc_attr($name).'" rows="3" class="large-text"'.$ph.'>'.esc_textarea($val).'</textarea>';
    } else {
        echo '<input type="text" name="'.esc_attr($name).'" value="'.esc_attr($val).'" class="regular-text"'.$ph.' />';
    }
    echo '</td></tr>';
}

function lp2m_repeater() {
    $json = get_option('lp2m_hero_infografis', '[]');
    $items = json_decode($json, true) ?: [];
    ?>
    <table class="widefat striped" style="margin-bottom:8px" id="lp2m-ig-table">
        <thead><tr><th>Label</th><th>Angka</th><th style="width:60px"></th></tr></thead>
        <tbody id="lp2m-ig-rows">
            <?php if (empty($items)): ?>
            <tr class="no-items"><td colspan="3" style="text-align:center;padding:20px;color:#999">Belum ada item.</td></tr>
            <?php else: ?>
            <?php foreach ($items as $item): ?>
            <tr>
                <td><input type="text" name="lp2m_ig_label[]" value="<?php echo esc_attr($item['label']??''); ?>" class="regular-text" placeholder="Label" /></td>
                <td><input type="text" name="lp2m_ig_angka[]" value="<?php echo esc_attr($item['angka']??''); ?>" class="small-text" placeholder="0" /></td>
                <td><button type="button" class="button lp2m-remove-row">✕</button></td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
    <button type="button" class="button lp2m-add-row">+ Tambah Item</button>
    <input type="hidden" name="lp2m_hero_infografis" id="lp2m_hero_infografis_json" value="<?php echo esc_attr($json); ?>" />
    <?php
}

function lp2m_media_js() {
    wp_enqueue_media();
    ?>
    <script>
    jQuery(function($) {
        // Repeater
        function rebuild() {
            var a=[];$('#lp2m-ig-rows tr').not('.no-items').each(function(){
                var l=$(this).find('input[name="lp2m_ig_label[]"]').val();
                var n=$(this).find('input[name="lp2m_ig_angka[]"]').val();
                if(l)a.push({label:l,angka:n});
            });$('#lp2m_hero_infografis_json').val(JSON.stringify(a));
        }
        $('.lp2m-add-row').on('click',function(e){e.preventDefault();$('.no-items').remove();
            $('#lp2m-ig-rows').append('<tr><td><input type="text" name="lp2m_ig_label[]" class="regular-text" placeholder="Label" /></td><td><input type="text" name="lp2m_ig_angka[]" class="small-text" placeholder="0" /></td><td><button type="button" class="button lp2m-remove-row">✕</button></td></tr>');
        });
        $(document).on('click','.lp2m-remove-row',function(){
            $(this).closest('tr').remove();
            if(!$('#lp2m-ig-rows tr').length)$('#lp2m-ig-rows').append('<tr class="no-items"><td colspan="3" style="text-align:center;padding:20px;color:#999">Belum ada item.</td></tr>');
            rebuild();
        });
        $(document).on('change input','#lp2m-ig-table input[type="text"]',rebuild);
    });
    </script>
    <?php
}

// =================================================================
// 4. HOOK: save repeater hidden JSON saat form submit
// =================================================================
add_action('admin_init', function() {
    if (isset($_POST['lp2m_hero_infografis']) && isset($_POST['option_page']) && $_POST['option_page'] === 'lp2m_group') {
        update_option('lp2m_hero_infografis', wp_unslash($_POST['lp2m_hero_infografis']));
    }
});

// =================================================================
// 5. REST API — GROUPED BY COMPONENT
// =================================================================
add_action('rest_api_init', function() {
    register_rest_route('lp2m/v1', '/settings', ['methods'=>'GET','callback'=>'lp2m_rest_all','permission_callback'=>'__return_true']);
    register_rest_route('lp2m/v1', '/settings/site', ['methods'=>'GET','callback'=>'lp2m_rest_site','permission_callback'=>'__return_true']);
    register_rest_route('lp2m/v1', '/settings/dokumen', ['methods'=>'GET','callback'=>'lp2m_rest_dokumen','permission_callback'=>'__return_true']);
    register_rest_route('lp2m/v1', '/settings/hero', ['methods'=>'GET','callback'=>'lp2m_rest_hero','permission_callback'=>'__return_true']);
    register_rest_route('lp2m/v1', '/settings/homepage', ['methods'=>'GET','callback'=>'lp2m_rest_homepage','permission_callback'=>'__return_true']);
});

function lp2m_rest_all(){return rest_ensure_response(['site'=>lp2m_site_data(),'dokumen'=>lp2m_dok_data(),'hero'=>lp2m_hero_data(),'homepage'=>lp2m_home_data()]);}
function lp2m_rest_site(){return rest_ensure_response(lp2m_site_data());}
function lp2m_rest_dokumen(){return rest_ensure_response(lp2m_dok_data());}
function lp2m_rest_hero(){return rest_ensure_response(lp2m_hero_data());}
function lp2m_rest_homepage(){return rest_ensure_response(lp2m_home_data());}

function lp2m_opt($k){return get_option('lp2m_'.$k,'');}
function lp2m_url($id){return $id?wp_get_attachment_url((int)$id):'';}
function lp2m_site_data(){return['logo_id'=>lp2m_opt('site_logo_id'),'logo_url'=>lp2m_url(lp2m_opt('site_logo_id')),'favicon_id'=>lp2m_opt('site_favicon_id'),'favicon_url'=>lp2m_url(lp2m_opt('site_favicon_id')),'nama'=>lp2m_opt('site_nama')?:'LP2M ITSI','nama_panjang'=>lp2m_opt('site_nama_panjang'),'email'=>lp2m_opt('site_email'),'telepon'=>lp2m_opt('site_telepon'),'alamat'=>lp2m_opt('site_alamat')];}
function lp2m_dok_data(){return['panduan_id'=>lp2m_opt('dok_panduan_id'),'panduan_url'=>lp2m_url(lp2m_opt('dok_panduan_id')),'template_id'=>lp2m_opt('dok_template_id'),'template_url'=>lp2m_url(lp2m_opt('dok_template_id'))];}
function lp2m_hero_data(){return['headline'=>lp2m_opt('hero_headline'),'title'=>lp2m_opt('hero_title'),'caption'=>lp2m_opt('hero_caption'),'btn_primary_text'=>lp2m_opt('hero_btn_primary_text'),'btn_primary_url'=>lp2m_opt('hero_btn_primary_url'),'btn_secondary_text'=>lp2m_opt('hero_btn_secondary_text'),'btn_secondary_url'=>lp2m_opt('hero_btn_secondary_url'),'infografis'=>json_decode(lp2m_opt('hero_infografis'),true)?:[]];}
function lp2m_home_data(){return['tentang_title'=>lp2m_opt('home_tentang_title'),'tentang_desc'=>lp2m_opt('home_tentang_desc'),'tentang_quote'=>lp2m_opt('home_tentang_quote'),'tentang_quote_body'=>lp2m_opt('home_tentang_quote_body'),'bidang_title'=>lp2m_opt('home_bidang_title'),'bidang_desc'=>lp2m_opt('home_bidang_desc'),'mitra_title'=>lp2m_opt('home_mitra_title'),'cta_title'=>lp2m_opt('home_cta_title'),'cta_desc'=>lp2m_opt('home_cta_desc'),'footer_tagline'=>lp2m_opt('home_footer_tagline')];}
