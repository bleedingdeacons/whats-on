# What's On

A Divi 5 module that shows upcoming events from **The Events Calendar** as a carousel.
Each card shows the event's featured image, title, and date and time, and the whole card
links to the event. Previous / next buttons move the carousel one event at a time. It also
scrolls by swipe or trackpad, and with the arrow keys when it has focus.

Requires **Divi 5** (developed against 5.19.0) and **The Events Calendar** (6.x). PHP 8.1+.

## Module settings

| Panel | Group | Setting |
|---|---|---|
| Content | Events | Number of events, event category slugs (comma-separated), featured only, date format (blank = site format), show time, "no events" text |
| Content | Carousel | Events per view (desktop / tablet / phone), loop, show previous / next buttons |
| Design | Event Card | Background, border, box shadow |
| Design | Image | Border, filters |
| Design | Title Text / Date Text | Full Divi font controls (title heading level defaults to H3) |
| Design | Previous / Next Buttons | Arrow colour, background, border, box shadow |
| Advanced | | The usual Divi options, plus Custom CSS for track, card, image, title, date and buttons |

Only events that have not ended are shown, soonest first. Past events never appear.

Date line formats:

- Same day: `Fri 20 Nov 2026 · 7:00 pm – 9:30 pm`
- All day: `Fri 20 Nov 2026 · All day`
- Multi-day: `Sat 21 Nov 2026 7:00 pm – Mon 23 Nov 2026 4:00 pm`

Filters: `whats_on/query_args` (arguments passed to `tribe_get_events()`) and
`whats_on/image_size` (default `large`). Kill switch: `define( 'WHATS_ON_KILL', true );`
in `wp-config.php`.

## Layout

```
whats-on.php                     plugin header + bootstrap
modules-json/events-carousel/    module.json: Divi 5 metadata, attributes and settings panels
src/                             PHP (namespace WhatsOn\)
  Plugin.php                     hooks
  Assets.php                     front-end + Visual Builder asset registration
  Divi/EventsCarouselModule.php  Divi render callback, classnames, styles
  Events/                        query options, TEC repository, date formatting
  Render/                        carousel markup (no Divi/WP dependency, unit-tested)
  Rest/EventsController.php      GET /wp-json/whats-on/v1/events (builder preview data)
assets/src/frontend/             carousel behaviour + CSS (shared with the builder)
assets/src/builder/              Visual Builder module (React edit renderer)
assets/build/                    compiled output (committed so the plugin runs without a build)
tests/php/                       Pest + Brain Monkey
tests/js/                        Vitest + jsdom
bin/dev.ps1                      Local site helper (link, seed, WP-CLI, Composer)
bin/seed.php                     sample events + demo page
```

How the Divi 5 parts fit together:

- **PHP** registers the module on `divi_module_library_modules_dependency_tree` with
  `ModuleRegistration::register_module()` and renders it with `Module::render()`.
- **The builder bundle** is registered on `divi_visual_builder_assets_before_enqueue_scripts`
  through `PackageBuildManager`, and registers the module on the
  `divi.moduleLibrary.registerModuleLibraryStore.after` hook. Its settings panels are
  generated from `module.json` (`"settings": { "content": "auto", ... }`), so the JS only
  supplies the edit renderer and styles.
- `react` and `@divi/*` imports are mapped to Divi's globals (`window.vendor.React`,
  `window.divi.module`, …) by `build.mjs`. Nothing from Divi is bundled.
- The builder preview fetches the same events through the REST endpoint (editors only),
  and uses the same carousel script as the front end.

## Development

The dev environment is the **`aa-bristol-future`** site in [Local](https://localwp.com),
which has Divi 5 and The Events Calendar. Start it in Local first. To use a different
Local site, pass `-Site <name>` or set `WHATS_ON_SITE`.

```bash
npm run dev:setup
```

That command installs the JS dependencies, builds the assets, and junctions
`wp-content/plugins/whats-on` to this checkout. It then activates the plugin and seeds the
site. After that, edits here are live on the site.

`bin/dev.ps1` reads the site's PHP, `php.ini` and MySQL port from Local's own config and uses
Local's bundled WP-CLI and Composer, so nothing else needs installing. Run it with `pwsh`
(PowerShell 7). Windows PowerShell 5 blocks unsigned scripts by default.

| Command | Does |
|---|---|
| `pwsh bin/dev.ps1 info` | Resolved paths and URLs |
| `pwsh bin/dev.ps1 seed` | 9 sample events (one past, one all-day, one multi-day, one without an image, two featured, three categories) and a demo page at `/whats-on-demo/`. Re-running replaces them |
| `pwsh bin/dev.ps1 reset` | Removes the sample data only. Everything seeded is tagged `_whats_on_sample` |
| `pwsh bin/dev.ps1 wp …` | Any WP-CLI command against the site |
| `pwsh bin/dev.ps1 composer …` | Local's Composer, run in this checkout |
| `pwsh bin/dev.ps1 unlink` | Removes the junction (with `rmdir`, never a recursive delete) |

Day to day:

```bash
npm run watch
```

```bash
npm test
```

```bash
pwsh bin/dev.ps1 composer test
```

The first time, install the PHP test dependencies with `pwsh bin/dev.ps1 composer install`.

- Front end: http://aa-bristol-future.local/whats-on-demo/
- Builder: http://aa-bristol-future.local/whats-on-demo/?et_fb=1

## Release

```bash
npm run package
```

This makes a production build and writes `dist/whats-on-<version>.zip`. The version comes
from the `Version:` header in `whats-on.php`. Keep `readme.txt`'s `Stable tag` and
`WHATS_ON_VERSION` in step with it.
