<?php

declare(strict_types=1);

namespace Survos\IiifBundle\Tests;

use PHPUnit\Framework\TestCase;
use Survos\IiifBundle\Enum\Motivation;
use Survos\IiifBundle\Model\Annotation;
use Survos\IiifBundle\Model\AnnotationPage;
use Survos\IiifBundle\Model\Canvas;
use Survos\IiifBundle\Model\Collection;
use Survos\IiifBundle\Model\LabelMap;
use Survos\IiifBundle\Model\Manifest;
use Survos\IiifBundle\Model\MetadataEntry;
use Survos\IiifBundle\Model\Range;
use Survos\IiifBundle\Model\ResourceItem;
use Survos\IiifBundle\Model\Service;
use Survos\IiifBundle\Model\TextualBody;

final class ModelClassesTest extends TestCase
{
    public function testManifestCreation(): void
    {
        $manifest = Manifest::create('https://example.org/manifest');

        $this->assertSame('https://example.org/manifest', $manifest->id);
        $this->assertSame('Manifest', $manifest->type);
    }

    public function testManifestJsonSerialize(): void
    {
        $manifest = Manifest::create('https://example.org/manifest');
        $manifest->setLabel('en', 'Test');

        $json = json_encode($manifest);
        $decoded = json_decode($json, true);

        $this->assertSame('https://example.org/manifest', $decoded['id']);
        $this->assertSame('Manifest', $decoded['type']);
        $this->assertSame(['en' => ['Test']], $decoded['label']);
    }

    public function testCanvasCreation(): void
    {
        $canvas = Canvas::create(
            'https://example.org/canvas/1',
            LabelMap::create('en', 'Page 1'),
            1000,
            1500
        );

        $this->assertSame('https://example.org/canvas/1', $canvas->id);
        $this->assertSame(1000, $canvas->width);
        $this->assertSame(1500, $canvas->height);
    }

    public function testCanvasJsonSerialize(): void
    {
        $canvas = Canvas::create(
            'https://example.org/canvas/1',
            LabelMap::create('en', 'Page 1'),
            1000,
            1500
        );

        $json = json_encode($canvas);
        $decoded = json_decode($json, true);

        $this->assertSame('Canvas', $decoded['type']);
        $this->assertSame(1000, $decoded['width']);
        $this->assertSame(1500, $decoded['height']);
    }

    public function testCollectionCreation(): void
    {
        $collection = Collection::create('https://example.org/collection');

        $this->assertSame('https://example.org/collection', $collection->id);
        $this->assertSame('Collection', $collection->type);
    }

    public function testRangeCreation(): void
    {
        $range = Range::create('https://example.org/range/1');

        $this->assertSame('https://example.org/range/1', $range->id);
        $this->assertSame('Range', $range->type);
    }

    public function testAnnotationPageCreation(): void
    {
        $page = AnnotationPage::create('https://example.org/annopage/1');

        $this->assertSame('https://example.org/annopage/1', $page->id);
    }

    public function testAnnotationPainting(): void
    {
        $resource = ResourceItem::createImage('https://example.org/image.jpg', 'image/jpeg', 1000, 1500);
        $annotation = Annotation::createPainting(
            'https://example.org/anno/1',
            $resource,
            'https://example.org/canvas/1'
        );

        $this->assertSame(Motivation::PAINTING, $annotation->motivation);
        $this->assertSame('https://example.org/canvas/1', $annotation->target);
    }

    public function testAnnotationSupplementing(): void
    {
        $body = TextualBody::create('OCR text', 'en');
        $annotation = Annotation::createSupplementing(
            'https://example.org/anno/1',
            $body,
            'https://example.org/canvas/1'
        );

        $this->assertSame(Motivation::SUPPLEMENTING, $annotation->motivation);
    }

    public function testAnnotationJsonSerialize(): void
    {
        $resource = ResourceItem::createImage('https://example.org/image.jpg', 'image/jpeg', 1000, 1500);
        $annotation = Annotation::createPainting(
            'https://example.org/anno/1',
            $resource,
            'https://example.org/canvas/1'
        );

        $json = json_encode($annotation);
        $decoded = json_decode($json, true);

        $this->assertSame('painting', $decoded['motivation']);
        $this->assertSame('Image', $decoded['body']['type']);
    }

    public function testTextualBody(): void
    {
        $body = TextualBody::create('Hello World', 'en');

        $json = json_encode($body);
        $decoded = json_decode($json, true);

        $this->assertSame('TextualBody', $decoded['type']);
        $this->assertSame('Hello World', $decoded['value']);
        $this->assertSame('en', $decoded['language']);
        $this->assertSame('text/plain', $decoded['format']);
    }

    public function testResourceItemWithService(): void
    {
        $resource = ResourceItem::createImage('https://example.org/image.jpg', 'image/jpeg', 1000, 1500);
        $resource->addService(new \Survos\IiifBundle\Model\ImageService3('https://example.org/iiif/1'));

        $json = json_encode($resource);
        $decoded = json_decode($json, true);

        $this->assertCount(1, $decoded['service']);
        $this->assertSame('ImageService3', $decoded['service'][0]['type']);
    }

    public function testServiceCreation(): void
    {
        $service = Service::create('https://example.org/service', 'ImageService3', 'level2');

        $json = json_encode($service);
        $decoded = json_decode($json, true);

        $this->assertSame('https://example.org/service', $decoded['id']);
        $this->assertSame('ImageService3', $decoded['type']);
        $this->assertSame('level2', $decoded['profile']);
    }

    public function testMetadataEntry(): void
    {
        $entry = MetadataEntry::create('en', 'Date', 'en', '2024');

        $json = json_encode($entry);
        $decoded = json_decode($json, true);

        $this->assertSame(['en' => ['Date']], $decoded['label']);
        $this->assertSame(['en' => ['2024']], $decoded['value']);
    }

    public function testFullManifestSerialization(): void
    {
        $manifest = Manifest::create('https://example.org/manifest');
        $manifest->setLabel('en', 'Test Document');

        $canvas = Canvas::create('https://example.org/canvas/1', LabelMap::create('en', 'Page 1'), 1000, 1500);
        $manifest->addItem($canvas);

        $range = Range::create('https://example.org/range/1');
        $range->setLabel('en', 'Table of Contents');
        $manifest->addStructure($range);

        $json = json_encode(['@context' => 'http://iiif.io/api/presentation/3/context.json'] + $manifest->jsonSerialize(), JSON_PRETTY_PRINT);

        $this->assertStringContainsString('Test Document', $json);
        $this->assertStringContainsString('Canvas', $json);
        $this->assertStringContainsString('Range', $json);
    }
}
