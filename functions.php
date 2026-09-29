<?php
/**
 * ITSI Theme functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package itsi
 *
 * ───────────────────────────────────────────────────────────────────────────
 * THEME STRUCTURE MAP  (see STRUCTURE.md for the full tree)
 * ───────────────────────────────────────────────────────────────────────────
 * Root templates:  header.php, footer.php, single*.php, archive-*.php,
 *                  page.php, search.php, 404.php, comments.php, sidebar.php,
 *                  index.php, template-home-static.php
 * Styling:         style.css (design system),
 *                  assets/css/{program-studi,artikel-detail}.css
 * Scripts:         assets/js/itsi-main.js (front), assets/js/typerocket-compat.js
 * Logo:            assets/img/logo.svg
 * Partials:        none (theme renders inline — no get_template_part() calls)
 * Includes (inc/):
 *   inc/bootstrap/     — theme support, rewrites, enqueue, ajax localize
 *   inc/content/       — CPTs, footer layout, info-publik, view counter, helpers
 *   inc/admin/         — admin menu, analytics, term icons, seeders
 *   inc/forms/         — public form handlers (permohonan)
 *   inc/theme/         — widgets, menu walker, schema.org JSON-LD
 *   inc/integrations/  — third-party shims (typerocket)
 *   inc/lp2m/ (+pdf/)  — LP2M integration (REST, auth, CORS, SMTP, PDF)
 *   inc/tools/seeders/ — CLI seed scripts (not loaded by the theme)
 * Docs:            STRUCTURE.md (full tree), inc/README.md, _backups/README.md
 * Unused code archived under _backups/_unused/ (never loaded — keep for ref).
 * ───────────────────────────────────────────────────────────────────────────
 */

if ( ! defined( '_S_VERSION' ) ) {
	define( '_S_VERSION', '1.0.1' );
}
/* ── Bootstrap: theme support, routing, assets ─────────────────────────────── */

// Theme supports, menus, image sizes, text domain, content width.
require_once get_template_directory() . '/inc/bootstrap/theme-support.php';

// Route /berita and the post archive to archive-berita.php.
require_once get_template_directory() . '/inc/bootstrap/rewrite/berita.php';

// Pretty permalinks for the program_studi CPT.
require_once get_template_directory() . '/inc/bootstrap/rewrite/program-studi.php';

// Pretty permalinks for the info_publik CPT.
require_once get_template_directory() . '/inc/bootstrap/rewrite/info-publik.php';

// Front-end styles/scripts.
require_once get_template_directory() . '/inc/bootstrap/enqueue.php';

/**
 * Load the data-driven widget classes (TOC, Popular Posts).
 *
 * Required here — not autoloaded — because the widget classes extend
 * WP_Widget, which is only fully available after WordPress core widgets
 * have been registered.
 */
require_once get_template_directory() . '/inc/theme/widgets.php';

/* ── LP2M integration ──────────────────────────────────────────────────────── */

/**
 * REST API enhancement untuk CPT Hibah LP2M.
 *
 * Register custom fields (timeline_items, file_panduan, file_template, etc.)
 * so the Vue frontend at lp2m.itsi.ac.id can fetch event data directly
 * from /wp-json/wp/v2/hibah.
 */
require_once get_template_directory() . '/inc/lp2m/rest-api-hibah.php';
require_once get_template_directory() . '/inc/lp2m/settings.php';
require_once get_template_directory() . '/inc/lp2m/pendaftaran.php';
require_once get_template_directory() . '/inc/lp2m/smtp.php';

/**
 * TypeRocket jQuery 3.x compatibility shim (admin).
 *
 * Mutes jquery-migrate warnings and re-implements deprecated static APIs
 * (`$.isFunction`, `$.type`, `$.trim`, `$.parseJSON`, `$.now`) so the
 * TypeRocket page builder loads cleanly under WP 6.9.1 — without touching
 * the shared plugin itself.
 */
require_once get_template_directory() . '/inc/integrations/typerocket/compat.php';

/**
 * LP2M CORS — izinkan akses lintas-origin dari SPA LP2M.
 *
 * SPA LP2M (lp2m.itsi.ac.id / lp2m-102.pages.dev)
 * memanggil REST API itsi.ac.id secara langsung lintas-origin. WordPress core
 * hanya mengirim header CORS untuk origin same-site, jadi tanpa filter ini
 * semua request /wp-json dari domain LP2M diblokir browser.
 */
require_once get_template_directory() . '/inc/lp2m/cors.php';

/**
 * LP2M Auth — fallback autentikasi REST dengan password akun.
 *
 * WordPress core hanya menerima Application Password via Basic Auth; filter
 * ini (rest_authentication_errors) menambahkan dukungan username + password
 * akun biasa agar SPA LP2M bisa login & mengakses /wp-json langsung dengan
 * password akun. Application password lama tetap valid (diproses core dulu).
 */
require_once get_template_directory() . '/inc/lp2m/auth.php';

/**
 * LP2M User Password — ganti password akun via REST (POST /lp2m/v1/me/password).
 *
 * Dipakai dashboard LP2M (Profile) untuk mengganti password akun pengguna.
 */
require_once get_template_directory() . '/inc/lp2m/class-user-password.php';

/**
 * LP2M Hibah Receiver — form submission, sanitization, REST endpoints.
 *
 * Handles POST/GET /lp2m/v1/hibah with:
 *   - Strict input sanitization (whitelist, regex, HTML strip)
 *   - Rate limiting (5 per 15 min per IP)
 *   - CPT pendaftaran_hibah + post meta storage
 *   - hibah_id foreign-key linking to CPT hibah
 *   - Dynamic form builder (custom fields per event)
 */
require_once get_template_directory() . '/inc/lp2m/class-hibah-receiver.php';
require_once get_template_directory() . '/inc/lp2m/pdf/class-lp2m-pdf.php';

/* ── Content helpers ───────────────────────────────────────────────────────── */

// Footer layout definition + accessors.
require_once get_template_directory() . '/inc/content/footer-layout.php';

// Shared rendering helpers for the info_publik CPT.
require_once get_template_directory() . '/inc/content/info-publik.php';

/* ── Widget areas & front-end data ─────────────────────────────────────────── */

// Widget areas (sidebars) registration, then the data-driven widget classes.
require_once get_template_directory() . '/inc/theme/widget-areas.php';

// Ships the admin-ajax URL + nonce to the front-end script.
require_once get_template_directory() . '/inc/bootstrap/ajax-localize.php';

/* ── Content: CPTs, forms, views, helpers ──────────────────────────────────── */

// Custom Post Types, taxonomies & meta boxes (TypeRocket).
require_once get_template_directory() . '/inc/content/post-types.php';

// AJAX handler for the public "Permohonan" form.
require_once get_template_directory() . '/inc/forms/permohonan.php';

// Post view counter + "no cache" headers.
require_once get_template_directory() . '/inc/content/view-counter.php';

// General-purpose helpers (logo URL, repeaters, latest pengumuman).
require_once get_template_directory() . '/inc/content/helpers.php';

/* ── Admin ─────────────────────────────────────────────────────────────────── */

// Brand colors CSS variables + Clarity / AdSense snippets.
require_once get_template_directory() . '/inc/admin/analytics.php';

/**
 * Load the ITSI admin sidebar menu (top-level "ITSI" + placeholder settings page).
 *
 * Separate file keeps this loader from ballooning as we migrate customizer
 * sections into admin pages. Required here — not autoloaded — so it runs in the
 * admin context only when wp-admin/admin.php loads.
 */
require_once get_template_directory() . '/inc/admin/admin-menu.php';

/*
 * Load schema.org JSON-LD emitter (EducationalOrganization + Article/WebPage/Course).
 * Hooked to wp_head at priority 20 (after Clarity at 1, after Clarity inline at 99).
 */
require_once get_template_directory() . '/inc/theme/schema.php';

// Taxonomy term image pickers (fakultas + kategori_info).
require_once get_template_directory() . '/inc/admin/term-icons.php';

// One-shot seeders (archive-berita widgets + schema theme_mods).
require_once get_template_directory() . '/inc/admin/seed-autopopulate.php';

// Bootstrap Icons map for the hero icon picker.
require_once get_template_directory() . '/inc/content/icons-map.php';

/* ── Theme extras ──────────────────────────────────────────────────────────── */

/**
 * Load custom nav-menu walkers so wp_nav_menu() output matches the theme's
 * CSS selectors (.nli, .nl-a, .dd, .dd-a for navbar; .mob-a for mobile).
 */
require_once get_template_directory() . '/inc/theme/menu-walker.php';
