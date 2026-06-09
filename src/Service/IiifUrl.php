<?php

declare(strict_types=1);

namespace Survos\IiifBundle\Service;

/**
 * Resolve a stored `iiifBase` / source-image URL into a fetchable image URL.
 *
 * `iiifBase` is overloaded across the pipeline: for genuine IIIF sources it is a
 * IIIF Image API base (append `/full/{size}/0/default.jpg`), but for most sources
 * it is simply the largest non-tiff source image handed to imgproxy. Deciding
 * which is which by file extension is unreliable — e.g. Smithsonian IDS delivery
 * URLs (`…/deliveryService?id=X`) have no extension yet are direct images. This is
 * the single place that makes that call; consumers must not re-implement it.
 */
final class IiifUrl
{
    /** A real IIIF Image API endpoint that accepts `/full/{region}/{size}/…` segments. */
    public static function isImageApiEndpoint(?string $url): bool
    {
        return $url !== null
            && $url !== ''
            && (str_contains($url, '/iiif/') || str_ends_with($url, '/info.json'));
    }

    /**
     * Fetchable image URL for a stored iiifBase/source image. Real IIIF endpoints
     * get the size segment appended; direct image URLs (including extensionless
     * ones) are returned verbatim for imgproxy to resize.
     *
     * @param string $size IIIF size segment, e.g. "max", "900,", "!300,300".
     */
    public static function imageUrl(?string $base, string $size = 'max'): ?string
    {
        if ($base === null || $base === '') {
            return null;
        }

        return self::isImageApiEndpoint($base)
            ? rtrim($base, '/') . '/full/' . $size . '/0/default.jpg'
            : $base;
    }
}
