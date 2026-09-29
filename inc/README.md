# `inc/` — Theme Includes

Every PHP file that `functions.php` pulls in with `require_once`, grouped by
concern so a feature is easy to locate. `functions.php` itself is a ~190-line
loader — no logic lives there.

For the full theme tree (assets, templates, backups, load order) see
[`../STRUCTURE.md`](../STRUCTURE.md).

## Layout

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
│   ├── info-publik.php            info_publik rendering helpers
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

## Where files are loaded

All files above are required from `functions.php` with
`get_template_directory() . '/inc/…'`. Search `functions.php` for the file name
to find its exact position; the include order is deliberate (see
[`../STRUCTURE.md`](../STRUCTURE.md) §3) and preserves the original hook
registration order.

`inc/tools/seeders/` is the one exception — nothing requires it, and it is
**locked**. Those files can no longer be run through WP-CLI, `require`, or a
browser request; they abort with `403`/an error message. See
[`tools/seeders/README.md`](tools/seeders/README.md) for the unlock procedure.

## Editing notes

- **Keep `pdf/fpdf/` a sibling of `pdf/class-lp2m-pdf.php`** — the class loads
  the library with a relative `__DIR__ . '/fpdf/fpdf.php'`.
- Every file starts with `<?php`, a `@package itsi` docblock, then an
  `ABSPATH` guard.
- Prefix globals: `itsi_*` for the theme, `lp2m_*` / `ITSI_LP2M_*` for the LP2M
  integration.
- Include with absolute `get_template_directory()` paths — never `__DIR__`
  outside `pdf/`.
- When adding a file: add its `require_once` under the matching comment block in
  `functions.php`, then update this README and `../STRUCTURE.md`.

## Not here

Dead Underscores scaffolding and the unused Theme Builder feature live in
`_backups/_unused/` (gitignored, never loaded). See
[`../_backups/README.md`](../_backups/README.md).
