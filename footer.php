<?php
/**
 * Footer – ITSI Theme.
 *
 * Refactored 2026-07-08:
 *   - Social links: dari theme_mod itsi_footer_social_* (admin atur di
 *     Appearance → ITSI → Footer tab). Default hard-coded fallback.
 *   - Kontak: dari theme_mod itsi_footer_address/phone/phone_link/email/hours.
 *   - Prodi / Info / Kontak columns: pakai wp_nav_menu() dengan location
 *     'footer-prodi' / 'footer-info' / 'footer-kontak' (admin setup di
 *     Appearance → Menus). Fallback ke hard-coded list kalau menu belum
 *     di-setup.
 *   - Copyright + Privacy/Terms/Sitemap: theme_mod + default fallback.
 *   - NO emoji — semua icon pakai inline SVG dari itsi_footer_icon_svg()
 *     helper (Feather-style 16x16, stroke="currentColor").
 *
 * @package itsi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Inline SVG icon helper (Feather-style). Returns string.
 *
 * @param string $name One of: location, phone, mail, globe, clock, facebook,
 *                     instagram, youtube, tiktok, twitter, x, linkedin, chevron, check.
 * @return string SVG markup.
 */
function itsi_footer_icon_svg( $name ) {
	$icons = array(
		'location' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>',
		'phone'    => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>',
		'mail'     => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>',
		'globe'    => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>',
		'clock'    => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg>',

		// Social brand SVGs (Simple Icons path data, 24x24 viewBox).
		'facebook'  => '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>',
		'instagram' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 1 0 0 12.324 6.162 6.162 0 0 0 0-12.324zM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm6.406-11.845a1.44 1.44 0 1 0 0 2.881 1.44 1.44 0 0 0 0-2.881z"/></svg>',
		'youtube'   => '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>',
		'tiktok'    => '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg>',
		'twitter'   => '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M23.953 4.57a10 10 0 0 1-2.825.775 4.958 4.958 0 0 0 2.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 0 0-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 0 0-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 0 1-2.228-.616v.06a4.923 4.923 0 0 0 3.946 4.827 4.996 4.996 0 0 1-2.212.085 4.936 4.936 0 0 0 4.604 3.417 9.867 9.867 0 0 1-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 0 0 7.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0 0 24 4.59z"/></svg>',
		'x'         => '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>',
		'linkedin'  => '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 0 1-2.063-2.065 2.063 2.063 0 1 1 2.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>',

		// Misc.
		'chevron' => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="18 15 12 9 6 15"/></svg>',
		'check'   => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>',
	);
	return isset( $icons[ $name ] ) ? $icons[ $name ] : '';
}

?>
	</div><!-- #content -->

	<!-- WIDGET AREA BEFORE FOOTER -->
	<?php if ( is_active_sidebar( 'itsi_before_footer' ) ) : ?>
		<section class="before-footer" aria-label="<?php esc_attr_e( 'Konten sebelum footer', 'itsi' ); ?>">
			<div class="container">
				<?php dynamic_sidebar( 'itsi_before_footer' ); ?>
			</div>
		</section>
	<?php endif; ?>

	<!-- FOOTER -->
	<footer id="colophon" role="contentinfo">
		<div class="footer-top">
			<div class="container">
				<?php
				/* === FOOTER LAYOUT BUILDER (dinamis) ===
				 *
				 * Layout dikonfigurasi di Appearance → ITSI → Footer → "Layout Footer"
				 * (repeater: Label + Width per baris). Setiap baris = 1 kolom footer &
				 * 1 widget area `footer_N` (diregistrasi di functions.php).
				 *
				 * Mode WIDGET : jika minimal satu widget area footer_N terisi, render
				 *                kolom dari widget tsb + inline grid-template-columns
				 *                sesuai width yang dikonfigurasi (terhubung ke class
				 *                .footer-grid). Kolom tanpa widget dilewati.
				 * Mode STATIS : jika tidak ada widget sama sekali, render footer statis
				 *                (Brand / Prodi / Informasi / Kontak) — identik dengan
				 *                tampilan lama, tanpa inline style.
				 */
				$itsi_layout       = itsi_get_footer_layout();
				$itsi_grid_columns = itsi_footer_grid_columns( $itsi_layout );
				$itsi_has_widgets  = false;
				foreach ( $itsi_layout as $i => $col ) {
					if ( is_active_sidebar( 'footer_' . ( (int) $i + 1 ) ) ) {
						$itsi_has_widgets = true;
						break;
					}
				}
				?>
				<div class="footer-grid"<?php echo $itsi_has_widgets ? ' style="--fg-cols:' . esc_attr( $itsi_grid_columns ) . '"' : ''; ?>>

					<?php if ( $itsi_has_widgets ) : ?>
						<?php
						/* === WIDGET MODE: render kolom dari widget area footer_N === */
						foreach ( $itsi_layout as $i => $col ) {
							$sidebar_id = 'footer_' . ( (int) $i + 1 );
							if ( ! is_active_sidebar( $sidebar_id ) ) {
								continue;
							}
							echo '<div class="f-col f-col-widget">';
							dynamic_sidebar( $sidebar_id );
							echo '</div>';
						}
						?>
					<?php else : ?>

					<?php
					/* === BRAND COLUMN === */
					$footer_logo   = itsi_get_logo_url();
					$footer_name   = get_bloginfo( 'name' );
					$footer_desc   = 'Institusi pendidikan tinggi unggulan yang berkomitmen menghasilkan SDM berkualitas di bidang teknologi perkebunan sawit dan industri pendukungnya.';
					?>
					<div class="f-brand">
						<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="f-logo" rel="home">
							<img src="<?php echo esc_url( $footer_logo ); ?>" alt="<?php echo esc_attr( $footer_name ); ?>">
							<div class="f-logo-txt">
								<span class="flt-s"><?php echo esc_html( 'ITSI' ); ?></span>
								<span class="flt-l"><?php echo esc_html( $footer_name ); ?></span>
							</div>
						</a>
						<p class="f-desc"><?php echo esc_html( $footer_desc ); ?></p>
						<?php
						/* Social media links — render only the ones admin has set. */
						$footer_socials = array(
							'facebook'  => 'Facebook',
							'instagram' => 'Instagram',
							'youtube'   => 'YouTube',
							'tiktok'    => 'TikTok',
							'twitter'   => 'Twitter',
							'x'         => 'X (Twitter)',
							'linkedin'  => 'LinkedIn',
						);
						$has_any_social = false;
						foreach ( $footer_socials as $net_key => $net_label ) {
							if ( get_theme_mod( 'itsi_footer_social_' . $net_key ) ) { $has_any_social = true; break; }
						}
						if ( $has_any_social ) : ?>
						<div class="f-socials">
							<?php foreach ( $footer_socials as $net_key => $net_label ) :
								$url = get_theme_mod( 'itsi_footer_social_' . $net_key );
								if ( ! $url ) { continue; }
								?>
								<a href="<?php echo esc_url( $url ); ?>" class="f-soc" title="<?php echo esc_attr( $net_label ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( $net_label ); ?>">
									<?php echo itsi_footer_icon_svg( $net_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — static SVG, safe. ?>
								</a>
							<?php endforeach; ?>
						</div>
						<?php endif; ?>
					</div>

					<?php
					/* === PRODI COLUMN === */
					?>
					<div class="f-col">
						<div class="f-col-ttl"><?php esc_html_e( 'Program Studi', 'itsi' ); ?></div>
						<?php
						if ( has_nav_menu( 'footer-prodi' ) ) {
							wp_nav_menu( array(
								'theme_location' => 'footer-prodi',
								'container'      => false,
								'menu_class'     => 'f-menu',
								'depth'          => 1,
								'fallback_cb'    => false,
							) );
						} else {
							/* Fallback: list semua CPT program_studi yang published. */
							$fb_prodi = new \WP_Query( array(
								'post_type'      => 'program_studi',
								'post_status'    => 'publish',
								'posts_per_page' => 12,
								'orderby'        => 'menu_order',
								'order'          => 'ASC',
								'no_found_rows'  => true,
							) );
							if ( $fb_prodi->have_posts() ) {
								echo '<ul class="f-menu">';
								while ( $fb_prodi->have_posts() ) {
									$fb_prodi->the_post();
									?>
									<li><a href="<?php the_permalink(); ?>"><?php echo itsi_footer_icon_svg( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php the_title(); ?></a></li>
									<?php
								}
								echo '</ul>';
								wp_reset_postdata();
							}
						}
						?>
					</div>

					<?php
					/* === INFO COLUMN === */
					?>
					<div class="f-col">
						<div class="f-col-ttl"><?php esc_html_e( 'Informasi', 'itsi' ); ?></div>
						<?php
						if ( has_nav_menu( 'footer-info' ) ) {
							wp_nav_menu( array(
								'theme_location' => 'footer-info',
								'container'      => false,
								'menu_class'     => 'f-menu',
								'depth'          => 1,
								'fallback_cb'    => false,
							) );
						} else {
							/* Fallback: hard-coded info list. */
							?>
							<ul class="f-menu">
								<li><a href="<?php echo esc_url( home_url( '/berita/?kat=pengumuman' ) ); ?>"><?php echo itsi_footer_icon_svg( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Pengumuman', 'itsi' ); ?></a></li>
								<li><a href="<?php echo esc_url( home_url( '/berita/' ) ); ?>"><?php echo itsi_footer_icon_svg( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Berita &amp; Kegiatan', 'itsi' ); ?></a></li>
								<li><a href="<?php echo esc_url( home_url( '/info-publik/' ) ); ?>"><?php echo itsi_footer_icon_svg( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Informasi Publik', 'itsi' ); ?></a></li>
								<li><a href="<?php echo esc_url( home_url( '/berita/?kat=agenda' ) ); ?>"><?php echo itsi_footer_icon_svg( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Agenda', 'itsi' ); ?></a></li>
							</ul>
							<?php
						}
						?>
					</div>

					<?php
					/* === KONTAK COLUMN === */
					$fb_address    = get_theme_mod( 'itsi_footer_address', 'Jl. Willem Iskandar, Medan' );
					$fb_phone      = get_theme_mod( 'itsi_footer_phone', '(061) 123-4567' );
					$fb_phone_link = get_theme_mod( 'itsi_footer_phone_link', '+6261****4567' );
					$fb_email      = get_theme_mod( 'itsi_footer_email', 'info@itsi.ac.id' );
					$fb_hours      = get_theme_mod( 'itsi_footer_hours', 'Senin–Jumat: 08.00–16.00 WIB' );
					?>
					<div class="f-col">
						<div class="f-col-ttl"><?php esc_html_e( 'Kontak', 'itsi' ); ?></div>
						<ul class="f-menu">
							<li>
								<span><?php echo itsi_footer_icon_svg( 'location' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $fb_address ); ?></span>
							</li>
							<?php if ( $fb_phone ) : ?>
							<li>
								<a href="<?php echo esc_url( 'tel:' . preg_replace( '/[^+\d]/', '', $fb_phone_link ) ); ?>">
									<?php echo itsi_footer_icon_svg( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $fb_phone ); ?>
								</a>
							</li>
							<?php endif; ?>
							<?php if ( $fb_email ) : ?>
							<li>
								<a href="<?php echo esc_url( 'mailto:' . $fb_email ); ?>">
									<?php echo itsi_footer_icon_svg( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $fb_email ); ?>
								</a>
							</li>
							<?php endif; ?>
							<li>
								<a href="<?php echo esc_url( home_url( '/' ) ); ?>">
									<?php echo itsi_footer_icon_svg( 'globe' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( wp_parse_url( home_url( '/' ), PHP_URL_HOST ) ); ?>
								</a>
							</li>
							<?php if ( $fb_hours ) : ?>
							<li>
								<span><?php echo itsi_footer_icon_svg( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $fb_hours ); ?></span>
							</li>
							<?php endif; ?>
						</ul>
					</div>

					<?php endif; // widget mode vs static mode ?>
				</div>
			</div>
		</div>
		<div class="container">
			<div class="footer-bottom">
				<?php
				$fb_copyright = get_theme_mod( 'itsi_footer_copyright', 'Institut Teknologi Sawit Indonesia. Semua hak dilindungi undang-undang.' );
				?>
				<p class="f-copy">© <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( $fb_copyright ); ?></p>
				<div class="f-links">
					<?php
					$fb_privacy = get_theme_mod( 'itsi_footer_privacy_url' );
					$fb_terms   = get_theme_mod( 'itsi_footer_terms_url' );
					$fb_sitemap = get_theme_mod( 'itsi_footer_sitemap_url' );
					?>
					<?php if ( $fb_privacy ) : ?><a href="<?php echo esc_url( $fb_privacy ); ?>"><?php esc_html_e( 'Kebijakan Privasi', 'itsi' ); ?></a><?php endif; ?>
					<?php if ( $fb_terms ) : ?><a href="<?php echo esc_url( $fb_terms ); ?>"><?php esc_html_e( 'Syarat Penggunaan', 'itsi' ); ?></a><?php endif; ?>
					<?php if ( $fb_sitemap ) : ?><a href="<?php echo esc_url( $fb_sitemap ); ?>"><?php esc_html_e( 'Sitemap', 'itsi' ); ?></a><?php endif; ?>
				</div>
			</div>
		</div>
	</footer>

	<button id="scrollTop" type="button" aria-label="<?php esc_attr_e( 'Kembali ke atas', 'itsi' ); ?>">
		<?php echo itsi_footer_icon_svg( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</button>
</div><!-- #page -->

<?php wp_footer(); ?>
</body>
</html>