<?php

declare(strict_types=1);

namespace Survos\IiifBundle\Tests;

use PHPUnit\Framework\TestCase;
use Survos\IiifBundle\Builder\ManifestBuilder;
use Survos\IiifBundle\Enum\Behavior;
use Survos\IiifBundle\Enum\ViewingDirection;
use Survos\IiifBundle\Model\ImageService3;

final class ManifestBuilderTest extends TestCase
{
    public function testCreateBasicManifest(): void
    {
        $builder = new ManifestBuilder('https://example.org/manifest');

        $manifest = $builder->getManifest();

        $this->assertSame('https://example.org/manifest', $manifest->id);
        $this->assertSame('Manifest', $manifest->type);
    }

    public function testSetLabel(): void
    {
        $builder = new ManifestBuilder('https://example.org/manifest');
        $builder->setLabel('en', 'Test Label');

        $json = $builder->toJson();
        $array = json_decode($json, true);

        $this->assertSame(['en' => ['Test Label']], $array['label']);
    }

    public function testSetSummary(): void
    {
        $builder = new ManifestBuilder('https://example.org/manifest');
        $builder->setSummary('en', 'Test Summary');

        $json = $builder->toJson();
        $array = json_decode($json, true);

        $this->assertSame(['en' => ['Test Summary']], $array['summary']);
    }

    public function testAddMetadata(): void
    {
        $builder = new ManifestBuilder('https://example.org/manifest');
        $builder->addMetadata('en', 'Date', 'en', '2024');

        $json = $builder->toJson();
        $array = json_decode($json, true);

        $this->assertCount(1, $array['metadata']);
        $this->assertSame(['en' => ['Date']], $array['metadata'][0]['label']);
        $this->assertSame(['en' => ['2024']], $array['metadata'][0]['value']);
    }

    public function testSetRights(): void
    {
        $builder = new ManifestBuilder('https://example.org/manifest');
        $builder->setRights('http://creativecommons.org/publicdomain/mark/1.0/');

        $json = $builder->toJson();
        $array = json_decode($json, true);

        $this->assertSame('http://creativecommons.org/publicdomain/mark/1.0/', $array['rights']);
    }

    public function testSetRequiredStatement(): void
    {
        $builder = new ManifestBuilder('https://example.org/manifest');
        $builder->setRequiredStatement('en', 'Attribution', 'Test Attribution');

        $json = $builder->toJson();
        $array = json_decode($json, true);

        $this->assertSame(['en' => ['Attribution']], $array['requiredStatement']['label']);
        $this->assertSame(['en' => ['Test Attribution']], $array['requiredStatement']['value']);
    }

    public function testSetViewingDirection(): void
    {
        $builder = new ManifestBuilder('https://example.org/manifest');
        $builder->setViewingDirection(ViewingDirection::LEFT_TO_RIGHT);

        $json = $builder->toJson();
        $array = json_decode($json, true);

        $this->assertArrayHasKey('metadata', $array);
    }

    public function testSetBehavior(): void
    {
        $builder = new ManifestBuilder('https://example.org/manifest');
        $builder->setBehavior(Behavior::PAGED);

        $json = $builder->toJson();
        $array = json_decode($json, true);

        $this->assertArrayHasKey('metadata', $array);
    }

    public function testAddCanvas(): void
    {
        $builder = new ManifestBuilder('https://example.org/manifest');
        $canvas = $builder->addCanvas(
            id: 'https://example.org/canvas/1',
            label: 'Page 1',
            width: 1000,
            height: 1500,
        );

        $this->assertSame('https://example.org/canvas/1', $canvas->id);
        $this->assertSame(1000, $canvas->width);
        $this->assertSame(1500, $canvas->height);

        $json = $builder->toJson();
        $array = json_decode($json, true);

        $this->assertCount(1, $array['items']);
        $this->assertSame('https://example.org/canvas/1', $array['items'][0]['id']);
    }

    public function testCanvasWithImage(): void
    {
        $builder = new ManifestBuilder('https://example.org/manifest');
        $canvas = $builder->addCanvas(
            id: 'https://example.org/canvas/1',
            label: 'Page 1',
            width: 1000,
            height: 1500,
        );

        $canvas->addImage(
            annotationId: 'https://example.org/anno/1',
            imageUrl: 'https://example.org/image1.jpg',
            format: 'image/jpeg',
            width: 1000,
            height: 1500,
            service: new ImageService3(
                id: 'https://example.org/iiif/1',
                profile: 'level2'
            ),
        );

        $json = $builder->toJson();
        $array = json_decode($json, true);

        $this->assertCount(1, $array['items'][0]['items']);
        $annotationPage = $array['items'][0]['items'][0];
        $this->assertSame('AnnotationPage', $annotationPage['type']);
        $this->assertCount(1, $annotationPage['items']);
        $this->assertSame('painting', $annotationPage['items'][0]['motivation']);
    }

    public function testAddSupplementingText(): void
    {
        $builder = new ManifestBuilder('https://example.org/manifest');
        $canvas = $builder->addCanvas(
            id: 'https://example.org/canvas/1',
            label: 'Page 1',
            width: 1000,
            height: 1500,
        );

        $canvas->addSupplementingText(
            annotationId: 'https://example.org/ocr/1',
            text: 'OCR text content',
            language: 'en',
        );

        $json = $builder->toJson();
        $array = json_decode($json, true);

        $this->assertCount(1, $array['items'][0]['annotations']);
        $annotationPage = $array['items'][0]['annotations'][0];
        $this->assertSame('supplementing', $annotationPage['items'][0]['motivation']);
    }

    public function testAddWordAnnotation(): void
    {
        $builder = new ManifestBuilder('https://example.org/manifest');
        $canvas = $builder->addCanvas(
            id: 'https://example.org/canvas/1',
            label: 'Page 1',
            width: 1000,
            height: 1500,
        );

        $canvas->addWordAnnotation(
            annotationId: 'https://example.org/word/1',
            text: 'test',
            language: 'en',
            x: 100,
            y: 200,
            width: 50,
            height: 20,
        );

        $json = $builder->toJson();
        $array = json_decode($json, true);

        $annotation = $array['items'][0]['annotations'][0]['items'][0];
        $this->assertStringContainsString('xywh=100,200,50,20', $annotation['target']);
    }

    public function testAddSearchService(): void
    {
        $builder = new ManifestBuilder('https://example.org/manifest');
        $builder->addSearchService('https://example.org/search');

        $json = $builder->toJson();
        $array = json_decode($json, true);

        $this->assertArrayHasKey('service', $array);
        $this->assertSame('SearchService2', $array['service'][0]['type']);
    }

    public function testToJson(): void
    {
        $builder = new ManifestBuilder('https://example.org/manifest');
        $builder->setLabel('en', 'Test Manifest');

        $json = $builder->toJson();

        $decoded = json_decode($json, true);
        $this->assertSame('https://example.org/manifest', $decoded['id']);
    }

    public function testContextIncluded(): void
    {
        $builder = new ManifestBuilder('https://example.org/manifest');

        $json = $builder->toJson();
        $array = json_decode($json, true);

        $this->assertArrayHasKey('@context', $array);
        $this->assertSame('http://iiif.io/api/presentation/3/context.json', $array['@context']);
    }
}
