<?php
declare(strict_types=1);

namespace Survos\IiifBundle\Model;

use JsonSerializable;

final class AnnotationPage implements JsonSerializable
{
    /** @var Annotation[] */
    public array $items = [];

    public function __construct(
        public string $id,
    ) {
    }

    public static function create(string $id): self
    {
        return new self($id);
    }

    public function addItem(Annotation $annotation): self
    {
        $this->items[] = $annotation;
        return $this;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'type' => 'AnnotationPage',
            'items' => $this->items,
        ];
    }
}
