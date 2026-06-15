import { Controller } from '@hotwired/stimulus';
import Diva from 'diva.js';

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

        this._diva = new Diva(this.element, {
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
