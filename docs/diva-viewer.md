# diva.js viewer — status and integration notes

The `<twig:iiif:viewer viewer="diva" />` component wraps the [diva.js](https://github.com/DDMAL/diva.js)
page-turning document viewer (good for "wrapper of images" cases — manuscripts,
pension files, multi-page scans). The OpenSeadragon single-image viewer
(`viewer="openseadragon"`) is the other mode.

## Current status: diva is DISABLED; folio uses OpenSeadragon instead

As of 2026-06, folio-bundle row pages render the **OpenSeadragon** viewer in its
plain-image / paged mode (`<twig:iiif:viewer :images="…">`) — it pages through
imgproxy-sized page JPGs we control, needs no IIIF Image API tile server, and
rides on a healthy npm package (`openseadragon` 6.x). See the "Plain images" mode
in `assets/controllers/iiif-viewer_controller.js`.

The diva viewer is **not used** until diva.js v7 is published to npm (it remains
in the bundle as `viewer="diva"` for when that happens).

**Root cause:** npm `diva.js` latest is `6.0.2`, published **2019-08-27**. v6 is
a 7-year-old webpack/UMD build with no ES module exports and an IIIF
Presentation **2.x**-only parser. v7.0.0–v7.2.2 are tagged on GitHub but were
**never published to npm**, so an import map (Symfony AssetMapper, which resolves
from npm/jsDelivr) can only get v6.

Tracking issue (asks DDMAL to publish v7 / provide a CDN-consumable ESM build):
**https://github.com/DDMAL/diva.js/issues/555**

## The five layered failures (why it looks "broken but registered")

The viewer rendered nothing while *appearing* wired up. Each fix exposed the
next; the bottom one is fundamental and can't be worked around locally.

1. **Controller never registered.** Two causes (now fixed, see kit-bundle README
   "UX controller never loads"): a stale precompiled `public/assets/` shadowed
   dev asset compilation, and `assets/package.json` lacked a per-controller
   `name`, so the Stimulus id was wrong.
2. **`import Diva from 'diva.js'`** → `does not provide an export named 'default'`.
3. **`import { Diva } from 'diva.js'`** → no named export either. v6 only assigns
   `window.Diva` as a side effect (`!function(e){e.Diva=e.Diva||fe}(window)`),
   so the import must be `import 'diva.js'` + read `window.Diva`.
4. **Stimulus `options` value crash:** the component passed `options: options ?? {}`,
   but an empty Twig hash `{}` serializes to JSON `[]`, and Stimulus rejects an
   array for an Object-typed value (`expected value of type "object" but instead
   got value "[]"`). Fixed by only emitting the value when non-empty — this also
   unbroke the OpenSeadragon branch, which had the same bug.
5. **v6 constructor quirks + IIIF version mismatch:**
   - `new Diva(element, settings)` leaves `this.element` undefined because the
     constructor only sets it in the `typeof === 'string'` (element-id) branch
     (`!(e instanceof HTMLElement) && (this.element = getElementById(e), ...)`),
     so it must be called with the element **id**, not the element.
   - Even then, v6's `fromIIIF` only understands IIIF Presentation 2
     (`sequences → canvases → images → resource`). Our manifests are
     Presentation 3 (`items`), which it can't parse.

## Re-enabling when v7 ships on npm

1. Bump the diva.js version in `assets/package.json` (`symfony.importmap`) and run
   `importmap:require diva.js@^7` (or the app equivalent).
2. In `assets/controllers/diva_viewer_controller.js`, switch the import from the
   v6 side-effect form (`import 'diva.js'` + `window.Diva`) to v7's real ESM
   export — most likely `import { Diva } from 'diva.js'` (verify against the
   published v7 package).
3. Drop the v6 element-id workaround if v7 accepts an `HTMLElement` directly.
4. Flip `divaEnabled` back to `true` in folio-bundle's `templates/folio/row.html.twig`.

The manifest endpoint (`FolioController::iiifManifest`) already emits canonical
IIIF Presentation 3, which is what a v3-capable diva 7 expects — no change needed
there.
