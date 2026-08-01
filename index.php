<?php

/**
 * Main index template – ITSI Theme.
 *
 * Routes to the Berita archive when displaying standard posts.
 * Falls back to a generic post list.
 *
 * @package itsi
 */

// if (is_home() || (is_archive() && 'post' === get_post_type()) || is_search()) {
// 	$load = locate_template('archive-berita.php');
// 	if ($load) {
// 		include $load;
// 		return;
// 	}
// }

get_header();

if (tr_show_page_builder("use_builder")) {
	tr_components_field('builder');
} else {
?>
	<main id="primary" class="site-main">
		<section class="page-banner">
			<div class="container">
				<span class="eyebrow"><span class="ey-dot"></span> Kabar Kampus</span>
				<h1 class="page-banner-h1"><?php single_post_title(); ?></h1>
			</div>
		</section>
		<section class="section">
			<div class="container">
				<?php if (have_posts()) : ?>
					<div class="arc-grid">
						<?php $i = 0;
						while (have_posts()) : the_post();
							$i++; ?>
							<article class="arc-card rv d<?php echo esc_attr($i % 4); ?>">
								<a href="<?php the_permalink(); ?>" class="arc-card-img">
									<?php if (has_post_thumbnail()) : the_post_thumbnail('medium_large');
									else : ?>
										<div class="arc-card-fallback">📰</div>
									<?php endif; ?>
									<span class="arc-card-cat">BERITA</span>
								</a>
								<div class="arc-card-body">
									<div class="arc-card-meta">
										<span>📅 <?php echo esc_html(get_the_date('d M Y')); ?></span>
										<span>👤 <?php the_author(); ?></span>
									</div>
									<h3 class="arc-card-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
									<p class="arc-card-excerpt"><?php echo esc_html(wp_trim_words(get_the_excerpt(), 18)); ?></p>
									<a href="<?php the_permalink(); ?>" class="arc-card-more">Baca →</a>
								</div>
							</article>
						<?php endwhile; ?>
					</div>
					<div class="arc-pagination">
						<?php the_posts_pagination(array(
							'mid_size'  => 2,
							'prev_text' => '← Sebelumnya',
							'next_text' => 'Selanjutnya →',
						)); ?>
					</div>
				<?php else : ?>
					<div class="arc-empty">
						<div class="arc-empty-ic">🔍</div>
						<h3>Belum ada konten</h3>
					</div>
				<?php endif; ?>
			</div>
		</section>
	</main>
<?php } ?>

<?php
get_footer();
