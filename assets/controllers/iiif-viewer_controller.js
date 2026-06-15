import { Controller } from '@hotwired/stimulus';
import OpenSeadragon from 'openseadragon';

/**
 * Stimulus controller for the IIIF viewer (OpenSeadragon).
 *
 * Two modes:
 *   1. Plain images — pass `images` (a list of image URLs, e.g. imgproxy-rendered
 *      JPGs). No IIIF Image API server / info.json is required; multiple images
 *      become a paged (sequenceMode) document viewer. This is the right mode when
 *      you control the source images but have no tile server.
 *   2. IIIF Image API — pass `tileSourceUrl` (an info.json URL) for true deep-zoom
 *      tiling, plus `manifestUrl` for reference.
 *
 * Values:
 *   images         (Array)     — image URLs for the plain-image / paged mode
 *   manifestUrl    (String)    — IIIF manifest URL (reference, info.json mode)
 *   tileSourceUrl  (String)    — IIIF image info.json URL (info.json mode)
 *   options        (Object)    — OpenSeadragon viewer options (optional)
 */
export default class extends Controller {
    static values = {
        images: { type: Array, default: [] },
        manifestUrl: { type: String, default: '' },
        tileSourceUrl: { type: String, default: '' },
        options: { type: Object, default: {} },
    };

    connect() {
        const defaults = {
            prefixUrl: 'https://cdn.jsdelivr.net/npm/openseadragon@6.0.1/build/openseadragon/images/',
            crossOriginPolicy: false,
            navigatorPosition: 'BOTTOM_RIGHT',
            showNavigator: true,
            showFlipControl: true,
            showRotationControl: true,
            minZoomLevel: 0.5,
        };
        const options = { ...defaults, ...this.optionsValue };

        let tileSources;
        if (this.imagesValue.length) {
            // Plain images — OSD's "simple image" source; no info.json needed.
            tileSources = this.imagesValue.map((url) => ({ type: 'image', url }));
        } else if (this.tileSourceUrlValue) {
            tileSources = [{ manifest: this.manifestUrlValue, tileSource: this.tileSourceUrlValue }];
        } else {
            console.warn('[iiif-viewer] Provide either `images` or `tileSourceUrl`.');
            return;
        }

        const paged = tileSources.length > 1;

        this._viewer = OpenSeadragon({
            element: this.element,
            tileSources,
            sequenceMode: paged,
            showReferenceStrip: paged,
            referenceStripScroll: 'horizontal',
            ...options,
        });

        // Emit `iiif-viewer:page` (0-based index + total) on open and every page
        // change, so the host page can react — e.g. show per-page OCR. Bubbles, so a
        // listener anywhere up the tree (or on document) catches it.
        const emitPage = () => this.dispatch('page', {
            prefix: 'iiif-viewer',
            detail: { index: this._viewer.currentPage(), total: tileSources.length },
        });
        this._viewer.addHandler('open', emitPage);
        if (paged) {
            this._viewer.addHandler('page', emitPage);
        }
    }

    disconnect() {
        this._viewer?.destroy();
        this._viewer = null;
    }
}
