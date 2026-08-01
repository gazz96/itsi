<?php
/**
 * Custom widgets for the ITSI theme.
 *
 * Two data-driven widgets for the single-post layout:
 *   - ITSI_TOC_Widget      → renders Daftar Isi (auto-parsed from <h2> in content)
 *   - ITSI_Popular_Widget  → renders "Paling Banyak Dibaca" (top posts by post_views_count)
 *
 * Drop these into the corresponding widget areas registered in functions.php
 * (itsi_single_post_widget_toc, itsi_single_post_widget_popular). Both widgets
 * only output anything on singular post / page — outside that context, the
 * "no items" message is suppressed so the sidebar slot collapses cleanly.
 *
 * @package itsi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Widget: Daftar Isi (Table of Contents).
 *
 * Auto-parses <h2> from the current singular post/page content. Mirrors the
 * original hardcoded behaviour in single.php (header, item list, numbered).
 * The original click-scroll JS handler in single.php still works because the
 * rendered DOM uses the same .at-toc-list / .at-toc-item / .at-toc-n classes
 * and #at-tocList id.
 */
class ITSI_TOC_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'itsi_toc_widget',
			__( 'ITSI — Daftar Isi (Auto)', 'itsi' ),
			array(
				'description'                 => __( 'Daftar Isi otomatis dari <h2> di konten artikel. Taruh di widget area Single Post — Table of Contents.', 'itsi' ),
				'customize_selective_refresh_widgets' => true,
			)
		);
	}

	public function widget( $args, $instance ) {
		if ( ! is_singular( 'post' ) && ! is_singular( 'page' ) ) {
			return;
		}

		$content = get_the_content();
		$toc_items = array();
		// Parse both <h2> and <h3> — some posts use only h3 for sectioning.
		if ( preg_match_all( '/<h(2|3)[^>]*>(.*?)<\/h\1>/is', $content, $m ) ) {
			foreach ( $m[2] as $h ) {
				$toc_items[] = wp_strip_all_tags( $h );
			}
		}

		if ( empty( $toc_items ) ) {
			return;
		}

		$title = isset( $instance['title'] ) ? $instance['title'] : '';
		$title = apply_filters( 'widget_title', $title, $instance, $this->id_base );

		echo $args['before_widget'];
		if ( ! empty( $title ) ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title'];
		}
		?>
		<div class="at-s-body">
			<div class="at-toc-list" id="at-tocList">
				<?php foreach ( $toc_items as $i => $h2 ) :
					$n = sprintf( '%02d', $i + 1 ); ?>
					<div class="at-toc-item" data-toc-index="<?php echo (int) $i; ?>">
						<span class="at-toc-n"><?php echo esc_html( $n ); ?></span>
						<span><?php echo esc_html( $h2 ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
		echo $args['after_widget'];
	}

	public function form( $instance ) {
		$title = isset( $instance['title'] ) ? $instance['title'] : 'Daftar Isi';
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Judul:', 'itsi' ); ?></label>
			<input class="widefat" type="text"
				id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
				value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p class="description"><?php esc_html_e( 'Item diambil otomatis dari tag <h2> di konten artikel. Tidak perlu isi manual.', 'itsi' ); ?></p>
		<?php
	}

	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = isset( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : 'Daftar Isi';
		return $instance;
	}
}

/**
 * Widget: Paling Banyak Dibaca (Popular Posts).
 *
 * Lists the top 5 posts by `post_views_count` meta, excluding the current
 * post. Falls back to most-recent posts if no view counts exist. Renders
 * the same DOM as the original hardcoded version so existing CSS still
 * applies (.at-s-card, .at-pop-item, .at-pop-num, .at-pop-title, .at-pop-meta).
 */
class ITSI_Popular_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'itsi_popular_widget',
			__( 'ITSI — Paling Banyak Dibaca (Auto)', 'itsi' ),
			array(
				'description'                 => __( 'Top 5 artikel paling banyak dibaca (by post_views_count). Taruh di widget area Single Post — Popular Posts.', 'itsi' ),
				'customize_selective_refresh_widgets' => true,
			)
		);
	}

	public function widget( $args, $instance ) {
		// Render di single (post/page) + archive (category, tag, search, post_type_archive, home).
		// Skip di admin, 404, dan CPT non-post (info_publik, program_studi — punya template sendiri).
		if ( is_admin() || is_404() ) {
			return;
		}
		$pt = get_post_type();
		if ( $pt && ! in_array( $pt, array( 'post' ), true ) && ! is_category() && ! is_tag() && ! is_search() && ! is_home() ) {
			return;
		}
		if ( ! is_singular( 'post' ) && ! is_singular( 'page' )
			&& ! is_category() && ! is_tag() && ! is_search() && ! is_home()
			&& ! is_post_type_archive( 'post' ) ) {
			return;
		}

		$current_id   = is_singular() ? (int) get_the_ID() : 0;
		$instance     = wp_parse_args( $instance, array(
			'title' => 'Paling Banyak Dibaca',
			'count' => 5,
		) );
		$count        = max( 1, min( 20, (int) $instance['count'] ) );
		$title        = apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base );

		$pop_q = new WP_Query( array(
			'post_type'      => 'post',
			'posts_per_page' => $count,
			'post__not_in'   => array( $current_id ),
			'ignore_sticky'  => true,
			'no_found_rows'  => true,
			'meta_key'       => 'post_views_count',
			'orderby'        => 'meta_value_num',
			'order'          => 'DESC',
		) );

		if ( ! $pop_q->have_posts() ) {
			$pop_q = new WP_Query( array(
				'post_type'      => 'post',
				'posts_per_page' => $count,
				'post__not_in'   => array( $current_id ),
				'ignore_sticky'  => true,
				'no_found_rows'  => true,
				'orderby'        => 'date',
				'order'          => 'DESC',
			) );
		}

		if ( ! $pop_q->have_posts() ) {
			return;
		}

		echo $args['before_widget'];
		if ( ! empty( $title ) ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title'];
		}
		?>
		<div class="at-s-body">
			<?php $i = 0; while ( $pop_q->have_posts() ) : $pop_q->the_post();
				$i++;
				$n = sprintf( '%02d', $i );
				$v = (int) get_post_meta( get_the_ID(), 'post_views_count', true ); ?>
				<div class="at-pop-item" onclick="location.href='<?php echo esc_url( get_the_permalink() ); ?>'">
					<span class="at-pop-num"><?php echo esc_html( $n ); ?></span>
					<div>
						<div class="at-pop-title"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( get_the_title() ), 12, '…' ) ); ?></div>
						<span class="at-pop-meta">👁 <?php echo $v > 0 ? esc_html( number_format_i18n( $v ) ) : '—'; ?></span>
					</div>
				</div>
			<?php endwhile; wp_reset_postdata(); ?>
		</div>
		<?php
		echo $args['after_widget'];
	}

	public function form( $instance ) {
		$instance = wp_parse_args( $instance, array(
			'title' => 'Paling Banyak Dibaca',
			'count' => 5,
		) );
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Judul:', 'itsi' ); ?></label>
			<input class="widefat" type="text"
				id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
				value="<?php echo esc_attr( $instance['title'] ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>"><?php esc_html_e( 'Jumlah:', 'itsi' ); ?></label>
			<input class="tiny-text" type="number" min="1" max="20" step="1"
				id="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'count' ) ); ?>"
				value="<?php echo esc_attr( (int) $instance['count'] ); ?>">
		</p>
		<p class="description"><?php esc_html_e( 'Item diurutkan otomatis dari post_views_count (fallback: terbaru).', 'itsi' ); ?></p>
		<?php
	}

	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = isset( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : 'Paling Banyak Dibaca';
		$instance['count'] = isset( $new_instance['count'] ) ? max( 1, min( 20, (int) $new_instance['count'] ) ) : 5;
		return $instance;
	}
}

/**
 * Widget: Filter Kategori (untuk archive berita).
 *
 * Menampilkan daftar kategori post + count, dengan link "?kat=slug" ke /berita/.
 * Item "Semua" hardcode di paling atas, link ke /berita/ tanpa query.
 * Active state otomatis detect dari ?kat=slug di URL atau dari is_category().
 *
 * Hanya render di archive konteks (category, tag, search, post_type_archive 'post',
 * home). Skip di single (post/page) dan CPT lain (info_publik, program_studi).
 *
 * Renders DOM sama dengan hardcode original di archive-berita.php supaya CSS
 * (.arc-berita-cat-list, .arc-berita-cat-item, .arc-berita-cat-c) tetap berlaku.
 */
class ITSI_CategoryFilter_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'itsi_category_filter_widget',
			__( 'ITSI — Filter Kategori (Auto)', 'itsi' ),
			array(
				'description' => __( 'Daftar kategori post + count, link ke /berita/?kat=slug. Taruh di widget area Archive Berita — Filter Kategori.', 'itsi' ),
				'customize_selective_refresh_widgets' => true,
			)
		);
	}

	public function widget( $args, $instance ) {
		if ( is_admin() || is_404() ) {
			return;
		}
		$pt = get_post_type();
		$allowed = is_category() || is_tag() || is_search() || is_home()
			|| is_post_type_archive( 'post' )
			|| ( is_singular( 'post' ) || is_singular( 'page' ) );
		if ( ! $allowed ) {
			return;
		}
		// Skip CPT non-post (info_publik, program_studi).
		if ( $pt && ! in_array( $pt, array( 'post', 'page' ), true ) && ! is_category() && ! is_tag() && ! is_search() && ! is_home() ) {
			return;
		}

		$instance = wp_parse_args( $instance, array(
			'title' => 'Filter Kategori',
			'count' => 12,
		) );
		$title = apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base );
		$count = max( 1, min( 50, (int) $instance['count'] ) );

		$cats = get_categories( array(
			'hide_empty' => true,
			'orderby'    => 'count',
			'order'      => 'DESC',
			'number'     => $count,
		) );
		if ( empty( $cats ) ) {
			return;
		}

		// Detect active slug: ?kat=slug query param wins, else fall back to is_category().
		$active_slug = isset( $_GET['kat'] ) ? sanitize_title( wp_unslash( $_GET['kat'] ) ) : '';
		if ( $active_slug === '' && is_category() ) {
			$qo = get_queried_object();
			if ( $qo && isset( $qo->slug ) ) {
				$active_slug = $qo->slug;
			}
		}

		$berita_url = home_url( '/index.php/berita/' );
		$total_posts = (int) wp_count_posts( 'post' )->publish;

		echo $args['before_widget'];
		if ( ! empty( $title ) ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title'];
		}
		?>
		<div class="at-s-body">
			<div class="arc-berita-cat-list">
				<a href="<?php echo esc_url( $berita_url ); ?>" class="arc-berita-cat-item <?php echo $active_slug === '' ? 'active' : ''; ?>">
					<span>Semua</span><span class="arc-berita-cat-c"><?php echo (int) $total_posts; ?></span>
				</a>
				<?php foreach ( $cats as $c ) :
					$active = ( $active_slug === $c->slug ) ? 'active' : ''; ?>
					<a href="<?php echo esc_url( add_query_arg( 'kat', $c->slug, $berita_url ) ); ?>" class="arc-berita-cat-item <?php echo esc_attr( $active ); ?>">
						<span><?php echo esc_html( $c->name ); ?></span><span class="arc-berita-cat-c"><?php echo (int) $c->count; ?></span>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
		echo $args['after_widget'];
	}

	public function form( $instance ) {
		$instance = wp_parse_args( $instance, array(
			'title' => 'Filter Kategori',
			'count' => 12,
		) );
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Judul:', 'itsi' ); ?></label>
			<input class="widefat" type="text"
				id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
				value="<?php echo esc_attr( $instance['title'] ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>"><?php esc_html_e( 'Jumlah kategori:', 'itsi' ); ?></label>
			<input class="tiny-text" type="number" min="1" max="50" step="1"
				id="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'count' ) ); ?>"
				value="<?php echo esc_attr( (int) $instance['count'] ); ?>">
		</p>
		<p class="description"><?php esc_html_e( 'Diurutkan otomatis dari jumlah post terbanyak.', 'itsi' ); ?></p>
		<?php
	}

	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = isset( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : 'Filter Kategori';
		$instance['count'] = isset( $new_instance['count'] ) ? max( 1, min( 50, (int) $new_instance['count'] ) ) : 12;
		return $instance;
	}
}
