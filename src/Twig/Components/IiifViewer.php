<?php

declare(strict_types=1);

namespace Survos\IiifBundle\Twig\Components;

use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * IIIF Image viewer Twig component backed by OpenSeadragon.
 *
 * Minimal usage — pass only the IIIF Image API info.json URL:
 *   <twig:iiif:viewer infoUrl="{{ url('iiif_image_info', {id: asset.id}) }}" />
 *
 * With external viewer links via the app's redirect routes:
 *   <twig:iiif:viewer
 *       infoUrl="{{ url('iiif_image_info', {id: asset.id}) }}"
 *       manifestUrl="{{ url('iiif_manifest', {id: asset.id}) }}"
 *       miradorUrl="{{ url('iiif_mirador', {id: asset.id}) }}"
 *       uvUrl="{{ url('iiif_uv', {id: asset.id}) }}"
 *       height="500"
 *   />
 */
#[AsTwigComponent(name: 'iiif:viewer', template: '@SurvosIiif/components/IiifViewer.html.twig')]
final class IiifViewer
{
    /** IIIF Image API info.json URL — required for the embedded viewer */
    public string $infoUrl = '';

    /**
     * Plain image URLs (e.g. imgproxy-rendered JPGs) for the OpenSeadragon viewer.
     * When set, the viewer pages through them — no IIIF Image API / info.json needed.
     *
     * @var list<string>
     */
    public array $images = [];

    /**
     * Optional per-page high-res image URLs, parallel to $images. Paging uses the
     * fast $images thumbnails; when the user zooms into a page, the matching
     * high-res URL here (e.g. an imgproxy 'archive' rendition) is overlaid so the
     * page becomes readable. Leave empty to disable progressive upgrading.
     *
     * @var list<string>
     */
    public array $hiresImages = [];

    /**
     * Optional per-page imgproxy `/info` URLs, parallel to $images. When set, the
     * OpenSeadragon viewer fetches the current page's true source dimensions (no
     * image bytes downloaded — imgproxy's metadata-only endpoint) and shows them
     * under the viewer, so "is there more detail here?" doesn't require zooming in.
     *
     * @var list<string>
     */
    public array $infoUrls = [];

    /**
     * Optional per-page descriptive text (caption/credit), parallel to $images — the same content
     * an IIIF Presentation manifest carries as each Canvas's `summary` (see
     * FolioController::iiifManifest()). Forwarded through the controller's `iiif-viewer:page` event
     * as `detail.summary` on every page change, so callers can display it without maintaining a
     * separate side-channel keyed to the current page index.
     *
     * @var list<string|null>
     */
    public array $summaries = [];

    /** IIIF Presentation API manifest URL — shown as a direct link */
    public string $manifestUrl = '';

    /**
     * URL that redirects to Mirador with the manifest pre-loaded.
     * If your app has an iiif_mirador route, pass url('iiif_mirador', {id: …}).
     * Leave empty to hide the Mirador button.
     */
    public string $miradorUrl = '';

    /**
     * URL that redirects to Universal Viewer with the manifest pre-loaded.
     * If your app has an iiif_uv route, pass url('iiif_uv', {id: …}).
     * Leave empty to hide the Universal Viewer button.
     */
    public string $uvUrl = '';

    /** Viewer height in pixels */
    public int $height = 420;

    /** Show the OpenSeadragon navigator mini-map */
    public bool $showNav = true;

    /** Show the external viewer link buttons */
    public bool $showLinks = true;

    /** Optional footer metadata line (e.g. "image/jpeg · 1200×800 · 340KB") */
    public string $meta = '';

    /**
     * Which embedded viewer to use:
     *   'openseadragon' — deep-zoom of a single image (needs infoUrl)
     *   'diva'          — page-turning multi-page document (needs manifestUrl); cleaner for documents
     */
    public string $viewer = 'openseadragon';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    /**
     * URL of the standalone diva.js viewer for this manifest, or null when it is
     * not reachable.
     *
     * The template used to call path('survos_iiif_debug', …) directly, which made
     * the "diva.js" compare link — and therefore every host page rendering this
     * component, e.g. folio-bundle's row/show — hard-depend on a route this bundle
     * only registers when survos_iiif.routes_enabled is true. Since that now
     * defaults to FALSE (it is a debug route, see SurvosIiifBundle::configure()),
     * generating it unconditionally would throw RouteNotFoundException on pages
     * that have nothing to do with the debug viewer.
     *
     * Resolving it here instead means the link simply does not render when the
     * route is switched off, and reappears the moment an app opts in.
     */
    public function getDivaDebugUrl(): ?string
    {
        if ('' === $this->manifestUrl) {
            return null;
        }

        try {
            return $this->urlGenerator->generate('survos_iiif_debug', ['manifest' => $this->manifestUrl]);
        } catch (RouteNotFoundException) {
            return null;
        }
    }

    // The Stimulus controller ids are resolved in the template via the kit-bundle
    // survos_stimulus('iiif', …) Twig helper, not hard-coded here — so the name can
    // never drift from what Flex registered (and is validated in dev).
}
