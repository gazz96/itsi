<?php
/**
 * Default archive template – ITSI Theme.
 *
 * Routes to the right archive-{post-type}.php when available.
 * Falls back to a generic post grid for unknown types.
 *
 * @package itsi
 */

$pt = get_post_type();

if ( 'post' === $pt ) {
	$load = locate_template( 'archive-berita.php' );
	if ( $load ) { include $load; return; }
} elseif ( 'info_publik' === $pt ) {
	$load = locate_template( 'archive-info_publik.php' );
	if ( $load ) { include $load; return; }
} elseif ( 'program_studi' === $pt ) {
	$load = locate_template( 'archive-program_studi.php' );
	if ( $load ) { include $load; return; }
}

get_header();

$title_text = get_the_archive_title();
?>

<main id="primary" class="site-main arc-archive">
	<section class="page-banner">
		<div class="container">
			<span class="eyebrow"><span class="ey-dot"></span> Arsip</span>
			<h1 class="page-banner-h1"><?php echo wp_kses_post( $title_text ); ?></h1>
			<?php $desc = get_the_archive_description(); if ( $desc ) : ?>
				<div class="page-banner-sub"><?php echo wp_kses_post( $desc ); ?></div>
			<?php endif; ?>
		</div>
	</section>
	<section class="section">
		<div class="container">
			<?php if ( have_posts() ) : ?>
				<div class="arc-grid">
					<?php $i = 0; while ( have_posts() ) : the_post(); $i++;
						$type = get_post_type();
						$lbl  = ( 'info_publik' === $type ? 'DOKUMEN' : 'BERITA' ); ?>
						<article class="arc-card rv d<?php echo esc_attr( $i % 4 ); ?>">
							<a href="<?php the_permalink(); ?>" class="arc-card-img">
								<?php if ( has_post_thumbnail() ) : the_post_thumbnail( 'medium_large' ); else : ?>
									<div class="arc-card-fallback">📰</div>
								<?php endif; ?>
								<span class="arc-card-cat"><?php echo esc_html( $lbl ); ?></span>
							</a>
							<div class="arc-card-body">
								<div class="arc-card-meta">
									<span>📅 <?php echo esc_html( get_the_date( 'd M Y' ) ); ?></span>
									<span>👤 <?php the_author(); ?></span>
								</div>
								<h3 class="arc-card-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
								<p class="arc-card-excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 18 ) ); ?></p>
								<a href="<?php the_permalink(); ?>" class="arc-card-more">Baca →</a>
							</div>
						</article>
					<?php endwhile; ?>
				</div>
				<div class="arc-pagination">
					<?php
					the_posts_pagination( array(
						'mid_size'  => 2,
						'prev_text' => '← Sebelumnya',
						'next_text' => 'Selanjutnya →',
					) );
					?>
				</div>
			<?php else : ?>
				<div class="arc-empty">
					<div class="arc-empty-ic">🔍</div>
					<h3>Tidak ada konten</h3>
				</div>
			<?php endif; ?>
		</div>
	</section>
</main>

<?php
get_footer();
