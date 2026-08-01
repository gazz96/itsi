<?php
/**
 * Template Name: Program Studi Archive
 *
 * @package itsi
 */

get_header();

$terms = get_terms( array( 'taxonomy' => 'fakultas', 'hide_empty' => false ) );
?>

<main id="primary" class="site-main arc-archive arc-prodi">

	<section class="arc-hero arc-hero-prodi">
		<div class="arc-hero-bg">
			<div class="arc-hero-mesh"></div>
			<div class="arc-hero-dots"></div>
		</div>
		<div class="blob blob-1"></div>
		<div class="blob blob-2"></div>
		<div class="container">
			<div class="arc-hero-inner">
				<span class="eyebrow rv"><span class="ey-dot"></span> Program Studi</span>
				<h1 class="arc-h1 rv d1">Pilih <span class="gold-line">Program Studi</span> Impianmu</h1>
				<p class="arc-sub rv d2">Enam program studi terakreditasi yang dirancang untuk menjawab tantangan industri sawit modern dan masa depan hijau Indonesia.</p>

				<div class="arc-stats-row rv d3">
					<div class="arc-stat"><span class="arc-stat-n">14</span><span class="arc-stat-l">Program Studi</span></div>
					<div class="arc-stat"><span class="arc-stat-n">4500+</span><span class="arc-stat-l">Mahasiswa</span></div>
					<div class="arc-stat"><span class="arc-stat-n">120+</span><span class="arc-stat-l">Dosen</span></div>
					<div class="arc-stat"><span class="arc-stat-n">A+</span><span class="arc-stat-l">Akreditasi</span></div>
				</div>
			</div>
		</div>
	</section>

	<section class="section arc-section">
		<div class="container">
			<?php
			$prodi_q = new WP_Query( array(
				'post_type'      => 'program_studi',
				'posts_per_page' => 12,
			) );
			if ( $prodi_q->have_posts() ) : ?>
				<div class="prodi-grid prodi-grid-arc">
					<?php $pi = 0; while ( $prodi_q->have_posts() ) : $prodi_q->the_post();
						$pi++;
						$gel      = get_post_meta( get_the_ID(), 'gelar', true );
						$akr      = get_post_meta( get_the_ID(), 'akreditasi', true );
						$fac_objs = get_the_terms( get_the_ID(), 'fakultas' );
						$fac      = $fac_objs && ! is_wp_error( $fac_objs ) ? $fac_objs[0]->name : 'Fakultas';
						?>
						<a href="<?php the_permalink(); ?>" class="pc rv d<?php echo esc_attr( $pi % 4 ); ?>">
							<div class="pc-icon">🎓</div>
							<span class="pc-deg"><?php echo esc_html( $gel ? $gel : 'S1' ); ?> · <?php echo esc_html( $fac ); ?></span>
							<h3 class="pc-name"><?php the_title(); ?></h3>
							<p class="pc-desc"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 20 ) ); ?></p>
							<div class="pc-meta">
								<span class="pc-acc">Akreditasi <?php echo esc_html( $akr ? $akr : 'A' ); ?></span>
								<span class="pc-link">Pelajari →</span>
							</div>
						</a>
					<?php endwhile; wp_reset_postdata(); ?>
				</div>
			<?php else : ?>
				<!-- Default content: 6 prodi cards matching reference design -->
				<div class="prodi-grid">
					<a href="#" class="pc rv"><div class="pc-icon">🌾</div><span class="pc-deg">S1 · Agribisnis</span><h3 class="pc-name">Agribisnis</h3><p class="pc-desc">Manajemen rantai pasok, pemasaran, dan kewirausahaan agribisnis sawit berkelanjutan.</p><div class="pc-meta"><span class="pc-acc">Akreditasi A</span><span class="pc-link">Pelajari →</span></div></a>
					<a href="#" class="pc rv d1"><div class="pc-icon">🛡️</div><span class="pc-deg">S1 · Proteksi Tanaman</span><h3 class="pc-name">Proteksi Tanaman</h3><p class="pc-desc">Pengendalian hama, penyakit, dan teknik perlindungan tanaman perkebunan modern.</p><div class="pc-meta"><span class="pc-acc">Akreditasi A</span><span class="pc-link">Pelajari →</span></div></a>
					<a href="#" class="pc rv d2"><div class="pc-icon">💻</div><span class="pc-deg">S1 · Sistem &amp; TI</span><h3 class="pc-name">Sistem &amp; Teknologi Informasi</h3><p class="pc-desc">Pemanfaatan IT untuk automasi industri, IoT perkebunan, dan transformasi digital.</p><div class="pc-meta"><span class="pc-acc">Akreditasi Unggul</span><span class="pc-link">Pelajari →</span></div></a>
					<a href="#" class="pc rv d3"><div class="pc-icon">⚗️</div><span class="pc-deg">S1 · Teknik Kimia</span><h3 class="pc-name">Teknik Kimia</h3><p class="pc-desc">Pengolahan hasil sawit, biorefineri, dan teknologi proses kimia industri.</p><div class="pc-meta"><span class="pc-acc">Akreditasi A</span><span class="pc-link">Pelajari →</span></div></a>
					<a href="#" class="pc rv"><div class="pc-icon">🏭</div><span class="pc-deg">D4 · Vokasi</span><h3 class="pc-name">T. Pengolahan Hasil Perkebunan</h3><p class="pc-desc">Pengolahan CPO, PKO, dan produk turunan dengan standar industri internasional.</p><div class="pc-meta"><span class="pc-acc">Akreditasi Unggul</span><span class="pc-link">Pelajari →</span></div></a>
					<a href="#" class="pc rv d1"><div class="pc-icon">🌴</div><span class="pc-deg">D4 · Vokasi</span><h3 class="pc-name">Budidaya Perkebunan</h3><p class="pc-desc">Praktik budidaya kelapa sawit presisi dan berkelanjutan dari hulu ke hilir.</p><div class="pc-meta"><span class="pc-acc">Akreditasi A</span><span class="pc-link">Pelajari →</span></div></a>
				</div>
			<?php endif; ?>
		</div>
	</section>

</main>

<?php
get_footer();
