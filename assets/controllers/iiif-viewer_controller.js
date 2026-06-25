import { Controller } from '@hotwired/stimulus';
import OpenSeadragon from 'openseadragon';

/**
 * Stimulus controller for the IIIF viewer (OpenSeadragon).
 *
 * Modes:
 *  1. Plain images — pass `images`
 *  2. IIIF Image API — pass `tileSourceUrl`
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
            drawer: 'canvas',
            sequenceMode: true,
            showSequenceControl: true,
            showReferenceStrip: false,
        };

        const options = { ...defaults, ...this.optionsValue };

        let tileSources;

        if (this.imagesValue.length) {
            tileSources = this.imagesValue.map((url) => ({
                type: 'image',
                url,
            }));
        } else if (this.tileSourceUrlValue) {
            tileSources = [{
                manifest: this.manifestUrlValue,
                tileSource: this.tileSourceUrlValue,
            }];
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

        this._installResponsiveResize();

        this.element.dispatchEvent(new CustomEvent('iiif-viewer:ready', {
            bubbles: true,
            detail: {
                viewer: this._viewer,
            },
        }));

        const emitPage = () => this.dispatch('page', {
            prefix: 'iiif-viewer',
            detail: {
                index: this._viewer.currentPage(),
                total: tileSources.length,
            },
        });

        this._viewer.addHandler('open', () => {
            emitPage();
            this._viewer.canvas?.focus?.({ preventScroll: true });
            this._resizeViewer();
        });

        if (paged) {
            this._viewer.addHandler('page', () => {
                emitPage();
                this._resizeViewer();
            });

            this._viewer.addHandler('canvas-key', (e) => {
                if (e.originalEvent.repeat) {
                    e.preventDefaultAction = true;
                    return;
                }

                const key = e.originalEvent.key;
                const last = tileSources.length - 1;
                const cur = this._viewer.currentPage();

                if (key === 'ArrowRight' || key === 'PageDown') {
                    if (cur < last) {
                        this._viewer.goToPage(cur + 1);
                    }

                    e.preventDefaultAction = true;
                } else if (key === 'ArrowLeft' || key === 'PageUp') {
                    if (cur > 0) {
                        this._viewer.goToPage(cur - 1);
                    }

                    e.preventDefaultAction = true;
                }
            });
        }

        if (this.hiresImagesValue.length) {
            this._installProgressiveResolution();
        }
    }

    _installResponsiveResize() {
        if (!this._viewer) return;

        this._resizeObserver = new ResizeObserver(() => {
            requestAnimationFrame(() => this._resizeViewer());
        });

        this._resizeObserver.observe(this.element);

        this._osdWindowResizeHandler = () => {
            this._resizeViewer();
        };

        window.addEventListener('resize', this._osdWindowResizeHandler);

        this._osdBootstrapResizeHandler = () => {
            requestAnimationFrame(() => this._resizeViewer());
        };

        document.addEventListener('shown.bs.tab', this._osdBootstrapResizeHandler);
        document.addEventListener('shown.bs.offcanvas', this._osdBootstrapResizeHandler);
        document.addEventListener('shown.bs.modal', this._osdBootstrapResizeHandler);
    }

    _resizeViewer() {
        if (!this._viewer || !this._viewer.viewport) return;

        const rect = this.element.getBoundingClientRect();

        // Skip resize while hidden, for example inactive tabs.
        if (!rect.width || !rect.height) return;

        // OpenSeadragon's viewport.resize() dereferences the container-size argument
        // (newContainerSize.x); calling it with no args throws "reading 'x' of undefined"
        // when the ResizeObserver fires before the viewer is open. Pass the current size.
        this._viewer.viewport.resize(new OpenSeadragon.Point(rect.width, rect.height), true);
        this._viewer.forceRedraw();
    }

    _installProgressiveResolution() {
        const spinner = this._buildSpinner();
        let upgradedPage = -1;

        const upgrade = () => {
            const page = this._viewer.currentPage();

            if (page === upgradedPage) return;

            const url = this.hiresImagesValue[page];

            if (!url || !this._viewer.world.getItemAt(0)) return;

            upgradedPage = page;
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
                error: () => {
                    spinner.hidden = true;
                },
            });
        };

        this._viewer.addHandler('zoom', () => {
            const vp = this._viewer.viewport;

            if (vp.getZoom() > vp.getHomeZoom() * 1.05) {
                upgrade();
            }
        });

        this._viewer.addHandler('canvas-scroll', upgrade);

        this._viewer.addHandler('page', () => {
            upgradedPage = -1;
            spinner.hidden = true;
        });
    }

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
            'position:absolute',
            'top:50%',
            'left:50%',
            'width:48px',
            'height:48px',
            'margin:-24px 0 0 -24px',
            'border-radius:50%',
            'pointer-events:none',
            'z-index:20',
            'border:4px solid rgba(255,255,255,.35)',
            'border-top-color:#fff',
            'animation:iiif-viewer-spin .8s linear infinite',
        ].join(';');

        this.element.appendChild(spinner);

        return spinner;
    }

    disconnect() {
        this._resizeObserver?.disconnect();
        this._resizeObserver = null;

        if (this._osdWindowResizeHandler) {
            window.removeEventListener('resize', this._osdWindowResizeHandler);
            this._osdWindowResizeHandler = null;
        }

        if (this._osdBootstrapResizeHandler) {
            document.removeEventListener('shown.bs.tab', this._osdBootstrapResizeHandler);
            document.removeEventListener('shown.bs.offcanvas', this._osdBootstrapResizeHandler);
            document.removeEventListener('shown.bs.modal', this._osdBootstrapResizeHandler);
            this._osdBootstrapResizeHandler = null;
        }

        this._viewer?.destroy();
        this._viewer = null;
    }
}
