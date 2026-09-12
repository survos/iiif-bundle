<?php

declare(strict_types=1);

namespace Survos\IiifBundle\Tests;

use PHPUnit\Framework\TestCase;
use Survos\IiifBundle\Service\IiifUrl;

final class IiifUrlTest extends TestCase
{
    public function testAdvertisedJpegIsNeverRewritten(): void
    {
        $images = [
            ['url' => 'https://example.org/a.jp2', 'format' => 'image/jp2', 'width' => 9000],
            ['url' => 'https://example.org/full/pct:3.125/0/default.jpg', 'format' => 'image/jpeg', 'width' => 200],
            ['url' => 'https://example.org/full/pct:12.5/0/default.jpg?token=public', 'format' => 'image/jpeg', 'width' => 800],
        ];
        self::assertSame($images[2]['url'], IiifUrl::preferredImage($images));
        self::assertSame($images[1]['url'], IiifUrl::preferredImage($images, width: 150));
        self::assertSame($images[2]['url'], IiifUrl::preferredImage($images, width: 400));
    }

    public function testLocV2UsesFullAndRespectsLiveDimensions(): void
    {
        $info = json_decode(file_get_contents(__DIR__.'/fixtures/loc-info.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertStringEndsWith('/full/full/0/default.jpg', IiifUrl::fromInfo($info));
        self::assertStringEndsWith('/full/300,/0/default.jpg', IiifUrl::fromInfo($info, 300));
        self::assertSame(['width' => 5274, 'height' => 6659], IiifUrl::dimensions($info));
    }

    public function testV3NeverUpscalesAndHonorsServiceLimits(): void
    {
        $info = ['id' => 'https://example.org/image', 'type' => 'ImageService3', 'profile' => 'level2', 'width' => 1000, 'height' => 2000];
        self::assertStringEndsWith('/full/max/0/default.jpg', IiifUrl::fromInfo($info, 9000));
        self::assertStringEndsWith('/full/200,/0/default.jpg', IiifUrl::fromInfo($info + ['maxWidth' => 400], 600));
        self::assertStringEndsWith('/full/500,/0/default.jpg', IiifUrl::fromInfo($info + ['maxArea' => 500000], 900));
    }

    public function testLevelZeroUsesOnlyAdvertisedSizes(): void
    {
        $info = ['@context' => 'http://iiif.io/api/image/2/context.json', '@id' => 'https://example.org/image',
            'profile' => 'http://iiif.io/api/image/2/level0.json', 'width' => 1000, 'height' => 2000,
            'sizes' => [['width' => 100, 'height' => 200], ['width' => 300, 'height' => 600]]];
        self::assertSame('https://example.org/image/full/300,600/0/default.jpg', IiifUrl::fromInfo($info, 250));
    }

    /**
     * `sizes` is mandatory for level 0 but only a hint for level 1/2, where it is commonly a short
     * list of small derivatives. Honouring it on a scaling service silently downgraded every page:
     * IAI's Goobi advertises a single 1000x1480 entry for a 1976x2924 scan, so captures lost half
     * their resolution with no error anywhere. A service that can scale must serve the full canvas.
     */
    public function testLevelTwoIgnoresSmallAdvertisedSizes(): void
    {
        $info = ['@context' => 'http://iiif.io/api/image/2/context.json',
            '@id' => 'https://example.org/image',
            'profile' => ['http://iiif.io/api/image/2/level2.json'],
            'width' => 1976, 'height' => 2924,
            'sizes' => [['width' => 1000, 'height' => 1480]]];
        self::assertSame('https://example.org/image/full/full/0/default.jpg', IiifUrl::fromInfo($info));
        self::assertSame('https://example.org/image/full/600,/0/default.jpg', IiifUrl::fromInfo($info, 600));
    }

    /** sizeByW alone is enough to scale, even without a level in the profile. */
    public function testSizeByWSupportIgnoresAdvertisedSizes(): void
    {
        $info = ['@context' => 'http://iiif.io/api/image/2/context.json',
            '@id' => 'https://example.org/image',
            'profile' => ['http://iiif.io/api/image/2/level0.json', ['supports' => ['sizeByW']]],
            'width' => 1000, 'height' => 2000,
            'sizes' => [['width' => 100, 'height' => 200]]];
        self::assertSame('https://example.org/image/full/full/0/default.jpg', IiifUrl::fromInfo($info));
    }

    public function testQueryTokensStayAfterTheImagePath(): void
    {
        $info = ['id' => 'https://example.org/image?token=public', 'type' => 'ImageService3', 'width' => 10, 'height' => 20];
        self::assertSame('https://example.org/image/full/max/0/default.jpg?token=public', IiifUrl::fromInfo($info));
    }

    public function testMissingVersionDoesNotGuess(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        IiifUrl::fromInfo(['id' => 'https://example.org/image', 'width' => 100, 'height' => 200]);
    }

    public function testLegacyInfoSuffixIsNotTreatedAsAnIdentifier(): void
    {
        self::assertSame('https://example.org/iiif/image/full/300,/0/default.jpg', IiifUrl::imageUrl('https://example.org/iiif/image/info.json', '300,'));
        self::assertSame('https://example.org/direct.jpg', IiifUrl::imageUrl('https://example.org/direct.jpg'));
    }
}
