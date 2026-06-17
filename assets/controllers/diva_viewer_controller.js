import { Controller } from '@hotwired/stimulus';
// diva.js 7.2.6+ ships a real ESM build (`build/diva.esm.js`) with a named `Diva`
// export — no more v6 `window.Diva` side-effect hack. v7 parses IIIF Presentation
// 2.x AND 3.x, so our canonical Presentation 3 manifests render directly.
import { Diva } from 'diva.js';
// diva 7 renders through OpenSeadragon but does NOT bundle it — it reads the
// `window.OpenSeadragon` global at viewer-init time. The npm `openseadragon` UMD,
// when resolved through the importmap, assigns to `module.exports` and skips the
// global branch, so we import it and expose the global ourselves.
import OpenSeadragon from 'openseadragon';
window.OpenSeadragon ||= OpenSeadragon;

/**
 * Stimulus controller for the diva.js IIIF document viewer.
 *
 * diva.js is a page-turning viewer for multi-page documents (manuscripts, PDFs,
 * pension files) — a document-scrolling interface over OpenSeadragon zooming,
 * cleaner than bare OpenSeadragon for the "wrapper of images" case.
 *
 * Values:
 *   manifestUrl  (String)  — IIIF Presentation manifest (2.x or 3.x) URL (required)
 *   options      (Object)  — diva.js settings overrides     (optional)
 *
 * Dispatched events (Stimulus `iiif-diva:*`, re-emitted from diva's own
 * non-bubbling events on the inner <osd-viewer> element so a host page can react
 * — e.g. show per-page OCR or tags). Each bubbles, so a listener anywhere up the
 * tree catches it:
 *   iiif-diva:page-change    detail { index, instant }  — current page (0-based) changed
 *   iiif-diva:zoom-change    detail { zoom }             — zoom level changed
 *   iiif-diva:loading-change detail { loading }          — tiles started/finished loading
 */
export default class extends Controller {
    static values = {
        manifestUrl: { type: String, default: '' },
        options: { type: Object, default: {} },
    };

    connect() {
        if (!this.manifestUrlValue) {
            console.warn('[iiif-diva] manifestUrl is required.');
            return;
        }

        // diva.js keys its instance off the element id and looks it up with
        // getElementById, so the element must carry one.
        if (!this.element.id) {
            this.element.id = 'diva-' + Math.abs(this.#hash(this.manifestUrlValue));
        }

        // diva 7's own events fire on the inner <osd-viewer> custom element and do
        // NOT bubble. Capturing listeners on an ancestor still see them (the capture
        // phase travels root→target regardless of `bubbles`), so we attach here once,
        // before constructing the viewer, and re-dispatch as bubbling Stimulus events.
        this.#relay('diva-page-change', 'page-change');
        this.#relay('diva-zoom-change', 'zoom-change');
        this.#relay('diva-loading-change', 'loading-change');

        // v7 constructor: `new Diva(elementId, settings)` — first arg is the id
        // string, not the element (it does getElementById internally and throws if
        // missing). showTitle/showSidebar default to true; override via `options`.
        this._diva = new Diva(this.element.id, {
            objectData: this.manifestUrlValue,
            ...this.optionsValue,
        });
    }

    disconnect() {
        if (this._diva && typeof this._diva.destroy === 'function') {
            this._diva.destroy();
        }
        this._diva = null;
    }

    /** Re-dispatch a diva `diva-*` event (caught in the capture phase) as a bubbling Stimulus event. */
    #relay(divaEvent, name) {
        this.element.addEventListener(
            divaEvent,
            (e) => this.dispatch(name, { prefix: 'iiif-diva', detail: e.detail }),
            true, // capture: diva's events don't bubble; capture reaches them from the wrapper
        );
    }

    #hash(str) {
        let h = 0;
        for (let i = 0; i < str.length; i++) {
            h = (h << 5) - h + str.charCodeAt(i);
            h |= 0;
        }
        return h;
    }
}
