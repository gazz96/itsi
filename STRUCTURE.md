# ITSI Theme — Structure Map

WordPress classic theme for **itsi.ac.id** (Bedrock layout).
This file is the map of where everything lives. `functions.php` is a thin
loader; real code lives in `inc/` and `assets/`.

---

## 1. Full tree

```
itsi/
├── functions.php                 Thin loader: 30 require_once lines + structure map
├── STRUCTURE.md                  This file
├── style.css                     Design system (must stay in root — WP header)
├── screenshot.png                Theme thumbnail (root only)
├── LICENSE
│
├── ── WordPress templates (must stay in root) ──────────────────────────────
├── index.php                     Fallback template
├── header.php  footer.php  sidebar.php  comments.php
├── page.php  single.php  archive.php  search.php  404.php
├── archive-berita.php            /berita + post archive (locate_template)
├── archive-info_publik.php       CPT info_publik archive
├── archive-program_studi.php     CPT program_studi archive
├── single-program_studi.php      CPT program_studi single
├── template-home-static.php      Page template (depth-1 rule — root only)
│
├── assets/
│   ├── css/
│   │   ├── program-studi.css
│   │   └── artikel-detail.css
│   ├── js/
│   │   ├── itsi-main.js          Front-end bundle
│   │   └── typerocket-compat.js  Admin shim (paired with inc/integrations/)
│   ├── img/
│   │   └── logo.svg              Footer/schema logo (itsi_get_logo_url)
│   └── mitra/                    12 partner SVGs (currently unreferenced)
│
├── languages/
│   └── itsi.pot                  Translation template (load_theme_textdomain)
│
├── inc/                          See §2
│
└── _backups/                     Local safety copies — .gitignore'd, never loaded
    ├── README.md
    ├── functions.php.bak.2026*   Pre-split snapshot
    ├── pre-reorg-20260916_235914/  Frozen snapshot of the pre-reorg layout
    └── _unused/
        ├── underscores-boilerplate/  Dead _s scaffolding (readme.txt, package.json…)
        ├── wordpress-boilerplate/    template-parts/ (theme renders inline)
        └── inc/ + js/ + assets/css/  Theme Builder + unused scripts
```

### Why the root templates cannot move

WordPress resolves hierarchy templates **only** from the theme root, and
`template-home-static.php` additionally has to be depth 1 because
`WP_Theme::get_post_templates()` scans at depth 1. Likewise `style.css`
carries the theme header. Everything else is free to organize.

---

## 2. `inc/` — concern-based groups

```
inc/
├── bootstrap/                     Wiring that runs on every request
│   ├── theme-support.php          add_theme_support, menus, image sizes, i18n
│   ├── enqueue.php                Front-end CSS/JS
│   ├── ajax-localize.php          admin-ajax URL + nonce → JS
│   └── rewrite/
│       ├── berita.php             /berita/<slug>/ routing + canonical fixes
│       ├── program-studi.php      /program-studi/<slug>/ routing
│       └── info-publik.php        /info-publik/<slug>/ routing
│
├── content/                       Site content features
│   ├── post-types.php             CPTs, taxonomies, meta boxes (TypeRocket) ⚠
│   ├── footer-layout.php          Footer layout definition + accessors
│   ├── info-publik.php            info_publik rendering helpers (8 functions)
│   ├── view-counter.php           Post view counter + no-cache headers
│   ├── helpers.php                Logo URL, repeaters, latest pengumuman
│   └── icons-map.php              Bootstrap Icons map for the hero picker
│
├── admin/                         wp-admin only
│   ├── admin-menu.php             Top-level "ITSI" menu + settings screens
│   ├── analytics.php              Brand colors CSS vars, Clarity, AdSense
│   ├── term-icons.php             Term image pickers (fakultas, kategori_info)
│   └── seed-autopopulate.php      One-shot seeders (theme_mods)
│
├── forms/
│   └── permohonan.php             Public "Permohonan" AJAX handler
│
├── theme/                         Theme-level extras
│   ├── widgets.php                ITSI_TOC_Widget, ITSI_Popular_Widget, …
│   ├── widget-areas.php           8 widget areas + widget registration
│   ├── menu-walker.php            Walker_Nav_Menu subclasses (navbar/mobile)
│   └── schema.php                 schema.org JSON-LD emitter (wp_head:20)
│
├── integrations/
│   └── typerocket/
│       └── compat.php             jQuery 3.x shim for the TypeRocket builder
│
├── lp2m/                          LP2M integration (itsi.ac.id ↔ LP2M SPA)
│   ├── rest-api-hibah.php         REST fields for the `hibah` CPT
│   ├── settings.php               LP2M Settings admin page + REST
│   ├── pendaftaran.php            Pendaftaran REST API + email
│   ├── smtp.php                   PHPMailer/SMTP config
│   ├── cors.php                   CORS allowlist for LP2M origins
│   ├── auth.php                   REST auth fallback (username + password)
│   ├── class-user-password.php    REST: change account password
│   ├── class-hibah-receiver.php   Hibah submit/validate/store + emails
│   └── pdf/
│       ├── class-lp2m-pdf.php     PDF generator wrapper
│       └── fpdf/                  FPDF library (MIT)
│
└── tools/
    └── seeders/                   🔒 LOCKED — see README.md in that folder
        ├── README.md              Why + how to unlock
        ├── seed-info-publik.php   Blocked (CLI + HTTP)
        └── seed-sdgs.php          Blocked (CLI + HTTP)
```

### ⚠ `inc/lp2m/pdf/` — do not separate

`class-lp2m-pdf.php` loads the library with a **relative** path:

```php
require_once __DIR__ . '/fpdf/fpdf.php';
```

`fpdf/` must therefore stay a sibling of the class. For the same reason
`fpdf/fpdf.php` resolves its fonts via `dirname(__FILE__) . '/font/'`.

`inc/tools/seeders/` is also deliberately outside the load chain: those files
are never `require_once`'d. They are additionally **locked** — they refuse to
run via WP-CLI, `require`, or a direct browser request. See
[`inc/tools/seeders/README.md`](inc/tools/seeders/README.md).

---

## 3. Load order (`functions.php`)

Includes are emitted in the **same order their code used to appear** in the
original 2 537-line `functions.php`, so hook registration order is unchanged:

| # | Include |
|---|---|
| 1 | `bootstrap/theme-support.php` |
| 2–4 | `bootstrap/rewrite/{berita,program-studi,info-publik}.php` |
| 5 | `bootstrap/enqueue.php` |
| 6 | `theme/widgets.php` |
| 7–10 | `lp2m/{rest-api-hibah,settings,pendaftaran,smtp}.php` |
| 11 | `integrations/typerocket/compat.php` |
| 12 | `lp2m/cors.php` |
| 13 | `lp2m/auth.php` |
| 14 | `lp2m/class-user-password.php` |
| 15–16 | `lp2m/class-hibah-receiver.php`, `lp2m/pdf/class-lp2m-pdf.php` |
| 17 | `content/footer-layout.php` |
| 18 | `content/info-publik.php` |
| 19 | `theme/widget-areas.php` |
| 20 | `bootstrap/ajax-localize.php` |
| 21 | `content/post-types.php` |
| 22 | `forms/permohonan.php` |
| 23 | `content/view-counter.php` |
| 24 | `content/helpers.php` |
| 25 | `admin/analytics.php` |
| 26 | `admin/admin-menu.php` |
| 27 | `theme/schema.php` |
| 28 | `admin/term-icons.php` |
| 29 | `admin/seed-autopopulate.php` |
| 30 | `content/icons-map.php` |
| 31 | `theme/menu-walker.php` |

**When adding a file:** add your `require_once` at the point where its side
effects should run, then update this table and `inc/README.md`.

---

## 4. Conventions

- Every file under `inc/` starts with `<?php`, a `@package itsi` docblock, then
  the standard guard:
  ```php
  if ( ! defined( 'ABSPATH' ) ) {
      exit;
  }
  ```
- Function/class names are globally prefixed: `itsi_*` (theme) or `lp2m_*` /
  `ITSI_LP2M_*` (LP2M integration).
- Includes always use **absolute** paths via `get_template_directory()`, never
  `__DIR__` — except inside `inc/lp2m/pdf/`, where the relative FPDF include is
  load-bearing.
- No `get_template_part()`, `get_sidebar()` or `comments_template()` calls: the
  theme renders markup inline, so there is no live `template-parts/` folder.

---

## 5. Archived / unused code

Moved to `_backups/` (gitignored, **never loaded**):

| Item | Why |
|---|---|
| `_unused/underscores-boilerplate/` | Underscores scaffolding: `readme.txt`, `package.json`, `composer.json`, `.eslintrc`, `.stylelintrc.json`, `phpcs.xml.dist`, `style-rtl.css`, `languages/itsi.pot`, `languages/readme.txt`, `README.md` |
| `_unused/wordpress-boilerplate/template-parts/` | 4 default partials, all unreferenced |
| `_unused/inc/theme-builder.php` + `class-theme-builder-*.php` | Theme Builder feature, never enqueued |
| `_unused/js/` + `_unused/assets/css/theme-builder.css` | Theme Builder assets |
| `_unused/js/{navigation,customizer,navbar-hover}.js` | Never enqueued |

Locked but **not** archived: `inc/tools/seeders/` (see §2).

`languages/itsi.pot` was archived because it is still the stock Underscores
catalog. Regenerate a real one with:

```bash
wp i18n make-pot . languages/itsi.pot
```

---

## 6. Performance notes (known, not yet addressed)

- `inc/content/helpers.php` → `itsi_get_latest_pengumuman()` runs a fresh
  `WP_Query` on every page render.
- `itsi_ip_get_*` and `itsi_schema_*` helpers call `get_theme_mod()` /
  `get_option()` repeatedly without memoization.
- `inc/content/post-types.php` (618 lines) is the largest single include and
  registers all CPTs plus the TypeRocket meta-box graph; split it only if you
  are prepared to re-verify metabox ordering.
- `assets/mitra/` (12 SVGs) has zero references — safe to delete if unused.
- `style.css:235` still mentions the archived `assets/js/navbar-hover.js`.

---

## 7. Validation

There is no test suite or CI. The checks used for every change:

```bash
# Syntax-check every PHP file
find . -name '*.php' -not -path './_backups/*' -print0 | xargs -0 -n1 php -l

# Confirm no stale paths remain
grep -rn "inc/setup\|inc/lp2m/fpdf\|'/js/" --include=*.php --include=*.js .
```
