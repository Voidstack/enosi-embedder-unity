# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Plugin Does

ENOSI Embedder For Unity is a WordPress plugin that lets admins upload Unity WebGL builds (as `.zip` files) and embed them in posts/pages via a Gutenberg block or shortcode. Builds are stored in `/wp-content/uploads/unity_webgl/{buildname}/`.

## Development Setup

No build step required. PHP and JavaScript are served as-is — no npm, webpack, or compilation involved.

To develop locally, you need a WordPress environment (tested on WP 6.8+, PHP 7.4+). The plugin is loaded directly from `wp-content/plugins/enosi-embedder-unity/`.

To create a distributable ZIP: manually zip the plugin folder excluding `.git/` and `*.zip` files (see `.gitignore`).

Translations are in `languages/`. To update them, use `wp i18n make-pot` and then `msgfmt` to compile `.po` → `.mo`.

## Architecture

### Entry Point

[enosi-embedder-unity.php](enosi-embedder-unity.php) bootstraps everything:
- Enqueues `enosi-main.css` and `client-unity-block.js` (as a JS module via `wp_enqueue_script_module`)
- Registers the `[unity_webgl]` shortcode
- Conditionally loads admin PHP files only in `is_admin()` context

### PHP Layer

| File | Role |
|------|------|
| [php/enosi-admin-page.php](php/enosi-admin-page.php) | Admin menu page, ZIP upload/download handler, build management table, WASM MIME config |
| [php/enosi-unity-block.php](php/enosi-unity-block.php) | Gutenberg block registration (`wpunity/unity-webgl`), passes build list to editor JS |
| [php/enosi-build-extractor.php](php/enosi-build-extractor.php) | ZIP extraction, file validation, lowercasing, moving to uploads |
| [php/enosi-filesystem-singleton.php](php/enosi-filesystem-singleton.php) | Singleton wrapping WP filesystem API; handles case-only renames on Windows/Mac |
| [php/enosi-utils.php](php/enosi-utils.php) | Utilities: build listing, folder ops, server detection, `.htaccess` WASM MIME setup, error display helpers |

### JavaScript Layer

All JS is vanilla (no frameworks, no bundler):

| File | Role |
|------|------|
| [js/client-unity-block.js](js/client-unity-block.js) | `UnityInstanceManager` — finds canvas elements, loads Unity loader script, calls `createUnityInstance()` |
| [js/client-unity-loader.js](js/client-unity-loader.js) | `UnityLoader` — animated loading screen with progress bar |
| [js/client-unity-toolbar.js](js/client-unity-toolbar.js) | `UnityToolbar` — optional overlay: FPS counter, screenshot, reload, fullscreen |
| [js/editor-unity-block.js](js/editor-unity-block.js) | Gutenberg block registration (`registerBlockType`) with block attributes and editor UI |

### Data Flow

1. Admin uploads a `.zip` → `EnosiBuildExtractor` validates, lowercases filenames, and moves to `uploads/unity_webgl/{name}/`
2. Editor inserts the Gutenberg block → `editor-unity-block.js` reads available builds from localized JS (`enosiUnityBuilds`)
3. Block is saved as a `[unity_webgl build="name" ...]` shortcode
4. Frontend renders a `<div>` + `<canvas>` with `data-*` attributes → `client-unity-block.js` picks it up via `canvas.unity-canvas`, loads the Unity `.loader.js`, and calls `createUnityInstance()`

### Shortcode Attributes

`[unity_webgl build="" showoptions="" showonmobile="" showlogs="" sizemode="" fixedheight="" aspectratio=""]`

- `sizemode`: `"aspect-ratio"` or `"fixed-height"`
- `showoptions`: shows the debug toolbar
- `showonmobile`: if false, hides the block on mobile

### Key Conventions

- All uploaded build files are lowercased on extraction (handles Unity's inconsistent casing)
- The filesystem singleton's custom `rename()` handles case-only renames correctly on case-insensitive filesystems (Windows, macOS)
- WASM MIME type (`application/wasm`) is added to `.htaccess` for Apache servers; Nginx users are shown manual instructions
- JS is loaded as ES modules (`wp_enqueue_script_module`) — requires WordPress 6.5+
- Block type is `wpunity/unity-webgl` in PHP but registered as `mon-plugin/unity-webgl` in JS (legacy inconsistency)

### Passing Data to the JS Module

Data is passed to `client-unity-block.js` exclusively via `data-*` attributes on the `<canvas>` element — **not** via `wp_localize_script` (which is incompatible with ES modules). All values read from `this.unityCanvas.dataset` in `UnityInstanceManager`.

### Unity Log Suppression

When `showlogs="false"`, Unity's own logs are suppressed via the `print` and `printErr` callbacks in the `createUnityInstance()` config — **not** by overriding `console.log` globally.

### Admin Build Download

Build download uses the `admin_post_download_unity_build` hook (`enosiDownloadUnityBuild` function). It creates a temporary ZIP of the build folder and serves it as a file download. The button uses `wp_nonce_url()` pointing to `admin-post.php`.

### Error Display in Admin

`EnosiUtils::error()` accepts HTML (sanitized via `wp_kses_post()`). `EnosiUtils::errorGifsHtml()` generates the two-GIF help block (both `res/unity_format.gif` and `res/build_name.gif` side by side) appended to upload errors. Guide GIFs live in `res/`.
