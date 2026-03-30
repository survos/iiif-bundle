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

    /** Namespaced Stimulus controller id from controllers.json */
    public string $stimulusController = '@survos/iiif/iiif-viewer';
}
