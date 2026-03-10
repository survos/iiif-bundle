import { Controller } from '@hotwired/stimulus';
import OpenSeadragon from 'openseadragon';

/**
 * Stimulus controller for the IIIF Image API viewer (OpenSeadragon).
 *
 * Values (all set as data-iiif-viewer-*-value attributes):
 *   infoUrl      (String)  — IIIF Image API info.json URL (required)
 *   height       (Number)  — viewer height in pixels (default 420)
 *   showNav      (Boolean) — show the navigator mini-map (default true)
 *   prefixUrl    (String)  — base URL for OSD control images (default cdnjs)
 */
export default class extends Controller {
    static values = {
        infoUrl:   { type: String,  default: '' },
        height:    { type: Number,  default: 420 },
        showNav:   { type: Boolean, default: true },
        prefixUrl: {
            type:    String,
            default: 'https://cdnjs.cloudflare.com/ajax/libs/openseadragon/4.1.1/images/',
        },
    };

    connect() {
        if (!this.infoUrlValue) {
            console.warn('[iiif-viewer] No infoUrl value set — viewer will not load.');
            return;
        }

        this.element.style.height = `${this.heightValue}px`;

        this._viewer = OpenSeadragon({
            element:              this.element,
            prefixUrl:            this.prefixUrlValue,
            tileSources:          this.infoUrlValue,
            showNavigator:        this.showNavValue,
            navigatorPosition:    'BOTTOM_RIGHT',
            showRotationControl:  true,
            showFlipControl:      false,
            gestureSettingsMouse: { scrollToZoom: true },
            minZoomLevel:         0.5,
            defaultZoomLevel:     0,
            visibilityRatio:      0.5,
            constrainDuringPan:   false,
            background:           '#111111',
        });
    }

    disconnect() {
        this._viewer?.destroy();
        this._viewer = null;
    }
}
