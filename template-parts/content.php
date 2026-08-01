<?php
/**
 * Template part for displaying posts in index/archive views.
 *
 * This file is no longer the primary layout — `index.php` renders the
 * `itsi-posts` grid directly. Kept for child-theme compatibility and as a
 * fallback `get_template_part( 'template-parts/content', get_post_type() )`.
 *
 * @package itsi
 */

$type  = get_post_type();
$label = ( 'info_publik' === $type ? 'DOKUMEN' : 'BERITA' );
?>

<article class="itsi-post" id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<a href="<?php the_permalink(); ?>" class="itsi-post-img">
		<?php if ( has_post_thumbnail() ) : ?>
			<?php the_post_thumbnail( 'medium_large' ); ?>
		<?php else : ?>
			<div class="itsi-post-fallback">📰</div>
		<?php endif; ?>
		<span class="itsi-post-cat"><?php echo esc_html( $label ); ?></span>
	</a>

	<div class="itsi-post-body">
		<div class="itsi-post-meta"><?php echo esc_html( get_the_date( 'd M Y' ) ); ?> · <?php the_author(); ?></div>
		<h2 class="itsi-post-title">
			<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
		</h2>
		<p class="itsi-post-excerpt"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( get_the_excerpt() ), 22, '…' ) ); ?></p>
		<a href="<?php the_permalink(); ?>" class="itsi-post-more">Baca selengkapnya →</a>
	</div>
</article>
