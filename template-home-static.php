<?php
/**
 * Template Name: Homepage Statis (ITSI)
 *
 * Halaman ini render konten post (post_content) secara langsung,
 * melewati TypeRocket PageBuilder. Pakai template ini untuk homepage
 * yang berisi HTML siap-pakai seperti yang Anda ketik di editor.
 */

get_header();

if (have_posts()) {
    while (have_posts()) {
        the_post();
        // Render dengan the_content() supaya shortcode dan wpautop berlaku.
        echo '<article id="post-' . get_the_ID() . '" class="itsi-static-homepage">';
        echo '<div class="entry-content">';
        the_content();
        echo '</div>';
        echo '</article>';
    }
}

get_footer();
