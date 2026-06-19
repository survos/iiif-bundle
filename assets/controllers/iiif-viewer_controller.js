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
 *   hiresImages    (Array)     — optional per-page high-res URLs, parallel to `images`.
 *                                Paging uses the fast `images` (thumbnails); when the
 *                                user zooms into a page, its high-res version is
 *                                overlaid so it becomes readable. (plain-image mode)
 *   manifestUrl    (String)    — IIIF manifest URL (reference, info.json mode)
 *   tileSourceUrl  (String)    — IIIF image info.json URL (info.json mode)
 *   options        (Object)    — OpenSeadragon viewer options (optional)
 */
export default class extends Controller {
    static values = {
        images: { type: Array, default: [] },
        hiresImages: { type: Array, default: [] },
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
            // Use the canvas drawer rather than OSD 6's default WebGL drawer. The WebGL
            // drawer leaves the canvas black on open and after every page change until
            // the user interacts (zoom/pan): its first draw uploads the freshly-decoded
            // image texture but renders it before the texture is ready, and no further
            // draw is scheduled, so the page stays black until an interaction forces a
            // repaint. The canvas drawer has no such texture-timing gap and paints
            // immediately; its performance is more than adequate for paged scan images.
            drawer: 'canvas',
            sequenceMode: true,
            showSequenceControl: true,
            showReferenceStrip: false,
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

        this.element.viewer = this._viewer;

        this.element.dispatchEvent(new CustomEvent('iiif-viewer:ready', {
            bubbles: true,
            detail: {
                viewer: this._viewer,
            },
        }));

        // Emit `iiif-viewer:page` (0-based index + total) on open and every page
        // change, so the host page can react — e.g. show per-page OCR. Bubbles, so a
        // listener anywhere up the tree (or on document) catches it.
        const emitPage = () => this.dispatch('page', {
            prefix: 'iiif-viewer',
            detail: { index: this._viewer.currentPage(), total: tileSources.length },
        });
        this._viewer.addHandler('open', emitPage);

        // Focus the viewer as soon as it opens so keyboard paging works immediately,
        // without the user having to click it first. preventScroll keeps the page from
        // jumping to the viewer when it isn't already in view.
        this._viewer.addHandler('open', () => this._viewer.canvas?.focus?.({ preventScroll: true }));

        if (paged) {
            this._viewer.addHandler('page', emitPage);

            // Keyboard paging when the viewer is focused: ←/PageUp = previous,
            // →/PageDown = next. `canvas-key` only fires while the viewer has focus,
            // so this never hijacks the page's arrow keys; we preventDefault to stop
            // OSD's default arrow-pan for these keys. Dragging still pans when zoomed.
            // Ignore auto-repeat (held key) so one keystroke advances exactly one page.
            this._viewer.addHandler('canvas-key', (e) => {
                if (e.originalEvent.repeat) { e.preventDefaultAction = true; return; }
                const key = e.originalEvent.key;
                const last = tileSources.length - 1;
                const cur = this._viewer.currentPage();
                if (key === 'ArrowRight' || key === 'PageDown') {
                    if (cur < last) this._viewer.goToPage(cur + 1);
                    e.preventDefaultAction = true;
                } else if (key === 'ArrowLeft' || key === 'PageUp') {
                    if (cur > 0) this._viewer.goToPage(cur - 1);
                    e.preventDefaultAction = true;
                }
            });
        }

        // Progressive resolution: the viewer pages through fast, low-res thumbnails
        // (`images`); when the user zooms into a page, overlay that page's high-res
        // rendition (`hiresImages`, e.g. imgproxy 'archive') so it becomes readable.
        // The hi-res shares the thumbnail's aspect ratio, so addSimpleImage's default
        // bounds land it exactly on top. In sequenceMode OSD clears the world on every
        // page change, so the overlay is discarded automatically when paging away; we
        // only track which page is already upgraded to avoid adding it twice.
        if (this.hiresImagesValue.length) {
            const spinner = this._buildSpinner();
            let upgradedPage = -1;
            const upgrade = () => {
                const page = this._viewer.currentPage();
                if (page === upgradedPage) return;
                const url = this.hiresImagesValue[page];
                if (!url || !this._viewer.world.getItemAt(0)) return;
                upgradedPage = page;
                // The 3000px rendition can take a moment to fetch; show a spinner so the
                // zoom doesn't look broken/frozen, and hide it once the hi-res has loaded.
                spinner.hidden = false;
                this._viewer.addSimpleImage({
                    url,
                    success: ({ item }) => {
                        const done = () => {
                            if (!item.getFullyLoaded()) return;
                            spinner.hidden = true;
                            item.removeHandler('fully-loaded-change', done);
                        };
                        item.addHandler('fully-loaded-change', done);
                        done();
                    },
                    error: () => { spinner.hidden = true; },
                });
            };
            // Trigger on any zoom past the fit-to-page level (covers scroll-wheel,
            // pinch, and the zoom-in button) and on a direct wheel gesture.
            this._viewer.addHandler('zoom', () => {
                const vp = this._viewer.viewport;
                if (vp.getZoom() > vp.getHomeZoom() * 1.05) upgrade();
            });
            this._viewer.addHandler('canvas-scroll', upgrade);
            this._viewer.addHandler('page', () => { upgradedPage = -1; spinner.hidden = true; });
        }
    }

    /**
     * Create a centered, hidden loading spinner overlaid on the viewer, shown while a
     * high-res page is being fetched. Self-contained (no Bootstrap dependency).
     */
    _buildSpinner() {
        if (!document.getElementById('iiif-viewer-spinner-style')) {
            const style = document.createElement('style');
            style.id = 'iiif-viewer-spinner-style';
            style.textContent = '@keyframes iiif-viewer-spin{to{transform:rotate(360deg)}}';
            document.head.appendChild(style);
        }
        const spinner = document.createElement('div');
        spinner.hidden = true;
        spinner.style.cssText = [
            'position:absolute', 'top:50%', 'left:50%', 'width:48px', 'height:48px',
            'margin:-24px 0 0 -24px', 'border-radius:50%', 'pointer-events:none', 'z-index:20',
            'border:4px solid rgba(255,255,255,.35)', 'border-top-color:#fff',
            'animation:iiif-viewer-spin .8s linear infinite',
        ].join(';');
        this.element.appendChild(spinner);
        return spinner;
    }

    disconnect() {
        this._viewer?.destroy();
        this._viewer = null;
    }
}
