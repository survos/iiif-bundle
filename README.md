# SurvosIiifBundle

PHP 8.4+ Symfony bundle for generating IIIF Presentation API 3.0 manifests.

@todo: make sure it works with https://manuscrits-france-angleterre.org/view3if/?target=https://gallica.bnf.fr/iiif/ark:/12148/bpt6k9907264/manifest.json&page=2

## Installation

```bash
composer require survos/iiif-bundle
```

## Quick Start

```php
use Survos\IiifBundle\Builder\ManifestBuilder;
use Survos\IiifBundle\Model\ImageService3;
use Survos\IiifBundle\Enum\ViewingDirection;
use Survos\IiifBundle\Enum\Behavior;

$builder = new ManifestBuilder('https://example.org/iiif/item-123/manifest');

$builder
    ->setLabel('en', 'Civil War Pension File — Pvt. James Wilson')
    ->setSummary('en', 'Pension application including affidavits and medical examination, 1892')
    ->addMetadata('en', 'Date', 'en', '1892')
    ->addMetadata('en', 'Creator', 'en', 'U.S. Pension Bureau')
    ->setRights('http://creativecommons.org/publicdomain/mark/1.0/')
    ->setRequiredStatement('en', 'Attribution', 'Courtesy of Carver 4-County Museum')
    ->setViewingDirection(ViewingDirection::LEFT_TO_RIGHT)
    ->setBehavior(Behavior::PAGED);

// Add pages as canvases
foreach ($pages as $i => $page) {
    $canvas = $builder->addCanvas(
        id: "https://example.org/iiif/item-123/canvas/p{$i}",
        label: "Page {$i}",
        width: $page->getWidth(),
        height: $page->getHeight(),
    );

    // Paint the image onto the canvas
    $canvas->addImage(
        annotationId: "https://example.org/iiif/item-123/canvas/p{$i}/anno/image",
        imageUrl: "https://s3.example.org/scans/item-123/page-{$i}.jpg",
        format: 'image/jpeg',
        width: $page->getWidth(),
        height: $page->getHeight(),
        service: new ImageService3(
            id: "https://iiif.example.org/image/item-123-page-{$i}",
            profile: 'level2'
        ),
    );

    // Add full-page OCR text as supplementing annotation
    $canvas->addSupplementingText(
        annotationId: "https://example.org/iiif/item-123/canvas/p{$i}/ocr/fulltext",
        text: $page->getOcrText(),
        language: 'en',
    );

    // Add word-level OCR annotations for search highlighting
    foreach ($page->getOcrWords() as $word) {
        $canvas->addWordAnnotation(
            annotationId: "https://example.org/iiif/item-123/canvas/p{$i}/ocr/word-" . $word->getId(),
            text: $word->getText(),
            language: 'en',
            x: $word->getX(),
            y: $word->getY(),
            width: $word->getWidth(),
            height: $word->getHeight(),
        );
    }
}

// Add Content Search service (Meilisearch-backed)
$builder->addSearchService('https://example.org/iiif/item-123/search');

// Serialize to JSON
$json = $builder->toJson();
$array = $builder->toArray();
```

## Output Example

```json
{
    "@context": "http://iiif.io/api/presentation/3/context.json",
    "id": "https://example.org/iiif/item-123/manifest",
    "type": "Manifest",
    "label": { "en": ["Civil War Pension File — Pvt. James Wilson"] },
    "summary": { "en": ["Pension application including affidavits and medical examination, 1892"] },
    "metadata": [
        { "label": { "en": ["Date"] }, "value": { "en": ["1892"] } },
        { "label": { "en": ["Creator"] }, "value": { "en": ["U.S. Pension Bureau"] } }
    ],
    "rights": "http://creativecommons.org/publicdomain/mark/1.0/",
    "requiredStatement": {
        "label": { "en": ["Attribution"] },
        "value": { "en": ["Courtesy of Carver 4-County Museum"] }
    },
    "items": [
        {
            "id": "https://example.org/iiif/item-123/canvas/p1",
            "type": "Canvas",
            "width": 3000,
            "height": 4000,
            "items": [
                {
                    "id": "https://example.org/iiif/item-123/canvas/p1/anno/image/page",
                    "type": "AnnotationPage",
                    "items": [
                        {
                            "id": "https://example.org/iiif/item-123/canvas/p1/anno/image",
                            "type": "Annotation",
                            "motivation": "painting",
                            "body": {
                                "id": "https://s3.example.org/scans/item-123/page-1.jpg",
                                "type": "Image",
                                "format": "image/jpeg",
                                "width": 3000,
                                "height": 4000,
                                "service": [
                                    {
                                        "id": "https://iiif.example.org/image/item-123-page-1",
                                        "type": "ImageService3",
                                        "profile": "level2"
                                    }
                                ]
                            },
                            "target": "https://example.org/iiif/item-123/canvas/p1"
                        }
                    ]
                }
            ],
            "annotations": [
                {
                    "id": "https://example.org/iiif/item-123/canvas/p1/ocr/fulltext/page",
                    "type": "AnnotationPage",
                    "items": [
                        {
                            "id": "https://example.org/iiif/item-123/canvas/p1/ocr/fulltext",
                            "type": "Annotation",
                            "motivation": "supplementing",
                            "body": {
                                "type": "TextualBody",
                                "value": "The full OCR text of page 1...",
                                "language": "en",
                                "format": "text/plain"
                            },
                            "target": "https://example.org/iiif/item-123/canvas/p1"
                        }
                    ]
                }
            ]
        }
    ],
    "service": [
        {
            "id": "https://example.org/iiif/item-123/search",
            "type": "SearchService2",
            "profile": "http://iiif.io/api/search/2/service"
        }
    ]
}
```

## Model Classes

All model classes implement `JsonSerializable` and use public properties with no boilerplate getters/setters.

### Core Resources

- `AbstractResource` - Base class with common properties (id, type, label, summary, metadata, rights, etc.)
- `Manifest` - IIIF Manifest with items (Canvases) and structures (Ranges)
- `Collection` - Collection of Manifests or other Collections
- `Canvas` - A canvas that contains annotations (images, OCR text)
- `Range` - For table of contents / structural navigation (TOC)
- `AnnotationPage` - Container for Annotations
- `Annotation` - W3C Web Annotation with motivation, body, and target

### Content/Body Classes

- `ResourceItem` - Image, Video, Audio with id, type, format, service
- `TextualBody` - Inline text content (OCR text, descriptions)

### Supporting Classes

- `Service` - IIIF Service reference
- `ImageService3` - Convenience class for Image Service 3
- `Thumbnail` - Simplified image reference
- `LabelMap` - Helper for language map construction
- `MetadataEntry` - label/value pair for metadata

### Enums

- `Motivation` - painting, supplementing, commenting, tagging, etc.
- `ViewingDirection` - left-to-right, right-to-left, top-to-bottom, bottom-to-top
- `Behavior` - paged, continuous, individuals, auto-advance, etc.

## Manual Construction

You can also construct manifests manually without using the builder:

```php
use Survos\IiifBundle\Model\Manifest;
use Survos\IiifBundle\Model\Canvas;
use Survos\IiifBundle\Model\AnnotationPage;
use Survos\IiifBundle\Model\Annotation;
use Survos\IiifBundle\Model\ResourceItem;
use Survos\IiifBundle\Model\LabelMap;
use Survos\IiifBundle\Enum\Motivation;

$manifest = Manifest::create('https://example.org/manifest');
$manifest->setLabel('en', 'My Document');

$canvas = Canvas::create(
    'https://example.org/canvas/1',
    LabelMap::create('en', 'Page 1'),
    3000,
    4000
);

$annotation = Annotation::createPainting(
    'https://example.org/annotation/1',
    ResourceItem::createImage('https://example.org/image1.jpg', 'image/jpeg', 3000, 4000),
    'https://example.org/canvas/1'
);

$annotationPage = AnnotationPage::create('https://example.org/annopage/1');
$annotationPage->addItem($annotation);
$canvas->addItem($annotationPage);

$manifest->addItem($canvas);

$json = json_encode(['@context' => 'http://iiif.io/api/presentation/3/context.json'] + $manifest->jsonSerialize(), JSON_PRETTY_PRINT);
```

## Requirements

- PHP 8.4+
- Symfony 7.3+ / 8.0+

## References

- [IIIF Presentation API 3.0](https://iiif.io/api/presentation/3.0/)
- [IIIF Cookbook Recipes](https://iiif.io/api/cookbook/)
- [iiif-prezi3 Python library](https://github.com/iiif-prezi/iiif-prezi3)
