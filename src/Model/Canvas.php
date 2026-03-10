<?php
declare(strict_types=1);

namespace Survos\IiifBundle\Model;

use JsonSerializable;
use Survos\IiifBundle\Trait\CanvasBuilderTrait;

final class Canvas implements JsonSerializable
{
    use CanvasBuilderTrait;

    /** @var AnnotationPage[] */
    public array $items = [];
    /** @var AnnotationPage[] */
    public array $annotations = [];

    public function __construct(
        public string $id,
        public ?LabelMap $label = null,
        public ?int $width = null,
        public ?int $height = null,
        public ?float $duration = null,
    ) {
    }

    public static function create(
        string $id,
        ?LabelMap $label = null,
        ?int $width = null,
        ?int $height = null,
    ): self {
        return new self($id, $label, $width, $height);
    }

    public function addItem(AnnotationPage $annotationPage): self
    {
        $this->items[] = $annotationPage;
        return $this;
    }

    public function addAnnotationPage(AnnotationPage $annotationPage): self
    {
        $this->annotations[] = $annotationPage;
        return $this;
    }

    public function jsonSerialize(): array
    {
        $data = [
            'id' => $this->id,
            'type' => 'Canvas',
        ];
        if ($this->label) {
            $data['label'] = $this->label;
        }
        if ($this->width) {
            $data['width'] = $this->width;
        }
        if ($this->height) {
            $data['height'] = $this->height;
        }
        if ($this->duration) {
            $data['duration'] = $this->duration;
        }
        if ($this->items) {
            $data['items'] = $this->items;
        }
        if ($this->annotations) {
            $data['annotations'] = $this->annotations;
        }
        return $data;
    }
}
