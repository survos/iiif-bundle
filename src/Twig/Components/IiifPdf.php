<?php

declare(strict_types=1);

namespace Survos\IiifBundle\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * PDF document viewer Twig component, backed by the bundle's pdf.js Stimulus controller.
 *
 * For rows whose canonical media is a PDF (e.g. Digital Commonwealth document_access.pdf) rather
 * than a sequence of images. Renders page-by-page via pdf.js so it emits the same
 * `iiif-viewer:page` event as <twig:iiif:viewer> (host per-page logic stays viewer-agnostic) and
 * carries a text layer the OCR pipeline can later populate.
 *
 * Minimal usage:
 *   <twig:iiif:pdf url="https://…/document_access.pdf" pageCount="23" />
 */
#[AsTwigComponent(name: 'iiif:pdf', template: '@SurvosIiif/components/IiifPdf.html.twig')]
final class IiifPdf
{
    /** The PDF URL — required. */
    public string $url = '';

    /** 1-based initial page. */
    public int $page = 1;

    /** Known page count (e.g. from a PdfMeta probe); 0 = discover from the document. */
    public int $pageCount = 0;

    /** Viewer height in pixels. */
    public int $height = 600;

    /** Optional footer metadata line (e.g. "PDF · 23 pages · 15 MB"). */
    public string $meta = '';

    /** Show a download / open-in-new-tab link to the raw PDF. */
    public bool $showLinks = true;
}
