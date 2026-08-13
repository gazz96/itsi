<?php
/**
 * Header – ITSI Theme.
 *
 * Outputs accent bar, top bar, navbar, mobile nav, and search overlay.
 *
 * @package itsi
 */
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="description" content="<?php echo esc_attr( get_bloginfo( 'description' ) ); ?>">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php
	// Favicon — Google SERP + browser tab compliance.
	// Hardcoded paths to /favicon/ so HTML is stable regardless of WP site_icon
	// option/theme_mod sync state. Files served by Apache ProxyPass carve-out
	// (see /etc/apache2/sites-enabled/itsi.ac.id-le-ssl.conf). Cloudflare caches
	// the PNGs with max-age=14400 — re-uploading without purging CF will keep
	// old bytes visible for up to 4h.
	?>
	<link rel="apple-touch-icon" sizes="180x180" href="<?php echo esc_url( home_url( '/favicon/apple-touch-icon.png' ) ); ?>">
	<link rel="icon" type="image/png" sizes="32x32" href="<?php echo esc_url( home_url( '/favicon/favicon-32x32.png' ) ); ?>">
	<link rel="icon" type="image/png" sizes="16x16" href="<?php echo esc_url( home_url( '/favicon/favicon-16x16.png' ) ); ?>">
	<link rel="manifest" href="<?php echo esc_url( home_url( '/favicon/site.webmanifest' ) ); ?>">
	<meta name="google-site-verification" content="qwJC6B2BHObdp-XZMupIERX0rmgsBgkIA_1DhZeTGns" />
	<script type="text/javascript">
		(function(c,l,a,r,i,t,y){
			c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
			t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
			y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
		})(window, document, "clarity", "script", "xgef7q4mf8");
	</script>
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="skip-link screen-reader-text" href="#primary"><?php esc_html_e( 'Lewati ke konten', 'itsi' ); ?></a>

<!-- ▓▓ ACCENT BAR ▓▓ -->
<div class="accent-bar"></div>

<!-- ▓▓ TOP BAR ▓▓ -->
<div id="topbar">
	<div class="tb-wrap">
		<div class="tb-left">
			<?php
			$itsi_tb_left_html = (string) get_theme_mod( 'itsi_tb_left_html', '' );
			if ( '' !== $itsi_tb_left_html ) {
				// Allow shortcodes (e.g. gtranslate), then wpautop to format line breaks.
				echo do_shortcode( wpautop( $itsi_tb_left_html ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — content saved via wp_kses_post in admin.
			}
			?>
		</div>
		<div class="tb-right">
			<?php
			$itsi_tb_right_html = (string) get_theme_mod( 'itsi_tb_right_html', '' );
			if ( '' !== $itsi_tb_right_html ) {
				echo do_shortcode( wpautop( $itsi_tb_right_html ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — content saved via wp_kses_post in admin.
			}
			?>
		</div>
	</div>
</div>

<!-- ▓▓ NAVBAR ▓▓ -->
<nav id="navbar" aria-label="<?php esc_attr_e( 'Primary', 'itsi' ); ?>">
	<div class="nav-wrap">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="nav-brand" rel="home">
			<img src="<?php echo esc_url( itsi_get_logo_url() ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
			<div class="nb-text">
				<span class="nb-short"><?php echo esc_html( get_theme_mod( 'itsi_brand_short', 'ITSI' ) ); ?></span>
				<span class="nb-full"><?php echo esc_html( get_theme_mod( 'itsi_brand_full', 'Institut Teknologi Sawit Indonesia' ) ); ?></span>
			</div>
		</a>

		<?php if ( has_nav_menu( 'menu-1' ) ) : ?>
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'menu-1',
					'container'      => false,
					'menu_class'     => 'nav-links',
					'depth'          => 3, // 0=link only, 1=top+sub, 2=+sub-sub (FAKULTAS>SAINTEK>5 prodi), 3=+sub-sub-sub
					'fallback_cb'    => false,
					'link_class'     => 'nl-a',
					'walker'         => itsi_get_menu_walker( 'menu-1' ),
				)
			);
			?>
		<?php else : ?>
			<ul class="nav-links">
				<li class="nli">
					<a href="#sambutan" class="nl-a">Tentang ITSI
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg>
					</a>
					<div class="dd">
						<div class="dd-group">
							<div class="dd-label">Profil Institusi</div>
							<a href="#sambutan" class="dd-a">Sambutan Rektor</a>
							<a href="#" class="dd-a">Sejarah ITSI</a>
							<a href="#" class="dd-a">Visi, Misi &amp; Tujuan</a>
							<a href="#" class="dd-a">Struktur Organisasi</a>
						</div>
						<div class="dd-group">
							<div class="dd-label">Fasilitas</div>
							<a href="#" class="dd-a">Laboratorium</a>
							<a href="#" class="dd-a">Perpustakaan</a>
							<a href="#" class="dd-a">Sarana &amp; Prasarana</a>
						</div>
					</div>
				</li>
				<li class="nli">
					<a href="#prodi" class="nl-a">Akademik
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg>
					</a>
					<div class="dd">
						<div class="dd-group">
							<div class="dd-label">Fakultas Sains &amp; Teknologi</div>
							<a href="#" class="dd-a">Agribisnis</a>
							<a href="#" class="dd-a">Proteksi Tanaman</a>
							<a href="#" class="dd-a">Sistem &amp; Teknologi Informasi</a>
							<a href="#" class="dd-a">Teknik Kimia</a>
						</div>
						<div class="dd-group">
							<div class="dd-label">Fakultas Vokasi</div>
							<a href="#" class="dd-a">T. Pengolahan Hasil Perkebunan</a>
							<a href="#" class="dd-a">Budidaya Perkebunan</a>
						</div>
					</div>
				</li>
				<li class="nli">
					<a href="#" class="nl-a">Mahasiswa
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg>
					</a>
					<div class="dd">
						<div class="dd-group">
							<a href="#" class="dd-a">Penerimaan Mahasiswa Baru</a>
							<a href="#" class="dd-a">Kurikulum &amp; Akademik</a>
							<a href="#" class="dd-a">Beasiswa</a>
							<a href="#" class="dd-a">Alumni</a>
							<a href="#" class="dd-a">Kemahasiswaan</a>
						</div>
					</div>
				</li>
				<li class="nli">
					<a href="#" class="nl-a">Riset
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg>
					</a>
					<div class="dd">
						<div class="dd-group">
							<a href="#" class="dd-a">Pusat Penelitian</a>
							<a href="#" class="dd-a">Publikasi Ilmiah</a>
							<a href="#" class="dd-a">Inovasi &amp; Paten</a>
							<a href="#" class="dd-a">Kerjasama Riset</a>
						</div>
					</div>
				</li>
				<li class="nli"><a href="#berita" class="nl-a">Berita</a></li>
				<li class="nli">
					<a href="#artikel" class="nl-a">Informasi
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg>
					</a>
					<div class="dd">
						<div class="dd-group">
							<a href="#pengumuman" class="dd-a">Pengumuman</a>
							<a href="#artikel"    class="dd-a">Artikel</a>
							<a href="#berita"     class="dd-a">Berita &amp; Kegiatan</a>
							<a href="#infopub"    class="dd-a">Informasi Publik</a>
						</div>
					</div>
				</li>
			</ul>
		<?php endif; ?>

		<div class="nav-actions">
			<button class="nav-btn" type="button" onclick="toggleSearch(true)" aria-label="<?php esc_attr_e( 'Cari', 'itsi' ); ?>">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
			</button>
		</div>

		<button class="hamburger" id="hamburger" type="button" onclick="toggleMob()" aria-label="<?php esc_attr_e( 'Menu', 'itsi' ); ?>">
			<span></span><span></span><span></span>
		</button>
	</div>
</nav>

<!-- ▓▓ MOBILE NAV ▓▓ -->
<div class="mob-nav" id="mobNav">
	<?php if ( has_nav_menu( 'mobile-menu' ) ) : ?>
		<?php
		wp_nav_menu(
			array(
				'theme_location' => 'mobile-menu',
				'container'      => false,
				'menu_class'     => 'mob-links',
				'depth'          => 3, // include sub-sub items (FAKULTAS>SAINTEK>prodi)
				'fallback_cb'    => false,
				'walker'         => itsi_get_menu_walker( 'mobile-menu' ),
			)
		);
		?>
	<?php else : ?>
		<a href="#sambutan" class="mob-a" onclick="closeMob()">Tentang ITSI</a>
		<div class="mob-sec-ttl">Fak. Sains &amp; Teknologi</div>
		<a href="#" class="mob-a" onclick="closeMob()">Agribisnis</a>
		<a href="#" class="mob-a" onclick="closeMob()">Proteksi Tanaman</a>
		<a href="#" class="mob-a" onclick="closeMob()">Sistem &amp; Teknologi Informasi</a>
		<a href="#" class="mob-a" onclick="closeMob()">Teknik Kimia</a>
		<div class="mob-sec-ttl">Fakultas Vokasi</div>
		<a href="#" class="mob-a" onclick="closeMob()">T. Pengolahan Hasil Perkebunan</a>
		<a href="#" class="mob-a" onclick="closeMob()">Budidaya Perkebunan</a>
		<div class="mob-sec-ttl">Informasi</div>
		<a href="#pengumuman" class="mob-a" onclick="closeMob()">Pengumuman</a>
		<a href="#artikel"    class="mob-a" onclick="closeMob()">Artikel</a>
		<a href="#berita"     class="mob-a" onclick="closeMob()">Berita &amp; Kegiatan</a>
		<a href="#infopub"    class="mob-a" onclick="closeMob()">Informasi Publik</a>
	<?php endif; ?>
</div>

<!-- ▓▓ SEARCH OVERLAY ▓▓ -->
<div class="search-ovl" id="searchOvl" onclick="toggleSearch(false)">
	<div class="search-box" onclick="event.stopPropagation()">
		<p class="search-hint-ttl">Pencarian</p>
		<form class="search-field" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<div class="search-field-icon">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
			</div>
			<input type="search" id="searchInp" name="s" class="search-inp" placeholder="Cari berita, prodi, pengumuman...">
			<button type="button" class="search-close-btn" onclick="toggleSearch(false)">✕</button>
		</form>
		<p class="search-note">Tekan Enter untuk mencari · Esc untuk menutup</p>
	</div>
</div>

<div id="page" class="site">
	<header id="masthead" class="site-header" style="display:none"></header>

	<div id="content" class="site-content">
