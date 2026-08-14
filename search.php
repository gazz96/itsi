<?php
/**
 * Search results template – ITSI Theme.
 *
 * Layout: dark hero with search form + query stats, then a 3-column grid
 * of result cards (thumbnail + category badge + title with <mark> highlight
 * + excerpt + date/views meta). No results: friendly empty state.
 *
 * @package itsi
 */

get_header();

$query       = get_search_query();
$paged       = max( 1, (int) get_query_var( 'paged' ) );
$result_q    = $GLOBALS['wp_query'];
$total       = (int) $result_q->found_posts;

$gradients = array(
    'linear-gradient(135deg,#04162E,#1E72D4)',
    'linear-gradient(135deg,#08274F,#0C3D7A)',
    'linear-gradient(135deg,#010D1E,#08274F)',
    'linear-gradient(135deg,#0a2540,#1459B3)',
    'linear-gradient(135deg,#04162E,#0C3D7A)',
    'linear-gradient(135deg,#08274F,#1459B3)',
);

$icons_by_cat = array(
    'kegiatan'           => 'bi-buildings',
    'kegiatan-akademik'  => 'bi-mortarboard',
    'pengabdian'         => 'bi-people',
    'kerja-sama'         => 'bi-diagram-3',
    'penelitian'         => 'bi-search',
    'akademik'           => 'bi-mortarboard',
    'agribisnis'         => 'bi-tree',
    'teknologi'          => 'bi-gear',
    'lingkungan'         => 'bi-recycle',
    'berita'             => 'bi-newspaper',
);
?>

<main>
  <!-- Hero -->
  <section class="at-search-hero">
    <div class="at-container at-search-hero-inner">
      <nav class="at-bc" style="color:rgba(255,255,255,.55)">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" style="color:rgba(255,255,255,.7)">Beranda</a>
        <span style="color:rgba(255,255,255,.4)">›</span>
        <span style="color:rgba(255,255,255,.85)">Pencarian</span>
      </nav>
      <h1>
        <?php if ( $query !== '' ) : ?>
          Hasil pencarian untuk <em>"<?php echo esc_html( $query ); ?>"</em>
        <?php else : ?>
          Telusuri <em>Wawasan &amp; Berita</em> ITSI
        <?php endif; ?>
      </h1>
      <p><?php echo esc_html( $total > 0 ? sprintf( '%d artikel ditemukan', $total ) : 'Coba kata kunci lain untuk menemukan artikel yang relevan.' ); ?></p>
      <form role="search" method="get" class="at-search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
        <input type="search" name="s" placeholder="Cari artikel, berita, penelitian…" value="<?php echo esc_attr( $query ); ?>" aria-label="Search">
        <button type="submit"><i class="bi bi-search"></i> Cari</button>
      </form>
    </div>
  </section>

  <div class="at-main-wrap">
    <div class="at-container">

      <?php if ( $query === '' ) : ?>
        <!-- Empty query: prompt + popular tags -->
        <div class="at-no-results">
          <h2>Ketik kata kunci untuk mulai mencari</h2>
          <p>Masukkan topik, judul artikel, atau nama program studi yang ingin Anda cari.</p>
          <form role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
            <input type="search" name="s" placeholder="Misalnya: sawit, PKL, BDP…" aria-label="Search">
            <button type="submit">Cari</button>
          </form>
          <div style="margin-top:1.5rem;display:flex;flex-wrap:wrap;gap:.5rem;justify-content:center">
            <?php
            $tags = get_tags( array( 'number' => 8, 'orderby' => 'count', 'order' => 'DESC' ) );
            if ( ! empty( $tags ) ) :
                foreach ( $tags as $t ) : ?>
                  <a href="<?php echo esc_url( home_url( '/?s=' . rawurlencode( $t->name ) ) ); ?>" class="at-tag-item" style="font-size:.78rem">#<?php echo esc_html( $t->name ); ?></a>
                <?php endforeach;
            endif; ?>
          </div>
        </div>

      <?php elseif ( have_posts() ) : ?>
        <div class="at-search-stats">
          Menampilkan <strong><?php echo (int) $result_q->post_count; ?></strong> dari <strong><?php echo (int) $total; ?></strong> hasil untuk "<strong><?php echo esc_html( $query ); ?></strong>"
        </div>

        <div class="at-results-grid">
          <?php $i = 0; while ( have_posts() ) : the_post();
            $pid        = get_the_ID();
            $pcats      = get_the_category();
            $pcat_name  = ( ! empty( $pcats ) && ! is_wp_error( $pcats ) ) ? $pcats[0]->name : 'Berita';
            $pcat_slug  = ( ! empty( $pcats ) ) ? $pcats[0]->slug : '';
            $picon      = isset( $icons_by_cat[ $pcat_slug ] ) ? $icons_by_cat[ $pcat_slug ] : 'bi-newspaper';
            $pgrad      = $gradients[ ( $pid + $i ) % count( $gradients ) ];
            $ptitle_h   = the_title( '', '', false );
            // Highlight query in title
            $ptitle_disp = $ptitle_h;
            if ( $query !== '' ) {
                $ptitle_disp = preg_replace(
                    '/(' . preg_quote( $query, '/' ) . ')/i',
                    '<mark>$1</mark>',
                    $ptitle_h
                );
            }
            $pexcerpt    = wp_trim_words( wp_strip_all_tags( get_the_excerpt() ), 22, '…' );
            $pdate_disp  = get_the_date( 'd M Y' );
            $pviews_raw  = (int) get_post_meta( $pid, 'post_views_count', true );
            $pviews_disp = itsi_format_views( $pviews_raw );
            $i++;
          ?>
            <a href="<?php the_permalink(); ?>" class="at-result-card rv d<?php echo esc_attr( ( $i % 3 ) + 1 ); ?>">
              <div class="at-result-thumb" style="background:<?php echo esc_attr( $pgrad ); ?>">
                <?php if ( has_post_thumbnail() ) : the_post_thumbnail( 'medium' ); else : ?>
                  <span style="font-size:2.6rem"><i class="bi <?php echo esc_attr( $picon ); ?>"></i></span>
                <?php endif; ?>
                <span class="at-result-cat"><?php echo esc_html( $pcat_name ); ?></span>
              </div>
              <div class="at-result-body">
                <div class="at-result-date"><i class="bi bi-calendar3"></i> <?php echo esc_html( $pdate_disp ); ?> · <i class="bi bi-eye"></i> <?php echo esc_html( $pviews_disp ); ?></div>
                <h3 class="at-result-title"><?php echo wp_kses_post( $ptitle_disp ); ?></h3>
                <p class="at-result-excerpt"><?php echo esc_html( $pexcerpt ); ?></p>
                <div class="at-result-foot"><span><?php echo esc_html( get_the_author() ); ?></span><span>Baca →</span></div>
              </div>
            </a>
          <?php endwhile; ?>
        </div>

        <!-- Pagination -->
        <div class="at-pagination">
          <?php
          $pag = paginate_links( array(
              'total'     => (int) $result_q->max_num_pages,
              'current'   => $paged,
              'type'      => 'array',
              'prev_text' => '‹',
              'next_text' => '›',
              'mid_size'  => 1,
              'end_size'  => 1,
          ) );
          if ( $pag ) {
              foreach ( $pag as $link ) {
                  // Wrap current link with our class
                  if ( strpos( $link, 'current' ) !== false ) {
                      echo '<span class="current">' . wp_strip_all_tags( $link ) . '</span>';
                  } else {
                      echo $link; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                  }
              }
          }
          ?>
        </div>

      <?php else : ?>
        <!-- No matches: friendly state -->
        <div class="at-no-results">
          <h2>Tidak ada hasil untuk "<?php echo esc_html( $query ); ?>"</h2>
          <p>Coba periksa ejaan, gunakan kata kunci yang lebih umum, atau telusuri kategori populer di bawah.</p>
          <form role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
            <input type="search" name="s" placeholder="Cari kata kunci lain…" value="<?php echo esc_attr( $query ); ?>" aria-label="Search">
            <button type="submit">Cari</button>
          </form>
          <div style="margin-top:1.5rem">
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="at-tag-item" style="font-size:.8rem">← Kembali ke Beranda</a>
          </div>
        </div>
      <?php endif; ?>

    </div>
  </div>
</main>

<?php get_footer();