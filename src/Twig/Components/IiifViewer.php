<?php

declare(strict_types=1);

namespace Survos\IiifBundle\Twig\Components;

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

    /** Namespaced Stimulus controller id from controllers.json (UX package @survos/iiif) */
    public string $stimulusController = '@survos/iiif/iiif-viewer';

    /** Namespaced Stimulus controller id for the diva.js document viewer */
    public string $divaController = '@survos/iiif/iiif-diva';
}
