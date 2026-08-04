<?php
/**
 * LP2M Settings — TypeRocket v6 Style + WordPress Native Save
 *
 * Akses: WP Admin → LP2M → Settings
 * REST API grouped by component:
 *   GET /wp-json/lp2m/v1/settings/{site|dokumen|hero|about|homepage}
 */

defined('ABSPATH') || exit;

// =================================================================
// 1. REGISTER ADMIN MENU + SETTINGS
// =================================================================
add_action('admin_menu', function() {
    add_menu_page('LP2M', 'LP2M', 'manage_options', 'lp2m-settings', 'lp2m_render', 'dashicons-welcome-learn-more', 30);
});

add_action('admin_init', function() {
    $keys = [
        'site_logo_id','site_favicon_id','site_nama','site_nama_panjang','site_email','site_telepon','site_alamat','site_admin_email','siteAdminEmail',
        'dok_panduan_id','dok_template_id',
        'hero_headline','hero_title','hero_caption','hero_btn_primary_text','hero_btn_primary_url','hero_btn_secondary_text','hero_btn_secondary_url','hero_infografis',
        'about_eyebrow','about_title','about_desc','about_quote','about_quote_body','about_pillars','about_leadership',
        'home_bidang_title','home_bidang_desc','home_mitra_title','home_cta_title','home_cta_desc','home_footer_tagline',
    ];
    foreach ($keys as $k) register_setting('lp2m_group', 'lp2m_'.$k);
});

// =================================================================
// 2. RENDER PAGE
// =================================================================
function lp2m_render() {
    $form = tr_form();
    ?>
    <div class="wrap">
        <h1>LP2M Settings</h1>
        <p>REST API: <code>/wp-json/lp2m/v1/settings/{site|dokumen|hero|about|homepage}</code></p>
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
                        <?php lp2m_row('lp2m_site_admin_email','Email Admin (Terima Notif)','Email yang terima notifikasi pendaftaran hibah'); ?>
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

            <!-- HERO - Infografis -->
            <div class="postbox">
                <div class="postbox-header"><h2>HERO — Infografis</h2></div>
                <div class="inside">
                    <?php lp2m_repeater('hero_infografis', [
                        ['key' => 'label', 'label' => 'Label', 'placeholder' => 'cth. Dosen Aktif'],
                        ['key' => 'angka', 'label' => 'Angka', 'placeholder' => 'cth. 58', 'class' => 'small-text'],
                    ]); ?>
                </div>
            </div>

            <!-- ABOUT -->
            <div class="postbox">
                <div class="postbox-header"><h2>ABOUT — Tentang LP2M</h2></div>
                <div class="inside">
                    <table class="form-table">
                        <?php lp2m_row('lp2m_about_eyebrow','Eyebrow','Tentang Kami'); ?>
                        <?php lp2m_row('lp2m_about_title','Judul','Kedudukan, Tugas, dan Fungsi LP2M'); ?>
                        <?php lp2m_row('lp2m_about_desc','Deskripsi','','textarea'); ?>
                        <?php lp2m_row('lp2m_about_quote','Lead Quote','','textarea'); ?>
                        <?php lp2m_row('lp2m_about_quote_body','Quote Body','','textarea'); ?>
                    </table>
                </div>
            </div>

            <!-- ABOUT - Pillars -->
            <div class="postbox">
                <div class="postbox-header"><h2>ABOUT — Pilar (Repeater)</h2></div>
                <div class="inside">
                    <?php lp2m_repeater('about_pillars', [
                        ['key' => 'num', 'label' => 'No', 'placeholder' => '01', 'class' => 'small-text'],
                        ['key' => 'title', 'label' => 'Judul', 'placeholder' => 'Perencanaan & Pengelolaan Hibah'],
                        ['key' => 'desc', 'label' => 'Deskripsi', 'placeholder' => ''],
                    ]); ?>
                </div>
            </div>

            <!-- ABOUT - Leadership -->
            <div class="postbox">
                <div class="postbox-header"><h2>ABOUT — Kepemimpinan (Repeater)</h2></div>
                <div class="inside">
                    <?php lp2m_repeater('about_leadership', [
                        ['key' => 'role', 'label' => 'Role', 'placeholder' => 'Ketua LP2M'],
                        ['key' => 'name', 'label' => 'Nama', 'placeholder' => 'Ketua Lembaga'],
                        ['key' => 'unit', 'label' => 'Unit', 'placeholder' => 'Koordinasi umum & kebijakan riset institusi'],
                    ]); ?>
                </div>
            </div>

            <!-- HOMEPAGE -->
            <div class="postbox">
                <div class="postbox-header"><h2>HOMEPAGE — Sections</h2></div>
                <div class="inside">
                    <table class="form-table">
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

function lp2m_repeater($key, $columns) {
    $json = get_option('lp2m_'.$key, '[]');
    $items = json_decode($json, true) ?: [];
    $tableId = 'lp2m-rpt-'.$key;
    ?>
    <table class="widefat striped" style="margin-bottom:8px" id="<?php echo $tableId; ?>">
        <thead><tr>
            <?php foreach ($columns as $col): ?>
            <th><?php echo esc_html($col['label']); ?></th>
            <?php endforeach; ?>
            <th style="width:60px"></th>
        </tr></thead>
        <tbody class="lp2m-rpt-rows">
            <?php if (empty($items)): ?>
            <tr class="no-items"><td colspan="<?php echo count($columns)+1; ?>" style="text-align:center;padding:20px;color:#999">Belum ada item.</td></tr>
            <?php else: ?>
            <?php foreach ($items as $item): ?>
            <tr>
                <?php foreach ($columns as $col): ?>
                <td><input type="text" name="lp2m_rpt_<?php echo $key; ?>_<?php echo $col['key']; ?>[]" value="<?php echo esc_attr($item[$col['key']]??''); ?>" class="<?php echo $col['class']??'regular-text'; ?>" placeholder="<?php echo esc_attr($col['placeholder']??''); ?>" /></td>
                <?php endforeach; ?>
                <td><button type="button" class="button lp2m-remove-row">✕</button></td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
    <button type="button" class="button lp2m-add-row">+ Tambah Item</button>
    <input type="hidden" name="lp2m_<?php echo $key; ?>" id="lp2m_json_<?php echo $key; ?>" value="<?php echo esc_attr($json); ?>" />
    <?php
}

function lp2m_media_js() {
    wp_enqueue_media();
    ?>
    <script>
    jQuery(function($) {
        function rebuild(table) {
            var $t = $(table), rows = $t.find('.lp2m-rpt-rows tr').not('.no-items');
            var key = $t.attr('id').replace('lp2m-rpt-', ''), items = [];
            rows.each(function() {
                var obj = {};
                $(this).find('input[type="text"]').each(function() {
                    var name = $(this).attr('name'), val = $(this).val();
                    var fieldKey = name.replace('lp2m_rpt_'+key+'_', '').replace('[]', '');
                    obj[fieldKey] = val;
                });
                if (Object.values(obj).some(function(v){return !!v;})) items.push(obj);
            });
            $('#lp2m_json_'+key).val(JSON.stringify(items));
        }

        $('.lp2m-add-row').on('click', function(e) {
            e.preventDefault();
            var $table = $(this).closest('.postbox').find('table.widefat');
            var $row = $table.find('.lp2m-rpt-rows tr').last();
            var $clone = $row.clone();
            $clone.find('input').val('');
            $clone.find('.no-items').remove();
            $table.find('.no-items').remove();
            if (!$row.hasClass('no-items')) {
                $table.find('.lp2m-rpt-rows').append($clone);
            }
            rebuild($table);
        });

        $(document).on('click', '.lp2m-remove-row', function() {
            var $table = $(this).closest('table.widefat'), $row = $(this).closest('tr');
            $row.remove();
            if (!$table.find('.lp2m-rpt-rows tr').length) {
                $table.find('.lp2m-rpt-rows').append('<tr class="no-items"><td colspan="99" style="text-align:center;padding:20px;color:#999">Belum ada item.</td></tr>');
            }
            rebuild($table);
        });

        $(document).on('change input', 'table.widefat input[type="text"]', function() {
            rebuild($(this).closest('table.widefat'));
        });
    });
    </script>
    <?php
}

// =================================================================
// 4. HOOK: save repeater hidden JSON
// =================================================================
add_action('admin_init', function() {
    if (isset($_POST['option_page']) && $_POST['option_page'] === 'lp2m_group') {
        foreach (['hero_infografis', 'about_pillars', 'about_leadership'] as $rpt) {
            if (isset($_POST['lp2m_'.$rpt])) {
                update_option('lp2m_'.$rpt, wp_unslash($_POST['lp2m_'.$rpt]));
            }
        }
    }
});

// =================================================================
// 5. REST API — GROUPED BY COMPONENT
// =================================================================
add_action('rest_api_init', function() {
    foreach (['','site','dokumen','hero','about','homepage'] as $g) {
        $path = $g ? '/settings/'.$g : '/settings';
        $cb = 'lp2m_rest'.($g ? '_'.$g : '_all');
        register_rest_route('lp2m/v1', $path, ['methods'=>'GET','callback'=>$cb,'permission_callback'=>'__return_true']);
    }
});

function lp2m_rest_all(){return rest_ensure_response(['site'=>lp2m_site_data(),'dokumen'=>lp2m_dok_data(),'hero'=>lp2m_hero_data(),'about'=>lp2m_about_data(),'homepage'=>lp2m_home_data()]);}
function lp2m_rest_site(){return rest_ensure_response(lp2m_site_data());}
function lp2m_rest_dokumen(){return rest_ensure_response(lp2m_dok_data());}
function lp2m_rest_hero(){return rest_ensure_response(lp2m_hero_data());}
function lp2m_rest_about(){return rest_ensure_response(lp2m_about_data());}
function lp2m_rest_homepage(){return rest_ensure_response(lp2m_home_data());}

function lp2m_opt($k){return get_option('lp2m_'.$k,'');}
function lp2m_url($id){return $id?wp_get_attachment_url((int)$id):'';}
function lp2m_site_data(){return['logo_id'=>lp2m_opt('site_logo_id'),'logo_url'=>lp2m_url(lp2m_opt('site_logo_id')),'favicon_id'=>lp2m_opt('site_favicon_id'),'favicon_url'=>lp2m_url(lp2m_opt('site_favicon_id')),'nama'=>lp2m_opt('site_nama')?:'LP2M ITSI','nama_panjang'=>lp2m_opt('site_nama_panjang'),'email'=>lp2m_opt('site_email'),'telepon'=>lp2m_opt('site_telepon'),'alamat'=>lp2m_opt('site_alamat')];}
function lp2m_dok_data(){return['panduan_id'=>lp2m_opt('dok_panduan_id'),'panduan_url'=>lp2m_url(lp2m_opt('dok_panduan_id')),'template_id'=>lp2m_opt('dok_template_id'),'template_url'=>lp2m_url(lp2m_opt('dok_template_id'))];}
function lp2m_hero_data(){return['headline'=>lp2m_opt('hero_headline'),'title'=>lp2m_opt('hero_title'),'caption'=>lp2m_opt('hero_caption'),'btn_primary_text'=>lp2m_opt('hero_btn_primary_text'),'btn_primary_url'=>lp2m_opt('hero_btn_primary_url'),'btn_secondary_text'=>lp2m_opt('hero_btn_secondary_text'),'btn_secondary_url'=>lp2m_opt('hero_btn_secondary_url'),'infografis'=>json_decode(lp2m_opt('hero_infografis'),true)?:[]];}
function lp2m_about_data(){return['eyebrow'=>lp2m_opt('about_eyebrow'),'title'=>lp2m_opt('about_title'),'desc'=>lp2m_opt('about_desc'),'quote'=>lp2m_opt('about_quote'),'quote_body'=>lp2m_opt('about_quote_body'),'pillars'=>json_decode(lp2m_opt('about_pillars'),true)?:[],'leadership'=>json_decode(lp2m_opt('about_leadership'),true)?:[]];}
function lp2m_home_data(){return['bidang_title'=>lp2m_opt('home_bidang_title'),'bidang_desc'=>lp2m_opt('home_bidang_desc'),'mitra_title'=>lp2m_opt('home_mitra_title'),'cta_title'=>lp2m_opt('home_cta_title'),'cta_desc'=>lp2m_opt('home_cta_desc'),'footer_tagline'=>lp2m_opt('home_footer_tagline')];}
