<?php
/**
 * Single post template – ITSI Theme.
 *
 * Renders single post (post_type=post) with article detail layout:
 * breadcrumb, hero header with category/title/author meta, featured image,
 * lead paragraph, article body with TOC anchors, tags, share bar,
 * related posts, and sticky sidebar (TOC + popular posts + ads).
 *
 * Falls back to standard article body if post has no featured image /
 * custom excerpt / tags — never breaks the page layout.
 *
 * @package itsi
 */

get_header();

while ( have_posts() ) :
    the_post();

    $post_id   = get_the_ID();
    $permalink = get_the_permalink();
    $title     = get_the_title();

    /* ── Category + author meta ─────────────────────────────── */
    $cats     = get_the_category();
    $cat_name = ( ! empty( $cats ) && ! is_wp_error( $cats ) ) ? $cats[0]->name : 'Berita';
    $cat_bg   = 'rgba(30,114,212,.1)';
    $cat_fg   = '#1459B3';

    $author_id   = (int) get_post_field( 'post_author', $post_id );
    $author_name = get_the_author_meta( 'display_name', $author_id );
    $author_role = get_the_author_meta( 'description', $author_id );
    if ( $author_role === '' ) {
        $author_role = 'Civitas Akademika ITSI';
    }
    $author_initials = strtoupper( mb_substr( preg_replace( '/\s+/', '', $author_name ), 0, 2 ) );

    $publish_date = get_the_date( 'c' );               // ISO for schema
    $publish_long = get_the_date( 'd F Y' );

    /* ── Reading time + view count (best-effort; falls back gracefully) ── */
    $word_count   = str_word_count( wp_strip_all_tags( get_the_content() ) );
    $reading_time = max( 1, (int) ceil( $word_count / 200 ) );
    $views_raw    = (int) get_post_meta( $post_id, 'post_views_count', true );
    $views_disp   = $views_raw > 0 ? number_format_i18n( $views_raw ) . ' views' : '— views';

    /* ── Featured image / fallback gradient + emoji ────────── */
    $gradients = array(
        'linear-gradient(135deg,#04162E,#1E72D4)',
        'linear-gradient(135deg,#08274F,#0C3D7A)',
        'linear-gradient(135deg,#010D1E,#08274F)',
        'linear-gradient(135deg,#0a2540,#1459B3)',
        'linear-gradient(135deg,#04162E,#0C3D7A)',
        'linear-gradient(135deg,#08274F,#1459B3)',
    );
    $emojis_by_cat = array(
        'kegiatan'         => '🏭',
        'kegiatan akademik'=> '🎓',
        'pengabdian'       => '🤝',
        'kerjasama'        => '🤝',
        'penelitian'       => '🔬',
        'akademik'         => '🎓',
        'agribisnis'       => '🌾',
        'teknologi'        => '⚙️',
        'lingkungan'       => '♻️',
        'berita'           => '📰',
    );
    $cat_slug   = ( ! empty( $cats ) ) ? $cats[0]->slug : '';
    $emoji      = isset( $emojis_by_cat[ $cat_slug ] ) ? $emojis_by_cat[ $cat_slug ] : '📝';
    $grad       = $gradients[ $post_id % count( $gradients ) ];

    /* ── TOC: parse <h2> from content ──────────────────────── */
    $content_raw = get_the_content();
    $toc_items   = array();
    if ( preg_match_all( '/<h2[^>]*>(.*?)<\/h2>/is', $content_raw, $m ) ) {
        foreach ( $m[1] as $i => $h2 ) {
            $toc_items[] = wp_strip_all_tags( $h2 );
        }
    }

    /* ── Lead paragraph: first <p> with > 80 chars, else custom excerpt ── */
    $lead_text = '';
    if ( preg_match( '/<p[^>]*>(.*?)<\/p>/is', $content_raw, $m ) ) {
        $first_p = wp_strip_all_tags( $m[1] );
        if ( mb_strlen( $first_p ) > 80 ) {
            $lead_text = $first_p;
        }
    }
    if ( $lead_text === '' ) {
        $lead_text = wp_strip_all_tags( get_the_excerpt() );
    }

    /* ── Tags ──────────────────────────────────────────────── */
    $post_tags = get_the_tags();

    /* ── Related posts: same category, exclude current ─────── */
    $related = array();
    if ( ! empty( $cats ) ) {
        $rel_q = new \WP_Query( array(
            'post_type'           => 'post',
            'posts_per_page'      => 3,
            'post__not_in'        => array( $post_id ),
            'ignore_sticky'       => true,
            'no_found_rows'       => true,
            'orderby'             => 'date',
            'order'               => 'DESC',
            'tax_query'           => array(
                array(
                    'taxonomy' => 'category',
                    'field'    => 'term_id',
                    'terms'    => $cats[0]->term_id,
                ),
            ),
        ) );
        if ( $rel_q->have_posts() ) {
            while ( $rel_q->have_posts() ) { $rel_q->the_post();
                $rcats = get_the_category();
                $related[] = array(
                    'permalink' => get_the_permalink(),
                    'title'     => get_the_title(),
                    'cat'       => ( ! empty( $rcats ) ) ? $rcats[0]->name : 'Berita',
                    'emoji'     => '📰',
                );
            }
            wp_reset_postdata();
        }
    }

    /* ── Popular posts: now rendered via the_widget('ITSI_Popular_Widget') in the sidebar ─ */
    /* (data-driven from post_views_count with recent-posts fallback, see inc/widgets.php) */
    ?>

    <main>
      <div class="at-main-wrap">
        <div class="at-container">

          <!-- BREADCRUMB -->
          <nav class="at-bc rv" aria-label="Breadcrumb">
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Beranda</a><span>›</span>
            <a href="<?php echo esc_url( get_permalink( get_option( 'page_for_posts' ) ) ?: home_url( '/berita' ) ); ?>">Berita &amp; Kegiatan</a><span>›</span>
            <span style="color:var(--td)"><?php echo esc_html( wp_trim_words( $title, 6, '…' ) ); ?></span>
          </nav>

          <!-- Before Article Widget Area (was: Top Ad Slot) -->
          <?php if ( is_active_sidebar( 'itsi_single_post_widget_before_post' ) ) : ?>
            <aside class="at-widget-slot-wrap rv" aria-label="Area widget sebelum artikel">
              <?php dynamic_sidebar( 'itsi_single_post_widget_before_post' ); ?>
            </aside>
          <?php endif; ?>

          <div class="at-detail-layout">
            <!-- ── ARTICLE CONTENT ── -->
            <article itemscope itemtype="https://schema.org/Article">
              <meta itemprop="datePublished" content="<?php echo esc_attr( $publish_date ); ?>">
              <meta itemprop="author" content="<?php echo esc_attr( $author_name ); ?>">
              <meta itemprop="publisher" content="Institut Teknologi Sawit Indonesia">

              <header class="at-art-hdr rv">
                <span class="at-art-hdr-cat" style="background:<?php echo esc_attr( $cat_bg ); ?>;color:<?php echo esc_attr( $cat_fg ); ?>"><?php echo esc_html( $cat_name ); ?></span>
                <h1 class="at-art-hdr-title" itemprop="headline"><?php the_title(); ?></h1>
                <div class="at-art-hdr-meta">
                  <div class="at-meta-author">
                    <div class="at-author-av"><?php echo esc_html( $author_initials ); ?></div>
                    <div>
                      <div class="at-author-nm" itemprop="author"><?php echo esc_html( $author_name ); ?></div>
                      <div class="at-author-role"><?php echo esc_html( wp_trim_words( $author_role, 8, '…' ) ); ?></div>
                    </div>
                  </div>
                  <div class="at-meta-info">
                    <span class="at-meta-tag">📅 <?php echo esc_html( $publish_long ); ?></span>
                    <span class="at-meta-tag">⏱ <?php echo (int) $reading_time; ?> menit membaca</span>
                    <span class="at-meta-tag">👁 <?php echo esc_html( $views_disp ); ?></span>
                  </div>
                </div>
              </header>

              <!-- Featured image -->
              <figure class="at-art-featured-img rv">
                <?php if ( has_post_thumbnail() ) : ?>
                  <?php the_post_thumbnail( 'large', array( 'style' => 'width:100%;height:auto;border-radius:20px;display:block' ) ); ?>
                <?php else : ?>
                  <div style="font-size:5rem;background:<?php echo esc_attr( $grad ); ?>;min-height:320px;display:flex;align-items:center;justify-content:center;border-radius:20px">
                    <?php echo esc_html( $emoji ); ?>
                  </div>
                <?php endif; ?>
                <figcaption class="at-cap"><?php echo esc_html( wp_strip_all_tags( $lead_text ) ); ?></figcaption>
              </figure>

              <!-- Ringkasan / Lead -->
              <?php if ( $lead_text !== '' ) : ?>
                <p class="at-lead rv"><?php echo esc_html( $lead_text ); ?></p>
              <?php endif; ?>

              <!-- Article body -->
              <div class="at-art-body rv" itemprop="articleBody">
                <?php
                /* the_content() preserves WP filters (shortcodes, wpautop, embeds) */
                the_content();

                wp_link_pages( array(
                    'before'      => '<div class="at-pagination" style="margin:1.5rem 0">' . esc_html__( 'Halaman:', 'itsi' ),
                    'after'       => '</div>',
                    'link_before' => '',
                    'link_after'  => '',
                ) );
                ?>
              </div>

              <!-- Tags -->
              <?php if ( ! empty( $post_tags ) ) : ?>
                <div class="at-art-tags rv">
                  <span class="at-tag-lbl">Tag:</span>
                  <?php foreach ( $post_tags as $t ) : ?>
                    <a href="<?php echo esc_url( get_tag_link( $t ) ); ?>" class="at-tag-item"><?php echo esc_html( $t->name ); ?></a>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <!-- Share bar -->
              <div class="at-share-bar rv">
                <span class="at-share-lbl">Bagikan:</span>
                <button type="button" class="at-share-btn" data-share="wa">📱 WhatsApp</button>
                <button type="button" class="at-share-btn" data-share="fb">📘 Facebook</button>
                <button type="button" class="at-share-btn" data-share="tw">🐦 Twitter / X</button>
                <button type="button" class="at-share-copy" data-share="copy">🔗 Salin Tautan</button>
              </div>

              <!-- Post-article Ad (Rectangle) — converted to widget area -->
              <?php if ( is_active_sidebar( 'itsi_single_post_widget_after_post' ) ) : ?>
                <aside class="at-widget-slot-wrap rv" aria-label="Area widget setelah artikel">
                  <?php dynamic_sidebar( 'itsi_single_post_widget_after_post' ); ?>
                </aside>
              <?php endif; ?>

              <!-- Related -->
              <?php if ( ! empty( $related ) ) : ?>
                <section aria-labelledby="at-related-ttl">
                  <h2 id="at-related-ttl" style="font-family:'Cormorant Garamond',serif;font-size:1.5rem;font-weight:700;color:var(--td);margin-bottom:1.2rem">
                    Berita &amp; Kegiatan Terkait
                  </h2>
                  <div class="at-related-grid">
                    <?php foreach ( $related as $i => $r ) : ?>
                      <a href="<?php echo esc_url( $r['permalink'] ); ?>" class="at-rel-card rv d<?php echo esc_attr( ( $i % 3 ) + 1 ); ?>">
                        <div class="at-rel-thumb" style="background:<?php echo esc_attr( $gradients[ $i % count( $gradients ) ] ); ?>"><?php echo esc_html( $r['emoji'] ); ?></div>
                        <div class="at-rel-body">
                          <div class="at-rel-cat"><?php echo esc_html( $r['cat'] ); ?></div>
                          <div class="at-rel-title"><?php echo esc_html( $r['title'] ); ?></div>
                        </div>
                      </a>
                    <?php endforeach; ?>
                  </div>
                </section>
              <?php endif; ?>

            </article>

            <!-- ── STICKY SIDEBAR ── -->
            <aside class="at-sidebar-detail" aria-label="Sidebar artikel">

              <!-- Table of Contents — render via widget area. Drag widget "ITSI — Daftar Isi (Auto)" here in admin. -->
              <?php if ( is_active_sidebar( 'itsi_single_post_widget_toc' ) ) : ?>
                <?php dynamic_sidebar( 'itsi_single_post_widget_toc' ); ?>
              <?php endif; ?>

              <!-- Popular Posts — render via widget area. Drag widget "ITSI — Paling Banyak Dibaca (Auto)" here in admin. -->
              <?php if ( is_active_sidebar( 'itsi_single_post_widget_popular' ) ) : ?>
                <?php dynamic_sidebar( 'itsi_single_post_widget_popular' ); ?>
              <?php endif; ?>

              <!-- Sidebar Ad (was: tall AdSense) — for Custom HTML / AdSense / CTA widgets -->
              <?php if ( is_active_sidebar( 'itsi_single_post_widget_sidebar' ) ) : ?>
                <div class="at-widget-slot-wrap">
                  <?php dynamic_sidebar( 'itsi_single_post_widget_sidebar' ); ?>
                </div>
              <?php endif; ?>

            </aside>

          </div>
        </div>
      </div>

      <!-- Inline JS: share handlers + TOC click + active-section observer -->
      <script>
      (function(){
        var url  = <?php echo wp_json_encode( $permalink ); ?>;
        var text = <?php echo wp_json_encode( wp_strip_all_tags( $title ) ); ?>;
        document.querySelectorAll('.at-share-bar [data-share]').forEach(function(btn){
          btn.addEventListener('click', function(e){
            e.preventDefault();
            var ch = btn.getAttribute('data-share');
            var u  = encodeURIComponent(url);
            var t  = encodeURIComponent(text + ' — ' + url);
            if (ch === 'wa') { window.open('https://wa.me/?text=' + t, '_blank'); return; }
            if (ch === 'fb') { window.open('https://www.facebook.com/sharer/sharer.php?u=' + u, '_blank'); return; }
            if (ch === 'tw') { window.open('https://twitter.com/intent/tweet?text=' + encodeURIComponent(text) + '&url=' + u, '_blank'); return; }
            if (ch === 'copy') {
              if (navigator.clipboard) { navigator.clipboard.writeText(url).then(function(){
                btn.textContent = '✅ Tersalin';
                setTimeout(function(){ btn.textContent = '🔗 Salin Tautan'; }, 1800);
              }); }
              else { var ta = document.createElement('textarea'); ta.value = url; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta); }
            }
          });
        });

        // TOC: click scroll to corresponding <h2> in article body
        var tocItems = document.querySelectorAll('#at-tocList .at-toc-item');
        var h2s = document.querySelectorAll('.at-art-body h2');
        tocItems.forEach(function(it){
          it.addEventListener('click', function(){
            var i = parseInt(it.getAttribute('data-toc-index'), 10);
            if (h2s[i]) { h2s[i].scrollIntoView({ behavior: 'smooth', block: 'start' }); }
          });
        });

        // Highlight active TOC item on scroll
        if ('IntersectionObserver' in window && h2s.length) {
          var io = new IntersectionObserver(function(entries){
            entries.forEach(function(en){
              if (en.isIntersecting) {
                var i = Array.prototype.indexOf.call(h2s, en.target);
                tocItems.forEach(function(t){ t.classList.remove('active'); });
                if (tocItems[i]) { tocItems[i].classList.add('active'); }
              }
            });
          }, { rootMargin: '-90px 0px -70% 0px', threshold: 0 });
          h2s.forEach(function(h){ io.observe(h); });
        }
      })();
      </script>
    </main>

<?php
endwhile;

get_footer();