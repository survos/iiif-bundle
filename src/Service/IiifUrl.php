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
     * Prefer a provider-advertised JPEG over synthesizing a request. Caller adapts source
     * metadata to {url, format, width?}; no LOC/IA-specific fields belong in this bundle.
     * With no requested width, select the largest advertised JPEG.
     */
    public static function preferredImage(array $images, ?array $info = null, ?int $width = null): string
    {
        $jpegs = array_values(array_filter($images, static fn (array $image) =>
            ($image['format'] ?? null) === 'image/jpeg' && isset($image['url'])));
        usort($jpegs, static fn ($a, $b) => ($a['width'] ?? 0) <=> ($b['width'] ?? 0));
        if ($width !== null) {
            if ($width < 1) { throw new \InvalidArgumentException('Image width must be positive.'); }
            foreach ($jpegs as $jpeg) {
                if (isset($jpeg['width']) && $jpeg['width'] >= $width) { return $jpeg['url']; }
            }
        }
        if ($jpegs !== []) { return $jpegs[array_key_last($jpegs)]['url']; }
        if ($info === null) { throw new \UnexpectedValueException('No advertised JPEG or image-service information.'); }
        return self::fromInfo($info, $width);
    }

    /** Authoritative full-canvas dimensions, independent of an advertised thumbnail's size. */
    public static function dimensions(array $info): array
    {
        if (!isset($info['width'], $info['height']) || !is_int($info['width']) || !is_int($info['height'])
            || $info['width'] < 1 || $info['height'] < 1) {
            throw new \UnexpectedValueException('IIIF information requires positive integer width and height.');
        }
        return ['width' => $info['width'], 'height' => $info['height']];
    }

    /**
     * Image API 2/3 info.json, respecting advertised sizes and service limits. No network I/O.
     * Version 2 uses the older full spelling for compatibility (LOC rejects max despite its
     * v2 profile); version 3 uses max. Unsupported versions fail instead of guessing.
     */
    public static function fromInfo(array $info, ?int $requestedWidth = null): string
    {
        $dimensions = self::dimensions($info);
        if ($requestedWidth !== null && $requestedWidth < 1) { throw new \InvalidArgumentException('Image width must be positive.'); }
        $context = implode(' ', (array) ($info['@context'] ?? []));
        $version = match (true) {
            ($info['type'] ?? null) === 'ImageService3', str_contains($context, '/image/3/') => 3,
            ($info['type'] ?? null) === 'ImageService2', str_contains($context, '/image/2/') => 2,
            default => throw new \UnexpectedValueException('Unsupported or absent IIIF Image API version.'),
        };
        $base = $info['id'] ?? $info['@id'] ?? throw new \UnexpectedValueException('Missing IIIF service identifier.');
        $profile = $info['profile'] ?? [];
        $details = [];
        foreach ((array) $profile as $part) { if (is_array($part)) { $details = array_replace($details, $part); } }
        $limits = array_replace($details, $info);
        $w = $dimensions['width']; $h = $dimensions['height'];
        $factor = min(1.0, ($requestedWidth ?? $w) / $w);
        if (isset($limits['maxWidth'])) { $factor = min($factor, $limits['maxWidth'] / $w); }
        if (isset($limits['maxHeight'])) { $factor = min($factor, $limits['maxHeight'] / $h); }
        elseif ($version === 3 && isset($limits['maxWidth'])) { $factor = min($factor, $limits['maxWidth'] / $h); }
        if (isset($limits['maxArea'])) { $factor = min($factor, sqrt($limits['maxArea'] / ($w * $h))); }
        $target = max(1, (int) floor($w * $factor));
        $size = $version === 3 ? 'max' : 'full';
        $features = array_merge($details['supports'] ?? [], $info['extraFeatures'] ?? []);
        $profileText = json_encode($profile, JSON_THROW_ON_ERROR);
        $canScale = str_contains($profileText, 'level1') || str_contains($profileText, 'level2') || in_array('sizeByW', $features, true);
        // Level-0/static services may only support listed sizes. Use those exact pairs.
        //
        // `sizes` is REQUIRED for level 0 but only a hint for level 1/2, where it is routinely a
        // short list of small derivatives. Honouring it unconditionally silently downgraded a
        // scaling service to its largest hint — IAI advertises one 1000x1480 entry for a
        // 1976x2924 page, so every captured scan lost half its resolution with no error. When the
        // service can scale, the computed target wins and `sizes` is ignored.
        if (!empty($info['sizes']) && !$canScale) {
            $sizes = $info['sizes'];
            usort($sizes, static fn ($a, $b) => $a['width'] <=> $b['width']);
            $selected = $sizes[array_key_last($sizes)];
            foreach ($sizes as $candidate) {
                if ($candidate['width'] >= $target) { $selected = $candidate; break; }
            }
            $size = $selected['width'].','.$selected['height'];
        } elseif ($factor < 1) {
            if ($canScale) { $size = $target.','; }
            elseif ($version === 2 && $factor < 1 && (isset($limits['maxWidth']) || isset($limits['maxHeight']) || isset($limits['maxArea']))) {
                throw new \UnexpectedValueException('Service limits require a size not advertised by this profile.');
            }
        }
        // A service identifier can carry a query token. Keep it after the image path.
        [$path, $query] = array_pad(explode('?', $base, 2), 2, null);
        $path = preg_replace('~/info\.json/?$~', '', $path);
        return rtrim($path, '/').'/full/'.$size.'/0/default.jpg'.($query === null ? '' : '?'.$query);
    }

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
            ? preg_replace('~/info\.json$~', '', rtrim($base, '/')) . '/full/' . $size . '/0/default.jpg'
            : $base;
    }
}
