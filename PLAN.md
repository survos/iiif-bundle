# survos/iiif-bundle — Symfony Bundle for IIIF Presentation API 3.0

## Purpose

A PHP 8.2+ / Symfony 7+ bundle for **generating** IIIF Presentation API 3.0 manifests, canvases, annotations, and collections. Primary use case: producing manifests for scanned multi-page documents with OCR text annotations, AI-generated descriptions, and image services — as part of the ScanStationAI/Scanseum/Mediary pipeline.

There is **no standalone PHP library** for generating Presentation API v3 manifests. This bundle fills that gap.

## Target IIIF Specifications

### Primary (must implement)

- **Presentation API 3.0** — the core manifest/canvas/annotation model
  - Spec: https://iiif.io/api/presentation/3.0/
  - JSON-LD context: `http://iiif.io/api/presentation/3/context.json`
  - Change log from v2→v3: https://iiif.io/api/presentation/3.0/change-log/

- **W3C Web Annotation Data Model** — used for all annotations in Prezi 3
  - Spec: https://www.w3.org/TR/annotation-model/
  - Replaces Open Annotation from Prezi 2

- **Image API 3.0** — for referencing image services (not serving images, just generating the service JSON)
  - Spec: https://iiif.io/api/image/3.0/

### Secondary (should implement)

- **Content Search API 2.0** — for search-within-document (OCR search)
  - Spec: https://iiif.io/api/search/2.0/
  - Aligned with Prezi 3 / Web Annotations

- **Text Granularity Extension** — for indicating OCR granularity (word, line, paragraph, page)
  - Spec: https://iiif.io/api/extension/text-granularity/

### Not in scope (for now)

- Image API tile serving (handled by imgproxy)
- Authorization Flow API
- Change Discovery API
- Content State API

## Key Differences from Presentation API v2

The Yale PHP generator and most existing PHP code targets v2. These are the breaking changes an agent must understand:

- `@id` → `id`, `@type` → `type` (no more @ prefix)
- **Sequences removed** — Manifest `items` is now a flat array of Canvases
- `label`, `summary`, `metadata` values use **language maps**: `{"en": ["Page 1"]}` not plain strings
- `description` → `summary`
- `license` → `rights` (must be a Creative Commons or Rights Statements URI)
- `attribution` → `requiredStatement` (with `label` and `value` language maps)
- Annotations use W3C Web Annotation model, not Open Annotation
- `motivation` values: `painting` (images/av on canvas), `supplementing` (OCR, descriptions), `commenting`, `tagging`
- `rendering` property for alternative representations (PDF, ALTO XML, etc.)
- `seeAlso` for machine-readable linked data

## Reference Implementations

### Python — iiif-prezi3 (the gold standard)

- Repository: https://github.com/iiif-prezi/iiif-prezi3
- PyPI: `pip install iiif-prezi3`
- Built on **Pydantic models auto-generated from the IIIF JSON Schema**
- Source for the Pydantic skeleton (use as class/field reference): https://github.com/iiif-prezi/iiif-prezi3/tree/main/iiif_prezi3
- Helper methods worth mirroring in PHP:
  - `make_canvas_from_iiif()` — creates Canvas + AnnotationPage + Annotation from an image service URL
  - `add_image()` — shortcut for painting an image onto a canvas
  - `add_label()` — handles language map construction
  - `make_range()` — for table of contents / structural navigation

### PHP — Existing libraries (for reference, not dependency)

- **Yale IIIF Manifest Generator** (v2 only, but useful for API design patterns)
  - Packagist: `yale-web-technologies/iiif-manifest-generator`
  - Repository: https://github.com/yale-web-technologies/IIIF-Manifest-Generator
  - PHP 8.1+, GPL-3.0
  - Good class structure but targets Presentation API v2 (uses Sequences)

- **Leipzig php-iiif-prezi-reader** (reader, not writer — useful for import/validation)
  - Packagist: `ubl/php-iiif-prezi-reader`
  - Repository: https://github.com/ubleipzig/php-iiif-prezi-reader
  - Reads v1, v2, and rudimentary v3

- **Daniel Berthereau's IIIF Server module** for Omeka-S
  - Generates v2 and v3 manifests but deeply coupled to Omeka-S
  - Repository: https://gitlab.com/Daniel-KM/Omeka-S-module-IiifServer
  - Useful to study for real-world v3 manifest generation patterns

### JSON Schema (for validation)

- Official IIIF Presentation 3.0 JSON Schema: https://github.com/IIIF/presentation-validator/blob/main/schema/iiif_3_0.json
- Prototype validator: https://github.com/glenrobson/iiif-jsonschema
- Online validator: https://presentation-validator.iiif.io/

### IIIF Cookbook (example manifests for every pattern)

- Full recipe list: https://iiif.io/api/cookbook/recipe/all/
- Recipe repository: https://github.com/IIIF/cookbook-recipes

Key recipes for our use cases:
- **0001** — Simplest Manifest (single image)
- **0009** — Multiple images in a single manifest (multi-page document)
- **0021** — Basic table of contents using Ranges
- **0068** — Basic Newspaper (multi-page + OCR annotations — closest to our scanned document use case)
- **0003** — Video manifest (for future audio/video support)
- **0017** — Providing Alternative Representations (linking to PDF, ALTO)
- **0019** — Annotating part of an image (for region-level OCR)
- **0230** — Annotations embedded vs referenced

## PHP Class Structure

All classes should implement `\JsonSerializable`. Use PHP 8.2+ features: `readonly` properties, enums, named arguments. Use Symfony Serializer for JSON-LD output.

### Core Resource Classes (namespace `Survos\IiifBundle\Model`)

```
AbstractResource          # base: id, type, label, summary, metadata, thumbnail, etc.
├── Collection            # type: "Collection", items: Manifest[]|Collection[]
├── Manifest              # type: "Manifest", items: Canvas[], structures: Range[]
├── Canvas                # type: "Canvas", width, height, duration, items: AnnotationPage[]
├── Range                 # type: "Range", items: (Canvas|Range)[], for TOC
├── AnnotationPage        # type: "AnnotationPage", items: Annotation[]
├── AnnotationCollection  # type: "AnnotationCollection", for Content Search results
└── Annotation            # type: "Annotation", motivation, body, target
```

### Content/Body Classes

```
ResourceItem              # Image, Video, Audio, etc. with id, type, format, service
TextualBody               # Inline text content (OCR text, descriptions)
SpecificResource          # Resource with a selector (for targeting regions)
```

### Supporting Classes

```
Service                   # IIIF Image API service reference (id, type, profile)
ImageService3             # Convenience: type="ImageService3", profile="level2"
Thumbnail                 # Simplified image reference for thumbnails
LabelMap                  # Helper for language map construction: {"en": ["text"]}
MetadataEntry             # label: LabelMap, value: LabelMap pair
```

### Selector Classes (for annotation targets)

```
FragmentSelector          # xywh=x,y,w,h for image regions
PointSelector             # For point annotations
SvgSelector               # SVG-based selections
```

### Enum Classes

```
Motivation                # painting, supplementing, commenting, tagging, etc.
ViewingDirection          # left-to-right, right-to-left, top-to-bottom, bottom-to-top
Behavior                  # paged, continuous, individuals, auto-advance, etc.
```

## Builder Pattern

Provide a fluent builder for the common case (scanned document with OCR):

```php
use Survos\IiifBundle\Builder\ManifestBuilder;

$builder = new ManifestBuilder('https://example.org/iiif/item-123/manifest');
$builder
    ->setLabel('en', 'Civil War Pension File — Pvt. James Wilson')
    ->setSummary('en', 'Pension application including affidavits and medical examination, 1892')
    ->addMetadata('en', 'Date', '1892')
    ->addMetadata('en', 'Creator', 'U.S. Pension Bureau')
    ->setRights('http://creativecommons.org/publicdomain/mark/1.0/')
    ->setRequiredStatement('en', 'Attribution', 'Courtesy of Carver 4-County Museum');

// Add pages — each becomes a Canvas with a painting annotation
foreach ($pages as $i => $page) {
    $canvas = $builder->addCanvas(
        id: "https://example.org/iiif/item-123/canvas/p{$i}",
        label: ['en' => ["Page {$i}"]],
        width: $page->getWidth(),
        height: $page->getHeight(),
    );

    // Paint the image onto the canvas
    $canvas->addImage(
        imageUrl: "https://s3.example.org/scans/item-123/page-{$i}.jpg",
        format: 'image/jpeg',
        width: $page->getWidth(),
        height: $page->getHeight(),
        // Optional: reference to IIIF Image Service (e.g., imgproxy)
        service: new ImageService3(
            id: "https://iiif.example.org/image/item-123-page-{$i}",
            profile: 'level2'
        ),
    );

    // Add OCR text as supplementing annotation (page-level)
    $canvas->addSupplementingText(
        text: $page->getOcrText(),
        language: 'en',
    );

    // Add word-level OCR annotations (for search highlighting)
    foreach ($page->getOcrWords() as $word) {
        $canvas->addWordAnnotation(
            text: $word->getText(),
            x: $word->getX(),
            y: $word->getY(),
            w: $word->getWidth(),
            h: $word->getHeight(),
        );
    }
}

// Add Content Search service reference (Meilisearch-backed)
$builder->addService([
    'id' => 'https://example.org/iiif/item-123/search',
    'type' => 'SearchService2',
]);

// Serialize to JSON-LD
$json = $builder->toJson();      // string
$array = $builder->toArray();    // array (for storing in S3 as static file)
```

## JSON-LD Output Format

The bundle must produce valid Presentation API 3.0 JSON-LD. Example minimal manifest:

```json
{
  "@context": "http://iiif.io/api/presentation/3/context.json",
  "id": "https://example.org/iiif/item-123/manifest",
  "type": "Manifest",
  "label": { "en": ["Civil War Pension File"] },
  "items": [
    {
      "id": "https://example.org/iiif/item-123/canvas/p1",
      "type": "Canvas",
      "width": 3000,
      "height": 4000,
      "items": [
        {
          "id": "https://example.org/iiif/item-123/canvas/p1/annopage",
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
          "id": "https://example.org/iiif/item-123/canvas/p1/ocr",
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
  ]
}
```

## Key Implementation Notes

### Language Maps

Every `label`, `summary`, `requiredStatement`, and `metadata` value must be a language map:

```php
// Helper class or trait
LabelMap::create('en', 'Page 1');          // → {"en": ["Page 1"]}
LabelMap::create('none', 'IMG_0042.jpg');  // → {"none": ["IMG_0042.jpg"]}
LabelMap::multilingual([
    'en' => 'Hello',
    'es' => 'Hola',
]);                                         // → {"en": ["Hello"], "es": ["Hola"]}
```

### Canvas items vs annotations

This distinction is critical:
- `Canvas.items` → AnnotationPages with `motivation: "painting"` — the content that IS the canvas (images, video, audio)
- `Canvas.annotations` → AnnotationPages with other motivations (`supplementing`, `commenting`, `tagging`) — content ABOUT the canvas (OCR, descriptions, comments)

### Static vs Dynamic Manifests

The bundle should support both patterns:
- `$builder->toJson()` → write to S3 as a static `.json` file (primary use case for Mediary pipeline)
- Symfony controller that generates manifests on the fly from database entities (secondary, for admin preview)

### Fragment Selectors for Word-Level OCR

Target a region of a canvas using xywh fragment:

```
"target": "https://example.org/iiif/item-123/canvas/p1#xywh=100,200,350,50"
```

This is how word-level OCR positions are encoded. The Kreuzberg/Mistral OCR output provides word bounding boxes that map directly to these fragments.

### Content Search Service Integration

The manifest should declare a Content Search service that points to a Meilisearch-backed endpoint:

```json
"service": [
  {
    "id": "https://example.org/iiif/item-123/search",
    "type": "SearchService2",
    "profile": "http://iiif.io/api/search/2/service"
  }
]
```

The actual search endpoint implementation (translating IIIF Content Search requests to Meilisearch queries) is a separate concern, but the bundle should make it easy to declare this service on a manifest.

## Bundle Configuration

```yaml
# config/packages/survos_iiif.yaml
survos_iiif:
    base_url: '%env(IIIF_BASE_URL)%'           # e.g., https://iiif.scanseum.com
    image_service_base: '%env(IIIF_IMAGE_URL)%' # e.g., https://img.scanseum.com
    image_service_profile: 'level2'              # level0, level1, level2
    default_rights: 'http://creativecommons.org/publicdomain/mark/1.0/'
    default_language: 'en'
```

## Symfony Integration

### Services

- `Survos\IiifBundle\Builder\ManifestBuilder` — registered as a service, injectable
- `Survos\IiifBundle\Serializer\IiifNormalizer` — custom normalizer for JSON-LD output (handles `@context` injection, language maps, etc.)
- `Survos\IiifBundle\Validator\ManifestValidator` — validates generated JSON against the IIIF 3.0 JSON Schema

### Console Commands

```bash
# Generate a manifest from an Item entity
bin/console iiif:manifest:generate --item=123 --output=/path/to/manifest.json

# Validate an existing manifest
bin/console iiif:manifest:validate /path/to/manifest.json
```

## Testing

### Validation

- Use the official JSON Schema: https://github.com/IIIF/presentation-validator/blob/main/schema/iiif_3_0.json
- Use `justinrainbow/json-schema` PHP package for local validation
- Test against the online validator: https://presentation-validator.iiif.io/

### Fixture Manifests

Generate test manifests and verify they load correctly in:
- Mirador 3: https://projectmirador.org/
- Universal Viewer: https://universalviewer.io/
- Clover IIIF: https://samvera-labs.github.io/clover-iiif/

### Unit Tests

- Each resource class serializes to valid JSON-LD
- Language maps produce correct structure
- Builder helper methods produce correct annotation nesting
- Fragment selectors format correctly
- Full manifest matches expected JSON structure from cookbook recipes

## Dependencies

```json
{
    "require": {
        "php": ">=8.2",
        "symfony/framework-bundle": "^7.0",
        "symfony/serializer": "^7.0"
    },
    "require-dev": {
        "justinrainbow/json-schema": "^6.0",
        "phpunit/phpunit": "^11.0"
    }
}
```

## Awesome IIIF Resources

- Curated list: https://github.com/IIIF/awesome-iiif
- IIIF API specifications index: https://iiif.io/api/
- IIIF Cookbook: https://iiif.io/api/cookbook/
- IIIF JSON-LD context: http://iiif.io/api/presentation/3/context.json
- Viewer matrix (which viewer supports which feature): included in cookbook recipes
- Text Granularity Extension: https://iiif.io/api/extension/text-granularity/
- Newspaper recipe (closest to our use case): https://iiif.io/api/cookbook/recipe/0068-newspaper/
