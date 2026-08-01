<?php
/**
 * Template part for displaying search results (legacy compatibility).
 *
 * Primary search layout is rendered directly from `search.php`. This file
 * remains as a fallback so `get_template_part('template-parts/content','search')`
 * never blows up when called from a child theme or third-party code.
 *
 * @package itsi
 */
?>

<article id="post-<?php the_ID(); ?>" <?php post_class( 'at-result-card' ); ?>>
  <a href="<?php the_permalink(); ?>" style="text-decoration:none;color:inherit;display:flex;flex-direction:column">
    <div class="at-result-thumb" style="background:linear-gradient(135deg,#04162E,#1E72D4)">
      <?php if ( has_post_thumbnail() ) : the_post_thumbnail( 'medium' ); else : ?>
        <span style="font-size:2.6rem">📰</span>
      <?php endif; ?>
    </div>
    <div class="at-result-body">
      <div class="at-result-date">📅 <?php echo esc_html( get_the_date( 'd M Y' ) ); ?></div>
      <h3 class="at-result-title"><?php the_title(); ?></h3>
      <p class="at-result-excerpt"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( get_the_excerpt() ), 22, '…' ) ); ?></p>
    </div>
  </a>
</article>