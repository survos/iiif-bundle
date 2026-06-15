import { Controller } from '@hotwired/stimulus';
// diva.js 6.0.2 (the latest on npm) is a webpack/UMD build with NO ES exports —
// it only assigns `window.Diva` as a side effect. v7.x is tagged on GitHub but
// never published to npm, so this is what the importmap can resolve. Import for
// the side effect and read the global. Switch to `import { Diva } from 'diva.js'`
// if/when DDMAL publishes a v7 ESM build.
import 'diva.js';

/**
 * Stimulus controller for the diva.js IIIF document viewer.
 *
 * diva.js is a page-turning viewer for multi-page documents (manuscripts, PDFs,
 * pension files) — cleaner than OpenSeadragon for the "wrapper of images" case.
 *
 * Values:
 *   manifestUrl  (String)  — IIIF Presentation manifest URL (required)
 *   options      (Object)  — diva.js settings overrides     (optional)
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

        this.#ensureStylesheet();

        // diva.js keys its instance off the element id.
        if (!this.element.id) {
            this.element.id = 'diva-' + Math.abs(this.#hash(this.manifestUrlValue));
        }

        const Diva = window.Diva;
        if (typeof Diva !== 'function') {
            console.error('[iiif-diva] window.Diva is not available after importing diva.js.');
            return;
        }

        // diva.js 6 only sets its internal element when the first arg is a string
        // id (the `e instanceof HTMLElement` branch leaves this.element undefined),
        // so pass the id, not the element itself.
        this._diva = new Diva(this.element.id, {
            objectData: this.manifestUrlValue,
            enableAutoTitle: false,
            enableFullscreen: true,
            ...this.optionsValue,
        });
    }

    disconnect() {
        if (this._diva && typeof this._diva.destroy === 'function') {
            this._diva.destroy();
        }
        this._diva = null;
    }

    /** Inject diva's stylesheet once (the npm CSS isn't on the importmap). */
    #ensureStylesheet() {
        if (document.querySelector('link[data-iiif-diva-css]')) {
            return;
        }
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = 'https://cdn.jsdelivr.net/npm/diva.js@6.0.1/dist/diva.min.css';
        link.setAttribute('data-iiif-diva-css', '1');
        document.head.appendChild(link);
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
