<?php
declare(strict_types=1);

namespace Survos\IiifBundle\Model;

use JsonSerializable;
use Survos\IiifBundle\Enum\Motivation;

final class Annotation implements JsonSerializable
{
    public function __construct(
        public string $id,
        public Motivation $motivation,
        public ResourceItem|TextualBody $body,
        public string $target,
    ) {
    }

    public static function createPainting(
        string $id,
        ResourceItem $body,
        string $target,
    ): self {
        return new self($id, Motivation::PAINTING, $body, $target);
    }

    public static function createSupplementing(
        string $id,
        TextualBody $body,
        string $target,
    ): self {
        return new self($id, Motivation::SUPPLEMENTING, $body, $target);
    }

    public static function createCommenting(
        string $id,
        TextualBody $body,
        string $target,
    ): self {
        return new self($id, Motivation::COMMENTING, $body, $target);
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'type' => 'Annotation',
            'motivation' => $this->motivation->value,
            'body' => $this->body,
            'target' => $this->target,
        ];
    }
}
