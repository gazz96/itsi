# `inc/tools/seeders/` — 🔒 LOCKED

One-shot data seeders. **They are kept for reference but cannot be run.**

```
seeders/
├── seed-info-publik.php    Populates the info_publik CPT (9 categories, 22 docs)
└── seed-sdgs.php           Populates the sdgs taxonomy (17 SDG terms)
```

## Why they are locked

These files sit inside the theme, and the theme directory is web-served. A
request to

```
https://itsi.ac.id/wp-content/themes/itsi/inc/tools/seeders/seed-sdgs.php
```

was reaching PHP and executing the file. The only thing preventing real damage
was the in-file `ABSPATH` guard. Locking them removes that exposure entirely.

## How the lock works

Both files abort immediately unless **all three** conditions hold:

| Condition | Blocks |
|---|---|
| `ITSI_ENABLE_SEEDERS` is `true` | Accidental / unattended runs |
| `PHP_SAPI === 'cli'` | Any browser or web-server request |
| `WP_CLI` is `true` | Any non-CLI runner |

When blocked over HTTP the response is now `403 Forbidden`.

## Running one again

1. Add to `wp-config.php`:
   ```php
   define( 'ITSI_ENABLE_SEEDERS', true );
   ```
2. Run it from the project root:
   ```bash
   wp eval-file web/app/themes/itsi/inc/tools/seeders/seed-sdgs.php
   ```
3. **Remove the constant again.**
4. The `--force` flags documented inside each file still behave as before.

> The constant must never be enabled on a production site.

## Re-enabling permanently

Not recommended. If you ever do want these runnable again, the cleanest option
is to move them outside the theme's web root — e.g. into a plugin whose
directory is not web-served — rather than deleting the gate.
