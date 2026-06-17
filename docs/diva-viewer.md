# diva.js viewer — integration notes

The `<twig:iiif:viewer viewer="diva" />` component wraps the [diva.js](https://github.com/DDMAL/diva.js)
page-turning document viewer (good for "wrapper of images" cases — manuscripts,
pension files, multi-page scans). The OpenSeadragon single-image viewer
(`viewer="openseadragon"`) is the other mode.

## Status: enabled, diva.js 7.2.6

diva.js **7.2.6** is published to npm (2026-06) and is what we use. v7 is a
ground-up rewrite: a document-scrolling interface built **on top of
OpenSeadragon**, shipping a real ESM build and parsing IIIF Presentation **2.x and
3.x**. Our canonical Presentation 3 manifests (`FolioController::iiifManifest`)
render directly — the v6 blockers are gone.

> Historical note: npm `diva.js` was stuck at `6.0.2` (published 2019) for years —
> a webpack/UMD build with no ES exports and a Presentation-2-only parser, so an
> AssetMapper importmap couldn't get a usable build. That's why this viewer was
> disabled. DDMAL published v7 to npm (tracking issue
> [DDMAL/diva.js#555](https://github.com/DDMAL/diva.js/issues/555)), which unblocked it.

## OpenSeadragon is a peer dependency (already satisfied)

diva 7 renders through OpenSeadragon but does **not bundle** it. The getting-started
docs include OSD via a separate `<script>` tag; at viewer-init time diva reads the
`window.OpenSeadragon` global (`syncViewer()` bails if it's absent).

In our AssetMapper setup there is no script tag — `openseadragon` is already in the
importmap (`assets/package.json`, `^6.0.1`; the OSD single-image controller uses it).
The npm OSD UMD, resolved through the importmap, assigns to `module.exports` and
skips its global branch, so `window.OpenSeadragon` is **not** set automatically.
`diva_viewer_controller.js` therefore does:

```js
import OpenSeadragon from 'openseadragon';
window.OpenSeadragon ||= OpenSeadragon;
```

before constructing the viewer. No extra dependency — we already require OSD.

## Construction

v7 constructor is `new Diva(rootElementId, settings)` — the **first arg is the
element id string** (it does `getElementById` internally and throws on a missing
element), not the element itself. The controller assigns a stable id to its element
before constructing.

Documented settings:

| setting        | type       | default  | purpose                                   |
| -------------- | ---------- | -------- | ----------------------------------------- |
| `objectData`   | string     | required | IIIF manifest or collection URL           |
| `acceptHeaders`| string[]   | `[]`     | extra `Accept` headers for fetches        |
| `showSidebar`  | boolean    | `true`   | sidebar visibility                        |
| `showTitle`    | boolean    | `true`   | show the manifest label as a title        |
| `setLanguage`  | string     | (auto)   | UI language override (BCP-47 subtag)       |

Pass overrides through the component's `options` object. (The old v6 settings
`enableAutoTitle` / `enableFullscreen` no longer exist.)

CSS and image assets are bundled into the built library — no separate stylesheet
include is needed (the v6 jsDelivr `diva.min.css` hack is gone). The toolbar icons
are inline SVGs in the DOM (not a webfont or sibling asset files), so there is
nothing extra for AssetMapper to serve.

### Use a LIGHT host background

diva renders its own chrome — title bar, toolbar, thumbnail sidebar — with **dark**
icons/text (`--diva-toolbar-button-icon: #2c2d33`, `--diva-page-bg: #f7f5f1`) and
ships **no dark theme**. `.diva-app` has no background of its own, so it inherits the
host element's. On a black background (fine for the OpenSeadragon deep-zoom viewer)
the toolbar icons and title render dark-on-black and look *missing*. The
`viewer="diva"` branch of `IiifViewer.html.twig` therefore gives its wrapper a light
surface (`#f7f5f1`) plus a definite `height` and `display: flex` (diva's `.diva-app`
is `height: 100%` and needs a sized, flex parent to fill).

## Events — wiring OCR / tags display

diva 7 dispatches `CustomEvent`s on the inner `<osd-viewer>` custom element it
renders inside our wrapper:

| event                 | detail              | when                              |
| --------------------- | ------------------- | --------------------------------- |
| `diva-page-change`    | `{ index, instant }`| current page (0-based) changed    |
| `diva-zoom-change`    | `{ zoom }`          | zoom level changed                |
| `diva-loading-change` | `{ loading }`       | tiles started / finished loading  |

You can listen directly on the element:

```js
const viewer = document.querySelector('osd-viewer');
viewer.addEventListener('diva-page-change', (e) => {
    console.log('page', e.detail.index, e.detail.instant);
});
```

**Caveat:** these events are dispatched with `bubbles: false`, so a delegated
listener on an ancestor won't see them in the bubble phase, and `<osd-viewer>` is
created asynchronously (you can't `querySelector` it the instant the controller
connects).

So `diva_viewer_controller.js` attaches **capturing** listeners on its own wrapper
element (the capture phase reaches a non-bubbling event on a descendant) and
re-dispatches them as bubbling Stimulus events. A host page reacts without touching
`<osd-viewer>` directly:

| diva event            | re-dispatched as          | detail                |
| --------------------- | ------------------------- | --------------------- |
| `diva-page-change`    | `iiif-diva:page-change`   | `{ index, instant }`  |
| `diva-zoom-change`    | `iiif-diva:zoom-change`   | `{ zoom }`            |
| `diva-loading-change` | `iiif-diva:loading-change`| `{ loading }`         |

```html
<div data-action="iiif-diva:page-change->ocr#show">…<twig:iiif:viewer viewer="diva" … /></div>
```

```js
// ocr_controller.js
show(event) {
    const page = event.detail.index; // 0-based — fetch/render OCR or tags for this page
}
```

This mirrors the OpenSeadragon controller's `iiif-viewer:page` event.

> Note: diva's `diva-*` events are **not documented** on diva.simssa.ca — they were
> read off the v7 build. See "Upstream documentation gap" below.

## Upstream documentation gap

Two things are real-but-undocumented upstream — filed as
[DDMAL/diva.js#561](https://github.com/DDMAL/diva.js/issues/561):

1. The `diva-page-change` / `diva-zoom-change` / `diva-loading-change` events (names,
   `detail` shapes, and that they don't bubble) aren't in the docs.
2. The OpenSeadragon **peer-dependency** requirement (`window.OpenSeadragon` must
   exist before init) is only implied by the script-tag in getting-started; it isn't
   called out for bundler/ESM consumers, where the global isn't set automatically.
