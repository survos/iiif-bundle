<?php

declare(strict_types=1);

namespace Survos\IiifBundle\Twig;

use Survos\IiifBundle\Enum\IiifSize;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Twig helpers for IIIF Image API URL construction.
 *
 * Functions:
 *   iiif_url(base, size, rotation, quality, format)  → full image URL
 *   iiif_thumb(base, width, height)                  → !w,h confined URL
 *
 * Filters:
 *   |iiif_url(size)        → same as iiif_url()
 *   |iiif_thumb(w, h)      → same as iiif_thumb()
 *
 * Size may be:
 *   - an IiifSize case name string: 'thumb', 'small', 'medium', 'large', 'max'
 *   - a raw IIIF size string:       'max', '!200,200', '400,', ',300'
 *
 * Examples (Twig):
 *   {{ iiif_url(item.iiifBase, 'thumb') }}
 *   {{ iiif_url(item.iiifBase, 'medium') }}
 *   {{ item.iiifBase|iiif_url('small') }}
 *   {{ iiif_thumb(item.iiifBase, 300, 200) }}
 */
final class IiifExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('iiif_url',   $this->iiifUrl(...)),
            new TwigFunction('iiif_thumb', $this->iiifThumb(...)),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('iiif_url',   $this->iiifUrl(...)),
            new TwigFilter('iiif_thumb', $this->iiifThumb(...)),
        ];
    }

    /**
     * Build a IIIF image URL from a base URL and a size.
     *
     * @param string|null $base     IIIF Image API base (up to and including identifier)
     * @param string      $size     IiifSize case name ('thumb','small','medium','large','max')
     *                              or raw IIIF size string ('max','!200,200','400,',',300')
     * @param int         $rotation Degrees clockwise (default 0)
     * @param string      $quality  'default'|'color'|'gray'|'bitonal'
     * @param string      $format   'jpg'|'png'|'webp'
     */
    public function iiifUrl(
        ?string $base,
        string  $size     = 'thumb',
        int     $rotation = 0,
        string  $quality  = 'default',
        string  $format   = 'jpg',
    ): string {
        if ($base === null || $base === '') {
            return '';
        }

        $sizeStr = $this->resolveSize($size);

        return rtrim($base, '/') . '/full/' . $sizeStr . '/' . $rotation . '/' . $quality . '.' . $format;
    }

    /**
     * Build a IIIF URL confined to an explicit pixel bounding box (!w,h).
     * Maintains aspect ratio; never upscales; never distorts.
     */
    public function iiifThumb(
        ?string $base,
        int     $width,
        int     $height,
        int     $rotation = 0,
        string  $quality  = 'default',
        string  $format   = 'jpg',
    ): string {
        if ($base === null || $base === '') {
            return '';
        }

        return rtrim($base, '/') . '/full/!' . $width . ',' . $height . '/' . $rotation . '/' . $quality . '.' . $format;
    }

    // ── Private ──────────────────────────────────────────────────────────────

    /**
     * Resolve a size argument to a raw IIIF size string.
     * Accepts IiifSize case names (case-insensitive) or raw strings.
     */
    private function resolveSize(string $size): string
    {
        // Try to match an IiifSize case by name (case-insensitive)
        $upper = strtoupper($size);
        foreach (IiifSize::cases() as $case) {
            if ($case->name === $upper) {
                return $case->value;
            }
        }

        // Already a raw IIIF size string — pass through
        return $size;
    }
}
