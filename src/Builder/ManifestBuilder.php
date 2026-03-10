<?php
declare(strict_types=1);

namespace Survos\IiifBundle\Builder;

use Survos\IiifBundle\Enum\Behavior;
use Survos\IiifBundle\Enum\Motivation;
use Survos\IiifBundle\Enum\ViewingDirection;
use Survos\IiifBundle\Model\Annotation;
use Survos\IiifBundle\Model\AnnotationPage;
use Survos\IiifBundle\Model\Canvas;
use Survos\IiifBundle\Model\ImageService3;
use Survos\IiifBundle\Model\LabelMap;
use Survos\IiifBundle\Model\Manifest;
use Survos\IiifBundle\Model\MetadataEntry;
use Survos\IiifBundle\Model\Range;
use Survos\IiifBundle\Model\ResourceItem;
use Survos\IiifBundle\Model\Service;
use Survos\IiifBundle\Model\TextualBody;

final class ManifestBuilder
{
    private Manifest $manifest;
    private string $defaultLanguage;

    public function __construct(
        string $manifestId,
        string $defaultLanguage = 'en',
    ) {
        $this->manifest = Manifest::create($manifestId);
        $this->defaultLanguage = $defaultLanguage;
    }

    public function setLabel(string $language, string $value): self
    {
        $this->manifest->setLabel($language, $value);
        return $this;
    }

    public function setSummary(string $language, string $value): self
    {
        $this->manifest->setSummary($language, $value);
        return $this;
    }

    public function addMetadata(string $labelLanguage, string $labelValue, string $valueLanguage, string $valueValue): self
    {
        $this->manifest->addMetadata($labelLanguage, $labelValue, $valueLanguage, $valueValue);
        return $this;
    }

    public function setRights(string $rights): self
    {
        $this->manifest->setRights($rights);
        return $this;
    }

    public function setRequiredStatement(string $language, string $label, string $value): self
    {
        $this->manifest->setRequiredStatement($language, $label, $value);
        return $this;
    }

    public function setViewingDirection(ViewingDirection $direction): self
    {
        $this->manifest->setViewingDirection($direction);
        return $this;
    }

    public function setBehavior(Behavior $behavior): self
    {
        $this->manifest->addMetadata($this->defaultLanguage, 'behavior', 'none', $behavior->value);
        return $this;
    }

    public function addCanvas(
        string $id,
        ?string $label = null,
        ?int $width = null,
        ?int $height = null,
    ): Canvas {
        $labelMap = $label ? LabelMap::create($this->defaultLanguage, $label) : null;
        $canvas = Canvas::create($id, $labelMap, $width, $height);
        $this->manifest->addItem($canvas);
        return $canvas;
    }

    public function addService(Service $service): self
    {
        $this->manifest->addService($service);
        return $this;
    }

    public function addSearchService(string $id): self
    {
        $this->manifest->addService(new Service($id, 'SearchService2', 'http://iiif.io/api/search/2/service'));
        return $this;
    }

    public function getManifest(): Manifest
    {
        return $this->manifest;
    }

    public function toArray(): array
    {
        return array_merge(
            ['@context' => 'http://iiif.io/api/presentation/3/context.json'],
            $this->manifest->jsonSerialize()
        );
    }

    public function toJson(int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES): string
    {
        return json_encode($this->toArray(), $flags);
    }
}
