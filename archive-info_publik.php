<?php
/**
 * Template Name: Informasi Publik Archive
 *
 * @package itsi
 */

get_header();

// Get current query vars
$paged  = max( 1, (int) get_query_var( 'paged' ) );
$q_obj  = get_queried_object();
$term   = ( is_tax( 'kategori_info' ) ) ? $q_obj : null;
$search = get_search_query();

// Stats
$total_docs = wp_count_posts( 'info_publik' )->publish;
$cat_terms  = get_terms( [ 'taxonomy' => 'kategori_info', 'hide_empty' => false ] );
$total_cats = ! is_wp_error( $cat_terms ) ? count( $cat_terms ) : 0;

// Dynamic content from theme_mods (admin: ITSI → Informasi Publik)
$ip_form     = itsi_ip_get_form_settings();
$ip_stats    = itsi_ip_get_stats( [ 'total_docs' => $total_docs, 'total_cats' => $total_cats ] );
$ip_kip      = itsi_ip_get_kip_cards();
$ppid        = itsi_ip_get_ppid_banner();

// Category color map
$kat_colors = [
	'profil-institusi' => [ 'bg' => '#1459B31a', 'color' => '#1459B3' ],
	'akademik'         => [ 'bg' => '#0891B21a', 'color' => '#0891B2' ],
	'keuangan'         => [ 'bg' => '#b91c1c1a', 'color' => '#b91c1c' ],
	'kemahasiswaan'    => [ 'bg' => '#15803d1a', 'color' => '#15803d' ],
	'sdm'              => [ 'bg' => '#6d28d91a', 'color' => '#6d28d9' ],
	'penelitian'       => [ 'bg' => '#0369a11a', 'color' => '#0369a1' ],
	'akreditasi'       => [ 'bg' => '#BF9B301a', 'color' => '#BF9B30' ],
	'kerja-sama'       => [ 'bg' => '#92400e1a', 'color' => '#92400e' ],
	'ppid'             => [ 'bg' => '#3741511a', 'color' => '#374151' ],
];

// Current filter (client-side filter via data-kat)
$current_kat = isset( $_GET['kat'] ) ? sanitize_text_field( wp_unslash( $_GET['kat'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>

<main id="primary" class="site-main">

	<!-- ═══ PAGE HERO ═══ -->
	<section id="phero">
		<div class="ph-bg"></div>
		<div class="ph-dots"></div>
		<div class="ph-blob ph-b1"></div>
		<div class="ph-blob ph-b2"></div>
		<div class="ph-inner container">
			<div class="bc rv">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Beranda</a>
				<span>›</span>
				<span style="color:rgba(255,255,255,.6)">Informasi Publik</span>
			</div>
			<h1 class="ph-title rv d1">Layanan <em>Informasi</em> Publik</h1>
			<p class="ph-sub rv d2">Akses dokumen resmi, regulasi, dan informasi publik Institut Teknologi Sawit Indonesia secara transparan dan terbuka sesuai UU KIP No. 14 Tahun 2008.</p>
		</div>
	</section>

	<!-- ═══ STATS BAR ═══ -->
	<?php if ( ! empty( $ip_stats ) ) : ?>
	<div id="statsbar">
		<div class="container">
			<div class="sb-inner">
				<?php
				$d = 0;
				foreach ( $ip_stats as $st ) :
					$st_icon = isset( $st['icon'] ) ? $st['icon'] : '';
					$st_angka = isset( $st['angka'] ) ? (string) $st['angka'] : '';
					$st_label = isset( $st['label'] ) ? (string) $st['label'] : '';
					?>
				<div class="sb-item rv <?php echo $d > 0 ? 'd' . $d : ''; ?>">
					<?php if ( '' !== $st_icon ) : ?>
						<div class="sb-ic"><?php echo itsi_ip_render_icon_img( $st_icon, '', $st_label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
					<?php endif; ?>
					<div class="sb-n"><?php echo esc_html( $st_angka ); ?></div>
					<div class="sb-l"><?php echo esc_html( $st_label ); ?></div>
				</div>
					<?php
					++$d;
				endforeach;
				?>
			</div>
		</div>
	</div>
	<?php endif; ?>

	<!-- ═══ MAIN CONTENT ═══ -->
	<div class="main-wrap">
		<div class="container">

			<!-- PPID Banner -->
			<div class="ppid-banner rv">
				<div class="ppid-ic"><?php echo itsi_ip_render_icon_img( $ppid['icon'], '', $ppid['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<div>
					<div class="ppid-title"><?php echo esc_html( $ppid['title'] ); ?></div>
					<p class="ppid-desc"><?php echo esc_html( $ppid['desc'] ); ?></p>
				</div>
				<div class="ppid-btns">
					<a href="#doc-table" class="ppid-btn ppid-btn-main">Lihat Dokumen</a>
					<?php if ( $ip_form['form_enabled'] ) : ?>
						<a href="#form-permohonan" class="ppid-btn ppid-btn-out">Ajukan Permohonan</a>
					<?php endif; ?>
				</div>
			</div>

			<!-- Toolbar: Search + Category Filter -->
			<div class="toolbar rv">
				<form class="search-wrap" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
					<input type="hidden" name="post_type" value="info_publik">
					<span aria-hidden="true" class="search-ic"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></span>
					<input type="search" name="s" placeholder="Cari dokumen, kategori, atau tahun…" value="<?php echo esc_attr( $search ); ?>">
				</form>
				<div class="kat-filter">
					<button type="button" class="kf-btn <?php echo '' === $current_kat ? 'on' : ''; ?>" data-kat="">Semua</button>
					<?php
					if ( ! is_wp_error( $cat_terms ) ) :
						foreach ( $cat_terms as $t ) :
							$t_icon = itsi_ip_get_term_icon( $t->term_id );
							?>
							<button type="button" class="kf-btn <?php echo $current_kat === $t->slug ? 'on' : ''; ?>" data-kat="<?php echo esc_attr( $t->slug ); ?>">
								<?php
								if ( '' !== $t_icon ) {
									echo itsi_ip_render_icon_img( $t_icon, 'kf-ic', $t->name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								}
								echo esc_html( $t->name );
								?>
							</button>
							<?php
						endforeach;
					endif;
					?>
				</div>
			</div>

			<!-- Document Table -->
			<div class="doc-table-wrap rv" id="doc-table">
				<?php
				$args = [
					'post_type'      => 'info_publik',
					'posts_per_page' => 12,
					'paged'          => $paged,
				];

				if ( $term ) {
					$args['tax_query'] = [
						[
							'taxonomy' => 'kategori_info',
							'field'    => 'term_id',
							'terms'    => $term->term_id,
						],
					];
				} elseif ( $current_kat ) {
					$args['tax_query'] = [
						[
							'taxonomy' => 'kategori_info',
							'field'    => 'slug',
							'terms'    => $current_kat,
						],
					];
				}

				if ( $search ) {
					$args['s'] = $search;
				}

				$q           = new WP_Query( $args );
				$total_found = $q->found_posts;
				?>

				<div class="doc-count" id="docCount">
					Menampilkan <strong><?php echo esc_html( $total_found ); ?></strong> dokumen
				</div>

				<?php if ( $q->have_posts() ) : ?>
					<table class="doc-tbl">
						<thead>
							<tr>
								<th>#</th>
								<th>Nama Dokumen</th>
								<th>Kategori</th>
								<th>Tahun</th>
								<th>Aksi</th>
							</tr>
						</thead>
						<tbody>
							<?php
							$i = 0;
							while ( $q->have_posts() ) :
								$q->the_post();
								++$i;
								$post_id = get_the_ID();

								$cats    = get_the_terms( $post_id, 'kategori_info' );
								$cat     = ( ! empty( $cats ) && ! is_wp_error( $cats ) ) ? $cats[0] : null;
								$kat_nm  = $cat ? $cat->name : '-';
								$kat_sl  = $cat ? $cat->slug : '';
								$kat_c   = $kat_colors[ $kat_sl ] ?? [ 'bg' => '#6880A01a', 'color' => '#6880A0' ];
								$kat_icon = $cat ? itsi_ip_get_term_icon( $cat->term_id ) : '';
								$file    = get_post_meta( $post_id, 'file_url', true );
													$size    = get_post_meta( $post_id, 'ukuran_file', true );
													$year    = get_post_meta( $post_id, 'tahun', true );
													$year    = $year ?: get_the_date( 'Y' );
													// Resolve file_url: jika numeric → attachment ID → wp_get_attachment_url(); jika URL string → pakai langsung
													if ( $file && '#' !== $file ) {
														if ( is_numeric( $file ) ) {
															$resolved = wp_get_attachment_url( (int) $file );
															$dl_link  = $resolved ? $resolved : get_permalink();
														} else {
															$dl_link = $file;
														}
													} else {
														$dl_link = get_permalink();
													}
								?>
							<tr class="doc-row" data-kat="<?php echo esc_attr( $kat_sl ); ?>">
								<td class="doc-no"><?php echo esc_html( ( $paged - 1 ) * 12 + $i ); ?></td>
								<td>
									<div class="doc-nm-wrap">
										<div class="doc-icon"><?php echo itsi_ip_render_icon_img( $kat_icon, '', $kat_nm ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
										<div>
											<div class="doc-nm"><?php the_title(); ?></div>
											<?php if ( $size ) : ?>
												<div class="doc-sub">
													<span class="doc-sub-ic" aria-hidden="true"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg></span>
													<?php echo esc_html( $size ); ?>
												</div>
											<?php endif; ?>
										</div>
									</div>
								</td>
								<td>
									<span class="doc-kat-badge" style="background:<?php echo esc_attr( $kat_c['bg'] ); ?>;color:<?php echo esc_attr( $kat_c['color'] ); ?>">
										<?php echo esc_html( $kat_nm ); ?>
									</span>
								</td>
								<td class="doc-thn"><?php echo esc_html( $year ); ?></td>
								<td>
									<a href="<?php echo esc_url( $dl_link ); ?>" target="_blank" rel="noopener" class="doc-dl-btn">
										<span aria-hidden="true" class="doc-dl-ic"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg></span>
										Unduh
									</a>
								</td>
							</tr>
							<?php endwhile; wp_reset_postdata(); ?>
						</tbody>
					</table>

					<div class="itsi-pagination" role="navigation" aria-label="Navigasi halaman">
						<?php
						$big = 999999999;
						$pag_links = paginate_links(
							[
								'base'      => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
								'format'    => '?paged=%#%',
								'current'   => $paged,
								'total'     => $q->max_num_pages,
								'mid_size'  => 2,
								'prev_text' => '← Sebelumnya',
								'next_text' => 'Selanjutnya →',
								'type'      => 'array',
							]
						);
						if ( ! empty( $pag_links ) ) {
							echo '<div class="nav-links">';
							foreach ( $pag_links as $link ) {
								// Normalize root <a> that lacks nav-links children, but
								// also wrap in span when paginate_links returns raw HTML
								// (the WP default string is fine, but array gives us a
								// single .nav-links container which already exists in
								// style.css line 744 — keep that hierarchy intact).
								echo $link; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							}
							echo '</div>';
						}
						?>
					</div>
				<?php else : ?>
					<div style="padding:3rem;text-align:center">
						<div style="display:inline-flex;align-items:center;justify-content:center;width:64px;height:64px;border-radius:16px;background:#eef2f7;color:#8aa0b8;margin-bottom:1rem">
							<svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/></svg>
						</div>
						<h3 style="color:var(--tx-dark);margin-bottom:.5rem;font-family:'Cormorant Garamond',serif;font-size:1.5rem">Belum ada dokumen</h3>
						<p style="color:var(--tx-mid)">Dokumen informasi publik akan ditampilkan di sini.</p>
					</div>
				<?php endif; ?>
			</div>

			<!-- KIP Info Cards -->
			<?php if ( ! empty( $ip_kip ) ) : ?>
			<div class="kip-cards" id="kip-info">
				<?php
				$d = 0;
				foreach ( $ip_kip as $kp ) :
					$kp_icon = isset( $kp['icon'] ) ? $kp['icon'] : '';
					$kp_title = isset( $kp['title'] ) ? (string) $kp['title'] : '';
					$kp_text  = isset( $kp['text'] ) ? (string) $kp['text'] : '';
					?>
				<div class="kip-card rv <?php echo $d > 0 ? 'd' . $d : ''; ?>">
					<div class="kip-ic"><?php echo itsi_ip_render_icon_img( $kp_icon, '', $kp_title ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
					<div class="kip-title"><?php echo esc_html( $kp_title ); ?></div>
					<p class="kip-text"><?php echo esc_html( $kp_text ); ?></p>
				</div>
					<?php
					++$d;
				endforeach;
				?>
			</div>
			<?php endif; ?>

			<!-- Permohonan Informasi Form -->
			<?php if ( $ip_form['form_enabled'] ) : ?>
			<div class="form-section rv" id="form-permohonan">
				<h2 class="form-title">Formulir <em>Permohonan</em> Informasi Publik</h2>
				<p class="form-sub">Isi formulir di bawah ini untuk mengajukan permohonan informasi publik kepada PPID Institut Teknologi Sawit Indonesia. Permohonan akan diproses dalam waktu 10 hari kerja.</p>

				<form id="formPermohonan" novalidate>
					<div class="form-grid">
						<div class="form-group">
							<label class="form-lbl" for="nama">Nama Lengkap <span>*</span></label>
							<input class="form-inp" id="nama" name="nama" type="text" placeholder="Masukkan nama lengkap Anda" required>
						</div>
						<div class="form-group">
							<label class="form-lbl" for="nik">NIK / Nomor Identitas <span>*</span></label>
							<input class="form-inp" id="nik" name="nik" type="text" placeholder="16 digit NIK KTP" required>
						</div>
						<div class="form-group">
							<label class="form-lbl" for="email">Alamat Email <span>*</span></label>
							<input class="form-inp" id="email" name="email" type="email" placeholder="nama@email.com" required>
						</div>
						<div class="form-group">
							<label class="form-lbl" for="no_hp">Nomor HP / WhatsApp <span>*</span></label>
							<input class="form-inp" id="no_hp" name="no_hp" type="tel" placeholder="08xxxxxxxxxx" required>
						</div>
						<div class="form-group">
							<label class="form-lbl" for="tujuan">Tujuan Penggunaan Informasi <span>*</span></label>
							<select class="form-inp" id="tujuan" name="tujuan" required>
								<option value="">Pilih tujuan penggunaan</option>
								<option>Penelitian / Akademik</option>
								<option>Jurnalisme / Media</option>
								<option>Kebutuhan Hukum</option>
								<option>Pengawasan Publik</option>
								<option>Kepentingan Pribadi</option>
								<option>Lainnya</option>
							</select>
						</div>
						<div class="form-group">
							<label class="form-lbl" for="pekerjaan">Pekerjaan / Instansi</label>
							<input class="form-inp" id="pekerjaan" name="pekerjaan" type="text" placeholder="Pekerjaan atau nama instansi">
						</div>
						<div class="form-group full">
							<label class="form-lbl" for="deskripsi">Informasi yang Dimohon <span>*</span></label>
							<textarea class="form-inp" id="deskripsi" name="deskripsi" rows="4" placeholder="Deskripsikan secara spesifik informasi yang Anda butuhkan..." required></textarea>
							<span class="form-note">Uraikan dengan jelas jenis dan spesifikasi informasi yang dibutuhkan agar dapat diproses lebih cepat.</span>
						</div>
						<div class="form-group full">
							<label class="form-lbl" for="cara_penerimaan">Cara Memperoleh Informasi <span>*</span></label>
							<select class="form-inp" id="cara_penerimaan" name="cara_penerimaan" required>
								<option value="">Pilih cara penerimaan</option>
								<option value="email">Dikirim via Email</option>
								<option value="langsung">Diambil Langsung ke PPID</option>
								<option value="pos">Dikirim via Pos</option>
							</select>
						</div>
					</div>
					<div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
						<button type="submit" class="form-submit" id="formSubmitBtn">
							<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
							Kirim Permohonan
						</button>
						<span style="font-size:.78rem;color:var(--ts)">Data Anda dilindungi sesuai UU ITE dan kebijakan privasi ITSI</span>
					</div>
				</form>
			</div>
			<?php else : ?>
			<!-- Form disabled by admin: show a notice instead -->
			<div class="form-section form-disabled rv" id="form-permohonan">
				<h2 class="form-title">Formulir <em>Permohonan</em> Informasi Publik</h2>
				<p class="form-sub">Formulir permohonan informasi sedang ditutup sementara. Silakan hubungi PPID Institut Teknologi Sawit Indonesia melalui kontak resmi yang tersedia.</p>
			</div>
			<?php endif; ?>

		</div>
	</div>

</main>


<script>
(function(){
	/* Client-side category filter */
	var allRows = Array.prototype.slice.call(document.querySelectorAll('.doc-row'));
	var countEl = document.getElementById('docCount');

	function updateCount(visible) {
		if (!countEl) return;
		countEl.innerHTML = 'Menampilkan <strong>' + visible + '</strong> dokumen';
	}

	function applyFilter(kat) {
		var vis = 0;
		allRows.forEach(function(r){
			var match = !kat || r.getAttribute('data-kat') === kat;
			r.style.display = match ? '' : 'none';
			if (match) vis++;
		});
		updateCount(vis);
	}

	document.querySelectorAll('.kf-btn').forEach(function(btn){
		btn.addEventListener('click', function(){
			document.querySelectorAll('.kf-btn').forEach(function(b){ b.classList.remove('on'); });
			btn.classList.add('on');
			applyFilter(btn.getAttribute('data-kat') || '');
		});
	});

	/* Reveal animations on scroll */
	if ('IntersectionObserver' in window) {
		var observer = new IntersectionObserver(function(entries){
			entries.forEach(function(entry){
				if (entry.isIntersecting) {
					entry.target.classList.add('on');
					observer.unobserve(entry.target);
				}
			});
		}, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });
		document.querySelectorAll('.rv').forEach(function(el){ observer.observe(el); });
	} else {
		document.querySelectorAll('.rv').forEach(function(el){ el.classList.add('on'); });
	}
})();
</script>

<?php get_footer(); ?>
