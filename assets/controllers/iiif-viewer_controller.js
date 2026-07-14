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
        icons: { type: Object, default: {} },
        infoUrls: { type: Array, default: [] },
    };

    connect() {
        const defaults = {
            crossOriginPolicy: false,
            navigatorPosition: 'BOTTOM_RIGHT',
            showNavigator: true,
            showFlipControl: true,
            showRotationControl: true,
            minZoomLevel: 0.5,
            drawer: 'canvas',
            sequenceMode: true,
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

        // Server-rendered ux-icon SVGs (see IiifViewer.html.twig `osdIcons`) instead of
        // OSD's stock jsDelivr-hosted PNG sprites — themed to match the rest of the app,
        // and same-origin/cached with the page instead of a third-party CDN fetch.
        const toolbarButtons = this._buildToolbar(paged);

        this._dimsRequestId = 0;
        this._buildDimensionsBadge();

        this._viewer = OpenSeadragon({
            element: this.element,
            tileSources,
            sequenceMode: paged,
            // Only show (and build) prev/next when there's more than one page — otherwise OSD
            // falls back to its own default image-based buttons (no `element` given for a single
            // untiled image), which 404 against prefixUrl-relative images we no longer serve.
            showSequenceControl: paged,
            showReferenceStrip: paged,
            referenceStripScroll: 'horizontal',
            ...options,
            ...toolbarButtons,
        });

        this.element.viewer = this._viewer;

        this._installResponsiveResize();

        this.element.dispatchEvent(new CustomEvent('iiif-viewer:ready', {
            bubbles: true,
            detail: {
                viewer: this._viewer,
            },
        }));

        const emitPage = () => {
            const index = this._viewer.currentPage();

            this.dispatch('page', {
                prefix: 'iiif-viewer',
                detail: { index, total: tileSources.length },
            });

            this._updateDimensions(index);
        };

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

    _buildToolbar(paged) {
        if (!document.getElementById('iiif-viewer-toolbar-style')) {
            const style = document.createElement('style');

            style.id = 'iiif-viewer-toolbar-style';
            style.textContent = `
                .iiif-osd-toolbar {
                    position: absolute;
                    top: 8px;
                    left: 8px;
                    z-index: 10;
                    display: flex;
                    gap: 4px;
                    flex-wrap: wrap;
                    max-width: calc(100% - 16px);
                }
                .iiif-osd-btn {
                    /* !important: OpenSeadragon's own Button control sets
                       element.style.display = "inline-block" directly (inline JS style),
                       which otherwise silently wins over this rule and the icon renders
                       flush top-left instead of centered. */
                    display: flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                    width: 30px;
                    height: 30px;
                    border-radius: 50%;
                    background: rgba(17, 17, 17, .65);
                    color: #fff;
                    cursor: pointer;
                    transition: background .15s ease;
                }
                .iiif-osd-btn:hover {
                    background: rgba(17, 17, 17, .9);
                }
                .iiif-osd-btn svg {
                    width: 16px;
                    height: 16px;
                }
            `;

            document.head.appendChild(style);
        }

        const toolbar = document.createElement('div');
        toolbar.className = 'iiif-osd-toolbar';

        const icons = this.iconsValue;

        const make = (iconKey, label) => {
            const btn = document.createElement('div');
            btn.className = 'iiif-osd-btn';
            btn.setAttribute('role', 'button');
            btn.setAttribute('aria-label', label);
            btn.title = label;
            btn.innerHTML = icons[iconKey] || '';
            toolbar.appendChild(btn);
            return btn;
        };

        const buttons = {};

        if (paged) {
            buttons.previousButton = make('previous', 'Previous page');
            buttons.nextButton = make('next', 'Next page');
        }

        buttons.zoomInButton = make('zoomIn', 'Zoom in');
        buttons.zoomOutButton = make('zoomOut', 'Zoom out');
        buttons.homeButton = make('home', 'Reset view');
        buttons.fullPageButton = make('fullPage', 'Full page');
        buttons.rotateLeftButton = make('rotateLeft', 'Rotate left');
        buttons.rotateRightButton = make('rotateRight', 'Rotate right');
        buttons.flipButton = make('flip', 'Flip');

        this.element.appendChild(toolbar);
        this._toolbarEl = toolbar;

        return buttons;
    }

    _buildDimensionsBadge() {
        if (!document.getElementById('iiif-viewer-dims-style')) {
            const style = document.createElement('style');

            style.id = 'iiif-viewer-dims-style';
            style.textContent = `
                .iiif-osd-dims {
                    position: absolute;
                    bottom: 8px;
                    left: 8px;
                    z-index: 10;
                    padding: 2px 8px;
                    border-radius: 12px;
                    background: rgba(17, 17, 17, .65);
                    color: #fff;
                    font-size: 12px;
                    line-height: 1.6;
                    pointer-events: none;
                }
            `;

            document.head.appendChild(style);
        }

        const badge = document.createElement('div');
        badge.className = 'iiif-osd-dims';
        badge.hidden = true;

        this.element.appendChild(badge);
        this._dimsEl = badge;
    }

    // Fetches the current page's true source dimensions from imgproxy's metadata-only
    // /info endpoint (no image bytes downloaded) — tells the user how much detail is
    // actually in the scan without eagerly loading the hi-res rendition. Degrades
    // silently (badge stays hidden) if infoUrls wasn't provided or the request fails,
    // e.g. imgproxy PRO isn't licensed on this instance.
    _updateDimensions(pageIndex) {
        if (!this._dimsEl) return;

        const url = this.infoUrlsValue[pageIndex];

        if (!url) {
            this._dimsEl.hidden = true;
            return;
        }

        const requestId = ++this._dimsRequestId;

        fetch(url)
            .then((res) => (res.ok ? res.json() : null))
            .then((data) => {
                if (requestId !== this._dimsRequestId) return;

                if (!data?.width || !data?.height) {
                    this._dimsEl.hidden = true;
                    return;
                }

                this._dimsEl.textContent = `${data.width} × ${data.height}px`;
                this._dimsEl.hidden = false;
            })
            .catch(() => {
                if (requestId === this._dimsRequestId) {
                    this._dimsEl.hidden = true;
                }
            });
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

        this._toolbarEl?.remove();
        this._toolbarEl = null;

        this._dimsEl?.remove();
        this._dimsEl = null;
        this._dimsRequestId = 0;
    }
}
