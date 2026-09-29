<?php
/**
 * Brand-color CSS variables plus the Clarity / AdSense snippets.
 *
 * itsi_inline_brand_colors_css() maps the Customizer color settings onto the
 * :root variables consumed by style.css; the remaining callbacks inject the
 * Microsoft Clarity and Google AdSense tags into <head> (front + login) and
 * admin_head.
 *
 * @package itsi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Inline CSS for Header & Top Menu → Brand Colors customizer settings.
 *
 * Emits a small <style> block in <head> AFTER style.css so the variables
 * override :root defaults. Defaults match :root values, so unset mods
 * produce no visual change.
 *
 * @return void
 */
function itsi_inline_brand_colors_css() {
	$topbar_bg  = get_theme_mod( 'itsi_color_topbar_bg', '#010D1E' );
	$navbar_bg  = get_theme_mod( 'itsi_color_navbar_bg', '#020C1C' );
	$navbar_alp = get_theme_mod( 'itsi_color_navbar_alpha', 0.88 );
	$pmb_from   = get_theme_mod( 'itsi_color_pmb_from', '#1459B3' );
	$pmb_to     = get_theme_mod( 'itsi_color_pmb_to', '#1E72D4' );

	// Defensive: sanitize_hex_color may return null/empty for invalid input.
	$topbar_bg = sanitize_hex_color( $topbar_bg ) ?: '#010D1E';
	$navbar_bg = sanitize_hex_color( $navbar_bg ) ?: '#020C1C';
	$pmb_from  = sanitize_hex_color( $pmb_from ) ?: '#1459B3';
	$pmb_to    = sanitize_hex_color( $pmb_to ) ?: '#1E72D4';
	$navbar_alp = is_numeric( $navbar_alp )
		? max( 0.0, min( 1.0, (float) $navbar_alp ) )
		: 0.88;

	// Convert hex to r,g,b for rgba() of navbar bg.
	$nav_r = hexdec( substr( $navbar_bg, 1, 2 ) );
	$nav_g = hexdec( substr( $navbar_bg, 3, 2 ) );
	$nav_b = hexdec( substr( $navbar_bg, 5, 2 ) );

	$css = sprintf(
		':root{--itsi-topbar-bg:%1$s;--itsi-navbar-bg-color:rgba(%2$d,%3$d,%4$d,%5$s);--itsi-pmb-from:%6$s;--itsi-pmb-to:%7$s;}',
		$topbar_bg,
		$nav_r,
		$nav_g,
		$nav_b,
		$navbar_alp,
		$pmb_from,
		$pmb_to
	);
	echo '<style id="itsi-brand-colors">' . $css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — pure hex/numbers, no user HTML.
}
add_action( 'wp_head', 'itsi_inline_brand_colors_css', 99 );

/**
 * Inject Microsoft Clarity tracking script into the WordPress admin <head>.
 *
 * Why: header.php only fires on the front-end (wp_head). Admin pages use
 *      WP core's own admin-header.php, so the Clarity <script> that lives
 *      in header.php does NOT appear in wp-admin. This hook re-emits the
 *      exact same tracking tag for the admin context so editor behaviour,
 *      settings changes, plugin UIs, and login flows are recorded in the
 *      same Clarity project.
 *
 * Scope: admin only (is_admin() guard). Not echoed on wp-login.php login_head
 *        is hooked separately so auth-page loads — including failed logins —
 *        are also tracked.
 */
function itsi_admin_clarity_script() {
    // No need for is_admin() guard here — admin_head only fires on wp-admin/*.
    ?>
    <!-- Microsoft Clarity (admin) — must match the project ID in header.php. -->
    <script type="text/javascript">
        (function(c,l,a,r,i,t,y){
            c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
            t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
            y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
        })(window, document, "clarity", "script", "xgef7q4mf8");
    </script>
    <?php
}
add_action( 'admin_head', 'itsi_admin_clarity_script' );

/**
 * Same Clarity tag, on the wp-login.php page (login_head fires inside
 * <head> of the standalone login screen, where admin_head is NOT called).
 * Keeps failed-login and auth-page visits in the recording.
 */
function itsi_login_clarity_script() {
    ?>
    <!-- Microsoft Clarity (login) — matches project ID in header.php + admin_head. -->
    <script type="text/javascript">
        (function(c,l,a,r,i,t,y){
            c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
            t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
            y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
        })(window, document, "clarity", "script", "xgef7q4mf8");
    </script>
    <?php
}
add_action( 'login_head', 'itsi_login_clarity_script' );

/**
 * Inject Google AdSense auto-ads script into the front-end <head>.
 *
 * Scope (intentionally narrow): the AdSense account only authorises ad
 * placement on article / post surfaces — never on the homepage, custom
 * post types like `program_studi` / `info_publik`, static `page`, or
 * admin / login screens.
 *
 * Show on:
 *   - is_singular('post')            — single post / artikel
 *   - is_home()                      — blog posts index (default home if no static front page)
 *   - is_post_type_archive('post')   — /berita/ archive (theme-rewritten from pagename=berita)
 *   - is_category()                  — category archives
 *   - is_tag()                       — tag archives
 *
 * Explicitly NOT on:
 *   - is_front_page()                — static homepage
 *   - is_singular('program_studi')   — prodi detail pages
 *   - is_singular('info_publik')     — informasi publik detail pages
 *   - is_singular('page')            — generic WP pages (profil, dll)
 *   - wp-admin / wp-login            — admin_head / login_head are not hooked, so
 *                                       this function never fires there
 *
 * Loaded async so it never blocks first paint. Hoisted to wp_head at
 * priority 10 (before the schema JSON-LD emitter at 20 and the brand-colors
 * inline <style> at 99).
 */
function itsi_adsense_script() {
	// Defensive guard — header.php's inline Clarity block has a fallback
	// for the admin context via admin_head; we don't echo here because
	// wp_head does not fire on wp-admin anyway. Still: bail explicitly if
	// somehow reached outside the front-end.
	if ( ! function_exists( 'is_singular' ) || is_admin() ) {
		return;
	}

	$show_on_post_surface = is_singular( 'post' )
		|| is_home()
		|| is_post_type_archive( 'post' )
		|| is_category()
		|| is_tag();

	if ( ! $show_on_post_surface ) {
		return;
	}

	?>
	<!-- Google AdSense (auto ads) — restricted to post / berita surfaces. -->
	<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-3996303435900501"
		crossorigin="anonymous"></script>
	<?php
}
add_action( 'wp_head', 'itsi_adsense_script', 10 );
