<?php
/**
 * Image pickers for taxonomy term meta.
 *
 * `fakultas_icon_image` (fakultas) and `kategori_info_icon` (kategori_info) are
 * stored as attachment IDs and rendered by the archive templates. Both
 * taxonomies get the same add/edit field, save handler and media-picker footer
 * script.
 *
 * @package itsi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fakultas taxonomy term meta: image picker di form Add/Edit term.
 *
 * Disimpan sebagai attachment ID di term meta `fakultas_icon_image`.
 * Dipakai oleh ProdiComponent::render() sebagai icon fakultas di section
 * "Program Studi" homepage.
 *
 * Pattern ini native WP — tidak bergantung pada TypeRocket form API karena
 * TR Taxonomy model tidak expose image field secara langsung di form add/edit
 * term. Kita render manual pakai wp.media() (sudah ada di admin).
 */
function itsi_fakultas_icon_form_field( $term = null ) {
	$icon_id = 0;
	if ( $term && isset( $term->term_id ) ) {
		$icon_id = (int) get_term_meta( $term->term_id, 'fakultas_icon_image', true );
	}
	$icon_url = $icon_id > 0 ? (string) wp_get_attachment_url( $icon_id ) : '';
	?>
	<tr class="form-field itsi-fakultas-icon-wrap">
		<th scope="row" valign="top"><label for="itsi_fakultas_icon_image"><?php esc_html_e( 'Icon Fakultas (gambar)', 'itsi' ); ?></label></th>
		<td>
			<input type="hidden" name="itsi_fakultas_icon_image" id="itsi_fakultas_icon_image" value="<?php echo esc_attr( $icon_id > 0 ? (string) $icon_id : '' ); ?>" />
			<div id="itsi-fakultas-icon-preview" style="margin-bottom:.6rem<?php echo $icon_url === '' ? ';display:none' : ''; ?>">
				<img src="<?php echo esc_url( $icon_url ); ?>" alt="" style="max-width:80px;max-height:80px;border:1px solid #ddd;border-radius:4px;padding:4px;background:#fff" />
			</div>
			<button type="button" class="button" id="itsi-fakultas-icon-upload">
				<?php echo $icon_id > 0 ? esc_html__( 'Ganti gambar', 'itsi' ) : esc_html__( 'Pilih gambar', 'itsi' ); ?>
			</button>
			<button type="button" class="button" id="itsi-fakultas-icon-clear" style="<?php echo $icon_id > 0 ? '' : 'display:none'; ?>">
				<?php esc_html_e( 'Hapus', 'itsi' ); ?>
			</button>
			<p class="description"><?php esc_html_e( 'Ditampilkan sebagai icon fakultas (~48×48 px) di section "Program Studi". Kosongkan jika tidak ada.', 'itsi' ); ?></p>
		</td>
	</tr>
	<?php
}
add_action( 'fakultas_add_form_fields', 'itsi_fakultas_icon_form_field', 10, 1 );
add_action( 'fakultas_edit_form_fields', 'itsi_fakultas_icon_form_field', 10, 1 );

/**
 * Simpan term meta fakultas_icon_image. Validasi: harus numeric attachment ID.
 */
function itsi_fakultas_icon_save( $term_id ) {
	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}
	// posted as POST[itsi_fakultas_icon_image]
	$raw = isset( $_POST['itsi_fakultas_icon_image'] ) ? sanitize_text_field( wp_unslash( $_POST['itsi_fakultas_icon_image'] ) ) : '';
	if ( $raw === '' ) {
		delete_term_meta( $term_id, 'fakultas_icon_image' );
		return;
	}
	if ( ! is_numeric( $raw ) ) {
		return;
	}
	$att_id = (int) $raw;
	// Verify it's a real attachment — wp_get_attachment_url returns false for non-media posts.
	if ( ! wp_get_attachment_url( $att_id ) ) {
		delete_term_meta( $term_id, 'fakultas_icon_image' );
		return;
	}
	update_term_meta( $term_id, 'fakultas_icon_image', $att_id );
}
add_action( 'created_fakultas', 'itsi_fakultas_icon_save', 10, 1 );
add_action( 'edited_fakultas', 'itsi_fakultas_icon_save', 10, 1 );

/**
 * Print wp.media picker JS di footer halaman taxonomy fakultas.
 * Lebih reliable daripada wp_add_inline_script yang bergantung pada handle script
 * yang mungkin tidak di-enqueue di halaman taxonomy.
 */
function itsi_fakultas_icon_admin_print_js() {
	$screen = get_current_screen();
	if ( ! $screen || $screen->taxonomy !== 'fakultas' ) {
		return;
	}
	wp_enqueue_media();
	?>
	<script type="text/javascript">
	(function($){
		$(function(){
			if (typeof wp === 'undefined' || !wp.media) { return; }
			$('#itsi-fakultas-icon-upload').on('click', function(e){
				e.preventDefault();
				var frame = wp.media({ title: 'Pilih Icon Fakultas', button: { text: 'Pilih' }, multiple: false, library: { type: 'image' } });
				frame.on('select', function(){
					var att = frame.state().get('selection').first().toJSON();
					$('#itsi_fakultas_icon_image').val(att.id);
					$('#itsi-fakultas-icon-preview img').attr('src', att.url);
					$('#itsi-fakultas-icon-preview').show();
					$('#itsi-fakultas-icon-clear').show();
					$('#itsi-fakultas-icon-upload').text('Ganti gambar');
				});
				frame.open();
			});
			$('#itsi-fakultas-icon-clear').on('click', function(e){
				e.preventDefault();
				$('#itsi_fakultas_icon_image').val('');
				$('#itsi-fakultas-icon-preview').hide();
				$('#itsi-fakultas-icon-clear').hide();
				$('#itsi-fakultas-icon-upload').text('Pilih gambar');
			});
		});
	})(jQuery);
	</script>
	<?php
}
add_action( 'admin_print_footer_scripts', 'itsi_fakultas_icon_admin_print_js' );

/**
 * Kategori Informasi taxonomy term meta: image picker di form Add/Edit term.
 *
 * Disimpan sebagai attachment ID di term meta `kategori_info_icon`.
 * Dipakai oleh archive-info_publik.php sebagai icon kategori (pengganti emoji)
 * di filter bar + badge dokumen. Pola sama persis dengan fakultas_icon_image.
 */
function itsi_kategori_info_icon_form_field( $term = null ) {
	$icon_id = 0;
	if ( $term && isset( $term->term_id ) ) {
		$icon_id = (int) get_term_meta( $term->term_id, 'kategori_info_icon', true );
	}
	$icon_url = $icon_id > 0 ? (string) wp_get_attachment_url( $icon_id ) : '';
	?>
	<tr class="form-field itsi-katinfo-icon-wrap">
		<th scope="row" valign="top"><label for="itsi_kategori_info_icon"><?php esc_html_e( 'Icon Kategori (gambar)', 'itsi' ); ?></label></th>
		<td>
			<input type="hidden" name="itsi_kategori_info_icon" id="itsi_kategori_info_icon" value="<?php echo esc_attr( $icon_id > 0 ? (string) $icon_id : '' ); ?>" />
			<div id="itsi-katinfo-icon-preview" style="margin-bottom:.6rem<?php echo $icon_url === '' ? ';display:none' : ''; ?>">
				<img src="<?php echo esc_url( $icon_url ); ?>" alt="" style="max-width:80px;max-height:80px;border:1px solid #ddd;border-radius:4px;padding:4px;background:#fff" />
			</div>
			<button type="button" class="button" id="itsi-katinfo-icon-upload">
				<?php echo $icon_id > 0 ? esc_html__( 'Ganti gambar', 'itsi' ) : esc_html__( 'Pilih gambar', 'itsi' ); ?>
			</button>
			<button type="button" class="button" id="itsi-katinfo-icon-clear" style="<?php echo $icon_id > 0 ? '' : 'display:none'; ?>">
				<?php esc_html_e( 'Hapus', 'itsi' ); ?>
			</button>
			<p class="description"><?php esc_html_e( 'Ditampilkan sebagai icon kategori di halaman Informasi Publik (menggantikan emoji). Kosongkan jika tidak ada.', 'itsi' ); ?></p>
		</td>
	</tr>
	<?php
}
add_action( 'kategori_info_add_form_fields', 'itsi_kategori_info_icon_form_field', 10, 1 );
add_action( 'kategori_info_edit_form_fields', 'itsi_kategori_info_icon_form_field', 10, 1 );

/**
 * Simpan term meta kategori_info_icon. Validasi: harus numeric attachment ID.
 */
function itsi_kategori_info_icon_save( $term_id ) {
	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}
	$raw = isset( $_POST['itsi_kategori_info_icon'] ) ? sanitize_text_field( wp_unslash( $_POST['itsi_kategori_info_icon'] ) ) : '';
	if ( $raw === '' ) {
		delete_term_meta( $term_id, 'kategori_info_icon' );
		return;
	}
	if ( ! is_numeric( $raw ) ) {
		return;
	}
	$att_id = (int) $raw;
	if ( ! wp_get_attachment_url( $att_id ) ) {
		delete_term_meta( $term_id, 'kategori_info_icon' );
		return;
	}
	update_term_meta( $term_id, 'kategori_info_icon', $att_id );
}
add_action( 'created_kategori_info', 'itsi_kategori_info_icon_save', 10, 1 );
add_action( 'edited_kategori_info', 'itsi_kategori_info_icon_save', 10, 1 );

/**
 * Print wp.media picker JS di footer halaman taxonomy kategori_info.
 */
function itsi_kategori_info_icon_admin_print_js() {
	$screen = get_current_screen();
	if ( ! $screen || $screen->taxonomy !== 'kategori_info' ) {
		return;
	}
	wp_enqueue_media();
	?>
	<script type="text/javascript">
	(function($){
		$(function(){
			if (typeof wp === 'undefined' || !wp.media) { return; }
			$('#itsi-katinfo-icon-upload').on('click', function(e){
				e.preventDefault();
				var frame = wp.media({ title: 'Pilih Icon Kategori', button: { text: 'Pilih' }, multiple: false, library: { type: 'image' } });
				frame.on('select', function(){
					var att = frame.state().get('selection').first().toJSON();
					$('#itsi_kategori_info_icon').val(att.id);
					$('#itsi-katinfo-icon-preview img').attr('src', att.url);
					$('#itsi-katinfo-icon-preview').show();
					$('#itsi-katinfo-icon-clear').show();
					$('#itsi-katinfo-icon-upload').text('Ganti gambar');
				});
				frame.open();
			});
			$('#itsi-katinfo-icon-clear').on('click', function(e){
				e.preventDefault();
				$('#itsi_kategori_info_icon').val('');
				$('#itsi-katinfo-icon-preview').hide();
				$('#itsi-katinfo-icon-clear').hide();
				$('#itsi-katinfo-icon-upload').text('Pilih gambar');
			});
		});
	})(jQuery);
	</script>
	<?php
}
add_action( 'admin_print_footer_scripts', 'itsi_kategori_info_icon_admin_print_js' );
