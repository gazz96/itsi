# `inc/` — Theme Includes

This folder holds all PHP that `functions.php` pulls in via `require_once`.
It is split into two concern-based groups so live features are easy to find.

```
inc/
├── setup/              WordPress theme bootstrap & site-wide features
│   ├── admin-menu.php          Admin "ITSI" menu (topbar/brand/footer settings)
│   ├── menu-walker.php         Custom Walker_Nav_Menu subclasses (navbar markup)
│   ├── widgets.php             ITSI_TOC_Widget, ITSI_Popular_Widget
│   ├── schema.php              schema.org JSON-LD emitter
│   └── typerocket-compat.php   jQuery 3.x shim for the TypeRocket admin builder
│
└── lp2m/               LP2M integration (itsi.ac.id ↔ LP2M SPA)
    ├── auth.php                REST auth fallback (username + password)
    ├── cors.php                CORS allowlist for the LP2M SPA origins
    ├── smtp.php                Global PHPMailer/SMTP config
    ├── settings.php            LP2M Settings admin page + REST
    ├── pendaftaran.php         LP2M pendaftaran REST API + email
    ├── rest-api-hibah.php      REST fields for the `hibah` CPT
    ├── class-hibah-receiver.php   Hibah form submit/validate/store + emails
    ├── class-lp2m-pdf.php         PDF generator (FPDF) for submissions
    ├── class-user-password.php    REST endpoint to change account password
    └── fpdf/                    FPDF library (MIT). Kept beside the PDF class
                                 because `class-lp2m-pdf.php` loads it via
                                 `require_once __DIR__ . '/fpdf/fpdf.php'`.
```

## Where files are loaded

All of the above are required from the theme's root **`functions.php`** using
`get_template_directory() . '/inc/...'`. To find a specific include, search
`functions.php` for the file name.

## Not here

Files that are **never loaded** (dead Underscores boilerplate + the unused
Theme Builder feature) were moved to `_backups/_unused/` — see that folder's
contents if you ever need to revive them. They are **not** part of the live
theme.

## Editing notes

- Keep `fpdf/` a sibling of `class-lp2m-pdf.php` (relative `__DIR__` include).
- When adding a new include, add its `require_once` line to `functions.php`
  under the matching group's comment block, and update this README.
