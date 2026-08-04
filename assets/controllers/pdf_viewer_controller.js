import { Controller } from '@hotwired/stimulus';
import * as pdfjsLib from 'pdfjs-dist';

/**
 * pdf.js document viewer — the third viewer in this bundle (alongside OpenSeadragon and diva),
 * for rows whose page is a PDF rather than a sequence of images.
 *
 * Why pdf.js and not a native <embed>: the browser's built-in PDF viewer is a black box — no
 * page-change callback, no text overlay. pdf.js renders each page ourselves, so we
 *   1. emit the SAME `iiif-viewer:page` event the OpenSeadragon controller does (prefix
 *      'iiif-viewer', detail {index, total}) — the host page's per-page logic is viewer-agnostic, and
 *   2. expose a text layer we can later drive with OCR (mediary / ai-tools pdf-to-ocr). The native
 *      PDF text layer is rendered today; when a page has no embedded text (a scan), that layer is
 *      empty — exactly the slot the OCR overlay fills (see setOcr()).
 *
 * The controller module is registered `fetch: lazy`, so pdf.js is only pulled when a PDF row mounts.
 *
 * @value  url        The PDF URL (e.g. DC document_access.pdf).
 * @value  page        Initial 1-based page. Defaults to 1.
 * @value  pageCount   Known page count (from PdfMeta probe); 0 discovers it from the document.
 * @target canvas      Canvas the current page is rendered onto.
 * @target textLayer   Selectable text layer, populated from embedded PDF text or later by setOcr().
 * @target status       Element showing "Page N of M" or an error message.
 * @target prev         Previous-page button; disabled at page 1.
 * @target next         Next-page button; disabled at the last page.
 * @action prev         Goes to the previous page.
 * @action next         Goes to the next page.
 */
export default class extends Controller {
    static values = {
        url: String,
        page: { type: Number, default: 1 },
        pageCount: { type: Number, default: 0 },
    };

    static targets = ['canvas', 'textLayer', 'status', 'prev', 'next'];

    async connect() {
        // Pin the worker to the importmap version (jsDelivr ESM build) — mirrors how the
        // OpenSeadragon controller sources its image assets from a CDN.
        if (!pdfjsLib.GlobalWorkerOptions.workerSrc) {
            pdfjsLib.GlobalWorkerOptions.workerSrc =
                'https://cdn.jsdelivr.net/npm/pdfjs-dist@6.0.227/build/pdf.worker.min.mjs';
        }

        this._current = Math.max(1, this.pageValue || 1);
        this._rendering = false;

        try {
            this._doc = await pdfjsLib.getDocument({ url: this.urlValue }).promise;
        } catch (e) {
            this._fail('Could not load PDF.');
            return;
        }

        this._total = this._doc.numPages || this.pageCountValue || 0;
        if (this._current > this._total) this._current = 1;

        await this._renderPage(this._current);
    }

    disconnect() {
        // Free the worker/transport so navigating away doesn't leak a background thread.
        this._doc?.cleanup?.();
        this._doc?.destroy?.();
        this._doc = null;
    }

    next() { this._go(this._current + 1); }
    prev() { this._go(this._current - 1); }

    _go(n) {
        if (!this._doc || this._rendering) return;
        if (n < 1 || n > this._total) return;
        this._renderPage(n);
    }

    async _renderPage(n) {
        if (!this._doc) return;
        this._rendering = true;
        try {
            const page = await this._doc.getPage(n);

            // Fit the rendered page to the host element's width (HiDPI-aware).
            const canvas = this.hasCanvasTarget ? this.canvasTarget : null;
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            const containerWidth = this.element.clientWidth || 800;
            const unscaled = page.getViewport({ scale: 1 });
            const scale = Math.max(0.2, containerWidth / unscaled.width);
            const dpr = window.devicePixelRatio || 1;
            const viewport = page.getViewport({ scale });

            canvas.width = Math.floor(viewport.width * dpr);
            canvas.height = Math.floor(viewport.height * dpr);
            canvas.style.width = '100%';
            canvas.style.height = 'auto';
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            await page.render({ canvasContext: ctx, viewport }).promise;

            await this._renderTextLayer(page, viewport);

            this._current = n;
            this._updateChrome();
            this._emitPage();
        } catch (e) {
            // A single bad page shouldn't kill the viewer.
            this._setStatus(`Page ${n} failed to render`);
        } finally {
            this._rendering = false;
        }
    }

    /**
     * Render pdf.js's own text layer (selectable text where the PDF has embedded text). Guarded:
     * the text-layer API differs across pdf.js majors, and a scanned PDF has no text — neither
     * should break the canvas view. This is the element setOcr() later replaces/augments.
     */
    async _renderTextLayer(page, viewport) {
        if (!this.hasTextLayerTarget) return;
        const layer = this.textLayerTarget;
        layer.replaceChildren();
        layer.style.width = `${viewport.width}px`;
        layer.style.height = `${viewport.height}px`;
        try {
            const textContent = await page.getTextContent();
            if (typeof pdfjsLib.TextLayer === 'function') {
                const tl = new pdfjsLib.TextLayer({ textContentSource: textContent, container: layer, viewport });
                await tl.render();
            }
        } catch (e) {
            // No embedded text (scan) or API drift — leave the layer empty for the OCR overlay.
        }
    }

    /**
     * Hook for the future OCR pipeline: given an array of per-page text (or a {page: text} map),
     * drop it into the text layer so scans become searchable/selectable. No-op until OCR exists.
     */
    setOcr(textForCurrentPage) {
        if (!this.hasTextLayerTarget || !textForCurrentPage) return;
        const el = document.createElement('div');
        el.className = 'pdf-ocr-text';
        el.textContent = textForCurrentPage;
        this.textLayerTarget.replaceChildren(el);
    }

    _updateChrome() {
        if (this.hasPrevTarget) this.prevTarget.disabled = this._current <= 1;
        if (this.hasNextTarget) this.nextTarget.disabled = this._current >= this._total;
        this._setStatus(`Page ${this._current} of ${this._total}`);
    }

    _setStatus(text) {
        if (this.hasStatusTarget) this.statusTarget.textContent = text;
    }

    _emitPage() {
        // Same contract as the OpenSeadragon viewer: 0-based index + total, bubbling.
        this.dispatch('page', {
            prefix: 'iiif-viewer',
            detail: { index: this._current - 1, total: this._total },
        });
    }

    _fail(message) {
        this._setStatus(message);
    }
}
