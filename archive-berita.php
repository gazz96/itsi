<?php
/**
 * Template Name: Berita Archive
 * Template Post Type: post
 *
 * Hybrid archive for standard "post" entries at /berita.
 * Layout:
 *   1. Hero with breadcrumb, title, search bar
 *   2. Featured first post — full detail-style block (header + lead + meta)
 *   3. Grid 3-col of remaining posts (paginated)
 *   4. Sticky sidebar: category filter, popular posts, ad slots
 *
 * Wired to the /berita route via itsi_force_berita_template() in functions.php.
 *
 * @package itsi
 */

get_header();

$paged    = max( 1, (int) get_query_var( 'paged' ) );
$cat_term = ( is_category() ) ? get_queried_object() : null;
$search   = get_search_query();

/* ── Filter by category from ?kat=slug (client-side chip filter) ── */
$filter_cat = isset( $_GET['kat'] ) ? sanitize_title( wp_unslash( $_GET['kat'] ) ) : '';
if ( $filter_cat !== '' ) {
    $cat_term = get_term_by( 'slug', $filter_cat, 'category' );
}

/* ── Main query: latest posts, exclude sticky from featured logic ── */
$ppp        = 12;                       /* posts per page (1 featured + 11 grid) */
$args       = array(
    'post_type'           => 'post',
    'posts_per_page'      => $ppp,
    'paged'               => $paged,
    'ignore_sticky_posts' => true,
);
if ( $cat_term && ! is_wp_error( $cat_term ) ) {
    $args['cat'] = (int) $cat_term->term_id;
}
if ( $search !== '' ) {
    $args['s'] = $search;
}
$posts_q = new \WP_Query( $args );

/* ── Featured post (only on page 1, when no filter narrows results) ── */
$show_featured = ( $paged === 1 ) && ( $filter_cat === '' ) && ( $search === '' ) && $posts_q->have_posts();
$featured_post = null;
$remaining_q   = null;

if ( $show_featured ) {
    $featured_post = $posts_q->posts[0];
    /* Second query for the grid (exclude featured) */
    $remaining_q = new \WP_Query( array(
        'post_type'           => 'post',
        'posts_per_page'      => $ppp - 1,
        'paged'               => 1,
        'post__not_in'        => array( (int) $featured_post->ID ),
        'ignore_sticky_posts' => true,
    ) );
}

/* ── Visual assets: gradient fallbacks + emoji map per category ── */
$gradients = array(
    'linear-gradient(135deg,#04162E,#1E72D4)',
    'linear-gradient(135deg,#08274F,#0C3D7A)',
    'linear-gradient(135deg,#010D1E,#08274F)',
    'linear-gradient(135deg,#0a2540,#1459B3)',
    'linear-gradient(135deg,#04162E,#0C3D7A)',
    'linear-gradient(135deg,#08274F,#1459B3)',
    'linear-gradient(135deg,#052e16,#15803d)',
    'linear-gradient(135deg,#451a03,#d97706)',
);
$emojis_by_cat = array(
    'kegiatan'         => '🏭',
    'kegiatan-akademik'=> '🎓',
    'pengabdian'       => '🤝',
    'kerja-sama'       => '🤝',
    'penelitian'       => '🔬',
    'akademik'         => '🎓',
    'agribisnis'       => '🌾',
    'teknologi'        => '⚙️',
    'lingkungan'       => '♻️',
    'berita'           => '📰',
    'agenda'           => '📅',
    'hot-news'         => '🔥',
    'pengumuman'       => '📢',
    'prestasi-mahasiswa'=> '🏆',
    'info-pendaftaran' => '🎓',
    'lowongan-kerja'   => '💼',
    'seputar-sawit'    => '🌴',
    'kompetisi'        => '🥇',
    'pendidikan'       => '📚',
);

/* ── Categories for sidebar filter ── */
$cats_all = get_categories( array(
    'hide_empty' => true,
    'orderby'    => 'count',
    'order'      => 'DESC',
    'number'     => 12,
) );

/* ── Popular posts (by post_views_count meta; fallback to recent) ── */
$popular_q = new \WP_Query( array(
    'post_type'           => 'post',
    'posts_per_page'      => 5,
    'ignore_sticky_posts' => true,
    'no_found_rows'       => true,
    'meta_key'            => 'post_views_count',
    'orderby'             => 'meta_value_num',
    'order'               => 'DESC',
) );
if ( ! $popular_q->have_posts() ) {
    $popular_q = new \WP_Query( array(
        'post_type'           => 'post',
        'posts_per_page'      => 5,
        'ignore_sticky_posts' => true,
        'no_found_rows'       => true,
        'orderby'             => 'date',
        'order'               => 'DESC',
    ) );
}

/* ── JSON-LD schema buffer ──
 * Schema.org structured data injected as <script type="application/ld+json">
 * at the end of this template. We collect post data here (featured + grid
 * items) and emit it in a single <script> block. Schema types:
 *   - BreadcrumbList (page nav)
 *   - ItemList       (collection of news, ordered by display position)
 *   - NewsArticle    (per-item, nested inside each ListItem.item via @graph
 *                     pattern so Google can read both as separate entities)
 *   - WebSite        (publisher/brand info for sitelinks search box)
 */
$schema_items = array();
$schema_featured_id = null;
?>

<main>
  <div class="at-main-wrap">
    <div class="at-container">

      <!-- BREADCRUMB -->
      <nav class="at-bc rv" aria-label="Breadcrumb">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Beranda</a><span>›</span>
        <a href="<?php echo esc_url( home_url( '/berita' ) ); ?>">Berita &amp; Kegiatan</a><span>›</span>
        <span style="color:var(--td)"><?php
            if ( $cat_term && ! is_wp_error( $cat_term ) )      echo esc_html( $cat_term->name );
            elseif ( $search !== '' )                           echo 'Pencarian: ' . esc_html( $search );
            elseif ( $paged > 1 )                               echo 'Halaman ' . (int) $paged;
            else                                                echo 'Semua Berita';
        ?></span>
      </nav>

      <!-- Hero header -->
      <header class="arc-berita-hero rv">
        <span class="at-art-hdr-cat" style="background:rgba(20,89,179,.1);color:#1459B3">📰 Kabar Kampus</span>
        <h1 class="arc-berita-title">Berita &amp; <em>Kegiatan</em> Terkini</h1>
        <p class="arc-berita-sub">Liputan terbaru tentang kegiatan, riset, dan pencapaian sivitas akademika Institut Teknologi Sawit Indonesia.</p>

        <form role="search" method="get" action="<?php echo esc_url( home_url( '/index.php/berita/' ) ); ?>" class="arc-berita-search">
          <input type="hidden" name="post_type" value="post">
          <span class="arc-berita-search-ic">🔍</span>
          <input type="search" name="s" placeholder="Cari berita, topik, atau kata kunci…" value="<?php echo esc_attr( $search ); ?>">
          <button type="submit">Cari</button>
        </form>

        <div class="arc-berita-stats">
          <div class="arc-berita-stat"><span class="arc-berita-stat-n"><?php echo (int) wp_count_posts( 'post' )->publish; ?></span><span class="arc-berita-stat-l">Total Berita</span></div>
          <div class="arc-berita-stat"><span class="arc-berita-stat-n"><?php echo (int) count( $cats_all ); ?></span><span class="arc-berita-stat-l">Kategori</span></div>
          <div class="arc-berita-stat"><span class="arc-berita-stat-n"><?php
            $this_month = new \WP_Query( array(
                'post_type'           => 'post',
                'posts_per_page'      => -1,
                'no_found_rows'       => true,
                'ignore_sticky_posts' => true,
                'date_query'          => array( array( 'after' => '1 month ago' ) ),
                'fields'              => 'ids',
            ) );
            echo (int) count( $this_month->posts );
          ?></span><span class="arc-berita-stat-l">Liputan Bulan Ini</span></div>
        </div>
      </header>

      <?php if ( $posts_q->have_posts() ) : ?>

        <div class="at-detail-layout">

          <!-- ── MAIN COLUMN ── -->
          <div class="arc-berita-main">

            <!-- ▌ FEATURED POST ▌ -->
            <?php if ( $show_featured && $featured_post ) :
                $f_id        = (int) $featured_post->ID;
                $f_permalink = get_permalink( $f_id );
                $f_title     = $featured_post->post_title;
                $f_cats      = get_the_category( $f_id );
                $f_cat_name  = ( ! empty( $f_cats ) && ! is_wp_error( $f_cats ) ) ? $f_cats[0]->name : 'Berita';
                $f_cat_slug  = ( ! empty( $f_cats ) ) ? $f_cats[0]->slug : '';
                $f_emoji     = isset( $emojis_by_cat[ $f_cat_slug ] ) ? $emojis_by_cat[ $f_cat_slug ] : '📰';
                $f_grad      = $gradients[ $f_id % count( $gradients ) ];
                $f_date_long = get_the_date( 'd F Y', $f_id );
                $f_content   = $featured_post->post_content;
                $f_word      = str_word_count( wp_strip_all_tags( $f_content ) );
                $f_read_min  = max( 1, (int) ceil( $f_word / 200 ) );
                $f_views_raw = (int) get_post_meta( $f_id, 'post_views_count', true );
                $f_views_disp= $f_views_raw > 0 ? number_format_i18n( $f_views_raw ) . ' views' : '— views';
                $f_author_id = (int) $featured_post->post_author;
                $f_author    = get_the_author_meta( 'display_name', $f_author_id );
                $f_role      = get_the_author_meta( 'description', $f_author_id );
                if ( $f_role === '' ) { $f_role = 'Civitas Akademika ITSI'; }
                $f_init      = strtoupper( mb_substr( preg_replace( '/\s+/', '', $f_author ), 0, 2 ) );
                /* Lead: first <p> with > 80 chars */
                $f_lead = '';
                if ( preg_match( '/<p[^>]*>(.*?)<\/p>/is', $f_content, $m ) ) {
                    $first_p = wp_strip_all_tags( $m[1] );
                    if ( mb_strlen( $first_p ) > 80 ) { $f_lead = $first_p; }
                }
                if ( $f_lead === '' ) { $f_lead = wp_strip_all_tags( get_the_excerpt( $f_id ) ); }
                /* Capture featured for JSON-LD: position 1, has thumbnail. */
                $schema_featured_id = $f_id;
                $schema_items[]     = array(
                    'id'      => $f_id,
                    'title'   => $f_title,
                    'url'     => $f_permalink,
                    'date'    => get_the_date( 'c', $f_id ),
                    'modified'=> get_post_modified_time( 'c', true, $f_id ),
                    'author'  => $f_author,
                    'excerpt' => $f_lead,
                    'image'   => get_the_post_thumbnail_url( $f_id, 'large' ) ?: '',
                    'cat'     => $f_cat_name,
                );
            ?>
              <article class="arc-feat rv" itemscope itemtype="https://schema.org/Article">
                <meta itemprop="datePublished" content="<?php echo esc_attr( get_the_date( 'c', $f_id ) ); ?>">
                <meta itemprop="author" content="<?php echo esc_attr( $f_author ); ?>">
                <meta itemprop="publisher" content="Institut Teknologi Sawit Indonesia">

                <header class="at-art-hdr">
                  <span class="at-art-hdr-cat" style="background:rgba(30,114,212,.1);color:#1459B3"><?php echo esc_html( $f_cat_name ); ?></span>
                  <h1 class="at-art-hdr-title" itemprop="headline">
                    <a href="<?php echo esc_url( $f_permalink ); ?>" style="color:inherit;text-decoration:none"><?php echo esc_html( $f_title ); ?></a>
                  </h1>
                  <div class="at-art-hdr-meta">
                    <div class="at-meta-author">
                      <div class="at-author-av"><?php echo esc_html( $f_init ); ?></div>
                      <div>
                        <div class="at-author-nm" itemprop="author"><?php echo esc_html( $f_author ); ?></div>
                        <div class="at-author-role"><?php echo esc_html( wp_trim_words( $f_role, 8, '…' ) ); ?></div>
                      </div>
                    </div>
                    <div class="at-meta-info">
                      <span class="at-meta-tag">📅 <?php echo esc_html( $f_date_long ); ?></span>
                      <span class="at-meta-tag">⏱ <?php echo (int) $f_read_min; ?> menit membaca</span>
                      <span class="at-meta-tag">👁 <?php echo esc_html( $f_views_disp ); ?></span>
                    </div>
                  </div>
                </header>

                <?php
                // Render featured image only if a valid src can be resolved.
                // Some legacy attachments have _thumbnail_id set but missing
                // _wp_attached_file / _wp_attachment_metadata, which makes
                // get_the_post_thumbnail() return empty string while
                // has_post_thumbnail() still returns true.
                $f_thumb_html = has_post_thumbnail( $f_id ) ? get_the_post_thumbnail( $f_id, 'large', array(
                    'style'         => 'width:100%;height:auto;border-radius:20px;display:block',
                    /* LCP element — eager + sync decode + high priority. */
                    'loading'       => 'eager',
                    'decoding'      => 'sync',
                    'fetchpriority' => 'high',
                ) ) : '';
                $f_has_img    = ( trim( (string) $f_thumb_html ) !== '' );
                ?>
                <?php if ( $f_has_img || ! has_post_thumbnail( $f_id ) ) : ?>
                <figure class="at-art-featured-img">
                  <?php if ( $f_has_img ) : ?>
                    <a href="<?php echo esc_url( $f_permalink ); ?>">
                      <?php echo $f_thumb_html; ?>
                    </a>
                  <?php else : ?>
                    <a href="<?php echo esc_url( $f_permalink ); ?>" style="display:block;font-size:5rem;background:<?php echo esc_attr( $f_grad ); ?>;min-height:320px;display:flex;align-items:center;justify-content:center;border-radius:20px">
                      <?php echo esc_html( $f_emoji ); ?>
                    </a>
                  <?php endif; ?>
                </figure>
                <?php endif; ?>

                <?php if ( $f_lead !== '' ) : ?>
                  <p class="at-lead"><?php echo esc_html( $f_lead ); ?></p>
                <?php endif; ?>

                <div style="text-align:right;margin-bottom:1.5rem">
                  <a href="<?php echo esc_url( $f_permalink ); ?>" class="arc-feat-cta">Baca Selengkapnya →</a>
                </div>
              </article>
            <?php endif; ?>

            <!-- ▌ GRID OF REMAINING POSTS ▌ -->
            <?php
            /* If featured shown, use $remaining_q; else reuse $posts_q */
            $grid_q = $show_featured ? $remaining_q : $posts_q;
            ?>
            <div class="arc-berita-grid-head">
              <h2 class="arc-berita-grid-title"><?php
                if ( $cat_term && ! is_wp_error( $cat_term ) )      echo 'Kategori: ' . esc_html( $cat_term->name );
                elseif ( $search !== '' )                           echo 'Hasil pencarian: "' . esc_html( $search ) . '"';
                elseif ( $show_featured )                           echo 'Berita Lainnya';
                else                                                echo 'Semua Berita';
              ?></h2>
              <?php if ( ! $show_featured && $paged === 1 && $search === '' && $filter_cat === '' ) : ?>
                <span class="arc-berita-grid-count"><?php echo (int) $grid_q->found_posts; ?> artikel</span>
              <?php endif; ?>
            </div>

            <?php if ( $grid_q->have_posts() ) : ?>
              <div class="arc-berita-grid">
                <?php $i = 0; while ( $grid_q->have_posts() ) : $grid_q->the_post();
                  $pid       = get_the_ID();
                  $pcats     = get_the_category();
                  $pcat_name = ( ! empty( $pcats ) && ! is_wp_error( $pcats ) ) ? $pcats[0]->name : 'Berita';
                  $pcat_slug = ( ! empty( $pcats ) ) ? $pcats[0]->slug : '';
                  $pemoji    = isset( $emojis_by_cat[ $pcat_slug ] ) ? $emojis_by_cat[ $pcat_slug ] : '📰';
                  $pgrad     = $gradients[ ( $pid + $i ) % count( $gradients ) ];
                  $pdate     = get_the_date( 'd M Y' );
                  $pviews    = (int) get_post_meta( $pid, 'post_views_count', true );
                  $pviews_d  = $pviews > 0 ? number_format_i18n( $pviews ) . ' views' : '—';
                  $i++;
                  /* Collect for JSON-LD ItemList. position = $i (1-based, after featured). */
                  $schema_items[] = array(
                      'id'      => $pid,
                      'title'   => get_the_title(),
                      'url'     => get_permalink(),
                      'date'    => get_the_date( 'c' ),
                      'modified'=> get_post_modified_time( 'c', true ),
                      'author'  => get_the_author(),
                      'excerpt' => wp_strip_all_tags( get_the_excerpt() ),
                      'image'   => get_the_post_thumbnail_url( null, 'medium_large' ) ?: '',
                      'cat'     => $pcat_name,
                  );
                ?>
                  <article class="arc-berita-card rv d<?php echo esc_attr( ( $i % 3 ) + 1 ); ?>" data-cat="<?php echo esc_attr( $pcat_slug ); ?>">
                    <?php
                    // Same fallback logic as featured: only render the thumb link
                    // block when an actual <img> can be produced. Otherwise the
                    // card stays clean (title + excerpt + read-more) without a
                    // broken-image placeholder.
                    //
                    // LCP strategy: when featured post is shown ($show_featured),
                    // grid position 1 is the second-most visible element → still
                    // deserves high priority. When featured hidden (paged > 1 /
                    // filtered), grid position 1 IS the LCP element.
                    $p_is_lcp_card = ( $i === 1 );
                    $p_thumb_attrs = array(
                        'loading' => $p_is_lcp_card ? 'eager' : 'lazy',
                    );
                    if ( $p_is_lcp_card ) {
                        $p_thumb_attrs['decoding']      = 'sync';
                        $p_thumb_attrs['fetchpriority'] = 'high';
                    } else {
                        $p_thumb_attrs['decoding'] = 'async';
                    }
                    $p_thumb_html = has_post_thumbnail() ? get_the_post_thumbnail( null, 'medium_large', $p_thumb_attrs ) : '';
                    $p_has_img    = ( trim( (string) $p_thumb_html ) !== '' );
                    ?>
                    <?php if ( $p_has_img || ! has_post_thumbnail() ) : ?>
                    <a href="<?php the_permalink(); ?>" class="arc-berita-thumb">
                      <?php if ( $p_has_img ) : ?>
                        <?php echo $p_thumb_html; ?>
                      <?php else : ?>
                        <span class="arc-berita-fallback" style="background:<?php echo esc_attr( $pgrad ); ?>"><?php echo esc_html( $pemoji ); ?></span>
                      <?php endif; ?>
                      <span class="arc-berita-card-cat"><?php echo esc_html( strtoupper( $pcat_name ) ); ?></span>
                    </a>
                    <?php endif; ?>
                    <div class="arc-berita-body">
                      <div class="arc-berita-meta">
                        <span>📅 <?php echo esc_html( $pdate ); ?></span>
                        <span>👤 <?php the_author(); ?></span>
                        <span>👁 <?php echo esc_html( $pviews_d ); ?></span>
                      </div>
                      <h3 class="arc-berita-title-sm"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                      <p class="arc-berita-excerpt"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( get_the_excerpt() ), 22, '…' ) ); ?></p>
                      <a href="<?php the_permalink(); ?>" class="arc-berita-more">Baca selengkapnya →</a>
                    </div>
                  </article>
                <?php endwhile; wp_reset_postdata(); ?>
              </div>

              <!-- Pagination -->
              <?php if ( $grid_q->max_num_pages > 1 ) :
                  /* Pagination URL base — match the current context so paging doesn't
                   * jump to /berita/ when user is on /category/X/ or /?s=foo.
                   *
                   * Strategy: get_pagenum_link(1) returns the current page's clean URL
                   * (with paged stripped) — this is the WP-native way to build a
                   * pagination base that respects the current query (category, tag,
                   * search, custom ?kat=).
                   *
                   * Format depends on whether the resulting URL has a query string:
                   *   - no ? in URL  → pretty permalink style "page/%#%/"
                   *   - has ?        → query-string style "&paged=%#%"
                   */
                  $pag_base_full = esc_url_raw( get_pagenum_link( 1 ) );
                  $pag_has_q     = ( strpos( $pag_base_full, '?' ) !== false );
                  $pag_format    = $pag_has_q ? '&paged=%#%' : 'page/%#%/';
              ?>
                <div class="at-pagination">
                  <?php
                  $pag = paginate_links( array(
                      'total'     => (int) $grid_q->max_num_pages,
                      'current'   => (int) $paged,
                      'type'      => 'array',
                      'prev_text' => '‹ Sebelumnya',
                      'next_text' => 'Selanjutnya ›',
                      'mid_size'  => 1,
                      'end_size'  => 1,
                      'format'    => $pag_format,
                      'base'      => $pag_has_q
                          ? ( $pag_base_full . '%_%' )
                          : ( rtrim( $pag_base_full, '/' ) . '/%_%' ),
                  ) );
                  if ( $pag ) {
                      foreach ( $pag as $link ) {
                          if ( strpos( $link, 'current' ) !== false ) {
                              echo '<span class="current">' . wp_strip_all_tags( $link ) . '</span>';
                          } else {
                              echo $link; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                          }
                      }
                  }
                  ?>
                </div>
              <?php endif; ?>

            <?php else : ?>
              <div class="at-no-results">
                <h2>Tidak ada berita ditemukan</h2>
                <p>Coba kata kunci lain atau pilih kategori yang berbeda.</p>
                <a href="<?php echo esc_url( home_url( '/berita/' ) ); ?>" class="at-tag-item" style="font-size:.8rem">← Kembali ke semua berita</a>
              </div>
            <?php endif; ?>
          </div>

          <!-- ── STICKY SIDEBAR ── -->
          <!--
            Sidebar sekarang dirender via 3 widget area (functions.php itsi_widgets_init):
              - itsi_archive_berita_widget_filter    → taruh "ITSI — Filter Kategori (Auto)"
              - itsi_archive_berita_widget_popular   → taruh "ITSI — Paling Banyak Dibaca (Auto)"
              - itsi_archive_berita_widget_sidebar   → taruh Custom HTML (AdSense) untuk iklan
            Admin dapat menata widget dari /wp/wp-admin/widgets.php.
            Kalau area kosong, slot tidak di-render (no dead UI).
          -->
          <aside class="at-sidebar-detail" aria-label="Sidebar berita">

            <?php if ( is_active_sidebar( 'itsi_archive_berita_widget_filter' ) ) : ?>
              <?php dynamic_sidebar( 'itsi_archive_berita_widget_filter' ); ?>
            <?php endif; ?>

            <?php if ( is_active_sidebar( 'itsi_archive_berita_widget_popular' ) ) : ?>
              <?php dynamic_sidebar( 'itsi_archive_berita_widget_popular' ); ?>
            <?php endif; ?>

            <?php if ( is_active_sidebar( 'itsi_archive_berita_widget_sidebar' ) ) : ?>
              <?php dynamic_sidebar( 'itsi_archive_berita_widget_sidebar' ); ?>
            <?php endif; ?>

          </aside>

        </div>

      <?php else : ?>
        <div class="at-no-results">
          <h2>Tidak ada berita ditemukan</h2>
          <p><?php
            if ( $search !== '' )                           echo 'Coba kata kunci lain untuk "' . esc_html( $search ) . '".';
            elseif ( $cat_term && ! is_wp_error( $cat_term ) ) echo 'Belum ada berita di kategori "' . esc_html( $cat_term->name ) . '".';
            else                                            echo 'Belum ada berita yang dipublikasikan.';
          ?></p>
          <a href="<?php echo esc_url( home_url( '/berita/' ) ); ?>" class="at-tag-item" style="font-size:.8rem">← Kembali ke semua berita</a>
        </div>
      <?php endif; ?>

    </div>
  </div>
</main>

<?php
/* ── JSON-LD structured data ──
 * Emit a single <script type="application/ld+json"> block. Google's
 * documentation supports @graph root for combining multiple top-level
 * entities (BreadcrumbList + ItemList + WebSite). Each ListItem.item
 * is a full NewsArticle node so search engines can extract rich-result
 * properties (image, datePublished, author, etc).
 *
 * Position numbering: featured = 1, grid items 2..12.
 */
$schema_graph = array(
    '@context' => 'https://schema.org',
    '@graph'   => array(),
);

/* WebSite (sitelinks search box eligible). */
$schema_graph['@graph'][] = array(
    '@type'       => 'WebSite',
    '@id'         => home_url( '/#website' ),
    'url'         => home_url( '/' ),
    'name'        => 'Institut Teknologi Sawit Indonesia',
    'inLanguage'  => 'id-ID',
    'publisher'   => array(
        '@type' => 'Organization',
        'name'  => 'Institut Teknologi Sawit Indonesia',
        'url'   => home_url( '/' ),
    ),
);

/* BreadcrumbList (matches the on-page breadcrumb HTML). */
$bc_items = array(
    array( '@type' => 'ListItem', 'position' => 1, 'name' => 'Beranda',  'item' => home_url( '/' ) ),
    array( '@type' => 'ListItem', 'position' => 2, 'name' => 'Berita & Kegiatan', 'item' => home_url( '/berita/' ) ),
);
if ( $cat_term && ! is_wp_error( $cat_term ) ) {
    $bc_items[] = array(
        '@type'    => 'ListItem',
        'position' => 3,
        'name'     => $cat_term->name,
        'item'     => esc_url_raw( get_category_link( $cat_term->term_id ) ),
    );
} elseif ( $search !== '' ) {
    $bc_items[] = array(
        '@type'    => 'ListItem',
        'position' => 3,
        'name'     => 'Pencarian: ' . $search,
        'item'     => esc_url_raw( add_query_arg( 's', $search, home_url( '/berita/' ) ) ),
    );
}
$schema_graph['@graph'][] = array(
    '@type'           => 'BreadcrumbList',
    'itemListElement' => $bc_items,
);

/* ItemList of NewsArticle nodes. */
if ( ! empty( $schema_items ) ) {
    $item_list_elements = array();
    $pos                = 0;
    foreach ( $schema_items as $si ) {
        $pos++;
        $article = array(
            '@type'            => 'NewsArticle',
            '@id'              => $si['url'] . '#article',
            'headline'         => wp_strip_all_tags( $si['title'] ),
            'url'              => $si['url'],
            'datePublished'    => $si['date'],
            'dateModified'     => $si['modified'],
            'mainEntityOfPage' => $si['url'],
            'author'           => array(
                '@type' => 'Person',
                'name'  => $si['author'],
            ),
            'publisher'        => array(
                '@type' => 'Organization',
                'name'  => 'Institut Teknologi Sawit Indonesia',
                'url'   => home_url( '/' ),
            ),
            'articleSection'  => $si['cat'],
        );
        if ( ! empty( $si['image'] ) ) {
            $article['image'] = array(
                '@type' => 'ImageObject',
                'url'   => $si['image'],
            );
        }
        if ( ! empty( $si['excerpt'] ) ) {
            $article['description'] = wp_trim_words( $si['excerpt'], 40, '…' );
        }
        $item_list_elements[] = array(
            '@type'    => 'ListItem',
            'position' => $pos,
            'url'      => $si['url'],
            'item'     => $article,
        );
    }
    $schema_graph['@graph'][] = array(
        '@type'           => 'ItemList',
        'itemListElement' => $item_list_elements,
    );
}
?>
<script type="application/ld+json"><?php echo wp_json_encode( $schema_graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); ?></script>

<?php get_footer();