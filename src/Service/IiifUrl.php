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
    /**
     * A real IIIF Image API endpoint that accepts `/full/{region}/{size}/…` segments.
     *
     * Detected by a `/iiif/` path segment, an `info.json` suffix, OR an `iiif.` host
     * label (`https://iiif.onb.ac.at/images/AKON/…` — the dedicated-subdomain hosting
     * convention, where "iiif" never appears in the path). Without the host check,
     * ONB bases were passed verbatim to imgproxy, which then failed with
     * "Invalid Source Image" on what was a directory URL, not an image.
     */
    public static function isImageApiEndpoint(?string $url): bool
    {
        if ($url === null || $url === '') {
            return false;
        }
        if (str_contains($url, '/iiif/') || str_ends_with($url, '/info.json')) {
            return true;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && (str_starts_with($host, 'iiif.') || $host === 'iiif');
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

        // Already a resolved Image API request (or a direct image file) — e.g. an
        // iiif.-host URL that ends …/full/full/0/native.jpg. Appending another size
        // segment would double it, so pass through verbatim.
        if (preg_match('~/(full|square|\d+,\d+,\d+,\d+)/[^/]+/-?\d+(?:\.\d+)?/\w+\.(jpg|jpeg|png|gif|webp|tif)$~i', $base)
            || preg_match('~\.(jpg|jpeg|png|gif|webp)([?#]|$)~i', $base)) {
            return $base;
        }

        return self::isImageApiEndpoint($base)
            ? rtrim($base, '/') . '/full/' . $size . '/0/default.jpg'
            : $base;
    }
}
