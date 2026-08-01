<?php
/**
 * Single page template – ITSI Theme.
 *
 * @package itsi
 */

get_header();

if (tr_show_page_builder("use_builder")) {
	tr_components_field('builder');
} else {
?>

<main id="primary" class="site-main">
	<section class="page-banner">
		<div class="container">
			<span class="eyebrow"><span class="ey-dot"></span> Halaman</span>
			<h1 class="page-banner-h1"><?php the_title(); ?></h1>
		</div>
	</section>

	<section class="section">
		<div class="container">
			<article class="itsi-page" id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
				<?php if ( has_post_thumbnail() ) : ?>
					<div class="itsi-page-feat"><?php the_post_thumbnail( 'full' ); ?></div>
				<?php endif; ?>

				<div class="entry-content itsi-entry">
					<?php
					the_content();

					wp_link_pages(
						array(
							'before' => '<div class="page-links">' . esc_html__( 'Halaman:', 'itsi' ),
							'after'  => '</div>',
						)
					);
					?>
				</div>

				<footer class="entry-footer">
					<?php edit_post_link( __( '✎ Edit halaman ini', 'itsi' ), '<span class="edit-link">', '</span>' ); ?>
				</footer>
			</article>
		</div>
	</section>
</main>
<?php } ?>
<?php

get_footer();
