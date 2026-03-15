import { Controller } from '@hotwired/stimulus';
import OpenSeadragon from 'openseadragon';

/**
 * Stimulus controller for the IIIF viewer (OpenSeadragon).
 *
 * Values:
 *   manifestUrl    (String)    — IIIF manifest URL             (required)
 *   tileSourceUrl  (String)    — IIIF image info.json URL      (required)
 *   options        (Object)    — OpenSeadragon viewer options  (optional)
 *
 */

export default class extends Controller {
    static values = {
        manifestUrl: {
            type: String,
            default: ''
        },
        tileSourceUrl: {
            type: String,
            default: ''
        },
        options: {
            type: Object,
            default: {}
        },
    };

    async connect() {
        if (!this.manifestUrlValue || !this.tileSourceUrlValue) {
            console.warn('[iiif-viewer] Both manifestUrl and tileSourceUrl are required.');
            return;
        }

        const defaults = {
            prefixUrl: 'https://cdn.jsdelivr.net/npm/openseadragon@6.0.1/build/openseadragon/images/',
            crossOriginPolicy: false,
            navigatorPosition: 'BOTTOM_RIGHT',
            showFlipControl: true,
            showRotationControl: true,
            minZoomLevel: 0.5,
        }

        const options = {
            ...defaults,
            ...this.optionsValue
        }

        this._viewer = OpenSeadragon({
            element: this.element,
            tileSources: [
                {
                    manifest: this.manifestUrlValue,
                    tileSource: this.tileSourceUrlValue
                }
            ],
            ...options,
        });
    }

    disconnect() {
        this._viewer?.destroy();
        this._viewer = null;
    }
}
