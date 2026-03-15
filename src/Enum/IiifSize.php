<?php

declare(strict_types=1);

namespace Survos\IiifBundle\Enum;

/**
 * Standard IIIF Image API 3.0 size parameter values.
 *
 * The IIIF spec defines no named shortcuts like "thumb" or "small".
 * These cases represent the conventional sizes used in practice,
 * expressed as IIIF size strings ready for use in image URIs.
 *
 * URI pattern:
 *   {iiifBase}/full/{size}/0/default.jpg
 *
 * @see https://iiif.io/api/image/3.0/#42-size
 */
enum IiifSize: string
{
    /** Largest available without upscaling. Use for archival/download. */
    case MAX = 'max';

    /** Fit within 1200×1200, maintain aspect ratio. Good for full-page display. */
    case LARGE = '!1200,1200';

    /** Fit within 800×800, maintain aspect ratio. Good for detail views. */
    case MEDIUM = '!800,800';

    /** Fit within 400×400, maintain aspect ratio. Good for grid/list views. */
    case SMALL = '!400,400';

    /** Fit within 200×200, maintain aspect ratio. Standard thumbnail. */
    case THUMB = '!200,200';

    /** Fit within 100×100, maintain aspect ratio. Icon / avatar size. */
    case ICON = '!100,100';

    /**
     * Build a full IIIF image URL from a base URL and this size.
     *
     * @param string $iiifBase  Base URL up to and including the identifier,
     *                          e.g. https://iiif.example.org/iiif/2/commonwealth%3Aabc123
     * @param int    $rotation  Degrees clockwise (default 0)
     * @param string $quality   'default' | 'color' | 'gray' | 'bitonal'
     * @param string $format    'jpg' | 'png' | 'webp'
     */
    public function url(
        string $iiifBase,
        int    $rotation = 0,
        string $quality  = 'default',
        string $format   = 'jpg',
    ): string {
        return rtrim($iiifBase, '/') . '/full/' . $this->value . '/' . $rotation . '/' . $quality . '.' . $format;
    }

    /**
     * Build a size-confined URL from explicit pixel dimensions.
     * Uses the !w,h form (fit within bounding box, no distortion, no upscaling).
     */
    public static function confine(int $width, int $height): string
    {
        return sprintf('!%d,%d', $width, $height);
    }

    /**
     * Build a URL confined to a bounding box from explicit pixel dimensions.
     */
    public static function confineUrl(
        string $iiifBase,
        int    $width,
        int    $height,
        int    $rotation = 0,
        string $quality  = 'default',
        string $format   = 'jpg',
    ): string {
        return rtrim($iiifBase, '/') . '/full/' . self::confine($width, $height) . '/' . $rotation . '/' . $quality . '.' . $format;
    }
}
